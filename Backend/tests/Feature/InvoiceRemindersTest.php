<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PlanTier;
use App\Models\Document;
use App\Notifications\BusinessAlert;
use App\Notifications\InvoiceReminderNotification;
use App\Services\Reminders\InvoiceReminders;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Customer due-date reminders (Business Plus) and the team's overdue alert. */
final class InvoiceRemindersTest extends TestCase
{
    private function invoice(string $due, array $extra = []): Document
    {
        $id = $this->postJson('/api/v1/documents', $this->receiptPayload([
            'type' => 'INVOICE', 'customer_email' => 'ada@example.com', 'due_date' => $due, ...$extra,
        ]))->assertCreated()->json('data.id');

        return Document::query()->findOrFail($id);
    }

    public function test_stage_rules(): void
    {
        $s = InvoiceReminders::DEFAULTS;
        $this->assertSame('BEFORE_DUE', InvoiceReminders::stageFor(3, $s));
        $this->assertNull(InvoiceReminders::stageFor(4, $s));
        $this->assertSame('DUE_TODAY', InvoiceReminders::stageFor(0, $s));
        $this->assertNull(InvoiceReminders::stageFor(-6, $s));
        $this->assertSame('OVERDUE_1', InvoiceReminders::stageFor(-7, $s));
        $this->assertSame('OVERDUE_3', InvoiceReminders::stageFor(-60, $s), 'capped at the maximum, no backlog');
        $this->assertNull(InvoiceReminders::stageFor(-7, [...$s, 'overdue_every_days' => 0]));
    }

    public function test_reminders_go_once_per_stage_and_only_on_business_plus(): void
    {
        Notification::fake();
        [$merchant, $owner] = $this->merchantWithOwner(PlanTier::BUSINESS_PLUS);
        $this->actingAs($owner);
        $today = CarbonImmutable::now(config('vendslip.timezone'))->startOfDay();
        $soon = $this->invoice($today->addDays(2)->toDateString());
        $this->invoice($today->addDays(10)->toDateString());
        $this->invoice($today->addDays(2)->toDateString(), ['customer_email' => null]);

        $service = app(InvoiceReminders::class);
        $this->assertSame(1, $service->run($today));
        $this->assertSame(0, $service->run($today), 'the same stage is never sent twice');
        Notification::assertSentTo(new AnonymousNotifiable, InvoiceReminderNotification::class, function (InvoiceReminderNotification $n, array $channels, AnonymousNotifiable $to) use ($soon): bool {
            return $to->routes['mail'] === 'ada@example.com' && $n->docNumber === $soon->doc_number && $n->stage === 'BEFORE_DUE' && $n->days === 2;
        });

        // Paid in full: nothing more is sent.
        $this->postJson("/api/v1/documents/{$soon->id}/payments", ['amount_minor' => $soon->balance_due_minor, 'method' => 'BANK_TRANSFER'])->assertSuccessful();
        $this->assertSame(0, $service->run($today->addDays(2)));

        // Turned off by the business.
        $this->putJson('/api/v1/settings/reminders', ['customer_emails' => false, 'days_before' => 3, 'on_due_date' => true, 'overdue_every_days' => 7, 'overdue_max' => 3])->assertOk();
        $this->assertSame(0, $service->run($today->addDays(10)));

        // Free plan: no customer emails at all.
        [, $freeOwner] = $this->merchantWithOwner();
        $this->actingAs($freeOwner);
        $this->invoice($today->toDateString());
        $this->assertSame(0, $service->run($today));
        $this->getJson('/api/v1/settings/reminders')->assertOk()->assertJsonPath('available', false);
    }

    public function test_marking_overdue_alerts_the_team_once(): void
    {
        [$merchant, $owner] = $this->merchantWithOwner();
        $this->actingAs($owner);
        $due = CarbonImmutable::now(config('vendslip.timezone'))->addDay()->toDateString();
        $a = $this->invoice($due);
        $this->invoice($due);
        $this->assertSame('UNPAID', $a->status->value);
        Notification::fake();
        $this->travel(3)->days();

        $this->artisan('vendslip:mark-overdue')->assertSuccessful();
        $this->assertSame('OVERDUE', $a->refresh()->status->value);
        Notification::assertSentToTimes($owner, BusinessAlert::class, 1);
        Notification::assertSentTo($owner, BusinessAlert::class, fn (BusinessAlert $n): bool => str_contains($n->title, '2 invoices'));

        $this->artisan('vendslip:mark-overdue')->assertSuccessful();
        Notification::assertSentToTimes($owner, BusinessAlert::class, 1);
    }
}
