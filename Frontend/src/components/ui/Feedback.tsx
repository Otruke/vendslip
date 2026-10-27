import { CircleCheck, CircleX, Info, LoaderCircle, TriangleAlert, X } from 'lucide-react'
import { useCallback, useEffect, useRef, useState, type CSSProperties, type ReactNode } from 'react'
import { cn } from '@/lib/cn'
import { useUiStore, type ToastTone } from '@/stores/ui'

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
  info: { tone: 'var(--tone-brand)', icon: <Info /> },
  success: { tone: 'var(--tone-settled)', icon: <CircleCheck /> },
  warning: { tone: 'var(--tone-partial)', icon: <TriangleAlert /> },
  danger: { tone: 'var(--tone-open)', icon: <TriangleAlert /> },
}

/** Inline notice: white card with a tone rule on the left (no tinted fill). */
export function Alert({ tone = 'info', title, children, action, className }: { tone?: keyof typeof alertTones; title?: ReactNode; children?: ReactNode; action?: ReactNode; className?: string }) {
  const t = alertTones[tone]
  return (
    <div role={tone === 'danger' ? 'alert' : 'status'} className={cn('ns-note', className)} style={{ '--t': t.tone } as CSSProperties}>
      <span className="mt-0.5 shrink-0 [&>svg]:size-4" style={{ color: t.tone }}>{t.icon}</span>
      <div className="min-w-0 flex-1">
        {title && <p className="font-semibold">{title}</p>}
        {children && <div className={cn(title && 'mt-0.5', 'text-ink-secondary')}>{children}</div>}
      </div>
      {action && <div className="shrink-0">{action}</div>}
    </div>
  )
}

/** Empty state drawn as a small torn slip: NetSlip's own motif instead of an icon in a tinted square. */
export function EmptyState({ icon, title, description, action, className }: { icon?: ReactNode; title: ReactNode; description?: ReactNode; action?: ReactNode; className?: string }) {
  return (
    <div className={cn('flex flex-col items-center justify-center px-6 py-10 text-center', className)}>
      {icon && (
        <span className="block drop-shadow-[0_6px_8px_rgba(22,53,64,0.14)]" aria-hidden>
          <span className="serrated flex h-14 w-12 flex-col items-center gap-1 border border-b-0 border-border-subtle bg-white pt-2.5">
            <span className="text-netslip-primary [&>svg]:size-4">{icon}</span>
            <span className="h-px w-6 bg-border-subtle" />
            <span className="h-px w-4 bg-border-subtle" />
          </span>
        </span>
      )}
      <p className="mt-4 text-sm font-semibold text-ink-primary">{title}</p>
      {description && <p className="mt-1 max-w-sm text-sm text-ink-secondary">{description}</p>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  )
}

const TOAST_ICON = { success: <CircleCheck />, error: <CircleX />, warning: <TriangleAlert />, info: <Info /> }
const TOAST_TITLE = { success: 'Done', error: 'Couldn’t do that', warning: 'Heads up', info: 'Note' }

function ToastCard({ id, message, tone }: { id: number; message: string; tone: ToastTone }) {
  const dismiss = useUiStore((s) => s.dismissToast)
  const [leaving, setLeaving] = useState(false)
  const ms = tone === 'error' ? 7000 : 4200
  const remaining = useRef(ms)
  const startedAt = useRef(0)
  const timer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined)

  const close = useCallback(() => {
    setLeaving(true)
    setTimeout(() => dismiss(id), 420) // also covers prefers-reduced-motion, where no exit animation runs
  }, [dismiss, id])
  const start = useCallback(() => {
    startedAt.current = Date.now()
    clearTimeout(timer.current)
    timer.current = setTimeout(close, Math.max(remaining.current, 500))
  }, [close])
  const pause = () => {
    clearTimeout(timer.current)
    remaining.current -= Date.now() - startedAt.current
  }

  useEffect(() => {
    start()
    return () => clearTimeout(timer.current)
  }, [start])

  return (
    <div
      className={cn('ns-toast', `ns-toast--${tone}`)}
      role={tone === 'error' ? 'alert' : 'status'}
      data-leaving={leaving ? '' : undefined}
      style={{ '--ns-toast-ms': `${ms}ms` } as CSSProperties}
      onMouseEnter={pause} onMouseLeave={start} onFocus={pause} onBlur={start}
    >
      <span className="ns-toast__icon [&>svg]:size-4">{TOAST_ICON[tone]}</span>
      <div className="min-w-0">
        <p className="ns-toast__title">{TOAST_TITLE[tone]}</p>
        <p className="ns-toast__body">{message}</p>
      </div>
      <button type="button" onClick={close} className="ns-toast__close" aria-label="Dismiss"><X className="size-4" /></button>
      <span className="ns-toast__timer" aria-hidden />
    </div>
  )
}

export function Toaster() {
  const toasts = useUiStore((s) => s.toasts)
  return (
    <div className="ns-toasts" aria-live="polite">
      {toasts.map((t) => <ToastCard key={t.id} {...t} />)}
    </div>
  )
}
