<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A bank account or crypto wallet the business is paid into, printed on its slips.
 *
 * @property int $id
 * @property string $merchant_id
 * @property string $kind BANK|CRYPTO
 * @property string|null $label
 * @property string|null $bank_name
 * @property string|null $account_number
 * @property string|null $account_name
 * @property string|null $currency
 * @property string|null $asset
 * @property string|null $network
 * @property string|null $wallet_address
 * @property bool $show_on_slips
 * @property bool $is_default
 * @property int $sort_order
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Merchant $merchant
 */
final class PayoutAccount extends Model
{
    public const string BANK = 'BANK';

    public const string CRYPTO = 'CRYPTO';

    protected $fillable = [
        'merchant_id', 'kind', 'label', 'bank_name', 'account_number', 'account_name', 'currency',
        'asset', 'network', 'wallet_address', 'show_on_slips', 'is_default', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'show_on_slips' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Merchant, $this> */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function isBank(): bool
    {
        return $this->kind === self::BANK;
    }

    /** "Providus Bank · 0192…7465" / "USDT (TRC20) · TXk2…9fQa", safe for logs and alerts. */
    public function maskedSummary(): string
    {
        $mask = static function (?string $v): string {
            $v = (string) $v;

            return strlen($v) <= 8 ? $v : substr($v, 0, 4).'…'.substr($v, -4);
        };

        return $this->isBank()
            ? trim(($this->bank_name ?? 'Bank').' · '.$mask($this->account_number))
            : "{$this->asset} ({$this->network}) · ".$mask($this->wallet_address);
    }

    /** @return array<string, mixed> */
    public function toPublicArray(): array
    {
        return $this->isBank()
            ? ['kind' => self::BANK, 'label' => $this->label, 'bank_name' => $this->bank_name, 'account_number' => $this->account_number, 'account_name' => $this->account_name, 'currency' => $this->currency]
            : ['kind' => self::CRYPTO, 'label' => $this->label, 'asset' => $this->asset, 'network' => $this->network, 'wallet_address' => $this->wallet_address];
    }
}
