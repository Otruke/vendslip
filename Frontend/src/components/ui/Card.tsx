import type { HTMLAttributes, ReactNode } from 'react'
import { TrendingDown, TrendingUp } from 'lucide-react'
import { cn } from '@/lib/cn'

type CardVariant = 'default' | 'elevated' | 'deep' | 'obsidian' | 'teal' | 'mist' | 'tinted' | 'interactive' | 'paper-slip'
type Padding = 'none' | 'sm' | 'md' | 'lg'

const cardVariants: Record<CardVariant, string> = {
  default: 'rounded-xl border border-border-subtle bg-white',
  elevated: 'rounded-2xl border border-border-subtle bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04),0_12px_32px_-16px_rgba(15,23,42,0.18)]',
  // Solid brand surfaces (no gradients): dark shades carry key numbers and highlights.
  deep: 'rounded-2xl bg-vendslip-deep text-white',
  obsidian: 'rounded-2xl bg-vendslip-obsidian text-white',
  teal: 'rounded-2xl bg-vendslip-primary text-white',
  mist: 'rounded-2xl bg-vendslip-mist text-ink-primary',
  tinted: 'rounded-xl bg-vendslip-mist',
  interactive: 'rounded-xl border border-border-subtle bg-white transition-all duration-200 hover:-translate-y-0.5 hover:shadow-[0_12px_32px_-16px_rgba(15,23,42,0.25)]',
  'paper-slip': 'relative rounded-t-xl border-t-4 border-vendslip-deep bg-white shadow-[0_18px_40px_-20px_rgba(15,23,42,0.35)]',
}
const paddings: Record<Padding, string> = { none: '', sm: 'p-4', md: 'p-5 sm:p-6', lg: 'p-6 sm:p-8' }

export function Card({ variant = 'default', padding = 'md', className, children, ...rest }: { variant?: CardVariant; padding?: Padding; children?: ReactNode } & HTMLAttributes<HTMLDivElement>) {
  return <div className={cn(cardVariants[variant], paddings[padding], className)} {...rest}>{children}</div>
}

type StatVariant = 'default' | 'deep' | 'obsidian' | 'teal' | 'mist'

/** Key-number card. Dark solid variants are the default look; 'default' (white) is for dense pages. */
export function StatCard({ title, value, trend, hint, icon, variant = 'default', className }: {
  title: string
  value: ReactNode
  trend?: number | null
  hint?: ReactNode
  icon?: ReactNode
  variant?: StatVariant
  className?: string
}) {
  const dark = variant === 'deep' || variant === 'obsidian' || variant === 'teal'
  return (
    <Card variant={variant} className={cn('min-w-0', variant === 'default' && 'rounded-2xl', className)}>
      <div className="flex items-start justify-between gap-3">
        <p className={cn('text-xs font-semibold uppercase tracking-wider', dark ? 'text-white/70' : 'text-ink-muted')}>{title}</p>
        {icon && <span className={cn('shrink-0 [&>svg]:size-[18px] [&>svg]:stroke-[1.6]', dark ? 'text-white/60' : 'text-vendslip-primary/70')}>{icon}</span>}
      </div>
      <div className={cn('tabular mt-3 truncate text-2xl font-bold tracking-tight', dark ? 'text-white' : 'text-ink-primary')}>{value}</div>
      {(trend !== undefined && trend !== null) || hint ? (
        <div className={cn('mt-1 flex items-center gap-1.5 text-xs', dark ? 'text-white/75 [&_.text-tone-open]:text-tone-open-light [&_.text-tone-settled]:text-tone-settled-light' : 'text-ink-secondary')}>
          {trend !== undefined && trend !== null && (
            <span className={cn('inline-flex items-center gap-0.5 font-semibold', trend >= 0 ? (dark ? 'text-tone-settled-light' : 'text-tone-settled') : dark ? 'text-tone-open-light' : 'text-tone-open')}>
              {trend >= 0 ? <TrendingUp className="size-3.5" /> : <TrendingDown className="size-3.5" />}
              {trend > 0 ? '+' : ''}{trend}%
            </span>
          )}
          {hint}
        </div>
      ) : null}
    </Card>
  )
}
