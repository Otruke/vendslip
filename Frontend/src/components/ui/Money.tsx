import { cn } from '@/lib/cn'
import { formatMoney } from '@/lib/money'

const sizes = { sm: 'text-sm', md: 'text-base', lg: 'text-xl font-bold', xl: 'text-3xl font-extrabold tracking-tight' }

export function Money({ amountMinor, currency = 'NGN', size = 'md', className, signed }: { amountMinor: number; currency?: string; size?: keyof typeof sizes; className?: string; signed?: boolean }) {
  return (
    <span className={cn('tabular whitespace-nowrap', sizes[size], className)}>
      {signed && amountMinor > 0 ? '+' : ''}{formatMoney(amountMinor, currency)}
    </span>
  )
}
