/**
 * Integer minor-unit money helpers — mirror of Backend/app/Support/Money.php.
 * Never use floats for stored amounts.
 */
export const MAX_MINOR = 1_000_000_000_000_000

const SYMBOLS: Record<string, string> = {
  NGN: '₦', USD: '$', GBP: '£', EUR: '€', GHS: 'GH₵', KES: 'KSh', ZAR: 'R',
  // Same friendlier local symbols as Backend/app/Support/Currencies.php
  UGX: 'USh', TZS: 'TSh', CDF: 'FC', ZMW: 'K', MWK: 'MK', ETB: 'Br', EGP: 'E£', MAD: 'DH', BWP: 'P',
  SLE: 'Le', GMD: 'D', LRD: 'L$', NAD: 'N$', AED: 'AED', SAR: 'SAR', KWD: 'KD', QAR: 'QR',
}
const symbolCache = new Map<string, string>()
const digitsCache = new Map<string, number>()

function intlSymbol(code: string): string {
  try {
    const part = new Intl.NumberFormat('en', { style: 'currency', currency: code, currencyDisplay: 'narrowSymbol' })
      .formatToParts(0).find((p) => p.type === 'currency')
    return part?.value ?? code
  } catch { return code }
}

/** "₦", "$", "KSh ", "FCFA ", "UGX " — letter symbols get a space, like Money::symbol(). */
export function currencySymbol(currency: string): string {
  const code = currency.toUpperCase()
  let symbol = symbolCache.get(code)
  if (symbol === undefined) {
    symbol = (SYMBOLS[code] ?? intlSymbol(code)).trim()
    if (/\p{L}$/u.test(symbol) && symbol !== 'R') symbol += ' '
    symbolCache.set(code, symbol)
  }
  return symbol
}

/** 0 for currencies shown without decimals (UGX, XAF, RWF, JPY…), otherwise 2. */
export function currencyDigits(currency: string): number {
  const code = currency.toUpperCase()
  let digits = digitsCache.get(code)
  if (digits === undefined) {
    try { digits = new Intl.NumberFormat('en', { style: 'currency', currency: code }).resolvedOptions().maximumFractionDigits === 0 ? 0 : 2 } catch { digits = 2 }
    digitsCache.set(code, digits)
  }
  return digits
}

/** "15000", "15,000.50", "₦15k", "NGN 1.5m", "N2,500" → minor units (null if unreadable). */
export function parseToMinor(input: string): number | null {
  let value = input.trim().toLowerCase()
  if (value === '') return null
  for (const token of ['₦', '$', '£', '€', 'ngn', 'usd', 'naira', ' ', ' ', '_']) value = value.split(token).join('')
  value = value.replace(/^n(?=[\d.])/, '').replace(/,/g, '')

  const m = /^(\d*)(?:\.(\d+))?([km])?$/.exec(value)
  if (!m || (m[1] === '' && (m[2] ?? '') === '')) return null
  const intPart = m[1] ?? ''
  const fracPart = m[2] ?? ''
  const suffix = m[3] ?? ''

  const mantissaStr = (intPart + fracPart).replace(/^0+/, '')
  if (mantissaStr.length > 17) return null
  const mantissa = mantissaStr === '' ? 0n : BigInt(mantissaStr)
  const exponent = 2 + (suffix === 'k' ? 3 : suffix === 'm' ? 6 : 0) - fracPart.length

  let minor: bigint
  if (exponent >= 0) {
    minor = mantissa * 10n ** BigInt(exponent)
  } else {
    const divisor = 10n ** BigInt(-exponent)
    minor = (2n * mantissa + divisor) / (2n * divisor) // round half up
  }
  if (minor > BigInt(MAX_MINOR)) return null
  return Number(minor)
}

/** round_half_up(a × b ÷ c) for non-negative integers, computed exactly in BigInt. */
export function mulDivRound(a: number, b: number, c: number): number {
  const numerator = BigInt(Math.trunc(a)) * BigInt(Math.trunc(b))
  const denominator = BigInt(Math.trunc(c))
  return Number((2n * numerator + denominator) / (2n * denominator))
}

/** "7.5", "7.5%" → 750 basis points. */
export function percentToBps(input: string): number | null {
  const m = /^(\d{1,3})(?:\.(\d{1,2}))?$/.exec(input.replace('%', '').trim())
  if (!m) return null
  const bps = Number(m[1]) * 100 + Number((m[2] ?? '').padEnd(2, '0') || '0')
  return bps <= 10000 ? bps : null
}

/** ₦15,000.00 — deterministic, matches PHP Money::format. */
export function formatMoney(minor: number, currency = 'NGN', withSymbol = true): string {
  const negative = minor < 0
  const abs = Math.abs(Math.trunc(minor))
  const sign = negative ? '-' : ''
  const symbol = withSymbol ? currencySymbol(currency) : ''
  // Amounts are stored in hundredths for every currency; zero-decimal ones round to whole units.
  if (currencyDigits(currency) === 0) return `${sign}${symbol}${Math.trunc((abs + 50) / 100).toLocaleString('en-US')}`
  const major = Math.trunc(abs / 100).toLocaleString('en-US')
  const cents = String(abs % 100).padStart(2, '0')
  return `${sign}${symbol}${major}.${cents}`
}

/** Compact for charts: ₦1.2m, ₦45k */
export function formatMoneyCompact(minor: number, currency = 'NGN'): string {
  const major = minor / 100
  const symbol = currencySymbol(currency)
  if (Math.abs(major) >= 1_000_000) return `${symbol}${(major / 1_000_000).toFixed(1).replace(/\.0$/, '')}m`
  if (Math.abs(major) >= 1_000) return `${symbol}${(major / 1_000).toFixed(1).replace(/\.0$/, '')}k`
  return `${symbol}${major.toFixed(0)}`
}

export function bpsToPercentLabel(bps: number): string {
  return `${(bps / 100).toFixed(2).replace(/\.?0+$/, '')}%`
}

/** Minor units → editable major string ("15000.5" → "15000.50"). */
export function minorToInput(minor: number | null | undefined): string {
  if (minor === null || minor === undefined) return ''
  return (minor / 100).toFixed(2).replace(/\.00$/, '')
}
