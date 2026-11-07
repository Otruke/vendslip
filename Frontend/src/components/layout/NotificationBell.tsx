import { useQueryClient } from '@tanstack/react-query'
import { Bell, BellRing, CheckCheck, Settings } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { apiPost, useNotifications } from '@/hooks/queries'
import { cn } from '@/lib/cn'
import { useCan } from '@/stores/auth'
import type { AppNotification, NotificationsResponse } from '@/types/api'

function ago(iso: string): string {
  const s = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 1000))
  if (s < 60) return 'just now'
  const m = Math.round(s / 60)
  if (m < 60) return `${m} min ago`
  const h = Math.round(m / 60)
  if (h < 24) return `${h} h ago`
  const d = Math.round(h / 24)
  return d < 7 ? `${d} d ago` : new Date(iso).toLocaleDateString([], { day: 'numeric', month: 'short' })
}

/** Header bell: this person's alerts for the business, checked every minute. */
export function NotificationBell() {
  const { data } = useNotifications()
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const canManage = useCan('settings.manage')
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLDivElement>(null)
  const unread = data?.unread ?? 0

  useEffect(() => {
    if (!open) return
    const onClick = (e: MouseEvent) => { if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false) }
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') setOpen(false) }
    document.addEventListener('mousedown', onClick)
    document.addEventListener('keydown', onKey)
    return () => { document.removeEventListener('mousedown', onClick); document.removeEventListener('keydown', onKey) }
  }, [open])

  // Mark read on the screen at once; the server catches up in the background.
  const markLocal = (id: string | null) => queryClient.setQueryData<NotificationsResponse>(['notifications'], (old) => old && {
    unread: id === null ? 0 : Math.max(0, old.unread - (old.data.some((n) => n.id === id && !n.read_at) ? 1 : 0)),
    data: old.data.map((n) => (id === null || n.id === id) && !n.read_at ? { ...n, read_at: new Date().toISOString() } : n),
  })

  const openItem = (n: AppNotification) => {
    if (!n.read_at) { markLocal(n.id); void apiPost(`/notifications/${n.id}/read`).catch(() => undefined) }
    setOpen(false)
    if (n.path) navigate(n.path)
  }
  const readAll = () => { markLocal(null); void apiPost('/notifications/read-all').catch(() => undefined) }

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        aria-expanded={open}
        aria-haspopup="dialog"
        aria-label={unread > 0 ? `Notifications, ${unread} unread` : 'Notifications'}
        className="relative inline-flex size-9 items-center justify-center rounded-lg text-ink-secondary hover:bg-surface-subtle hover:text-ink-primary"
      >
        {unread > 0 ? <BellRing className="size-5" /> : <Bell className="size-5" />}
        {unread > 0 && (
          <span className="tabular absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-tone-open px-1 text-[10px] font-bold leading-none text-white">
            {unread > 9 ? '9+' : unread}
          </span>
        )}
      </button>

      {open && (
        <div role="dialog" aria-label="Notifications" className="absolute right-0 z-40 mt-2 w-[min(92vw,380px)] overflow-hidden rounded-xl border border-border-subtle bg-white shadow-lg">
          <div className="flex items-center justify-between gap-2 border-b border-border-subtle px-4 py-3">
            <p className="text-sm font-semibold text-ink-primary">Notifications</p>
            <div className="flex items-center gap-1">
              {unread > 0 && (
                <button type="button" onClick={readAll} className="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-vendslip-primary hover:bg-vendslip-tint">
                  <CheckCheck className="size-3.5" />Mark all read
                </button>
              )}
              {canManage && (
                <Link to="/dashboard/settings?tab=alerts" onClick={() => setOpen(false)} className="inline-flex size-7 items-center justify-center rounded-md text-ink-muted hover:bg-surface-subtle hover:text-ink-primary" aria-label="Alert settings">
                  <Settings className="size-4" />
                </Link>
              )}
            </div>
          </div>
          {!data || data.data.length === 0 ? (
            <div className="px-4 py-10 text-center">
              <Bell className="mx-auto size-6 text-ink-muted" aria-hidden />
              <p className="mt-2 text-sm text-ink-secondary">Nothing yet. Low stock, overdue invoices and security changes show up here.</p>
            </div>
          ) : (
            <ul className="max-h-[60vh] divide-y divide-border-subtle overflow-y-auto">
              {data.data.map((n) => (
                <li key={n.id}>
                  <button type="button" onClick={() => openItem(n)} className={cn('flex w-full gap-3 px-4 py-3 text-left hover:bg-surface-subtle', !n.read_at && 'bg-vendslip-tint/50')}>
                    <span className={cn('mt-1.5 size-2 shrink-0 rounded-full', n.read_at ? 'bg-transparent' : 'bg-vendslip-primary')} aria-hidden />
                    <span className="min-w-0 flex-1">
                      <span className={cn('block text-sm', n.read_at ? 'text-ink-primary' : 'font-semibold text-ink-primary')}>{n.title}</span>
                      <span className="mt-0.5 block text-xs text-ink-secondary">{n.body}</span>
                      <span className="mt-1 block text-[11px] text-ink-muted">{ago(n.created_at)}</span>
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  )
}
