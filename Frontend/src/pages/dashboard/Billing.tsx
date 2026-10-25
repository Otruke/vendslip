import { Bitcoin, CalendarClock, Coins, CreditCard, Receipt } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Alert, Button, Card, DataTable, Modal, Money, PageHeader, Spinner, StatCard, StatusBadge } from '@/components/ui'
import { apiPost, useBilling, usePlans, useRefreshSession, useTransaction } from '@/hooks/queries'
import { errorMessage } from '@/lib/api'
import { formatDate, formatDateTime } from '@/lib/format'
import { formatMoney } from '@/lib/money'
import { useAuthStore, useCan } from '@/stores/auth'
import type { Transaction } from '@/types/api'
import { PricingCards } from '../public/PricingCards'

type Purchase = { purpose: 'SUBSCRIPTION'; plan_tier: string; billing_cycle: 'MONTHLY' | 'YEARLY'; label: string } | { purpose: 'CREDIT_PACK'; credit_pack_id: number; label: string }

export default function Billing() {
  const session = useAuthStore((s) => s.session)
  const merchant = session?.merchant
  const usage = session?.usage
  const canBuy = useCan('billing.manage')
  const { data: billing } = useBilling()
  const { data: plans } = usePlans()
  const [params, setParams] = useSearchParams()
  const reference = params.get('ref')
  const { data: tx } = useTransaction(reference)
  const refreshSession = useRefreshSession()
  const [purchase, setPurchase] = useState<Purchase | null>(null)
  const [busy, setBusy] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (tx?.status === 'SUCCESSFUL') void refreshSession()
  }, [tx?.status]) // eslint-disable-line react-hooks/exhaustive-deps

  const checkout = async (gateway: 'FLUTTERWAVE' | 'NOWPAYMENTS') => {
    if (!purchase) return
    setBusy(gateway)
    setError(null)
    try {
      const { label: _label, ...body } = purchase
      void _label
      const res = await apiPost<{ checkout_url: string }>('/billing/checkout', { ...body, gateway })
      window.location.assign(res.checkout_url)
    } catch (e) {
      setError(errorMessage(e))
      setBusy(null)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader title="Billing & credits" subtitle="Upgrade, top up, and see every payment." />

      {reference && tx && (
        tx.status === 'SUCCESSFUL' ? <Alert tone="success" title="Payment successful" action={<Button size="sm" variant="ghost" onClick={() => setParams({})}>Dismiss</Button>}>{tx.purpose === 'CREDIT_PACK' ? `${tx.credits} credits added to your account.` : 'Your plan is active.'}</Alert>
          : tx.status === 'PENDING' ? <Alert tone="info" title="Confirming your payment…"><span className="inline-flex items-center gap-2"><Spinner className="size-4" />This usually takes a few seconds.</span></Alert>
            : <Alert tone="danger" title="Payment not completed" action={<Button size="sm" variant="ghost" onClick={() => setParams({})}>Dismiss</Button>}>No money was taken. You can try again.</Alert>
      )}

      <div className="grid gap-4 sm:grid-cols-3">
        <StatCard variant="deep" title="Current plan" value={merchant?.effective_tier === 'BUSINESS_PLUS' ? 'Business Plus' : merchant?.effective_tier === 'PRO' ? 'Pro Merchant' : 'Free'} hint={merchant?.plan_expires_at && merchant.effective_tier !== 'FREE' ? `Renews ${formatDate(merchant.plan_expires_at)}` : '15 slips every month'} icon={<CalendarClock />} />
        <StatCard title="Free slips this month" value={usage?.monthly_quota === null ? 'Unlimited' : `${usage?.free_used ?? 0} / ${usage?.monthly_quota ?? 15}`} icon={<Receipt />} />
        <StatCard title="Non-expiring credits" value={usage?.paid_credits ?? 0} hint="1 credit = 1 clean slip" icon={<Coins />} />
      </div>

      {canBuy ? (
        <>
          <div>
            <h2 className="mb-1 font-semibold">Credit packs</h2>
            <p className="mb-3 text-sm text-ink-secondary">Never expire{plans?.paid_credit_removes_watermark ? ' · every paid slip is watermark-free' : ''}.</p>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {plans?.credit_packs.map((p) => (
                <Card key={p.id} variant="interactive" className="flex items-center justify-between gap-4">
                  <div><p className="font-semibold">{p.name}</p><p className="tabular text-sm text-ink-secondary">{p.credits} credits · {formatMoney(Math.round(p.price_minor / p.credits), p.currency)} each</p></div>
                  <Button onClick={() => setPurchase({ purpose: 'CREDIT_PACK', credit_pack_id: p.id, label: `${p.credits} credits · ${formatMoney(p.price_minor, p.currency)}` })}>{formatMoney(p.price_minor, p.currency).replace(/\.00$/, '')}</Button>
                </Card>
              ))}
            </div>
          </div>
          <div>
            <h2 className="mb-3 font-semibold">Plans</h2>
            <PricingCards onSelect={(tier, cycle) => {
              const plan = plans?.plans.find((p) => p.tier === tier)
              setPurchase({ purpose: 'SUBSCRIPTION', plan_tier: tier, billing_cycle: cycle, label: `${plan?.name ?? tier} · ${cycle === 'YEARLY' ? 'yearly' : 'monthly'} · ${formatMoney(cycle === 'YEARLY' ? plan?.yearly_price_minor ?? 0 : plan?.monthly_price_minor ?? 0)}` })
            }} />
          </div>
        </>
      ) : <Alert tone="info">Only the account owner can change plans or buy credits.</Alert>}

      <div>
        <h2 className="mb-3 font-semibold">Payment history</h2>
        <DataTable<Transaction>
          data={billing?.transactions}
          isLoading={!billing}
          getRowKey={(t) => t.reference}
          emptyState={<p className="text-center text-sm text-ink-muted">No payments yet.</p>}
          columns={[
            { key: 'r', header: 'Reference', cell: (t) => <span className="font-mono text-xs">{t.reference}</span> },
            { key: 'p', header: 'Purchase', cell: (t) => t.purpose === 'CREDIT_PACK' ? `${t.credits} credits` : `${t.plan_tier?.replace('_', ' ').toLowerCase()} · ${t.billing_cycle?.toLowerCase()}` },
            { key: 'g', header: 'Via', cell: (t) => (t.gateway === 'NOWPAYMENTS' ? 'Crypto' : 'Flutterwave'), hideOnMobile: true },
            { key: 'a', header: 'Amount', align: 'right', cell: (t) => <Money amountMinor={t.amount_minor} currency={t.currency} size="sm" /> },
            { key: 's', header: 'Status', cell: (t) => <StatusBadge status={t.status} size="sm" /> },
            { key: 'd', header: 'Date', cell: (t) => <span className="text-ink-secondary">{formatDateTime(t.created_at)}</span>, hideOnMobile: true },
          ]}
        />
      </div>

      <Modal isOpen={purchase !== null} onClose={() => setPurchase(null)} title="Choose how to pay" description={purchase?.label}>
        <div className="space-y-3">
          {error && <Alert tone="danger">{error}</Alert>}
          <Button fullWidth size="lg" variant="primary" isLoading={busy === 'FLUTTERWAVE'} disabled={!billing?.gateways.flutterwave || busy !== null} leftIcon={<CreditCard className="size-5" />} onClick={() => checkout('FLUTTERWAVE')}>Card, transfer or USSD</Button>
          <Button fullWidth size="lg" variant="outline" isLoading={busy === 'NOWPAYMENTS'} disabled={!billing?.gateways.nowpayments || busy !== null} leftIcon={<Bitcoin className="size-5" />} onClick={() => checkout('NOWPAYMENTS')}>Crypto (USDT, USDC, BTC, TON)</Button>
          {billing && !billing.gateways.flutterwave && !billing.gateways.nowpayments && <Alert tone="warning">Payments aren’t configured yet. Contact NetSlip support.</Alert>}
          <p className="text-center text-xs text-ink-muted">You’ll return here automatically after paying.</p>
        </div>
      </Modal>
    </div>
  )
}
