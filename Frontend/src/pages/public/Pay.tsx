import { Bitcoin, CircleCheck, CreditCard, Download, Landmark, Lock } from 'lucide-react'
import { useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { Alert, Button, Card, Container, Input, Money, Section, Skeleton, StatusBadge, Text } from '@/components/ui'
import { apiPost, usePublicInvoice } from '@/hooks/queries'
import { errorMessage } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { formatMoney } from '@/lib/money'

export default function Pay() {
  const { uid } = useParams()
  const [params] = useSearchParams()
  const { data, isLoading, isError, refetch } = usePublicInvoice(uid)
  const [gateway, setGateway] = useState<'FLUTTERWAVE' | 'NOWPAYMENTS'>('FLUTTERWAVE')
  const [email, setEmail] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const returned = params.get('status')

  const pay = async () => {
    setBusy(true)
    setError(null)
    try {
      const res = await apiPost<{ checkout_url: string }>(`/public/invoices/${uid}/pay`, { gateway, email: email || null })
      window.location.assign(res.checkout_url)
    } catch (e) {
      setError(errorMessage(e))
      setBusy(false)
    }
  }

  if (isLoading) return <Section><Container size="sm"><Skeleton className="h-[520px] rounded-2xl" /></Container></Section>
  if (isError || !data) {
    return (
      <Section><Container size="sm" className="text-center">
        <h1 className="text-2xl font-bold">Invoice not found</h1>
        <Text className="mt-2">This payment link is invalid or the invoice was removed.</Text>
      </Container></Section>
    )
  }

  const paid = data.balance_due_minor === 0
  const canPayOnline = data.gateways.flutterwave || data.gateways.nowpayments
  const selected = data.gateways[gateway === 'FLUTTERWAVE' ? 'flutterwave' : 'nowpayments'] ? gateway : data.gateways.flutterwave ? 'FLUTTERWAVE' : 'NOWPAYMENTS'

  return (
    <Section tone="canvas" className="py-10 lg:py-16">
      <Container size="md" className="grid gap-6 lg:grid-cols-[1.2fr_1fr]">
        <Card variant="elevated" padding="none" className="overflow-hidden rounded-2xl">
          <div className="px-6 py-5 text-white" style={{ background: data.merchant.secondary_color }}>
            <div className="flex items-center justify-between gap-3">
              <div className="flex items-center gap-3">
                {data.merchant.logo_url && <img src={data.merchant.logo_url} alt="" className="size-10 rounded-lg bg-white object-contain p-0.5" />}
                <div><p className="font-bold">{data.merchant.name}</p><p className="text-xs text-white/70">{data.merchant.phone}</p></div>
              </div>
              <div className="text-right"><p className="text-[10px] font-bold uppercase tracking-[0.25em] text-white/70">Invoice</p><p className="tabular font-black">{data.doc_number}</p></div>
            </div>
          </div>
          <div className="px-6 py-5">
            <div className="flex items-start justify-between gap-3 text-sm">
              <div><p className="text-ink-muted">Billed to</p><p className="font-semibold">{data.customer_name}</p></div>
              <div className="text-right"><StatusBadge status={data.status} size="sm" /><p className="mt-1 text-xs text-ink-muted">Due {formatDate(data.due_date)}</p></div>
            </div>
            <ul className="mt-5 divide-y divide-border-subtle border-y border-border-subtle text-sm">
              {data.items.map((it, i) => (
                <li key={i} className="flex justify-between gap-3 py-2.5">
                  <span><span className="font-medium">{it.description}</span> <span className="tabular text-ink-muted">× {it.quantity}</span></span>
                  <Money amountMinor={it.line_total_minor} currency={data.currency} size="sm" />
                </li>
              ))}
            </ul>
            <dl className="mt-4 space-y-1.5 text-sm">
              <div className="flex justify-between text-ink-secondary"><dt>Subtotal</dt><dd><Money amountMinor={data.subtotal_minor} currency={data.currency} size="sm" /></dd></div>
              {data.discount_minor > 0 && <div className="flex justify-between text-ink-secondary"><dt>Discount</dt><dd>-<Money amountMinor={data.discount_minor} currency={data.currency} size="sm" /></dd></div>}
              {data.tax_minor > 0 && <div className="flex justify-between text-ink-secondary"><dt>VAT</dt><dd><Money amountMinor={data.tax_minor} currency={data.currency} size="sm" /></dd></div>}
              {data.shipping_minor > 0 && <div className="flex justify-between text-ink-secondary"><dt>Shipping</dt><dd><Money amountMinor={data.shipping_minor} currency={data.currency} size="sm" /></dd></div>}
              <div className="flex justify-between border-t border-border-subtle pt-2 font-bold"><dt>Total</dt><dd><Money amountMinor={data.total_minor} currency={data.currency} /></dd></div>
              {data.amount_paid_minor > 0 && <div className="flex justify-between text-emerald-700"><dt>Paid</dt><dd>-<Money amountMinor={data.amount_paid_minor} currency={data.currency} size="sm" /></dd></div>}
            </dl>
            <div className="mt-5 flex flex-wrap items-center justify-between gap-3 text-xs">
              <Link to={`/verify/${data.verification_hash}`} className="font-semibold text-netslip-primary hover:underline">Verify {data.verification_code}</Link>
              <Button href={data.pdf_url} target="_blank" variant="ghost" size="xs" leftIcon={<Download className="size-3.5" />}>PDF</Button>
            </div>
          </div>
        </Card>

        <div className="space-y-4">
          {returned === 'successful' && <Alert tone="success" title="Payment received">Your receipt is being issued. Thank you!</Alert>}
          {returned === 'failed' && <Alert tone="danger" title="Payment not completed">No money was taken. You can try again.</Alert>}
          <Card variant="elevated" padding="lg" className="rounded-2xl">
            <p className="text-sm text-ink-secondary">{paid ? 'Settled' : 'Amount due'}</p>
            <Money amountMinor={data.balance_due_minor} currency={data.currency} size="xl" className="mt-1 block" />
            {paid ? (
              <div className="mt-6 flex items-center gap-2 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700"><CircleCheck className="size-5" />This invoice is fully paid.</div>
            ) : canPayOnline ? (
              <div className="mt-6 space-y-4">
                <div className="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Payment method">
                  {data.gateways.flutterwave && (
                    <button type="button" role="radio" aria-checked={selected === 'FLUTTERWAVE'} onClick={() => setGateway('FLUTTERWAVE')} className={`rounded-xl border p-3 text-left text-sm transition ${selected === 'FLUTTERWAVE' ? 'border-netslip-primary bg-netslip-tint' : 'border-border-subtle hover:bg-surface-canvas'}`}>
                      <CreditCard className="size-5 text-netslip-deep" /><p className="mt-2 font-semibold">Card · Transfer · USSD</p><p className="text-xs text-ink-muted">Flutterwave</p>
                    </button>
                  )}
                  {data.gateways.nowpayments && (
                    <button type="button" role="radio" aria-checked={selected === 'NOWPAYMENTS'} onClick={() => setGateway('NOWPAYMENTS')} className={`rounded-xl border p-3 text-left text-sm transition ${selected === 'NOWPAYMENTS' ? 'border-netslip-primary bg-netslip-tint' : 'border-border-subtle hover:bg-surface-canvas'}`}>
                      <Bitcoin className="size-5 text-netslip-deep" /><p className="mt-2 font-semibold">Crypto</p><p className="text-xs text-ink-muted">USDT · USDC · BTC · TON</p>
                    </button>
                  )}
                </div>
                <Input type="email" label="Email for receipt (optional)" placeholder="you@example.com" value={email} onChange={(e) => setEmail(e.target.value)} />
                {error && <Alert tone="danger">{error}</Alert>}
                <Button fullWidth size="lg" isLoading={busy} onClick={pay} leftIcon={<Lock className="size-4" />}>Pay {formatMoney(data.balance_due_minor, data.currency)}</Button>
                <p className="text-center text-xs text-ink-muted">You’ll be redirected to {selected === 'FLUTTERWAVE' ? 'Flutterwave' : 'NOWPayments'} to complete payment.</p>
              </div>
            ) : (
              <Text className="mt-4">Online payment isn’t enabled for this merchant.</Text>
            )}
            {!paid && data.merchant.bank_account_number && (
              <div className="mt-6 rounded-xl border border-border-subtle bg-surface-canvas p-4 text-sm">
                <p className="flex items-center gap-2 font-semibold"><Landmark className="size-4" />Or pay by bank transfer</p>
                <p className="tabular mt-2 text-lg font-bold">{data.merchant.bank_account_number}</p>
                <p className="text-ink-secondary">{data.merchant.bank_name} · {data.merchant.bank_account_name}</p>
              </div>
            )}
            {returned && <Button variant="ghost" size="sm" className="mt-3" onClick={() => refetch()}>Refresh status</Button>}
          </Card>
        </div>
      </Container>
    </Section>
  )
}
