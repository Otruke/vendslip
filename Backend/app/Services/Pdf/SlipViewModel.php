<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Support\Money;
use App\Support\Urls;

/**
 * Pre-formatted, template-agnostic data for slip templates (Blade and tokenised HTML).
 * Money is pre-rendered as HTML because the Lupio demo cut lacks the ₦ glyph: the
 * currency symbol is wrapped in <span class="cur"> and drawn with DejaVu Sans.
 */
final class SlipViewModel
{
    public function __construct(private readonly QrCodeService $qr) {}

    /** @return array<string, mixed> */
    public function build(Document $document): array
    {
        $document->loadMissing('items', 'merchant', 'parent');
        $merchant = $document->merchant;
        $currency = $document->currency;
        $money = static fn (int $minor): string => self::moneyHtml($minor, $currency);

        $verifyUrl = Urls::verify($document->verification_hash);
        $payUrl = $document->doc_type === DocumentType::INVOICE && $document->balance_due_minor > 0
            && ($merchant->hasFlutterwave() || $merchant->hasNowPayments())
            ? Urls::pay($document->public_uid) : null;

        $logoPath = $merchant->logoAbsolutePath();
        $logo = $logoPath !== null ? 'data:'.(mime_content_type($logoPath) ?: 'image/png').';base64,'.base64_encode((string) file_get_contents($logoPath)) : null;

        $title = match ($document->doc_type) {
            DocumentType::RECEIPT => $document->parent_document_id !== null ? 'Payment Receipt' : 'Receipt',
            DocumentType::INVOICE => 'Invoice',
            DocumentType::CREDIT_NOTE => 'Credit Note',
        };

        $statusLabel = match ($document->status) {
            DocumentStatus::PAID => $document->doc_type === DocumentType::CREDIT_NOTE ? 'Applied' : 'Paid',
            DocumentStatus::PARTIAL => 'Part paid',
            DocumentStatus::UNPAID => 'Unpaid',
            DocumentStatus::OVERDUE => 'Overdue',
            DocumentStatus::VOID => 'Void',
            DocumentStatus::DRAFT => 'Draft',
        };

        return [
            'document' => $document,
            'merchant' => $merchant,
            'title' => $title,
            'status_label' => $statusLabel,
            'status' => $document->status->value,
            'is_void' => $document->isVoid(),
            'primary' => self::safeColor($merchant->primary_color, '#377581'),
            'secondary' => self::safeColor($merchant->secondary_color, '#265363'),
            'logo' => $logo,
            'netslip_logo' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(resource_path('brand/netslip-icon.png'))),
            'issued_at' => $document->issued_at->setTimezone(config('netslip.timezone'))->format('d M Y, g:i A'),
            'issued_date' => $document->issued_at->setTimezone(config('netslip.timezone'))->format('d M Y'),
            'due_date' => $document->due_date?->format('d M Y'),
            'parent_number' => $document->parent?->doc_number,
            'items' => $document->items->map(static fn ($item): array => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $money($item->unit_price_minor),
                'total' => $money($item->line_total_minor),
            ])->all(),
            'subtotal' => $money($document->subtotal_minor),
            'discount' => $document->discount_minor > 0 ? $money($document->discount_minor) : null,
            'tax' => $document->tax_minor > 0 ? $money($document->tax_minor) : null,
            'tax_label' => 'VAT '.rtrim(rtrim(number_format($document->vat_rate_bps / 100, 2), '0'), '.').'%'.($document->vat_inclusive ? ' (incl.)' : ''),
            'shipping' => $document->shipping_minor > 0 ? $money($document->shipping_minor) : null,
            'total' => $money($document->total_minor),
            'paid' => $document->amount_paid_minor > 0 ? $money($document->amount_paid_minor) : null,
            'balance' => $document->balance_due_minor > 0 ? $money($document->balance_due_minor) : null,
            'verify_url' => $verifyUrl,
            'pay_url' => $payUrl,
            'qr' => $this->qr->pngDataUri($verifyUrl),
            'verification_code' => $document->verification_code,
            'hash_short' => substr($document->verification_hash, 0, 16).'…'.substr($document->verification_hash, -8),
            'watermark' => $document->has_watermark,
            'tagline' => 'Close the sale. Send the slip',
            'footer' => $merchant->receipt_footer ?: 'Thank you for your patronage!',
            'bank' => $merchant->bank_account_number !== null ? [
                'name' => $merchant->bank_name,
                'number' => $merchant->bank_account_number,
                'account_name' => $merchant->bank_account_name ?: $merchant->name,
            ] : null,
        ];
    }

    public static function moneyHtml(int $minor, string $currency): string
    {
        $symbol = e(Money::symbol($currency));
        $amount = e(Money::format($minor, $currency, false));

        return '<span class="cur">'.$symbol.'</span>'.$amount;
    }

    private static function safeColor(?string $color, string $fallback): string
    {
        return is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? $color : $fallback;
    }
}
