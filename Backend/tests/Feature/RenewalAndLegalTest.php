<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BillingCycle;
use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\PlanTier;
use App\Enums\Role;
use App\Models\LegalAcceptance;
use App\Models\Merchant;
use App\Models\PaymentTransaction;
use App\Models\StaffInvitation;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\BusinessAlert;
use App\Services\Billing\RenewalReminders;
use App\Services\Legal\LegalConsent;
use App\Services\Payments\PaymentFulfillmentService;
use App\Services\Settings\PlatformSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Renewal reminders, the mid-term plan change credit, and recorded acceptance of the legal terms. */
final class RenewalAndLegalTest extends TestCase
{
    private function paidMerchant(int $daysLeft, PlanTier $tier = PlanTier::PRO): array
    {
        [$merchant, $owner] = $this->merchantWithOwner();
        $end = CarbonImmutable::now(config('vendslip.timezone'))->startOfDay()->addDays($daysLeft)->setTime(15, 0);
        $merchant->forceFill(['plan_tier' => $tier, 'billing_cycle' => BillingCycle::MONTHLY, 'plan_expires_at' => $end])->save();

        return [$merchant->refresh(), $owner];
    }

    private function spa(): self
    {
        return $this->withHeaders(['Origin' => 'http://localhost', 'Referer' => 'http://localhost/', 'Accept' => 'application/json']);
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('vendslip.timezone'))->startOfDay();
    }

    public function test_timing_rules(): void
    {
        $this->assertSame([14, 7, 1, 0, -3], RenewalReminders::parse(' -3, 7,14 ,1 0 7'));
        $this->assertSame([], RenewalReminders::parse('off'));
        $this->assertNull(RenewalReminders::parse('7, soon'));
        $this->assertNull(RenewalReminders::parse('90'));
        $this->assertSame('7 days before · on the day · 3 days after', RenewalReminders::describe([7, 0, -3]));

        $offsets = [7, 3, 1, 0, -3];
        $this->assertSame(3, RenewalReminders::stageFor(3, $offsets));
        $this->assertSame(7, RenewalReminders::stageFor(6, $offsets), 'a missed day still catches up');
        $this->assertNull(RenewalReminders::stageFor(10, $offsets));
        $this->assertSame(0, RenewalReminders::stageFor(0, $offsets));
        $this->assertSame(-3, RenewalReminders::stageFor(-3, $offsets));
        $this->assertNull(RenewalReminders::stageFor(-9, $offsets), 'no reminders long after the plan ended');
    }

    public function test_owners_are_reminded_once_per_stage_and_per_business_timing_wins(): void
    {
        Notification::fake();
        [$soon, $owner] = $this->paidMerchant(3);
        [$custom, $customOwner] = $this->paidMerchant(3);
        $custom->forceFill(['renewal_reminder_days' => '14,2'])->save();
        [, $freeOwner] = $this->merchantWithOwner();
        $service = app(RenewalReminders::class);

        $this->assertSame(1, $service->run($this->today()));
        $this->assertSame(0, $service->run($this->today()), 'never twice for the same stage');
        Notification::assertSentTo($owner, BusinessAlert::class, fn (BusinessAlert $n, array $via): bool => $n->title === 'Your Pro Merchant plan ends in 3 days' && in_array('mail', $via, true) && $n->path === '/dashboard/billing?renew=1');
        Notification::assertNotSentTo($customOwner, BusinessAlert::class);
        Notification::assertNotSentTo($freeOwner, BusinessAlert::class);

        // Their own timing: 2 days before.
        $this->assertSame(1, $service->run($this->today()->addDay()));
        Notification::assertSentTo($customOwner, BusinessAlert::class);

        // After it lapsed (platform default -3).
        $this->assertSame(1, $service->run($this->today()->addDays(6)));
        Notification::assertSentTo($owner, BusinessAlert::class, fn (BusinessAlert $n): bool => $n->title === 'Your Pro Merchant plan has ended');

        // Renewing moves the end date: a fresh set of reminders for the new term.
        $soon->forceFill(['plan_expires_at' => $this->today()->addDays(7)->setTime(12, 0)])->save();
        $this->assertSame(1, $service->run($this->today()));
    }

    public function test_super_admin_sets_platform_and_per_business_timing(): void
    {
        [$merchant] = $this->paidMerchant(20);
        $super = User::factory()->create();
        $super->assignRole(Role::SUPER_ADMIN->value);
        $admin = User::factory()->create();
        $admin->assignRole(Role::ADMIN->value);

        $this->actingAs($super)->put(route('admin.monetization.renewal'), ['renewal_reminder_days' => '10, 3, 0'])->assertSessionHas('status');
        $this->assertSame('10,3,0', app(PlatformSettings::class)->get('billing.renewal_reminder_days'));
        $this->put(route('admin.monetization.renewal'), ['renewal_reminder_days' => 'tomorrow'])->assertSessionHasErrors('renewal_reminder_days');

        $this->put(route('admin.merchants.renewal', $merchant), ['renewal_reminder_days' => '21, 7'])->assertSessionHas('status');
        $this->assertSame('21,7', $merchant->refresh()->renewal_reminder_days);
        $this->get(route('admin.merchants.show', $merchant))->assertOk()->assertSee('21 days before · 7 days before');
        $this->get(route('admin.monetization.edit'))->assertOk()->assertSee($merchant->name);
        $this->put(route('admin.merchants.renewal', $merchant), ['renewal_reminder_days' => ''])->assertSessionHas('status');
        $this->assertNull($merchant->refresh()->renewal_reminder_days);

        // Platform Admins (not Super) can't change timing.
        $this->actingAs($admin)->put(route('admin.merchants.renewal', $merchant), ['renewal_reminder_days' => '5'])->assertForbidden();
    }

    public function test_changing_plan_mid_term_keeps_the_value_of_unused_time(): void
    {
        SubscriptionPlan::forTier(PlanTier::PRO)?->update(['currency' => 'NGN', 'monthly_price_minor' => 500_000, 'price_overrides' => null]);
        SubscriptionPlan::forTier(PlanTier::BUSINESS_PLUS)?->update(['currency' => 'NGN', 'monthly_price_minor' => 1_000_000, 'price_overrides' => null]);
        [$merchant] = $this->paidMerchant(20);
        $before = $merchant->plan_expires_at;
        $tx = PaymentTransaction::query()->create([
            'reference' => 'VS-TEST-'.Str::random(6), 'merchant_id' => $merchant->id, 'purpose' => PaymentPurpose::SUBSCRIPTION,
            'gateway' => PaymentGateway::FLUTTERWAVE, 'status' => PaymentStatus::PENDING, 'amount_minor' => 1_000_000, 'currency' => 'NGN',
            'plan_tier' => PlanTier::BUSINESS_PLUS, 'billing_cycle' => BillingCycle::MONTHLY,
        ]);
        $now = CarbonImmutable::now();
        app(PaymentFulfillmentService::class)->markSuccessful($tx->reference, 1_000_000, 'NGN', 'flw-1');

        $merchant->refresh();
        $this->assertSame(PlanTier::BUSINESS_PLUS, $merchant->plan_tier);
        // A month from now + half the remaining Pro time (Pro costs half as much).
        $expected = $now->addMonth()->addSeconds((int) floor($now->diffInSeconds($before) / 2));
        $this->assertEqualsWithDelta($expected->getTimestamp(), $merchant->plan_expires_at?->getTimestamp(), 5);
        $this->assertGreaterThan(9.0, $tx->refresh()->metadata['carried_over_days'] ?? 0);
    }

    public function test_terms_must_be_accepted_at_signup_invitation_and_checkout(): void
    {
        $version = (string) config('vendslip.legal.version');
        $this->getJson('/api/v1/public/legal')->assertOk()->assertJsonPath('version', $version)->assertJsonPath('entity', 'Techberryz Futurecore Limited');

        $signup = ['business_name' => 'Ada Wears', 'name' => 'Ada', 'email' => 'ada@wears.test', 'password' => 'Secret-123A!', 'password_confirmation' => 'Secret-123A!'];
        $this->spa()->postJson('/api/v1/auth/register', $signup)->assertUnprocessable()->assertJsonValidationErrors('accept_terms');
        $this->spa()->postJson('/api/v1/auth/register', [...$signup, 'accept_terms' => true])->assertCreated();
        $ada = User::query()->where('email', 'ada@wears.test')->firstOrFail();
        $this->assertSame(['PRIVACY', 'TERMS'], LegalAcceptance::query()->where('user_id', $ada->id)->where('context', 'SIGNUP')->where('version', $version)->orderBy('document')->pluck('document')->all());

        // Joining a team.
        [$merchant, $owner] = $this->merchantWithOwner();
        $token = Str::random(48);
        StaffInvitation::query()->create(['merchant_id' => $merchant->id, 'email' => 'tobi@team.test', 'role' => Role::SALES_AGENT, 'token_hash' => hash('sha256', $token), 'invited_by_user_id' => $owner->id, 'expires_at' => now()->addDay()]);
        $join = ['name' => 'Tobi', 'password' => 'Secret-123A!', 'password_confirmation' => 'Secret-123A!'];
        $this->spa()->postJson("/api/v1/invitations/{$token}/accept", $join)->assertUnprocessable()->assertJsonValidationErrors('accept_terms');
        $this->spa()->postJson("/api/v1/invitations/{$token}/accept", [...$join, 'accept_terms' => true])->assertSuccessful();
        $this->assertTrue(LegalAcceptance::query()->where('context', 'INVITATION')->exists());
    }

    public function test_terms_must_be_accepted_before_paying_vendslip(): void
    {
        $version = (string) config('vendslip.legal.version');
        [$merchant, $owner] = $this->merchantWithOwner();

        // Before paying VendSlip: no acceptance, no checkout.
        Http::fake(['api.flutterwave.com/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/pay/y']])]);
        $order = ['purpose' => 'SUBSCRIPTION', 'gateway' => 'FLUTTERWAVE', 'plan_tier' => 'PRO', 'billing_cycle' => 'MONTHLY'];
        $this->actingAs($owner)->postJson('/api/v1/billing/checkout', $order)->assertUnprocessable()->assertJsonValidationErrors('accept_terms');
        $this->assertSame(0, PaymentTransaction::query()->where('merchant_id', $merchant->id)->count());
        $ref = $this->postJson('/api/v1/billing/checkout', [...$order, 'accept_terms' => true])->assertCreated()->json('reference');
        $this->assertTrue(LegalAcceptance::query()->where('user_id', $owner->id)->where('context', 'CHECKOUT')->where('reference', $ref)->where('document', 'TERMS')->exists());

        $super = User::factory()->create();
        $super->assignRole(Role::SUPER_ADMIN->value);
        $this->actingAs($super)->get(route('admin.merchants.show', Merchant::query()->findOrFail($merchant->id)))->assertOk()->assertSee('v'.$version.' · checkout', false);
    }

    public function test_a_new_version_is_announced_then_must_be_accepted(): void
    {
        config(['vendslip.legal.require_current' => true]);
        Notification::fake();
        $base = (string) config('vendslip.legal.version');
        [, $owner] = $this->merchantWithOwner();
        LegalAcceptance::query()->create(['user_id' => $owner->id, 'merchant_id' => $owner->merchant_id, 'document' => 'TERMS', 'version' => $base, 'context' => 'SIGNUP', 'accepted_at' => now()]);
        $super = User::factory()->create(['name' => 'Sade Super']);
        $super->assignRole(Role::SUPER_ADMIN->value);
        $admin = User::factory()->create();
        $admin->assignRole(Role::ADMIN->value);
        $today = $this->today();
        $effective = $today->addDays(14)->toDateString();

        // Only Super-Admins publish, and only with the wording live and a sensible date.
        $this->actingAs($admin)->get(route('admin.legal.index'))->assertForbidden();
        $this->actingAs($super)->get(route('admin.legal.index'))->assertOk()->assertSee('v'.$base);
        $this->post(route('admin.legal.publish'), ['effective_on' => $today->subDay()->toDateString(), 'summary' => 'Added Paystack as a provider.', 'live' => '1'])->assertSessionHasErrors('effective_on');
        $this->post(route('admin.legal.publish'), ['effective_on' => $effective, 'summary' => 'Added Paystack as a provider.'])->assertSessionHasErrors('live');
        $this->post(route('admin.legal.publish'), ['effective_on' => $effective, 'summary' => 'Added Paystack as a provider.', 'live' => '1', 'notify' => '1'])->assertSessionHas('status');
        $this->assertSame($effective, LegalConsent::version());
        Notification::assertSentTo($owner, BusinessAlert::class, fn (BusinessAlert $n, array $via): bool => str_contains($n->title, 'change on') && in_array('mail', $via, true));
        $this->get(route('admin.legal.index'))->assertSee('Added Paystack as a provider.')->assertSee('Sade Super');
        $this->getJson('/api/v1/public/legal')->assertJsonPath('version', $effective)->assertJsonPath('in_force', false)->assertJsonPath('summary', 'Added Paystack as a provider.');

        // Notice period: told, not blocked.
        $this->actingAs($owner)->getJson('/api/v1/auth/me')->assertJsonPath('legal.up_to_date', false)->assertJsonPath('legal.required', false)->assertJsonPath('legal.version', $effective);
        $this->postJson('/api/v1/documents', $this->receiptPayload())->assertCreated();

        // From the effective date: reads work, changes wait for acceptance.
        $this->travelTo($today->addDays(14)->setTime(10, 0));
        $this->getJson('/api/v1/auth/me')->assertJsonPath('legal.required', true);
        $this->getJson('/api/v1/documents')->assertOk();
        $this->postJson('/api/v1/documents', $this->receiptPayload())->assertStatus(409)->assertJsonPath('code', 'terms_update_required');
        $this->postJson('/api/v1/legal/accept', [])->assertUnprocessable()->assertJsonValidationErrors('accept_terms');
        $this->postJson('/api/v1/legal/accept', ['accept_terms' => true])->assertOk()->assertJsonPath('legal.up_to_date', true)->assertJsonPath('legal.required', false);
        $this->assertTrue(LegalAcceptance::query()->where('user_id', $owner->id)->where('version', $effective)->where('context', 'UPDATE')->where('document', 'PRIVACY')->exists());
        $this->postJson('/api/v1/documents', $this->receiptPayload())->assertCreated();
    }

    public function test_people_with_no_acceptance_on_record_are_asked_once(): void
    {
        config(['vendslip.legal.require_current' => true]);
        [, $owner] = $this->merchantWithOwner();
        $this->actingAs($owner)->getJson('/api/v1/auth/me')->assertJsonPath('legal.accepted_version', null)->assertJsonPath('legal.required', true);
        $this->postJson('/api/v1/documents', $this->receiptPayload())->assertStatus(409);
        // Paying still works: accepting is part of checkout.
        Http::fake(['api.flutterwave.com/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/pay/z']])]);
        $this->postJson('/api/v1/billing/checkout', ['purpose' => 'SUBSCRIPTION', 'gateway' => 'FLUTTERWAVE', 'plan_tier' => 'PRO', 'billing_cycle' => 'MONTHLY', 'accept_terms' => true])->assertCreated();
        $this->getJson('/api/v1/auth/me')->assertJsonPath('legal.required', false);
        $this->postJson('/api/v1/documents', $this->receiptPayload())->assertCreated();
    }
}
