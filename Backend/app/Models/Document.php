<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Channel;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Concerns\BelongsToMerchant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $merchant_id
 * @property string|null $customer_id
 * @property int|null $template_id
 * @property int|null $created_by_user_id
 * @property string|null $parent_document_id
 * @property DocumentType $doc_type
 * @property string $doc_number
 * @property string $public_uid
 * @property DocumentStatus $status
 * @property Channel $channel
 * @property string $currency
 * @property string $customer_name
 * @property string|null $customer_phone
 * @property string|null $customer_email
 * @property string|null $customer_address
 * @property int $subtotal_minor
 * @property int $discount_minor
 * @property int $vat_rate_bps
 * @property bool $vat_inclusive
 * @property int $tax_minor
 * @property int $shipping_minor
 * @property int $total_minor
 * @property int $amount_paid_minor
 * @property int $balance_due_minor
 * @property bool $consumed_paid_credit
 * @property bool $has_watermark
 * @property int $revision_number
 * @property string $verification_hash
 * @property string $verification_code
 * @property string|null $pdf_storage_path
 * @property string|null $notes
 * @property CarbonImmutable|null $due_date
 * @property CarbonImmutable $issued_at
 * @property CarbonImmutable|null $voided_at
 * @property string|null $void_reason
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Merchant $merchant
 * @property-read Template|null $template
 * @property-read Collection<int, DocumentItem> $items
 * @property-read Collection<int, DocumentRevision> $revisions
 * @property-read Collection<int, DocumentPayment> $payments
 */
class Document extends Model
{
    use BelongsToMerchant, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'merchant_id', 'customer_id', 'template_id', 'created_by_user_id', 'parent_document_id',
        'doc_type', 'doc_number', 'public_uid', 'status', 'channel', 'currency',
        'customer_name', 'customer_phone', 'customer_email', 'customer_address',
        'subtotal_minor', 'discount_minor', 'vat_rate_bps', 'vat_inclusive', 'tax_minor', 'shipping_minor',
        'total_minor', 'amount_paid_minor', 'balance_due_minor',
        'consumed_paid_credit', 'has_watermark', 'revision_number', 'verification_hash', 'verification_code',
        'pdf_storage_path', 'notes', 'due_date', 'issued_at', 'voided_at', 'void_reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'doc_type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'channel' => Channel::class,
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'vat_rate_bps' => 'integer',
            'vat_inclusive' => 'boolean',
            'tax_minor' => 'integer',
            'shipping_minor' => 'integer',
            'total_minor' => 'integer',
            'amount_paid_minor' => 'integer',
            'balance_due_minor' => 'integer',
            'consumed_paid_credit' => 'boolean',
            'has_watermark' => 'boolean',
            'revision_number' => 'integer',
            'due_date' => 'immutable_date',
            'issued_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Template, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<Document, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_document_id');
    }

    /** @return HasMany<Document, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_document_id');
    }

    /** @return HasMany<DocumentItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(DocumentItem::class)->orderBy('position');
    }

    /** @return HasMany<DocumentRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(DocumentRevision::class)->orderByDesc('id');
    }

    /** @return HasMany<DocumentPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(DocumentPayment::class)->orderBy('paid_at');
    }

    public function isVoid(): bool
    {
        return $this->status === DocumentStatus::VOID;
    }

    /** Sum of credit notes already issued against this document. */
    public function creditedAmountMinor(): int
    {
        return (int) $this->children()
            ->where('doc_type', DocumentType::CREDIT_NOTE->value)
            ->where('status', '!=', DocumentStatus::VOID->value)
            ->sum('total_minor');
    }
}
