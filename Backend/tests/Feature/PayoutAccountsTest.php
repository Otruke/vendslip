<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Merchant;
use App\Models\PayoutAccount;
use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Notifications\BusinessAlert;
use App\Services\Payouts\PayoutAccounts;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Several bank accounts and wallets, password-confirmed, alerting Owners and Managers. */
final class PayoutAccountsTest extends TestCase
{
    private function staff(Merchant $merchant, Role $role): User
    {
        $user = User::factory()->create(['merchant_id' => $merchant->id]);
        $user->assignRole($role->value);

        return $user;
    }

    public function test_adding_a_bank_needs_password_mirrors_legacy_fields_and_alerts_owner_and_manager(): void
    {
        [$merchant, $owner] = $this->merchantWithOwner();
        $manager = $this->staff($merchant, Role::MANAGER);
        $accountant = $this->staff($merchant, Role::ACCOUNTANT);
        $this->actingAs($owner);
        Notification::fake();

        $bank = ['kind' => 'BANK', 'bank_name' => 'Providus Bank', 'account_number' => '0192 837 465', 'account_name' => 'Amina Couture'];
        $this->postJson('/api/v1/settings/payout-accounts', $bank)->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->postJson('/api/v1/settings/payout-accounts', [...$bank, 'current_password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->postJson('/api/v1/settings/payout-accounts', [...$bank, 'current_password' => 'password'])
            ->assertCreated()->assertJsonPath('data.account_number', '0192837465')->assertJsonPath('data.is_default', true);

        $merchant->refresh();
        $this->assertSame('0192837465', $merchant->bank_account_number);
        $this->assertSame('Providus Bank', $merchant->bank_name);
        Notification::assertSentTo([$owner, $manager], BusinessAlert::class, fn (BusinessAlert $n, array $via): bool => in_array('mail', $via, true));
        Notification::assertNotSentTo($accountant, BusinessAlert::class);
        $this->assertTrue(SecurityAuditLog::query()->where('event', 'payout_account.added')->exists());
    }

    public function test_crypto_wallet_network_and_address_are_checked(): void
    {
        [, $owner] = $this->merchantWithOwner();
        $this->actingAs($owner);
        $wallet = ['kind' => 'CRYPTO', 'asset' => 'USDT', 'current_password' => 'password'];

        $this->postJson('/api/v1/settings/payout-accounts', [...$wallet, 'network' => 'BTC', 'wallet_address' => 'TXk2c1yDBBqHUuGRLWfKLbqG6UK4Xr9fQa'])->assertUnprocessable()->assertJsonValidationErrors('network');
        $this->postJson('/api/v1/settings/payout-accounts', [...$wallet, 'network' => 'TRC20', 'wallet_address' => '0x52908400098527886E0F7030069857D2E4169EE7'])->assertUnprocessable()->assertJsonValidationErrors('wallet_address');
        $this->postJson('/api/v1/settings/payout-accounts', [...$wallet, 'network' => 'TRC20', 'wallet_address' => 'TXk2c1yDBBqHUuGRLWfKLbqG6UK4Xr9fQa'])->assertCreated();
        $this->assertTrue(PayoutAccounts::addressLooksValid('ERC20', '0x52908400098527886E0F7030069857D2E4169EE7'));
        $this->assertTrue(PayoutAccounts::addressLooksValid('BTC', 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq'));
    }

    public function test_only_managers_outside_support_can_change_and_other_businesses_are_invisible(): void
    {
        [$merchant, $owner] = $this->merchantWithOwner();
        [$other] = $this->merchantWithOwner();
        $foreign = PayoutAccount::query()->create(['merchant_id' => $other->id, 'kind' => 'BANK', 'bank_name' => 'X', 'account_number' => '1234567890', 'account_name' => 'Y', 'is_default' => true]);
        $agent = $this->staff($merchant, Role::SALES_AGENT);

        $this->actingAs($agent)->postJson('/api/v1/settings/payout-accounts', ['kind' => 'BANK', 'bank_name' => 'A', 'account_number' => '1234567890', 'account_name' => 'B', 'current_password' => 'password'])->assertForbidden();
        $this->actingAs($owner)->deleteJson("/api/v1/settings/payout-accounts/{$foreign->id}", ['current_password' => 'password'])->assertNotFound();
        $this->getJson('/api/v1/settings/payout-accounts')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_removing_the_default_promotes_the_next_and_slips_list_shown_accounts(): void
    {
        [$merchant, $owner] = $this->merchantWithOwner();
        $service = app(PayoutAccounts::class);
        $first = $service->create($merchant, $owner, ['kind' => 'BANK', 'bank_name' => 'Providus', 'account_number' => '0192837465', 'account_name' => 'Amina']);
        $second = $service->create($merchant, $owner, ['kind' => 'BANK', 'bank_name' => 'GTBank', 'account_number' => '0011223344', 'account_name' => 'Amina', 'currency' => 'USD']);
        $service->create($merchant, $owner, ['kind' => 'CRYPTO', 'asset' => 'USDT', 'network' => 'TRC20', 'wallet_address' => 'TXk2c1yDBBqHUuGRLWfKLbqG6UK4Xr9fQa']);
        $service->create($merchant, $owner, ['kind' => 'BANK', 'bank_name' => 'Hidden', 'account_number' => '9999999999', 'account_name' => 'Amina', 'show_on_slips' => false]);

        $this->assertCount(3, PayoutAccounts::forSlips($merchant->refresh()));

        $this->actingAs($owner)->deleteJson("/api/v1/settings/payout-accounts/{$first->id}", ['current_password' => 'password'])->assertOk();
        $this->assertTrue($second->refresh()->is_default);
        $this->assertSame('0011223344', $merchant->refresh()->bank_account_number);

        $html = view('pdf.partials.bank', ['d' => ['payouts' => PayoutAccounts::forSlips($merchant), 'bank' => null, 'pay_url' => null, 'primary' => '#377581']])->render();
        $this->assertStringContainsString('0011223344', $html);
        $this->assertStringContainsString('TXk2c1yDBBqHUuGRLWfKLbqG6UK4Xr9fQa', $html);
        $this->assertStringNotContainsString('9999999999', $html);
    }
}
