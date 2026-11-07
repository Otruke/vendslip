<?php

declare(strict_types=1);

namespace App\Services\Payouts;

use App\Enums\AuditSeverity;
use App\Exceptions\DomainRuleException;
use App\Models\Merchant;
use App\Models\PayoutAccount;
use App\Models\User;
use App\Services\Notifications\AlertCatalog;
use App\Services\Notifications\MerchantAlerts;
use App\Services\Security\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Bank accounts and crypto wallets printed on a business's slips.
 *
 * Changing where customers send money is the classic way payments get diverted, so every change is a
 * critical audit entry and a security alert that always reaches Owners and Managers by email. The
 * merchant's old single bank fields are kept as a mirror of the default bank account, so existing
 * slip templates and the public pay page keep working.
 */
final class PayoutAccounts
{
    public const int MAX_ACCOUNTS = 10;

    /** Coin → the networks we accept for it. */
    public const array CRYPTO = [
        'USDT' => ['TRC20', 'ERC20', 'BEP20', 'TON', 'SOL'],
        'USDC' => ['ERC20', 'BEP20', 'SOL', 'BASE'],
        'BTC' => ['BTC'],
        'ETH' => ['ERC20', 'BASE'],
        'BNB' => ['BEP20'],
        'SOL' => ['SOL'],
        'TON' => ['TON'],
    ];

    /** Address shape per network: catches pasting a wallet on the wrong network. */
    private const array ADDRESS_PATTERNS = [
        'TRC20' => '/^T[1-9A-HJ-NP-Za-km-z]{33}$/',
        'ERC20' => '/^0x[a-fA-F0-9]{40}$/',
        'BEP20' => '/^0x[a-fA-F0-9]{40}$/',
        'BASE' => '/^0x[a-fA-F0-9]{40}$/',
        'BTC' => '/^(bc1[ac-hj-np-z02-9]{11,71}|[13][1-9A-HJ-NP-Za-km-z]{25,34})$/',
        'SOL' => '/^[1-9A-HJ-NP-Za-km-z]{32,44}$/',
        'TON' => '/^((EQ|UQ|Ef|Uf|kQ|0Q)[A-Za-z0-9_-]{46}|-?\d:[a-fA-F0-9]{64})$/',
    ];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly MerchantAlerts $alerts,
    ) {}

    public static function addressLooksValid(string $network, string $address): bool
    {
        $pattern = self::ADDRESS_PATTERNS[$network] ?? null;

        return $pattern !== null && preg_match($pattern, $address) === 1;
    }

    /** @param array<string, mixed> $data validated input */
    public function create(Merchant $merchant, User $actor, array $data): PayoutAccount
    {
        $account = DB::transaction(function () use ($merchant, $data): PayoutAccount {
            Merchant::query()->whereKey($merchant->id)->lockForUpdate()->first();
            if (PayoutAccount::query()->where('merchant_id', $merchant->id)->count() >= self::MAX_ACCOUNTS) {
                throw new DomainRuleException('You can keep up to '.self::MAX_ACCOUNTS.' payout accounts. Remove one first.', 'payout_limit');
            }
            $first = ! PayoutAccount::query()->where('merchant_id', $merchant->id)->where('kind', $data['kind'])->exists();
            $account = PayoutAccount::query()->create([
                ...$this->fields($data),
                'merchant_id' => $merchant->id,
                'kind' => $data['kind'],
                'is_default' => $first || (bool) ($data['is_default'] ?? false),
                'sort_order' => (int) PayoutAccount::query()->where('merchant_id', $merchant->id)->max('sort_order') + 1,
            ]);
            if ($account->is_default) {
                $this->onlyDefault($account);
            }
            $this->mirror($merchant);

            return $account;
        });
        $this->announce($merchant, $actor, 'added', $account);

        return $account;
    }

    /** @param array<string, mixed> $data validated input */
    public function update(Merchant $merchant, User $actor, PayoutAccount $account, array $data): PayoutAccount
    {
        DB::transaction(function () use ($merchant, $account, $data): void {
            $account->fill($this->fields($data));
            if (array_key_exists('is_default', $data) && (bool) $data['is_default']) {
                $account->is_default = true;
            }
            $account->save();
            if ($account->is_default) {
                $this->onlyDefault($account);
            }
            $this->mirror($merchant);
        });
        if ($account->wasChanged()) {
            $this->announce($merchant, $actor, 'changed', $account);
        }

        return $account;
    }

    public function delete(Merchant $merchant, User $actor, PayoutAccount $account): void
    {
        DB::transaction(function () use ($merchant, $account): void {
            $account->delete();
            if ($account->is_default) {
                // Promote the next account of the same kind so slips still show one.
                $next = PayoutAccount::query()->where('merchant_id', $merchant->id)->where('kind', $account->kind)->orderBy('sort_order')->orderBy('id')->first();
                $next?->forceFill(['is_default' => true])->save();
            }
            $this->mirror($merchant);
        });
        $this->announce($merchant, $actor, 'removed', $account);
    }

    /**
     * The accounts shown on slips, default first.
     *
     * @return list<array<string, mixed>>
     */
    public static function forSlips(Merchant $merchant): array
    {
        return array_values($merchant->payoutAccounts()->where('show_on_slips', true)->get()
            ->map(static fn (PayoutAccount $a): array => $a->toPublicArray())->all());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fields(array $data): array
    {
        $bank = ($data['kind'] ?? null) === PayoutAccount::BANK;
        $label = isset($data['label']) && trim((string) $data['label']) !== '' ? trim((string) $data['label']) : null;

        return [
            'label' => $label,
            'show_on_slips' => (bool) ($data['show_on_slips'] ?? true),
            'bank_name' => $bank ? trim((string) $data['bank_name']) : null,
            'account_number' => $bank ? preg_replace('/[\s-]/', '', (string) $data['account_number']) : null,
            'account_name' => $bank ? trim((string) $data['account_name']) : null,
            'currency' => $bank && ! empty($data['currency']) ? strtoupper((string) $data['currency']) : null,
            'asset' => $bank ? null : strtoupper((string) $data['asset']),
            'network' => $bank ? null : strtoupper((string) $data['network']),
            'wallet_address' => $bank ? null : trim((string) $data['wallet_address']),
        ];
    }

    private function onlyDefault(PayoutAccount $account): void
    {
        PayoutAccount::query()->where('merchant_id', $account->merchant_id)->where('kind', $account->kind)
            ->whereKeyNot($account->id)->update(['is_default' => false]);
    }

    /** Keep the merchant's legacy single-bank fields equal to the default bank account shown on slips. */
    private function mirror(Merchant $merchant): void
    {
        $bank = PayoutAccount::query()->where('merchant_id', $merchant->id)->where('kind', PayoutAccount::BANK)
            ->where('show_on_slips', true)->orderByDesc('is_default')->orderBy('sort_order')->orderBy('id')->first();
        $merchant->forceFill([
            'bank_name' => $bank?->bank_name,
            'bank_account_number' => $bank?->account_number,
            'bank_account_name' => $bank?->account_name,
        ])->save();
    }

    private function announce(Merchant $merchant, User $actor, string $verb, PayoutAccount $account): void
    {
        $what = $account->isBank() ? 'Bank account' : 'Crypto wallet';
        $this->audit->log("payout_account.{$verb}", $actor, $merchant, [
            'account_id' => $account->id, 'kind' => $account->kind, 'summary' => $account->maskedSummary(),
            'changes' => $verb === 'changed' ? array_keys(array_diff_key($account->getChanges(), ['updated_at' => true])) : null,
        ], AuditSeverity::CRITICAL);
        $this->alerts->send(
            $merchant,
            AlertCatalog::PAYOUT_CHANGED,
            "{$what} {$verb}",
            "{$actor->name} {$verb} a payout account on your slips: {$account->maskedSummary()}. If this wasn't you or your team, change it back and review your team's access now.",
            '/dashboard/settings?tab=payouts',
        );
    }
}
