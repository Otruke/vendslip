import { CircleCheck, Info, LoaderCircle, TriangleAlert, X } from 'lucide-react'
import type { ReactNode } from 'react'
import { cn } from '@/lib/cn'
import { useUiStore } from '@/stores/ui'

export function Skeleton({ className }: { className?: string }) {
  return <div className={cn('animate-pulse rounded-md bg-surface-subtle', className)} aria-hidden />
}

export function Spinner({ className, label = 'Loading' }: { className?: string; label?: string }) {
  return <LoaderCircle className={cn('size-5 animate-spin text-netslip-primary', className)} aria-label={label} role="status" />
}

export function PageLoader() {
  return <div className="flex min-h-[40vh] items-center justify-center"><Spinner className="size-7" /></div>
}

const alertTones = {
  info: { box: 'border-[#cfe3e8] bg-netslip-tint text-netslip-deep', icon: <Info /> },
  success: { box: 'border-emerald-200 bg-emerald-50 text-emerald-800', icon: <CircleCheck /> },
  warning: { box: 'border-amber-200 bg-amber-50 text-amber-800', icon: <TriangleAlert /> },
  danger: { box: 'border-rose-200 bg-rose-50 text-rose-800', icon: <TriangleAlert /> },
}

export function Alert({ tone = 'info', title, children, action, className }: { tone?: keyof typeof alertTones; title?: ReactNode; children?: ReactNode; action?: ReactNode; className?: string }) {
  const t = alertTones[tone]
  return (
    <div role={tone === 'danger' ? 'alert' : 'status'} className={cn('flex items-start gap-3 rounded-xl border px-4 py-3 text-sm', t.box, className)}>
      <span className="mt-0.5 shrink-0 [&>svg]:size-4">{t.icon}</span>
      <div className="min-w-0 flex-1">
        {title && <p className="font-semibold">{title}</p>}
        {children && <div className={cn(title && 'mt-0.5', 'opacity-90')}>{children}</div>}
      </div>
      {action && <div className="shrink-0">{action}</div>}
    </div>
  )
}

export function EmptyState({ icon, title, description, action, className }: { icon?: ReactNode; title: ReactNode; description?: ReactNode; action?: ReactNode; className?: string }) {
  return (
    <div className={cn('flex flex-col items-center justify-center px-6 py-10 text-center', className)}>
      {icon && <span className="inline-flex size-11 items-center justify-center rounded-xl bg-netslip-tint text-netslip-deep [&>svg]:size-5">{icon}</span>}
      <p className="mt-3 text-sm font-semibold text-ink-primary">{title}</p>
      {description && <p className="mt-1 max-w-sm text-sm text-ink-secondary">{description}</p>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  )
}

export function Toaster() {
  const toasts = useUiStore((s) => s.toasts)
  const dismiss = useUiStore((s) => s.dismissToast)
  return (
    <div className="pointer-events-none fixed inset-x-0 top-4 z-[60] flex flex-col items-center gap-2 px-4 sm:inset-x-auto sm:top-auto sm:bottom-6 sm:left-6 sm:items-start" aria-live="polite">
      {toasts.map((t) => (
        <div key={t.id} className={cn('pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border bg-white px-4 py-3 text-sm shadow-lg animate-slip-in', t.tone === 'error' ? 'border-rose-200' : 'border-border-subtle')}>
          <span className={cn('mt-0.5 [&>svg]:size-4', t.tone === 'error' ? 'text-rose-600' : 'text-emerald-600')}>{t.tone === 'error' ? <TriangleAlert /> : <CircleCheck />}</span>
          <p className="flex-1 text-ink-primary">{t.message}</p>
          <button type="button" onClick={() => dismiss(t.id)} className="text-ink-muted hover:text-ink-primary" aria-label="Dismiss"><X className="size-4" /></button>
        </div>
      ))}
    </div>
  )
}
