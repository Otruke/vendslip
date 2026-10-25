<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Document;
use App\Support\Urls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Document */
class DocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Document $doc */
        $doc = $this->resource;

        return [
            'id' => $doc->id,
            'doc_type' => $doc->doc_type->value,
            'doc_number' => $doc->doc_number,
            'status' => $doc->status->value,
            'channel' => $doc->channel->value,
            'currency' => $doc->currency,
            'customer_id' => $doc->customer_id,
            'customer_name' => $doc->customer_name,
            'customer_phone' => $doc->customer_phone,
            'customer_email' => $doc->customer_email,
            'customer_address' => $doc->customer_address,
            'subtotal_minor' => $doc->subtotal_minor,
            'discount_minor' => $doc->discount_minor,
            'vat_rate_bps' => $doc->vat_rate_bps,
            'vat_inclusive' => $doc->vat_inclusive,
            'tax_minor' => $doc->tax_minor,
            'shipping_minor' => $doc->shipping_minor,
            'total_minor' => $doc->total_minor,
            'amount_paid_minor' => $doc->amount_paid_minor,
            'balance_due_minor' => $doc->balance_due_minor,
            'consumed_paid_credit' => $doc->consumed_paid_credit,
            'has_watermark' => $doc->has_watermark,
            'revision_number' => $doc->revision_number,
            'verification_code' => $doc->verification_code,
            'verification_hash' => $doc->verification_hash,
            'verify_url' => Urls::verify($doc->verification_hash),
            'pay_url' => $doc->doc_type->value === 'INVOICE' ? Urls::pay($doc->public_uid) : null,
            'template_id' => $doc->template_id,
            'parent_document_id' => $doc->parent_document_id,
            'notes' => $doc->notes,
            'due_date' => $doc->due_date?->toDateString(),
            'issued_at' => $doc->issued_at->toIso8601String(),
            'voided_at' => $doc->voided_at?->toIso8601String(),
            'void_reason' => $doc->void_reason,
            'created_by' => $this->whenLoaded('createdBy', fn () => $doc->createdBy?->name),
            'items' => $this->whenLoaded('items', fn () => $doc->items->map(static fn ($item): array => [
                'id' => $item->id,
                'sku_id' => $item->sku_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price_minor' => $item->unit_price_minor,
                'line_total_minor' => $item->line_total_minor,
            ])->values()),
            'payments' => $this->whenLoaded('payments', fn () => $doc->payments->map(static fn ($p): array => [
                'id' => $p->id,
                'amount_minor' => $p->amount_minor,
                'method' => $p->method->value,
                'reference' => $p->reference,
                'paid_at' => $p->paid_at->toIso8601String(),
                'receipt_document_id' => $p->receipt_document_id,
            ])->values()),
            'children' => $this->whenLoaded('children', fn () => $doc->children->map(static fn (Document $c): array => [
                'id' => $c->id,
                'doc_type' => $c->doc_type->value,
                'doc_number' => $c->doc_number,
                'status' => $c->status->value,
                'total_minor' => $c->total_minor,
                'issued_at' => $c->issued_at->toIso8601String(),
            ])->values()),
            'parent' => $this->whenLoaded('parent', fn () => $doc->parent === null ? null : [
                'id' => $doc->parent->id,
                'doc_number' => $doc->parent->doc_number,
                'doc_type' => $doc->parent->doc_type->value,
            ]),
            'revisions_count' => $this->whenCounted('revisions'),
        ];
    }
}
