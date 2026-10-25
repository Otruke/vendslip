import type { ReactNode } from 'react'
import { cn } from '@/lib/cn'

export function SegmentedTabs<T extends string>({ value, onChange, options, className, size = 'md' }: {
  value: T
  onChange: (value: T) => void
  options: { value: T; label: ReactNode; icon?: ReactNode; count?: number }[]
  className?: string
  size?: 'sm' | 'md'
}) {
  return (
    <div role="tablist" className={cn('no-scrollbar inline-flex max-w-full gap-1 overflow-x-auto rounded-xl bg-surface-subtle p-1', className)}>
      {options.map((o) => (
        <button
          key={o.value}
          type="button"
          role="tab"
          aria-selected={value === o.value}
          onClick={() => onChange(o.value)}
          className={cn(
            'inline-flex shrink-0 items-center gap-1.5 rounded-lg font-semibold transition [&>svg]:size-4',
            size === 'sm' ? 'px-2.5 py-1 text-xs' : 'px-3.5 py-1.5 text-sm',
            value === o.value ? 'bg-white text-ink-primary shadow-sm' : 'text-ink-secondary hover:text-ink-primary',
          )}
        >
          {o.icon}
          {o.label}
          {o.count !== undefined && <span className={cn('tabular rounded-full px-1.5 text-[11px]', value === o.value ? 'bg-netslip-tint text-netslip-deep' : 'bg-white/70')}>{o.count}</span>}
        </button>
      ))}
    </div>
  )
}
