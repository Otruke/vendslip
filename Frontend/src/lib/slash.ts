import { parseToMinor, percentToBps } from './money'

/**
 * VendSlip /slash parser — 1:1 mirror of Backend/app/Services/Slash/SlashCommandParser.php.
 * Keep both in sync; Backend tests in tests/Unit/SlashParserTest.php define the contract.
 */
export const KEY_ALIASES: Record<string, string> = {
  name: 'name', customer: 'name', client: 'name', buyer: 'name',
  phone: 'phone', tel: 'phone', mobile: 'phone', whatsapp: 'phone',
  email: 'email', mail: 'email',
  address: 'address', addr: 'address', location: 'address',
  amount: 'amount', total: 'amount', price: 'amount',
  item: 'item', items: 'item', product: 'item',
  type: 'type', doc: 'type',
  discount: 'discount', disc: 'discount',
  shipping: 'shipping', delivery: 'shipping', logistics: 'shipping',
  paid: 'paid', deposit: 'paid',
  tax: 'vat', vat: 'vat',
  due: 'due', duedate: 'due', due_date: 'due',
  note: 'note', notes: 'note', memo: 'note',
  template: 'template',
  currency: 'currency', cur: 'currency', ccy: 'currency',
}

export const SHORTCUTS = ['/name = ', '/item = ', '/amount = ', '/phone = ', '/address = ', '/discount = ', '/shipping = ', '/vat = ', '/paid = ', '/due = ', '/currency = ', '/type = invoice']

export type ParsedItem = { description: string; quantity: number; unit_price_minor: number | null; list_price_minor: number | null; raw: string }

export type ParseResult = {
  fields: Record<string, string>
  items: ParsedItem[]
  errors: string[]
  unknownKeys: string[]
}

const KEY_PATTERN = /(?:^|(?<=[\s,;]))\/([A-Za-z_]+)\s*=/g

export function parseSlash(message: string): ParseResult {
  const result: ParseResult = { fields: {}, items: [], errors: [], unknownKeys: [] }
  const text = message.replace(/\r\n?/g, '\n').trim()
  if (text === '') return result

  const matches = [...text.matchAll(KEY_PATTERN)]
  matches.forEach((match, i) => {
    const alias = (match[1] ?? '').toLowerCase()
    const start = (match.index ?? 0) + match[0].length
    const end = i + 1 < matches.length ? (matches[i + 1]?.index ?? text.length) : text.length
    const value = text.slice(start, end).trim().replace(/[,;\s]+$/, '').trim()

    const key = KEY_ALIASES[alias]
    if (!key) {
      result.unknownKeys.push(alias)
      return
    }
    if (value === '') return

    if (key === 'item') {
      for (const line of value.split(/\s*[|\n;]\s*/).map((p) => p.trim()).filter(Boolean)) {
        const item = readItem(line)
        if (typeof item === 'string') result.errors.push(item)
        else result.items.push(item)
      }
      return
    }
    result.fields[key] = value
  })

  return result
}

export function parseItem(line: string): ParsedItem | null {
  const item = readItem(line)
  return typeof item === 'string' ? null : item
}

const AMOUNT = String.raw`(?:₦|NGN|N|\$)?\s*\d[\d,]*(?:\.\d+)?\s*[kKmM]?`
const PERCENT = String.raw`\d{1,3}(?:\.\d{1,2})?\s*%`
const SCOPE = String.raw`(each|per\s+(?:unit|item|one|piece)|total|all|for\s+all|on\s+all|in\s+total|line)?`
const WHOLE = ['total', 'all', 'for all', 'on all', 'in total', 'line']
const LESS_RE = new RegExp(String.raw`^(.*\S)\s+(?:less|off|disc|discount)\s*[:=]?\s*(${PERCENT}|${AMOUNT})\s*${SCOPE}\s*$`, 'iu')
const OFF_RE = new RegExp(String.raw`^(.*\S)\s+(${PERCENT}|${AMOUNT})\s*off\s*${SCOPE}\s*$`, 'iu')
const MINUS_RE = /^(.*\S)\s+-\s*(\d{1,3}(?:\.\d{1,2})?)\s*%\s*$/u
const plain = (minor: number) => (minor % 100 === 0 ? String(minor / 100) : (minor / 100).toFixed(2))
const scopeIsWhole = (scope: string | undefined) => WHOLE.includes((scope ?? '').toLowerCase().replace(/\s+/g, ' '))

/**
 * Like parseItem(), but says what is wrong. Also reads a discount on the item (same rules as the bot):
 *   "2x Shoe @ 15000 less 1000"        1,000 off each one
 *   "2x Shoe @ 15000 less 10%"         10% off (also "-10%", "10% off", "disc 10%")
 *   "2x Shoe @ 15000 less 3000 total"  3,000 off the two together
 */
export function readItem(line: string): ParsedItem | string {
  const raw = line.trim()
  const usage = `Couldn't read item "${raw}". Try: 2x Shoe @ 15000`
  if (raw === '') return usage
  let work = raw

  let discount: { value: string; whole: boolean } | null = null
  let dm = LESS_RE.exec(work)
  if (dm) {
    discount = { value: (dm[2] ?? '').trim(), whole: scopeIsWhole(dm[3]) }
    work = (dm[1] ?? '').trim()
  } else if ((dm = OFF_RE.exec(work)) && (dm[1] ?? '').includes('@')) {
    discount = { value: (dm[2] ?? '').trim(), whole: scopeIsWhole(dm[3]) }
    work = (dm[1] ?? '').trim()
  } else if ((dm = MINUS_RE.exec(work))) {
    discount = { value: `${dm[2]}%`, whole: false }
    work = (dm[1] ?? '').trim()
  }

  // "… @ 15000 was 18000": the normal (list) price, so the line's discount is traceable.
  let listPrice: number | null = null
  const lm = /^(.*\S)\s*\(?\s*(?:was|list|rrp|normally)\s*[:=]?\s*((?:₦|NGN|N|\$)?\s*\d[\d,]*(?:\.\d+)?\s*[kKmM]?)\s*\)?\s*$/iu.exec(work)
  if (lm) {
    if (discount) return `Item "${raw}": use either "was" (the normal price) or "less" (the discount), not both.`
    listPrice = parseToMinor((lm[2] ?? '').trim())
    if (listPrice === null) return usage
    work = (lm[1] ?? '').trim()
  }

  let description = work
  let priceText: string | null = null
  let quantity: number | null = null

  if (work.includes('@')) {
    const at = work.lastIndexOf('@')
    description = work.slice(0, at).trim()
    priceText = work.slice(at + 1).trim()
    const pm = /^(.+?)\s*[x×*]\s*(\d{1,6})$/iu.exec(priceText)
    if (pm) {
      priceText = (pm[1] ?? '').trim()
      quantity = Number(pm[2])
    }
  } else {
    const pm = /^(.*?)(?:\s+-\s+|\s+)((?:₦|NGN|N|\$)?\s*\d[\d,]*(?:\.\d+)?\s*[kKmM]?)$/u.exec(work)
    if (pm && (pm[1] ?? '').trim() !== '') {
      description = (pm[1] ?? '').trim()
      priceText = (pm[2] ?? '').trim()
    }
  }

  if (quantity === null) {
    let qm = /^(\d{1,6})\s*[x×*]\s*(.+)$/iu.exec(description)
    if (qm) {
      quantity = Number(qm[1])
      description = (qm[2] ?? '').trim()
    } else if ((qm = /^(.+?)\s*[x×*]\s*(\d{1,6})$/iu.exec(description))) {
      quantity = Number(qm[2])
      description = (qm[1] ?? '').trim()
    } else if ((qm = /^(\d{1,6})\s+(\D.*)$/u.exec(description))) {
      quantity = Number(qm[1])
      description = (qm[2] ?? '').trim()
    }
  }

  description = description.replace(/^[\s\-:]+|[\s\-:]+$/g, '')
  if (description === '' || description.length > 500) return usage

  let unitPrice: number | null = null
  if (priceText !== null) {
    unitPrice = parseToMinor(priceText)
    if (unitPrice === null) return usage
  }
  quantity ??= 1
  if (quantity < 1) return usage

  if (discount) {
    if (unitPrice === null) return `Item "${raw}": add the price before the discount, e.g. 2x Shoe @ 15000 less 1000.`
    let perUnit: number
    if (discount.value.endsWith('%')) {
      const bps = percentToBps(discount.value)
      if (bps === null || bps === 0 || bps >= 10000) return `Item "${raw}": the discount must be more than 0% and less than 100%.`
      perUnit = Math.floor((unitPrice * bps + 5000) / 10000)
    } else {
      const off = parseToMinor(discount.value)
      if (off === null || off === 0) return usage
      if (discount.whole && quantity > 1) {
        if (off % quantity !== 0) return `Item "${raw}": ${plain(off)} off ${quantity} items doesn't split evenly. Give the discount on each one (e.g. less ${plain(Math.floor(off / quantity))} each), or use /discount = ${plain(off)} for the whole order.`
        perUnit = off / quantity
      } else {
        perUnit = off
      }
    }
    if (perUnit >= unitPrice) return `Item "${raw}": the discount is as much as the price or more.`
    listPrice = unitPrice
    unitPrice -= perUnit
  }

  return { description, quantity, unit_price_minor: unitPrice, list_price_minor: listPrice, raw }
}
