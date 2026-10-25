<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\PlanTier;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $merchant_id
 * @property string|null $document_id
 * @property int|null $initiated_by_user_id
 * @property PaymentPurpose $purpose
 * @property PaymentGateway $gateway
 * @property PaymentStatus $status
 * @property string $reference
 * @property string|null $gateway_reference
 * @property int $amount_minor
 * @property string $currency
 * @property int|null $credits
 * @property PlanTier|null $plan_tier
 * @property BillingCycle|null $billing_cycle
 * @property string|null $checkout_url
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable $created_at
 * @property-read Merchant|null $merchant
 * @property-read Document|null $document
 */
class PaymentTransaction extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'merchant_id', 'document_id', 'initiated_by_user_id', 'purpose', 'gateway', 'status', 'reference',
        'gateway_reference', 'amount_minor', 'currency', 'credits', 'plan_tier', 'billing_cycle', 'checkout_url',
        'metadata', 'paid_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'purpose' => PaymentPurpose::class,
            'gateway' => PaymentGateway::class,
            'status' => PaymentStatus::class,
            'amount_minor' => 'integer',
            'credits' => 'integer',
            'plan_tier' => PlanTier::class,
            'billing_cycle' => BillingCycle::class,
            'metadata' => 'array',
            'paid_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Merchant, $this> */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
