import { useQuery } from '@tanstack/react-query'
import { BadgeCheck, FileText, Receipt, Sparkles, Store, X } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { api } from '@/lib/api'
import type { ActivityFeed, ActivityFeedEvent } from '@/types/api'

const HIDE_KEY = 'vendslip.activity-notes.hidden'

function isHidden(): boolean {
  try { return window.sessionStorage.getItem(HIDE_KEY) === '1' } catch { return false }
}

function ago(minutes: number): string {
  if (minutes < 2) return 'just now'
  if (minutes < 60) return `${minutes} minutes ago`
  const h = Math.round(minutes / 60)
  return h === 1 ? 'an hour ago' : `${h} hours ago`
}

type Note = { key: string; icon: React.ReactNode; text: string; when: string | null }

function toNote(e: ActivityFeedEvent, i: number): Note {
  const text = {
    RECEIPT: `A business in ${e.place} just issued a receipt`,
    INVOICE: `A business in ${e.place} just sent an invoice`,
    SIGNUP: `A new business in ${e.place} joined VendSlip`,
    UPGRADE: `A business in ${e.place} upgraded to ${e.plan ?? 'Pro'}`,
  }[e.kind]
  const icon = { RECEIPT: <Receipt />, INVOICE: <FileText />, SIGNUP: <Store />, UPGRADE: <Sparkles /> }[e.kind]
  return { key: `e${i}`, icon, text, when: ago(e.minutes_ago) }
}

/**
 * Website activity notes: real, anonymous recent events (city at most, never names or amounts),
 * at a random pace the Super-Admin sets. Nothing shows when there's no real activity, the switch is
 * off, or the visitor dismissed them for this visit.
 */
export function ActivityNotes() {
  const { data } = useQuery({ queryKey: ['activity-feed'], queryFn: async () => (await api.get<ActivityFeed>('/public/activity-feed')).data, staleTime: 5 * 60_000, retry: false })
  const [note, setNote] = useState<Note | null>(null)
  const [hidden, setHidden] = useState(isHidden)
  const shown = useRef(0)

  useEffect(() => {
    if (!data?.enabled || hidden) return
    const notes: Note[] = data.events.map(toNote)
    if (data.stats && data.stats.slips_this_week > 0) {
      notes.splice(Math.min(2, notes.length), 0, { key: 'stats', icon: <BadgeCheck />, text: `${data.stats.slips_this_week.toLocaleString('en-US')} slips issued on VendSlip this week`, when: null })
    }
    if (notes.length === 0) return
    let index = 0
    let timer = 0
    const wait = () => (data.min_seconds + Math.random() * (data.max_seconds - data.min_seconds)) * 1000
    const next = () => {
      if (shown.current >= data.max_per_visit || index >= notes.length) return
      timer = window.setTimeout(() => {
        setNote(notes[index++] ?? null)
        shown.current += 1
        timer = window.setTimeout(() => { setNote(null); next() }, 6500)
      }, wait())
    }
    next()
    return () => window.clearTimeout(timer)
  }, [data, hidden])

  if (!note || hidden) return null
  const dismiss = () => {
    setHidden(true)
    try { window.sessionStorage.setItem(HIDE_KEY, '1') } catch { /* private mode */ }
  }

  return (
    <div role="status" aria-live="polite" className="fixed bottom-4 left-4 z-40 w-[min(calc(100vw-2rem),340px)] animate-fade-up motion-reduce:animate-none">
      <div className="flex items-start gap-3 rounded-xl border border-border-subtle bg-white p-3 pr-2 shadow-lg">
        <span className="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-vendslip-tint text-vendslip-deep [&>svg]:size-4">{note.icon}</span>
        <div className="min-w-0 flex-1">
          <p className="text-sm font-medium text-ink-primary">{note.text}</p>
          {note.when && <p className="mt-0.5 text-xs text-ink-muted">{note.when}</p>}
        </div>
        <button type="button" onClick={dismiss} className="inline-flex size-7 shrink-0 items-center justify-center rounded-md text-ink-muted hover:bg-surface-subtle hover:text-ink-primary" aria-label="Hide these notes">
          <X className="size-4" />
        </button>
      </div>
    </div>
  )
}
