<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Support\Money;
use InvalidArgumentException;

/**
 * Pure minor-unit arithmetic. Mirrored by Frontend/src/lib/totals.ts.
 *
 *  subtotal = Σ qty × unit
 *  taxable  = subtotal − discount               (discount capped at subtotal)
 *  VAT exclusive: tax = round(taxable × bps / 10000);  total = taxable + tax + shipping
 *  VAT inclusive: tax = taxable − round(taxable × 10000 / (10000 + bps)); total = taxable + shipping
 *  balance  = total − paid                      (paid capped at total)
 */
final class TotalsCalculator
{
    /**
     * @param  list<array{description: string, quantity: int, unit_price_minor: int, sku_id?: string|null}>  $lines
     * @param  int|null  $amountPaidMinor  null = nothing paid
     */
    public function calculate(
        array $lines,
        int $discountMinor,
        int $shippingMinor,
        int $vatRateBps,
        bool $vatInclusive,
        ?int $amountPaidMinor,
    ): Totals {
        if ($discountMinor < 0 || $shippingMinor < 0 || $vatRateBps < 0 || $vatRateBps > 10000 || ($amountPaidMinor ?? 0) < 0) {
            throw new InvalidArgumentException('Monetary inputs must be non-negative and VAT at most 100%.');
        }

        $resolved = [];
        $subtotal = 0;
        foreach ($lines as $line) {
            if ($line['quantity'] < 1 || $line['unit_price_minor'] < 0) {
                throw new InvalidArgumentException('Each line needs quantity ≥ 1 and a non-negative price.');
            }
            $lineTotal = $line['quantity'] * $line['unit_price_minor'];
            if ($lineTotal > Money::MAX_MINOR) {
                throw new InvalidArgumentException('Line total exceeds the supported maximum.');
            }
            $subtotal += $lineTotal;
            $resolved[] = [
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price_minor' => $line['unit_price_minor'],
                'line_total_minor' => $lineTotal,
                'sku_id' => $line['sku_id'] ?? null,
            ];
        }
        if ($subtotal > Money::MAX_MINOR) {
            throw new InvalidArgumentException('Subtotal exceeds the supported maximum.');
        }

        $discount = min($discountMinor, $subtotal);
        $taxable = $subtotal - $discount;

        if ($vatRateBps === 0) {
            $tax = 0;
            $total = $taxable + $shippingMinor;
        } elseif ($vatInclusive) {
            $net = Money::divRound($taxable * 10000, 10000 + $vatRateBps);
            $tax = $taxable - $net;
            $total = $taxable + $shippingMinor;
        } else {
            $tax = Money::divRound($taxable * $vatRateBps, 10000);
            $total = $taxable + $tax + $shippingMinor;
        }

        $paid = min($amountPaidMinor ?? 0, $total);

        return new Totals(
            lines: $resolved,
            subtotalMinor: $subtotal,
            discountMinor: $discount,
            vatRateBps: $vatRateBps,
            vatInclusive: $vatInclusive,
            taxMinor: $tax,
            shippingMinor: $shippingMinor,
            totalMinor: $total,
            amountPaidMinor: $paid,
            balanceDueMinor: $total - $paid,
        );
    }
}
