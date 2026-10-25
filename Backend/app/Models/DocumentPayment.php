<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $document_id
 * @property int $amount_minor
 * @property PaymentMethod $method
 * @property string|null $reference
 * @property CarbonImmutable $paid_at
 * @property int|null $recorded_by_user_id
 * @property string|null $payment_transaction_id
 * @property string|null $receipt_document_id
 */
class DocumentPayment extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'document_id', 'amount_minor', 'method', 'reference', 'paid_at', 'recorded_by_user_id',
        'payment_transaction_id', 'receipt_document_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'method' => PaymentMethod::class,
            'paid_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<Document, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'receipt_document_id');
    }
}
