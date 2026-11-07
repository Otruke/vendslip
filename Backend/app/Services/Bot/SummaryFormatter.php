<?php

declare(strict_types=1);

namespace App\Services\Bot;

use App\Services\Bot\Actions\AskConfirmation;
use App\Support\Money;

/** Plain-text consolidated summary used by Twilio channels (and as Telegram fallback). */
final class SummaryFormatter
{
    public static function text(AskConfirmation $a, bool $withNumberedChoices): string
    {
        $m = static fn (int $minor): string => Money::format($minor, $a->currency);
        $out = ["🧾 {$a->docLabel} for {$a->customerName}"];
        if ($a->customerPhone !== null) {
            $out[] = $a->customerPhone;
        }
        if ($a->customerAddress !== null) {
            $out[] = $a->customerAddress;
        }
        $out[] = '';
        foreach ($a->lines as $i => $line) {
            $was = isset($line['list_minor']) ? ' (was '.$m($line['list_minor']).')' : '';
            $out[] = ($i + 1).'. '.$line['description'].' — '.$line['quantity'].' × '.$m($line['unit_minor']).$was.' = '.$m($line['total_minor']);
        }
        $out[] = '';
        if ($a->discountMinor > 0 || $a->taxMinor > 0 || $a->shippingMinor > 0 || $a->lineDiscountMinor > 0) {
            $out[] = 'Subtotal: '.$m($a->subtotalMinor);
        }
        if ($a->lineDiscountMinor > 0) {
            $out[] = 'Item discounts: -'.$m($a->lineDiscountMinor).' (already in subtotal)';
        }
        if ($a->discountMinor > 0) {
            $out[] = 'Discount: -'.$m($a->discountMinor);
        }
        if ($a->taxMinor > 0) {
            $out[] = $a->taxLabel.': '.$m($a->taxMinor);
        }
        if ($a->shippingMinor > 0) {
            $out[] = 'Shipping: '.$m($a->shippingMinor);
        }
        $out[] = '*Total: '.$m($a->totalMinor).'*';
        if ($a->balanceMinor > 0) {
            $out[] = 'Paid: '.$m($a->paidMinor).' · Balance: '.$m($a->balanceMinor);
        }
        if ($a->willWatermark) {
            $out[] = $a->canUseCleanCredit ? 'Free slip (watermarked). Reply /confirm clean to spend 1 credit instead.' : 'Free slip (watermarked).';
        }
        $out[] = '';
        $out[] = $withNumberedChoices ? 'Reply 1 ✅ Confirm · 2 ✏️ Edit · 3 ❌ Cancel' : 'Confirm to generate the PDF.';

        return implode("\n", $out);
    }
}
