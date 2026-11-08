import { Bitcoin, CircleCheck, CreditCard, Download, Landmark, Lock, ShieldCheck } from 'lucide-react'
import { useState, type CSSProperties } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { Alert, Button, Card, Container, FormError, Input, Money, Section, Skeleton, StatusBadge, Text } from '@/components/ui'
import { apiPost, usePublicInvoice, usePublicSlip } from '@/hooks/queries'
import { api, errorMessage } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { formatMoney } from '@/lib/money'
import type { PublicInvoice } from '@/types/api'

const TITLES: Record<string, string> = { RECEIPT: 'Receipt', INVOICE: 'Invoice', CREDIT_NOTE: 'Credit note' }

/** Older invoice links: /pay/{long id}. */
export default function Pay() {
  const { uid } = useParams()
  const query = usePublicInvoice(uid)
  return <SlipView {...query} />
}

/** The short link a business sends its customer: /s/{code}. Receipts, invoices and credit notes. */
export function Slip() {
  const { code } = useParams()
  const query = usePublicSlip(code)
  return <SlipView {...query} />
}

function SlipView({ data, isLoading, isError, refetch }: { data: PublicInvoice | undefined; isLoading: boolean; isError: boolean; refetch: () => unknown }) {
  const [params] = useSearchParams()
  const [gateway, setGateway] = useState<'FLUTTERWAVE' | 'NOWPAYMENTS'>('FLUTTERWAVE')
  const [email, setEmail] = useState('')
  const [busy, setBusy] = useState(false)
  const [downloading, setDownloading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const returned = params.get('status')

  const pay = async () => {
    if (!data?.pay_uid) return
    setBusy(true)
    setError(null)
    try {
      const res = await apiPost<{ checkout_url: string }>(`/public/invoices/${data.pay_uid}/pay`, { gateway, email: email || null })
      window.location.assign(res.checkout_url)
    } catch (e) {
      setError(errorMessage(e))
      setBusy(false)
    }
  }

  // Fetched here and saved as a file, so no file address is ever shown to the customer.
  const download = async () => {
    if (!data) return
    setDownloading(true)
    setError(null)
    try {
      const res = await api.get<Blob>(`/public/s/${data.short_code}/pdf`, { responseType: 'blob' })
      const url = URL.createObjectURL(res.data)
      const a = document.createElement('a')
      a.href = url
      a.download = `${data.doc_number}.pdf`
      document.body.appendChild(a)
      a.click()
      a.remove()
      window.setTimeout(() => URL.revokeObjectURL(url), 10_000)
    } catch (e) {
      setError(errorMessage(e))
    } finally {
      setDownloading(false)
    }
  }

  if (isLoading) return <Section><Container size="sm"><Skeleton className="h-[520px] rounded-2xl" /></Container></Section>
  if (isError || !data) {
    return (
      <Section><Container size="sm" className="text-center">
        <h1 className="text-2xl font-bold">Slip not found</h1>
        <Text className="mt-2">This link is invalid, or the slip was removed. Ask the business to send it again.</Text>
      </Container></Section>
    )
  }

  const isInvoice = data.doc_type === 'INVOICE'
  const voided = data.status === 'VOID'
  const paid = data.balance_due_minor === 0
  const canPayOnline = isInvoice && !voided && (data.gateways.flutterwave || data.gateways.nowpayments)
  const selected = data.gateways[gateway === 'FLUTTERWAVE' ? 'flutterwave' : 'nowpayments'] ? gateway : data.gateways.flutterwave ? 'FLUTTERWAVE' : 'NOWPAYMENTS'
  const title = TITLES[data.doc_type] ?? 'Slip'

  return (
    <Section tone="canvas" className="py-10 lg:py-16">
      <Container size="md" className="grid gap-6 lg:grid-cols-[1.2fr_1fr]">
        <Card variant="elevated" padding="none" className="overflow-hidden rounded-2xl">
          <div className="px-6 py-5 text-white" style={{ background: data.merchant.secondary_color }}>
            <div className="flex items-center justify-between gap-3">
              <div className="flex min-w-0 items-center gap-3">
                {data.merchant.logo_url && <img src={data.merchant.logo_url} alt="" className="size-10 shrink-0 rounded-lg bg-white object-contain p-0.5" />}
                <div className="min-w-0"><p className="truncate font-bold">{data.merchant.name}</p><p className="text-xs text-white/70">{data.merchant.phone}</p></div>
              </div>
              <div className="shrink-0 text-right"><p className="text-[10px] font-bold uppercase tracking-[0.25em] text-white/70">{title}</p><p className="tabular font-black">{data.doc_number}</p></div>
            </div>
          </div>
          <div className="px-6 py-5">
            <div className="flex items-start justify-between gap-3 text-sm">
              <div><p className="text-ink-muted">{isInvoice ? 'Billed to' : 'Issued to'}</p><p className="font-semibold">{data.customer_name}</p></div>
              <div className="text-right"><StatusBadge status={data.status} size="sm" /><p className="mt-1 text-xs text-ink-muted">{isInvoice && data.due_date ? `Due ${formatDate(data.due_date)}` : formatDate(data.issued_at)}</p></div>
            </div>
            <ul className="mt-5 divide-y divide-border-subtle border-y border-border-subtle text-sm">
              {data.items.map((it, i) => (
                <li key={i} className="flex justify-between gap-3 py-2.5">
                  <span className="min-w-0">
                    <span className="font-medium">{it.description}</span> <span className="tabular text-ink-muted">× {it.quantity}</span>
                    {it.list_price_minor !== null && it.list_price_minor > it.unit_price_minor && (
                      <span className="block text-xs text-ink-muted"><s>{formatMoney(it.list_price_minor, data.currency)}</s> {formatMoney(it.unit_price_minor, data.currency)} each</span>
                    )}
                  </span>
                  <Money amountMinor={it.line_total_minor} currency={data.currency} size="sm" />
                </li>
              ))}
            </ul>
            <dl className="mt-4 space-y-1.5 text-sm">
              <div className="flex justify-between text-ink-secondary"><dt>Subtotal</dt><dd><Money amountMinor={data.subtotal_minor} currency={data.currency} size="sm" /></dd></div>
              {data.line_discount_minor > 0 && <div className="flex justify-between text-xs text-tone-settled"><dt>You saved on items (included)</dt><dd><Money amountMinor={data.line_discount_minor} currency={data.currency} size="sm" /></dd></div>}
              {data.discount_minor > 0 && <div className="flex justify-between text-ink-secondary"><dt>Discount</dt><dd>-<Money amountMinor={data.discount_minor} currency={data.currency} size="sm" /></dd></div>}
              {data.tax_minor > 0 && <div className="flex justify-between text-ink-secondary"><dt>VAT</dt><dd><Money amountMinor={data.tax_minor} currency={data.currency} size="sm" /></dd></div>}
              {data.shipping_minor > 0 && <div className="flex justify-between text-ink-secondary"><dt>Shipping</dt><dd><Money amountMinor={data.shipping_minor} currency={data.currency} size="sm" /></dd></div>}
              <div className="flex justify-between border-t border-border-subtle pt-2 font-bold"><dt>Total</dt><dd><Money amountMinor={data.total_minor} currency={data.currency} /></dd></div>
              {data.amount_paid_minor > 0 && <div className="flex justify-between text-tone-settled"><dt>Paid</dt><dd>-<Money amountMinor={data.amount_paid_minor} currency={data.currency} size="sm" /></dd></div>}
            </dl>
            <div className="mt-5 flex flex-wrap items-center justify-between gap-3 text-xs">
              <Link to={`/verify/${data.verification_hash}`} className="inline-flex items-center gap-1.5 font-semibold text-vendslip-primary hover:underline"><ShieldCheck className="size-3.5" />Verify {data.verification_code}</Link>
            </div>
          </div>
        </Card>

        <div className="space-y-4">
          {returned === 'successful' && <Alert tone="success" title="Payment received">Your receipt is being issued. Thank you!</Alert>}
          {returned === 'failed' && <Alert tone="danger" title="Payment not completed">No money was taken. You can try again.</Alert>}
          <Card variant="elevated" padding="lg" className="rounded-2xl">
            {voided ? (
              <div className="vs-note font-semibold" style={{ '--t': 'var(--tone-open)' } as CSSProperties}>This {title.toLowerCase()} was cancelled by {data.merchant.name}.</div>
            ) : isInvoice ? (
              <>
                <p className="text-sm text-ink-secondary">{paid ? 'Settled' : 'Amount due'}</p>
                <Money amountMinor={data.balance_due_minor} currency={data.currency} size="xl" className="mt-1 block" />
              </>
            ) : (
              <>
                <p className="text-sm text-ink-secondary">{data.doc_type === 'CREDIT_NOTE' ? 'Credit' : 'Amount'}</p>
                <Money amountMinor={data.total_minor} currency={data.currency} size="xl" className="mt-1 block" />
              </>
            )}
            <Button fullWidth variant={canPayOnline && !paid ? 'outline' : 'primary'} className="mt-5" isLoading={downloading} onClick={download} leftIcon={<Download className="size-4" />}>Download PDF</Button>
            {isInvoice && !voided && (paid ? (
              <div className="vs-note mt-4 font-semibold" style={{ '--t': 'var(--tone-settled)' } as CSSProperties}><CircleCheck className="size-5" />This invoice is fully paid.</div>
            ) : canPayOnline ? (
              <div className="mt-6 space-y-4 border-t border-border-subtle pt-5">
                <div className="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Payment method">
                  {data.gateways.flutterwave && (
                    <button type="button" role="radio" aria-checked={selected === 'FLUTTERWAVE'} onClick={() => setGateway('FLUTTERWAVE')} className={`rounded-xl border p-3 text-left text-sm transition ${selected === 'FLUTTERWAVE' ? 'border-vendslip-primary bg-vendslip-tint' : 'border-border-subtle hover:bg-surface-canvas'}`}>
                      <CreditCard className="size-5 text-vendslip-deep" /><p className="mt-2 font-semibold">Card · Transfer · USSD</p><p className="text-xs text-ink-muted">Flutterwave</p>
                    </button>
                  )}
                  {data.gateways.nowpayments && (
                    <button type="button" role="radio" aria-checked={selected === 'NOWPAYMENTS'} onClick={() => setGateway('NOWPAYMENTS')} className={`rounded-xl border p-3 text-left text-sm transition ${selected === 'NOWPAYMENTS' ? 'border-vendslip-primary bg-vendslip-tint' : 'border-border-subtle hover:bg-surface-canvas'}`}>
                      <Bitcoin className="size-5 text-vendslip-deep" /><p className="mt-2 font-semibold">Crypto</p><p className="text-xs text-ink-muted">USDT · USDC · BTC · TON</p>
                    </button>
                  )}
                </div>
                <Input type="email" label="Email for receipt (optional)" placeholder="you@example.com" value={email} onChange={(e) => setEmail(e.target.value)} />
                <Button fullWidth size="lg" isLoading={busy} onClick={pay} leftIcon={<Lock className="size-4" />}>Pay {formatMoney(data.balance_due_minor, data.currency)}</Button>
                <p className="text-center text-xs text-ink-muted">You pay {data.merchant.name} directly through their payment provider. VendSlip handles this page under its <a href="/privacy" target="_blank" rel="noopener" className="font-medium text-vendslip-primary hover:underline">Privacy Policy</a>.</p>
                <p className="text-center text-xs text-ink-muted">You’ll be redirected to {selected === 'FLUTTERWAVE' ? 'Flutterwave' : 'NOWPayments'} to complete payment.</p>
              </div>
            ) : (
              <Text className="mt-4">Online payment isn’t enabled for this business.</Text>
            ))}
            {error && <FormError className="mt-3">{error}</FormError>}
            {isInvoice && !voided && !paid && <PayoutList data={data} />}
            {returned && <Button variant="ghost" size="sm" className="mt-3" onClick={() => refetch()}>Refresh status</Button>}
          </Card>
        </div>
      </Container>
    </Section>
  )
}

type PayoutItem = PublicInvoice['merchant']['payouts'][number]

/** Bank accounts and wallets the business prints on its slips, with copy buttons. */
function PayoutList({ data }: { data: PublicInvoice }) {
  const [copied, setCopied] = useState<string | null>(null)
  const legacy: PayoutItem[] = data.merchant.bank_account_number
    ? [{ kind: 'BANK', label: null, bank_name: data.merchant.bank_name, account_number: data.merchant.bank_account_number, account_name: data.merchant.bank_account_name, currency: null }]
    : []
  const items = (data.merchant.payouts?.length ?? 0) > 0 ? data.merchant.payouts : legacy
  if (items.length === 0) return null
  const copy = (value: string) => {
    void navigator.clipboard?.writeText(value).then(() => { setCopied(value); window.setTimeout(() => setCopied(null), 1500) }).catch(() => undefined)
  }
  const banks = items.filter((p) => p.kind === 'BANK')
  const wallets = items.filter((p) => p.kind === 'CRYPTO')
  return (
    <div className="mt-6 space-y-3">
      {banks.length > 0 && (
        <div className="rounded-xl border border-border-subtle bg-surface-canvas p-4 text-sm">
          <p className="flex items-center gap-2 font-semibold"><Landmark className="size-4" />Or pay by bank transfer</p>
          <ul className="mt-2 space-y-3">
            {banks.map((b, i) => (
              <li key={i} className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <p className="tabular text-lg font-bold">{b.account_number}{b.currency && <span className="ml-1.5 text-xs font-medium text-ink-muted">{b.currency}</span>}</p>
                  <p className="text-ink-secondary">{[b.bank_name, b.account_name, b.label].filter(Boolean).join(' · ')}</p>
                </div>
                <Button size="xs" variant="outline" onClick={() => copy(b.account_number ?? '')}>{copied === b.account_number ? 'Copied' : 'Copy'}</Button>
              </li>
            ))}
          </ul>
        </div>
      )}
      {wallets.length > 0 && (
        <div className="rounded-xl border border-border-subtle bg-surface-canvas p-4 text-sm">
          <p className="flex items-center gap-2 font-semibold"><Bitcoin className="size-4" />Or pay with crypto</p>
          <ul className="mt-2 space-y-3">
            {wallets.map((w, i) => (
              <li key={i} className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <p className="font-semibold">{w.asset} <span className="font-normal text-ink-secondary">on {w.network}</span>{w.label && <span className="text-ink-secondary"> · {w.label}</span>}</p>
                  <p className="break-all font-mono text-xs text-ink-primary">{w.wallet_address}</p>
                </div>
                <Button size="xs" variant="outline" onClick={() => copy(w.wallet_address ?? '')}>{copied === w.wallet_address ? 'Copied' : 'Copy'}</Button>
              </li>
            ))}
          </ul>
          <p className="mt-2 text-xs text-ink-muted">Send only the coin on the network shown. Coins sent on another network can be lost.</p>
        </div>
      )}
    </div>
  )
}
