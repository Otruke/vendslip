import { Check, Coins } from 'lucide-react'
import { useState } from 'react'
import { Button, Card, SegmentedTabs, Skeleton, StatusBadge } from '@/components/ui'
import { usePlans } from '@/hooks/queries'
import { cn } from '@/lib/cn'
import { formatMoney } from '@/lib/money'

export function PricingCards({ className, ctaTo = '/register', onSelect }: { className?: string; ctaTo?: string; onSelect?: (tier: string, cycle: 'MONTHLY' | 'YEARLY') => void }) {
  const { data, isLoading } = usePlans()
  const [cycle, setCycle] = useState<'MONTHLY' | 'YEARLY'>('MONTHLY')

  if (isLoading || !data) {
    return <div className={cn('grid gap-5 md:grid-cols-3', className)}>{[0, 1, 2].map((i) => <Skeleton key={i} className="h-[420px] rounded-2xl" />)}</div>
  }

  const pack = data.credit_packs[0]
  return (
    <div className={className}>
      <div className="flex justify-center">
        <SegmentedTabs value={cycle} onChange={setCycle} options={[{ value: 'MONTHLY', label: 'Monthly' }, { value: 'YEARLY', label: <span>Yearly <span className="ml-1 rounded-full bg-emerald-100 px-1.5 text-[11px] text-emerald-700">{data.yearly_discount_label ?? 'Save'}</span></span> }]} />
      </div>
      <div className="mt-8 grid gap-5 md:grid-cols-3">
        {data.plans.map((plan) => {
          const featured = plan.tier === 'PRO'
          const price = cycle === 'YEARLY' ? plan.yearly_price_minor : plan.monthly_price_minor
          return (
            <Card key={plan.tier} variant={featured ? 'deep' : 'elevated'} padding="lg" className={cn('relative flex flex-col rounded-2xl', featured && 'shadow-xl ring-1 ring-netslip-primary/40')}>
              {featured && <span className="absolute -top-3 left-6 rounded-full bg-netslip-primary px-3 py-1 text-xs font-bold text-white">Most popular</span>}
              <div className="flex items-center justify-between">
                <h3 className={cn('text-lg font-bold', featured ? 'text-white' : 'text-ink-primary')}>{plan.name}</h3>
                {plan.tier === 'BUSINESS_PLUS' && <StatusBadge status="BUSINESS_PLUS" label="Teams" size="sm" />}
              </div>
              <p className={cn('mt-1 text-sm', featured ? 'text-[#cfe3e8]' : 'text-ink-secondary')}>{plan.tagline}</p>
              <p className="mt-6 flex items-baseline gap-1">
                <span className={cn('tabular text-4xl font-extrabold tracking-tight', featured ? 'text-white' : 'text-ink-primary')}>{formatMoney(price, plan.currency).replace(/\.00$/, '')}</span>
                <span className={cn('text-sm', featured ? 'text-[#cfe3e8]' : 'text-ink-muted')}>/{cycle === 'YEARLY' ? 'yr' : 'mo'}</span>
              </p>
              <ul className="mt-6 flex-1 space-y-3">
                {plan.highlights.slice(0, 6).map((h) => (
                  <li key={h} className={cn('flex gap-2.5 text-sm', featured ? 'text-white/90' : 'text-ink-secondary')}>
                    <Check className={cn('mt-0.5 size-4 shrink-0', featured ? 'text-emerald-300' : 'text-netslip-primary')} />{h}
                  </li>
                ))}
              </ul>
              {onSelect && plan.tier !== 'FREE' ? (
                <Button className="mt-8" fullWidth variant={featured ? 'white' : 'primary'} onClick={() => onSelect(plan.tier, cycle)}>Choose {plan.name}</Button>
              ) : (
                <Button className="mt-8" fullWidth to={ctaTo} variant={featured ? 'white' : plan.tier === 'FREE' ? 'outline' : 'primary'}>
                  {plan.tier === 'FREE' ? 'Start free' : `Get ${plan.name}`}
                </Button>
              )}
            </Card>
          )
        })}
      </div>
      {pack && (
        <Card variant="tinted" className="mt-6 flex flex-col items-start gap-4 rounded-2xl sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-center gap-4">
            <span className="inline-flex size-11 shrink-0 items-center justify-center rounded-xl bg-white text-netslip-deep shadow-sm"><Coins className="size-5" /></span>
            <div>
              <p className="font-bold text-ink-primary">Pay as you go · {pack.credits} credits for {formatMoney(pack.price_minor, pack.currency).replace(/\.00$/, '')}</p>
              <p className="text-sm text-ink-secondary">Credits never expire{data.paid_credit_removes_watermark ? ' — and every paid slip is watermark-free.' : '.'}</p>
            </div>
          </div>
          <Button to={ctaTo} variant="deep">Buy credits</Button>
        </Card>
      )}
    </div>
  )
}
