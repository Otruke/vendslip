import { Bitcoin, CalendarClock, Coins, CreditCard, Receipt } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Alert, Button, Card, Checkbox, DataTable, Modal, Money, PageHeader, Spinner, StatCard, StatusBadge } from '@/components/ui'
import { useLegalInfo } from '@/hooks/useLegalInfo'
import { LegalAgreement } from '@/pages/public/Legal'
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
  const { data: plans } = usePlans(merchant?.currency)
  const [params, setParams] = useSearchParams()
  const reference = params.get('ref')
  const { data: tx } = useTransaction(reference)
  const refreshSession = useRefreshSession()
  const [purchase, setPurchase] = useState<Purchase | null>(null)
  const [busy, setBusy] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [accepted, setAccepted] = useState(false)
  const legal = useLegalInfo()

  // Plans are prepaid: work out how long is left (or how long ago it ended) for the renewal callout.
  const paidTier = merchant && merchant.plan_tier !== 'FREE' ? merchant.plan_tier : null
  const endsAt = merchant?.plan_expires_at ? new Date(merchant.plan_expires_at) : null
  const [now] = useState(() => Date.now())
  const daysLeft = endsAt ? Math.ceil((endsAt.getTime() - now) / 86_400_000) : null
  const planName = (tier: string | null | undefined) => plans?.plans.find((p) => p.tier === tier)?.name ?? (tier === 'BUSINESS_PLUS' ? 'Business Plus' : 'Pro Merchant')
  const renewOffer = paidTier && daysLeft !== null && daysLeft <= 14 && daysLeft >= -60
  // Every new purchase starts with the terms unticked: acceptance is per payment.
  const openPurchase = (next: Purchase) => { setPurchase(next); setAccepted(false); setError(null) }
  const startRenewal = () => {
    if (!paidTier) return
    const plan = plans?.plans.find((p) => p.tier === paidTier)
    const cycle = merchant?.billing_cycle === 'YEARLY' ? 'YEARLY' : 'MONTHLY'
    const price = cycle === 'YEARLY' ? plan?.yearly_price_minor : plan?.monthly_price_minor
    openPurchase({ purpose: 'SUBSCRIPTION', plan_tier: paidTier, billing_cycle: cycle, label: `Renew ${planName(paidTier)} · ${cycle === 'YEARLY' ? 'yearly' : 'monthly'}${price !== undefined ? ` · ${formatMoney(price, plan?.currency)}` : ''}` })
  }

  // Opened from a renewal reminder (?renew=1): go straight to paying for the same plan, once.
  const [renewHandled, setRenewHandled] = useState(false)
  if (!renewHandled && params.get('renew') === '1' && canBuy && paidTier && plans) {
    setRenewHandled(true)
    startRenewal()
  }

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
      const res = await apiPost<{ checkout_url: string }>('/billing/checkout', { ...body, gateway, accept_terms: accepted })
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

      {renewOffer && daysLeft !== null && (
        <Alert
          tone={daysLeft <= 0 ? 'danger' : daysLeft <= 3 ? 'warning' : 'info'}
          title={daysLeft < 0 ? `Your ${planName(paidTier)} plan ended ${formatDate(merchant?.plan_expires_at ?? '')}` : daysLeft === 0 ? `Your ${planName(paidTier)} plan ends today` : `Your ${planName(paidTier)} plan ends in ${daysLeft} ${daysLeft === 1 ? 'day' : 'days'}`}
          action={canBuy ? <Button size="sm" onClick={startRenewal}>Renew now</Button> : undefined}
        >
          {daysLeft < 0
            ? 'The business is on the Free plan. Your slips and settings are safe; renew to switch your paid features back on.'
            : 'Plans are prepaid and don’t renew automatically. Renewing early keeps every remaining day.'}
          {!canBuy && ' Ask the account owner to renew.'}
        </Alert>
      )}

      <div className="grid gap-4 sm:grid-cols-3">
        <StatCard variant="obsidian" title="Current plan" value={merchant?.effective_tier === 'BUSINESS_PLUS' ? 'Business Plus' : merchant?.effective_tier === 'PRO' ? 'Pro Merchant' : 'Free'} hint={merchant?.plan_expires_at && merchant.effective_tier !== 'FREE' ? `Paid until ${formatDate(merchant.plan_expires_at)} · doesn’t auto-renew` : '15 slips every month'} icon={<CalendarClock />} />
        <StatCard variant="deep" title="Free slips this month" value={usage?.monthly_quota === null ? 'Unlimited' : `${usage?.free_used ?? 0} / ${usage?.monthly_quota ?? 15}`} icon={<Receipt />} />
        <StatCard variant="teal" title="Non-expiring credits" value={usage?.paid_credits ?? 0} hint="1 credit = 1 clean slip" icon={<Coins />} />
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
                  <Button onClick={() => openPurchase({ purpose: 'CREDIT_PACK', credit_pack_id: p.id, label: `${p.credits} credits · ${formatMoney(p.price_minor, p.currency)}` })}>{formatMoney(p.price_minor, p.currency).replace(/\.00$/, '')}</Button>
                </Card>
              ))}
            </div>
          </div>
          <div>
            <h2 className="mb-3 font-semibold">Plans</h2>
            <PricingCards onSelect={(tier, cycle) => {
              const plan = plans?.plans.find((p) => p.tier === tier)
              openPurchase({ purpose: 'SUBSCRIPTION', plan_tier: tier, billing_cycle: cycle, label: `${plan?.name ?? tier} · ${cycle === 'YEARLY' ? 'yearly' : 'monthly'} · ${formatMoney(cycle === 'YEARLY' ? plan?.yearly_price_minor ?? 0 : plan?.monthly_price_minor ?? 0, plan?.currency)}` })
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

      <Modal isOpen={purchase !== null} onClose={() => { setPurchase(null); if (params.has('renew')) { const next = new URLSearchParams(params); next.delete('renew'); setParams(next, { replace: true }) } }} title="Choose how to pay" description={purchase?.label}>
        <div className="space-y-3">
          {error && <Alert tone="danger">{error}</Alert>}
          {purchase?.purpose === 'SUBSCRIPTION' && paidTier && paidTier !== purchase.plan_tier && daysLeft !== null && daysLeft > 0 && (
            <p className="rounded-lg bg-vendslip-tint px-3 py-2 text-xs text-vendslip-deep">Your unused {planName(paidTier)} time is turned into extra days on the new plan, so nothing you paid for is lost.</p>
          )}
          <Checkbox className="items-start rounded-lg border border-border-subtle p-3 text-ink-secondary [&>input]:mt-0.5" checked={accepted} onChange={(e) => setAccepted(e.target.checked)}
            label={<span><LegalAgreement /> <span className="text-ink-muted">(version {legal.version})</span></span>} />
          <Button fullWidth size="lg" variant="primary" isLoading={busy === 'FLUTTERWAVE'} disabled={!accepted || !billing?.gateways.flutterwave || busy !== null} leftIcon={<CreditCard className="size-5" />} onClick={() => checkout('FLUTTERWAVE')}>Card, transfer or USSD</Button>
          <Button fullWidth size="lg" variant="outline" isLoading={busy === 'NOWPAYMENTS'} disabled={!accepted || !billing?.gateways.nowpayments || busy !== null} leftIcon={<Bitcoin className="size-5" />} onClick={() => checkout('NOWPAYMENTS')}>Crypto (USDT, USDC, BTC, TON)</Button>
          {billing && !billing.gateways.flutterwave && !billing.gateways.nowpayments && <Alert tone="warning">Payments aren’t configured yet. Contact VendSlip support.</Alert>}
          <p className="text-center text-xs text-ink-muted">You’ll return here automatically after paying.</p>
        </div>
      </Modal>
    </div>
  )
}
