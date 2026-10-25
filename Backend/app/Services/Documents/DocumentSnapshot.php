<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Document;

final class DocumentSnapshot
{
    /** @return array<string, mixed> */
    public static function of(Document $document): array
    {
        $document->loadMissing('items');

        return [
            'doc_number' => $document->doc_number,
            'status' => $document->status->value,
            'revision_number' => $document->revision_number,
            'customer_name' => $document->customer_name,
            'customer_phone' => $document->customer_phone,
            'customer_email' => $document->customer_email,
            'customer_address' => $document->customer_address,
            'items' => $document->items->map(static fn ($item): array => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price_minor' => $item->unit_price_minor,
                'line_total_minor' => $item->line_total_minor,
            ])->values()->all(),
            'subtotal_minor' => $document->subtotal_minor,
            'discount_minor' => $document->discount_minor,
            'vat_rate_bps' => $document->vat_rate_bps,
            'vat_inclusive' => $document->vat_inclusive,
            'tax_minor' => $document->tax_minor,
            'shipping_minor' => $document->shipping_minor,
            'total_minor' => $document->total_minor,
            'amount_paid_minor' => $document->amount_paid_minor,
            'balance_due_minor' => $document->balance_due_minor,
            'due_date' => $document->due_date?->toDateString(),
            'notes' => $document->notes,
            'template_id' => $document->template_id,
            'verification_hash' => $document->verification_hash,
        ];
    }
}
