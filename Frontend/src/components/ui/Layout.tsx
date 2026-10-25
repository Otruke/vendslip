import type { HTMLAttributes, ReactNode } from 'react'
import { cn } from '@/lib/cn'

const containerSizes = { sm: 'max-w-3xl', md: 'max-w-5xl', lg: 'max-w-6xl', xl: 'max-w-7xl', full: 'max-w-[1440px]' }

export function Container({ size = 'xl', className, children, ...rest }: { size?: keyof typeof containerSizes; children: ReactNode } & HTMLAttributes<HTMLDivElement>) {
  return <div className={cn('mx-auto w-full px-4 sm:px-6 lg:px-8', containerSizes[size], className)} {...rest}>{children}</div>
}

export function PageHeader({ title, subtitle, eyebrow, actions, className }: { title: ReactNode; subtitle?: ReactNode; eyebrow?: ReactNode; actions?: ReactNode; className?: string }) {
  return (
    <div className={cn('flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between', className)}>
      <div className="min-w-0">
        {eyebrow && <p className="text-xs font-semibold uppercase tracking-[0.14em] text-netslip-primary">{eyebrow}</p>}
        <h1 className="mt-1 text-2xl font-bold tracking-tight text-ink-primary">{title}</h1>
        {subtitle && <p className="mt-1 text-sm text-ink-secondary">{subtitle}</p>}
      </div>
      {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
    </div>
  )
}

/** 50/50 workspace (Composer): stacked on mobile, 12-col split from lg. */
export function SplitWorkspace({ left, right, className }: { left: ReactNode; right: ReactNode; className?: string }) {
  return (
    <div className={cn('grid gap-6 lg:grid-cols-12', className)}>
      <div className="min-w-0 lg:col-span-6 xl:col-span-7">{left}</div>
      <div className="min-w-0 lg:col-span-6 xl:col-span-5">{right}</div>
    </div>
  )
}

export function Section({ tone = 'white', className, children, id }: { tone?: 'white' | 'canvas' | 'deep'; className?: string; children: ReactNode; id?: string }) {
  return (
    <section id={id} className={cn('py-20 lg:py-28', tone === 'canvas' && 'bg-surface-canvas', tone === 'deep' && 'bg-netslip-deep text-white', className)}>
      {children}
    </section>
  )
}
