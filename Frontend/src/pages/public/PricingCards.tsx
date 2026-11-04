import { Check, Coins } from 'lucide-react'
import { useState, type CSSProperties } from 'react'
import { Button, Card, SegmentedTabs, Skeleton } from '@/components/ui'
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
        <SegmentedTabs value={cycle} onChange={setCycle} options={[{ value: 'MONTHLY', label: 'Monthly' }, { value: 'YEARLY', label: <span>Yearly <span className="vs-tag vs-tag--settled vs-tag--sm ml-1.5" data-fill="solid"><span className="vs-tag__mark" aria-hidden />{data.yearly_discount_label ?? 'Save'}</span></span> }]} />
      </div>
      <div className="mt-8 grid gap-5 md:grid-cols-3" data-stagger>
        {data.plans.map((plan) => {
          const featured = plan.tier === 'PRO'
          // Solid surfaces: Free on mist, Pro on obsidian (featured), Business Plus on deep teal.
          const surface = plan.tier === 'FREE' ? 'mist' : featured ? 'obsidian' : 'deep'
          const dark = surface !== 'mist'
          const price = cycle === 'YEARLY' ? plan.yearly_price_minor : plan.monthly_price_minor
          return (
            <div key={plan.tier} className="flex" data-reveal="feed">
            <Card variant={surface} padding="lg" className={cn('vs-slip relative flex min-w-0 flex-1 flex-col', featured && 'shadow-xl ring-2 ring-vendslip-primary')}>
              {featured && <span className="absolute -top-3 left-6 rounded-md bg-vendslip-primary px-3 py-1 text-xs font-bold text-white" data-reveal="stamp" style={{ '--d': '520ms' } as CSSProperties}>Most popular</span>}
              <div className="flex items-center justify-between">
                <h3 className={cn('text-lg font-bold', dark ? 'text-white' : 'text-ink-primary')}>{plan.name}</h3>
                {plan.tier === 'BUSINESS_PLUS' && <span className="rounded-md bg-white/15 px-2 py-0.5 text-[10.5px] font-bold uppercase tracking-wider text-white">Teams</span>}
              </div>
              <p className={cn('mt-1 text-sm', dark ? 'text-[#cfe3e8]' : 'text-ink-secondary')}>{plan.tagline}</p>
              <p className="mt-6 flex items-baseline gap-1">
                <span className={cn('tabular text-4xl font-extrabold tracking-tight', dark ? 'text-white' : 'text-ink-primary')}>{formatMoney(price, plan.currency).replace(/\.00$/, '')}</span>
                <span className={cn('text-sm', dark ? 'text-[#cfe3e8]' : 'text-ink-muted')}>/{cycle === 'YEARLY' ? 'yr' : 'mo'}</span>
              </p>
              <ul className="mt-6 flex-1 space-y-3">
                {plan.highlights.slice(0, 6).map((h) => (
                  <li key={h} className={cn('flex gap-2.5 text-sm', dark ? 'text-white/90' : 'text-ink-secondary')}>
                    <Check className={cn('mt-0.5 size-4 shrink-0', dark ? 'text-tone-settled-light' : 'text-vendslip-primary')} />{h}
                  </li>
                ))}
              </ul>
              {onSelect && plan.tier !== 'FREE' ? (
                <Button className="mt-8" fullWidth variant={dark ? 'white' : 'primary'} onClick={() => onSelect(plan.tier, cycle)}>Choose {plan.name}</Button>
              ) : (
                <Button className="mt-8" fullWidth to={ctaTo} variant={dark ? 'white' : 'deep'}>
                  {plan.tier === 'FREE' ? 'Start free' : `Get ${plan.name}`}
                </Button>
              )}
            </Card>
            </div>
          )
        })}
      </div>
      {pack && (
        <div className="mt-6" data-reveal="rise">
        <Card variant="teal" className="vs-slip flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-center gap-4">
            <Coins className="vs-bob size-6 shrink-0 text-white/80" />
            <div>
              <p className="font-bold text-white">Pay as you go · {pack.credits} credits for {formatMoney(pack.price_minor, pack.currency).replace(/\.00$/, '')}</p>
              <p className="text-sm text-white/80">Credits never expire{data.paid_credit_removes_watermark ? ' — and every paid slip is watermark-free.' : '.'}</p>
            </div>
          </div>
          <Button to={ctaTo} variant="white">Buy credits</Button>
        </Card>
        </div>
      )}
    </div>
  )
}
