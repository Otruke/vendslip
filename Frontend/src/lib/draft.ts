import type { DocumentType } from '@/types/generated'
import { mulDivRound, parseToMinor, percentToBps } from './money'
import type { ParseResult } from './slash'
import { calculateTotals, type Totals } from './totals'

/** Mirror of Backend/app/Services/Slash/DraftPayload.php */
export const PAID_FULL = 'FULL' as const

export type DraftItem = { description: string; quantity: number; unit_price_minor: number | null; sku_id?: string | null }

export type DraftPayload = {
  type: 'RECEIPT' | 'INVOICE' | null
  name: string | null
  phone: string | null
  email: string | null
  address: string | null
  items: DraftItem[]
  amount_minor: number | null
  discount_minor: number | null
  discount_bps: number | null
  shipping_minor: number | null
  paid: number | typeof PAID_FULL | null
  vat_rate_bps: number | null
  vat_inclusive: boolean | null
  due_date: string | null
  note: string | null
  template: string | null
}

export function emptyDraft(): DraftPayload {
  return {
    type: null, name: null, phone: null, email: null, address: null, items: [], amount_minor: null,
    discount_minor: null, discount_bps: null, shipping_minor: null, paid: null, vat_rate_bps: null,
    vat_inclusive: null, due_date: null, note: null, template: null,
  }
}

function isoDate(d: Date): string {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

function validDate(y: number, m: number, d: number): Date | null {
  const date = new Date(y, m - 1, d)
  return date.getFullYear() === y && date.getMonth() === m - 1 && date.getDate() === d ? date : null
}

export function parseDueDate(value: string, today = new Date()): string | null {
  const v = value.trim().toLowerCase()
  let m = /^\+?(\d{1,3})\s*(d|day|days)?$/.exec(v)
  if (m) {
    const d = new Date(today.getFullYear(), today.getMonth(), today.getDate() + Number(m[1]))
    return isoDate(d)
  }
  if ((m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v))) {
    const d = validDate(Number(m[1]), Number(m[2]), Number(m[3]))
    return d ? isoDate(d) : null
  }
  if ((m = /^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$/.exec(v))) {
    const d = validDate(Number(m[3]), Number(m[2]), Number(m[1]))
    return d ? isoDate(d) : null
  }
  if (v === 'tomorrow') return isoDate(new Date(today.getFullYear(), today.getMonth(), today.getDate() + 1))
  return null
}

function applyVat(draft: DraftPayload, raw: string): string | null {
  let inclusive: boolean | null = null
  if (/\b(incl|inclusive|inc)\b/.test(raw)) inclusive = true
  else if (/\b(excl|exclusive|exc)\b/.test(raw)) inclusive = false
  const rate = raw.replace(/\b(incl|inclusive|inc|excl|exclusive|exc)\b/g, '').trim()

  if (['off', 'no', 'none', '0', '0%'].includes(rate)) draft.vat_rate_bps = 0
  else if (['', 'on', 'yes', 'default'].includes(rate)) draft.vat_rate_bps = null
  else {
    const bps = percentToBps(rate)
    if (bps === null) return `Couldn't read /vat "${raw}". Try 7.5 or off.`
    draft.vat_rate_bps = bps
  }
  if (inclusive !== null) draft.vat_inclusive = inclusive
  return null
}

function applyField(draft: DraftPayload, key: string, value: string, today: Date): string | null {
  const lower = value.trim().toLowerCase()
  switch (key) {
    case 'type': {
      if (['receipt', 'rcp', 'r', 'rct'].includes(lower)) draft.type = 'RECEIPT'
      else if (['invoice', 'inv', 'i', 'bill'].includes(lower)) draft.type = 'INVOICE'
      else return '/type must be receipt or invoice.'
      return null
    }
    case 'name': draft.name = value.slice(0, 160); return null
    case 'address': draft.address = value.slice(0, 500); return null
    case 'note': draft.note = value.slice(0, 1000); return null
    case 'template': draft.template = lower.slice(0, 100); return null
    case 'phone': {
      const phone = value.replace(/[^\d+]/g, '')
      const digits = phone.replace(/^\+/, '').length
      if (digits < 7 || digits > 15) return `That phone number doesn't look right: ${value}`
      draft.phone = phone
      return null
    }
    case 'email': {
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())) return `That email doesn't look right: ${value}`
      draft.email = value.trim().toLowerCase()
      return null
    }
    case 'amount':
    case 'shipping': {
      const minor = parseToMinor(value)
      if (minor === null) return `Couldn't read /${key} "${value}". Try 15000 or 15k.`
      if (key === 'amount') draft.amount_minor = minor
      else draft.shipping_minor = minor
      return null
    }
    case 'discount': {
      if (lower.endsWith('%')) {
        const bps = percentToBps(lower)
        if (bps === null) return `Couldn't read /discount "${value}".`
        draft.discount_bps = bps
        draft.discount_minor = null
        return null
      }
      const minor = parseToMinor(value)
      if (minor === null) return `Couldn't read /discount "${value}".`
      draft.discount_minor = minor
      draft.discount_bps = null
      return null
    }
    case 'paid': {
      if (['full', 'all', 'yes', 'paid'].includes(lower)) { draft.paid = PAID_FULL; return null }
      if (['none', 'no', 'nothing'].includes(lower)) { draft.paid = 0; return null }
      const minor = parseToMinor(value)
      if (minor === null) return `Couldn't read /paid "${value}". Use an amount or 'full'.`
      draft.paid = minor
      return null
    }
    case 'vat': return applyVat(draft, lower)
    case 'due': {
      const date = parseDueDate(lower, today)
      if (date === null) return `Couldn't read /due "${value}". Try 7d or 2026-11-30.`
      draft.due_date = date
      return null
    }
  }
  return null
}

/** Merge a parsed message into a draft (scalars overwrite, /item appends). Pure function. */
export function applyParse(base: DraftPayload, parsed: ParseResult, today = new Date()): { draft: DraftPayload; errors: string[] } {
  const draft: DraftPayload = { ...base, items: [...base.items] }
  const errors = [...parsed.errors]
  for (const [key, value] of Object.entries(parsed.fields)) {
    const error = applyField(draft, key, value, today)
    if (error) errors.push(error)
  }
  for (const item of parsed.items) draft.items.push({ description: item.description, quantity: item.quantity, unit_price_minor: item.unit_price_minor })
  for (const unknown of parsed.unknownKeys) errors.push(`/${unknown} isn't a NetSlip field.`)
  return { draft, errors }
}

export function missingFields(draft: DraftPayload): string[] {
  const missing: string[] = []
  if (!draft.name || draft.name.trim() === '') missing.push('/name')
  if (draft.items.length === 0 && draft.amount_minor === null) missing.push('/amount or /item')
  draft.items.forEach((item, i) => {
    if (item.unit_price_minor === null) missing.push(`price for item ${i + 1} (${item.description})`)
  })
  return missing
}

export function draftLines(draft: DraftPayload): { description: string; quantity: number; unit_price_minor: number; sku_id: string | null }[] {
  const lines = draft.items.map((i) => ({ description: i.description, quantity: i.quantity, unit_price_minor: i.unit_price_minor ?? 0, sku_id: i.sku_id ?? null }))
  if (lines.length === 0 && draft.amount_minor !== null) {
    lines.push({ description: 'Goods & services', quantity: 1, unit_price_minor: draft.amount_minor, sku_id: null })
  }
  return lines
}

export function draftType(draft: DraftPayload): DocumentType {
  return draft.type ?? 'RECEIPT'
}

/** Live totals for the preview using merchant VAT defaults when the draft doesn't override them. */
export function draftTotals(draft: DraftPayload, merchantVatBps: number, merchantVatInclusive: boolean): Totals {
  const lines = draftLines(draft)
  const subtotal = lines.reduce((s, l) => s + l.quantity * l.unit_price_minor, 0)
  const discount = draft.discount_bps !== null ? mulDivRound(subtotal, draft.discount_bps, 10000) : draft.discount_minor ?? 0
  const preview = calculateTotals(lines, discount, draft.shipping_minor ?? 0, draft.vat_rate_bps ?? merchantVatBps, draft.vat_inclusive ?? merchantVatInclusive, null)
  const paid = draft.paid === PAID_FULL || draft.paid === null
    ? (draftType(draft) === 'RECEIPT' ? preview.totalMinor : 0)
    : draft.paid
  return calculateTotals(lines, discount, draft.shipping_minor ?? 0, preview.vatRateBps, preview.vatInclusive, paid)
}

/** Request body for POST /api/v1/documents */
export function draftToRequest(draft: DraftPayload, templateId: number | null, useCredit: boolean) {
  const lines = draftLines(draft)
  const subtotal = lines.reduce((s, l) => s + l.quantity * l.unit_price_minor, 0)
  const discount = draft.discount_bps !== null ? mulDivRound(subtotal, draft.discount_bps, 10000) : draft.discount_minor ?? 0
  return {
    type: draftType(draft),
    customer_name: draft.name ?? '',
    customer_phone: draft.phone,
    customer_email: draft.email,
    customer_address: draft.address,
    items: lines,
    discount_minor: discount,
    shipping_minor: draft.shipping_minor ?? 0,
    vat_rate_bps: draft.vat_rate_bps,
    vat_inclusive: draft.vat_inclusive,
    amount_paid_minor: draft.paid === PAID_FULL || draft.paid === null ? null : draft.paid,
    due_date: draftType(draft) === 'INVOICE' ? draft.due_date : null,
    notes: draft.note,
    template_id: templateId,
    use_paid_credit: useCredit,
  }
}

function majorString(minor: number): string {
  const s = (minor / 100).toFixed(2)
  return s.endsWith('.00') ? s.slice(0, -3) : s
}

/** Serialise a draft back into /slash text (form → command bar). Round-trips through parseSlash. */
export function draftToSlash(draft: DraftPayload): string {
  const clean = (v: string) => v.replace(/[@|;\n]/g, ' ').replace(/\s+\//g, ' ').trim()
  const lines: string[] = []
  if (draft.type === 'INVOICE') lines.push('/type = invoice')
  if (draft.name) lines.push(`/name = ${clean(draft.name)}`)
  if (draft.phone) lines.push(`/phone = ${draft.phone}`)
  if (draft.email) lines.push(`/email = ${draft.email}`)
  if (draft.address) lines.push(`/address = ${clean(draft.address)}`)
  for (const item of draft.items) {
    if (!item.description.trim()) continue
    const price = item.unit_price_minor !== null ? ` @ ${majorString(item.unit_price_minor)}` : ''
    lines.push(`/item = ${item.quantity}x ${clean(item.description)}${price}`)
  }
  if (draft.items.length === 0 && draft.amount_minor !== null) lines.push(`/amount = ${majorString(draft.amount_minor)}`)
  if (draft.discount_bps !== null) lines.push(`/discount = ${(draft.discount_bps / 100).toString()}%`)
  else if (draft.discount_minor) lines.push(`/discount = ${majorString(draft.discount_minor)}`)
  if (draft.shipping_minor) lines.push(`/shipping = ${majorString(draft.shipping_minor)}`)
  if (draft.vat_rate_bps !== null || draft.vat_inclusive !== null) {
    const rate = draft.vat_rate_bps === null ? 'on' : draft.vat_rate_bps === 0 ? 'off' : (draft.vat_rate_bps / 100).toString()
    const incl = draft.vat_inclusive === null ? '' : draft.vat_inclusive ? ' incl' : ' excl'
    lines.push(`/vat = ${rate}${incl}`)
  }
  if (draft.paid === PAID_FULL) lines.push('/paid = full')
  else if (draft.paid !== null) lines.push(`/paid = ${majorString(draft.paid)}`)
  if (draft.due_date) lines.push(`/due = ${draft.due_date}`)
  if (draft.note) lines.push(`/note = ${clean(draft.note)}`)
  return lines.join('\n')
}
