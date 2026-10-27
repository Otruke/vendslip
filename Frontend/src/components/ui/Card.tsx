import type { HTMLAttributes, ReactNode } from 'react'
import { TrendingDown, TrendingUp } from 'lucide-react'
import { cn } from '@/lib/cn'

type CardVariant = 'default' | 'elevated' | 'deep' | 'tinted' | 'interactive' | 'paper-slip'
type Padding = 'none' | 'sm' | 'md' | 'lg'

const cardVariants: Record<CardVariant, string> = {
  default: 'rounded-xl border border-border-subtle bg-white',
  elevated: 'rounded-2xl border border-border-subtle bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04),0_12px_32px_-16px_rgba(15,23,42,0.18)]',
  deep: 'rounded-2xl bg-gradient-to-br from-netslip-deep to-netslip-obsidian text-white',
  tinted: 'rounded-xl border border-[#d3e4e8] bg-netslip-tint',
  interactive: 'rounded-xl border border-border-subtle bg-white transition-all duration-200 hover:-translate-y-0.5 hover:shadow-[0_12px_32px_-16px_rgba(15,23,42,0.25)]',
  'paper-slip': 'relative rounded-t-xl border-t-4 border-netslip-deep bg-white shadow-[0_18px_40px_-20px_rgba(15,23,42,0.35)]',
}
const paddings: Record<Padding, string> = { none: '', sm: 'p-4', md: 'p-5 sm:p-6', lg: 'p-6 sm:p-8' }

export function Card({ variant = 'default', padding = 'md', className, children, ...rest }: { variant?: CardVariant; padding?: Padding; children?: ReactNode } & HTMLAttributes<HTMLDivElement>) {
  return <div className={cn(cardVariants[variant], paddings[padding], className)} {...rest}>{children}</div>
}

export function StatCard({ title, value, trend, hint, icon, variant = 'default', className }: {
  title: string
  value: ReactNode
  trend?: number | null
  hint?: ReactNode
  icon?: ReactNode
  variant?: 'default' | 'deep'
  className?: string
}) {
  const deep = variant === 'deep'
  return (
    <Card variant={deep ? 'deep' : 'default'} className={cn('min-w-0', className)}>
      <div className="flex items-start justify-between gap-3">
        <p className={cn('text-xs font-semibold uppercase tracking-wider', deep ? 'text-[#cfe3e8]' : 'text-ink-muted')}>{title}</p>
        {icon && <span className={cn('shrink-0 [&>svg]:size-[18px] [&>svg]:stroke-[1.6]', deep ? 'text-white/60' : 'text-netslip-primary/70')}>{icon}</span>}
      </div>
      <div className={cn('tabular mt-3 truncate text-2xl font-bold tracking-tight', deep ? 'text-white' : 'text-ink-primary')}>{value}</div>
      {(trend !== undefined && trend !== null) || hint ? (
        <p className={cn('mt-1 flex items-center gap-1.5 text-xs', deep ? 'text-[#cfe3e8]' : 'text-ink-secondary')}>
          {trend !== undefined && trend !== null && (
            <span className={cn('inline-flex items-center gap-0.5 font-semibold', trend >= 0 ? (deep ? 'text-tone-settled-light' : 'text-tone-settled') : deep ? 'text-tone-open-light' : 'text-tone-open')}>
              {trend >= 0 ? <TrendingUp className="size-3.5" /> : <TrendingDown className="size-3.5" />}
              {trend > 0 ? '+' : ''}{trend}%
            </span>
          )}
          {hint}
        </p>
      ) : null}
    </Card>
  )
}
