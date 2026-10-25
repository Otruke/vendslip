import { draftTotals, draftType, type DraftPayload } from '@/lib/draft'
import { bpsToPercentLabel } from '@/lib/money'
import type { SlipVariant, SlipView } from './types'

type Brand = { name: string; meta?: string | null; logoUrl?: string | null; primary: string; secondary: string; vatBps: number; vatInclusive: boolean; currency: string; footer?: string | null; bank?: SlipView['bank'] }

const today = () => new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date())

/** Draft (composer / playground) → presentational slip model. */
export function draftToSlipView(draft: DraftPayload, brand: Brand, opts: { variant?: SlipVariant; docNumber?: string; watermark?: boolean } = {}): SlipView {
  const totals = draftTotals(draft, brand.vatBps, brand.vatInclusive)
  const type = draftType(draft)
  const status = totals.balanceDueMinor === 0 ? 'PAID' : totals.amountPaidMinor > 0 ? 'PARTIAL' : 'UNPAID'
  const due = draft.due_date ? new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(`${draft.due_date}T00:00:00`)) : null
  return {
    variant: opts.variant ?? 'classic',
    docType: type,
    docNumber: opts.docNumber ?? `${type === 'INVOICE' ? 'INV' : 'RCP'}-${new Date().getFullYear()}-····`,
    status,
    businessName: brand.name,
    businessMeta: brand.meta,
    logoUrl: brand.logoUrl,
    primary: brand.primary,
    secondary: brand.secondary,
    customerName: draft.name ?? '',
    customerPhone: draft.phone,
    customerAddress: draft.address,
    issuedAt: today(),
    dueDate: type === 'INVOICE' ? due : null,
    currency: brand.currency,
    lines: totals.lines.map((l) => ({ description: l.description, quantity: l.quantity, unit_minor: l.unit_price_minor, total_minor: l.line_total_minor })),
    subtotal: totals.subtotalMinor,
    discount: totals.discountMinor,
    tax: totals.taxMinor,
    taxLabel: `VAT ${bpsToPercentLabel(totals.vatRateBps)}${totals.vatInclusive ? ' incl.' : ''}`,
    shipping: totals.shippingMinor,
    total: totals.totalMinor,
    paid: totals.amountPaidMinor,
    balance: totals.balanceDueMinor,
    verificationCode: null,
    watermark: opts.watermark ?? false,
    bank: brand.bank ?? null,
    footer: brand.footer,
  }
}
