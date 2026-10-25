<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\MerchantStatus;
use App\Enums\MutabilityMode;
use App\Enums\PlanTier;
use Carbon\CarbonImmutable;
use Database\Factories\MerchantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $rc_number
 * @property string|null $tax_id
 * @property string $currency
 * @property int $vat_rate_bps
 * @property bool $vat_inclusive
 * @property string $primary_color
 * @property string $secondary_color
 * @property string|null $logo_path
 * @property string|null $receipt_footer
 * @property string|null $bank_name
 * @property string|null $bank_account_number
 * @property string|null $bank_account_name
 * @property int $invoice_due_days
 * @property int|null $default_receipt_template_id
 * @property int|null $default_invoice_template_id
 * @property PlanTier $plan_tier
 * @property BillingCycle|null $billing_cycle
 * @property CarbonImmutable|null $plan_expires_at
 * @property int $free_quota_used
 * @property string|null $free_quota_period
 * @property int $paid_credits_balance
 * @property MutabilityMode $record_mutability_mode
 * @property bool $allow_staff_edits
 * @property int $mutable_window_hours
 * @property string|null $flutterwave_public_key
 * @property string|null $flutterwave_secret_key
 * @property string|null $flutterwave_secret_hash
 * @property string|null $nowpayments_api_key
 * @property string|null $nowpayments_ipn_secret
 * @property MerchantStatus $status
 * @property string|null $suspension_reason
 * @property CarbonImmutable|null $onboarding_completed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class Merchant extends Model
{
    /** @use HasFactory<MerchantFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'name', 'slug', 'email', 'phone', 'address', 'rc_number', 'tax_id', 'currency',
        'vat_rate_bps', 'vat_inclusive', 'primary_color', 'secondary_color', 'logo_path', 'receipt_footer',
        'bank_name', 'bank_account_number', 'bank_account_name', 'invoice_due_days',
        'default_receipt_template_id', 'default_invoice_template_id',
        'plan_tier', 'billing_cycle', 'plan_expires_at', 'free_quota_used', 'free_quota_period', 'paid_credits_balance',
        'record_mutability_mode', 'allow_staff_edits', 'mutable_window_hours',
        'flutterwave_public_key', 'flutterwave_secret_key', 'flutterwave_secret_hash',
        'nowpayments_api_key', 'nowpayments_ipn_secret',
        'status', 'suspension_reason', 'onboarding_completed_at',
    ];

    /** @var list<string> */
    protected $hidden = [
        'flutterwave_secret_key', 'flutterwave_secret_hash', 'nowpayments_api_key', 'nowpayments_ipn_secret',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'vat_rate_bps' => 'integer',
            'vat_inclusive' => 'boolean',
            'invoice_due_days' => 'integer',
            'plan_tier' => PlanTier::class,
            'billing_cycle' => BillingCycle::class,
            'plan_expires_at' => 'immutable_datetime',
            'free_quota_used' => 'integer',
            'paid_credits_balance' => 'integer',
            'record_mutability_mode' => MutabilityMode::class,
            'allow_staff_edits' => 'boolean',
            'mutable_window_hours' => 'integer',
            'flutterwave_secret_key' => 'encrypted',
            'flutterwave_secret_hash' => 'encrypted',
            'nowpayments_api_key' => 'encrypted',
            'nowpayments_ipn_secret' => 'encrypted',
            'status' => MerchantStatus::class,
            'onboarding_completed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** @return HasMany<Customer, $this> */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /** @return HasMany<InventorySku, $this> */
    public function skus(): HasMany
    {
        return $this->hasMany(InventorySku::class);
    }

    /** @return BelongsTo<Template, $this> */
    public function defaultReceiptTemplate(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'default_receipt_template_id');
    }

    /** @return BelongsTo<Template, $this> */
    public function defaultInvoiceTemplate(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'default_invoice_template_id');
    }

    /**
     * The tier the merchant is actually entitled to right now. A paid tier whose
     * expiry has passed silently degrades to FREE until renewed.
     */
    public function effectiveTier(): PlanTier
    {
        if ($this->plan_tier !== PlanTier::FREE && $this->plan_expires_at !== null && $this->plan_expires_at->isPast()) {
            return PlanTier::FREE;
        }

        return $this->plan_tier;
    }

    public function isSuspended(): bool
    {
        return $this->status === MerchantStatus::SUSPENDED;
    }

    public function hasFlutterwave(): bool
    {
        return filled($this->flutterwave_secret_key);
    }

    public function hasNowPayments(): bool
    {
        return filled($this->nowpayments_api_key);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path !== null ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function logoAbsolutePath(): ?string
    {
        if ($this->logo_path === null || ! Storage::disk('public')->exists($this->logo_path)) {
            return null;
        }

        return Storage::disk('public')->path($this->logo_path);
    }
}
