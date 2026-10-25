<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Enums\Channel;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\Merchant;
use App\Models\Template;
use App\Services\Documents\TotalsCalculator;
use App\Services\Documents\VerificationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/** Builds an unsaved, realistic document for template previews (never persisted). */
final class SampleDocumentFactory
{
    public function __construct(
        private readonly TotalsCalculator $calculator,
        private readonly VerificationService $verification,
    ) {}

    public function make(Merchant $merchant, Template $template, DocumentType $type, bool $watermark = false): Document
    {
        $lines = [
            ['description' => 'Leather Minimalist Sneakers', 'quantity' => 2, 'unit_price_minor' => 2850000],
            ['description' => 'Hand-stitched Leather Belt', 'quantity' => 1, 'unit_price_minor' => 1200000],
        ];
        $paid = $type === DocumentType::RECEIPT ? PHP_INT_MAX : 2000000;
        $totals = $this->calculator->calculate($lines, 0, 350000, $merchant->vat_rate_bps, $merchant->vat_inclusive, $paid);
        $now = CarbonImmutable::now(config('netslip.timezone'));

        $document = new Document([
            'merchant_id' => $merchant->id,
            'template_id' => $template->id,
            'doc_type' => $type,
            'doc_number' => $type->prefix().'-'.$now->format('Y').'-0042',
            'public_uid' => 'preview',
            'status' => $totals->statusFor($type),
            'channel' => Channel::WHATSAPP,
            'currency' => $merchant->currency,
            'customer_name' => 'Chief Emeka Nwosu',
            'customer_phone' => '+2348039981122',
            'customer_address' => '42 Wuse II Crescent, Abuja',
            'subtotal_minor' => $totals->subtotalMinor,
            'discount_minor' => 0,
            'vat_rate_bps' => $totals->vatRateBps,
            'vat_inclusive' => $totals->vatInclusive,
            'tax_minor' => $totals->taxMinor,
            'shipping_minor' => $totals->shippingMinor,
            'total_minor' => $totals->totalMinor,
            'amount_paid_minor' => $totals->amountPaidMinor,
            'balance_due_minor' => $totals->balanceDueMinor,
            'has_watermark' => $watermark,
            'revision_number' => 1,
            'notes' => 'Sample slip for template preview.',
            'due_date' => $type === DocumentType::INVOICE ? $now->addDays(7) : null,
            'issued_at' => $now,
        ]);
        if ($document->status === DocumentStatus::DRAFT) {
            $document->status = DocumentStatus::PAID;
        }

        $items = new Collection(array_map(static fn (array $l, int $i): DocumentItem => new DocumentItem([...$l, 'position' => $i]), $totals->lines, array_keys($totals->lines)));
        $document->setRelation('items', $items);
        $document->setRelation('merchant', $merchant);
        $document->setRelation('template', $template);
        $document->setRelation('parent', null);
        $this->verification->stamp($document);

        return $document;
    }
}
