import { parseToMinor } from './money'

/**
 * NetSlip /slash parser — 1:1 mirror of Backend/app/Services/Slash/SlashCommandParser.php.
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
}

export const SHORTCUTS = ['/name = ', '/item = ', '/amount = ', '/phone = ', '/address = ', '/discount = ', '/shipping = ', '/vat = ', '/paid = ', '/due = ', '/type = invoice']

export type ParsedItem = { description: string; quantity: number; unit_price_minor: number | null; raw: string }

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
        const item = parseItem(line)
        if (item) result.items.push(item)
        else result.errors.push(`Couldn't read item "${line}". Try: 2x Shoe @ 15000`)
      }
      return
    }
    result.fields[key] = value
  })

  return result
}

export function parseItem(line: string): ParsedItem | null {
  const raw = line.trim()
  if (raw === '') return null

  let description = raw
  let priceText: string | null = null
  let quantity: number | null = null

  if (raw.includes('@')) {
    const at = raw.lastIndexOf('@')
    description = raw.slice(0, at).trim()
    priceText = raw.slice(at + 1).trim()
    const pm = /^(.+?)\s*[x×*]\s*(\d{1,6})$/iu.exec(priceText)
    if (pm) {
      priceText = (pm[1] ?? '').trim()
      quantity = Number(pm[2])
    }
  } else {
    const pm = /^(.*?)(?:\s+-\s+|\s+)((?:₦|NGN|N|\$)?\s*\d[\d,]*(?:\.\d+)?\s*[kKmM]?)$/u.exec(raw)
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
  if (description === '' || description.length > 500) return null

  let unitPrice: number | null = null
  if (priceText !== null) {
    unitPrice = parseToMinor(priceText)
    if (unitPrice === null) return null
  }
  quantity ??= 1
  if (quantity < 1) return null

  return { description, quantity, unit_price_minor: unitPrice, raw }
}
