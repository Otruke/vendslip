import { mulDivRound } from './money'

/** Mirror of Backend/app/Services/Documents/TotalsCalculator.php */
export type Line = { description: string; quantity: number; unit_price_minor: number; sku_id?: string | null }

export type Totals = {
  lines: (Line & { line_total_minor: number })[]
  subtotalMinor: number
  discountMinor: number
  vatRateBps: number
  vatInclusive: boolean
  taxMinor: number
  shippingMinor: number
  totalMinor: number
  amountPaidMinor: number
  balanceDueMinor: number
}

export function calculateTotals(
  lines: Line[],
  discountMinor: number,
  shippingMinor: number,
  vatRateBps: number,
  vatInclusive: boolean,
  amountPaidMinor: number | null,
): Totals {
  const resolved = lines
    .filter((l) => l.quantity >= 1 && l.unit_price_minor >= 0)
    .map((l) => ({ ...l, line_total_minor: l.quantity * l.unit_price_minor }))
  const subtotal = resolved.reduce((sum, l) => sum + l.line_total_minor, 0)
  const discount = Math.min(Math.max(0, discountMinor), subtotal)
  const taxable = subtotal - discount
  const shipping = Math.max(0, shippingMinor)

  let tax = 0
  let total = taxable + shipping
  if (vatRateBps > 0 && vatInclusive) {
    tax = taxable - mulDivRound(taxable, 10000, 10000 + vatRateBps)
  } else if (vatRateBps > 0) {
    tax = mulDivRound(taxable, vatRateBps, 10000)
    total = taxable + tax + shipping
  }
  const paid = Math.min(Math.max(0, amountPaidMinor ?? 0), total)

  return {
    lines: resolved,
    subtotalMinor: subtotal,
    discountMinor: discount,
    vatRateBps,
    vatInclusive,
    taxMinor: tax,
    shippingMinor: shipping,
    totalMinor: total,
    amountPaidMinor: paid,
    balanceDueMinor: total - paid,
  }
}
