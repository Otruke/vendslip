import { CheckCheck, MessageCircle, Send, SendHorizontal } from 'lucide-react'
import { useMemo, useState, type FormEvent } from 'react'
import { SegmentedTabs } from '@/components/ui'
import { cn } from '@/lib/cn'
import { applyParse, emptyDraft, missingFields, type DraftPayload } from '@/lib/draft'
import { formatMoney } from '@/lib/money'
import { parseSlash } from '@/lib/slash'
import { PaperSlipPreview } from './PaperSlipPreview'
import { draftToSlipView } from './toSlipView'

const SCRIPT = ['/name = Ada Obi', '/item = 2x Ankara Dress @ 18,500', '/address = Lekki Phase 1, Lagos']
const BRAND = { name: 'Ada’s Closet', meta: 'Lekki, Lagos', primary: '#377581', secondary: '#265363', vatBps: 750, vatInclusive: false, currency: 'NGN', footer: 'Thank you for shopping with us!' }

type Bubble = { from: 'vendor' | 'bot'; text: string }

/** Interactive hero: type /slash in a chat, confirm once, get a slip. Runs the real parser. */
export function ChatToSlipDemo() {
  const [channel, setChannel] = useState<'whatsapp' | 'telegram'>('whatsapp')
  const [bubbles, setBubbles] = useState<Bubble[]>(SCRIPT.map((text) => ({ from: 'vendor', text })))
  const [draft, setDraft] = useState<DraftPayload>(() => SCRIPT.reduce((d, line) => applyParse(d, parseSlash(line)).draft, emptyDraft()))
  const [input, setInput] = useState('/shipping = 2500')
  const [confirmed, setConfirmed] = useState(false)

  const ready = missingFields(draft).length === 0
  const view = useMemo(() => draftToSlipView(draft, BRAND, { docNumber: confirmed ? 'RCP-2026-0108' : undefined }), [draft, confirmed])
  const slip = confirmed ? { ...view, verificationCode: 'NSV-7C1E-04B9-A2D3' } : view

  const send = (e: FormEvent) => {
    e.preventDefault()
    const text = input.trim()
    if (!text) return
    const { draft: next, errors } = applyParse(draft, parseSlash(text))
    setBubbles((b): Bubble[] => [...b, { from: 'vendor' as const, text }, ...(errors.length ? [{ from: 'bot' as const, text: errors[0] ?? '' }] : [])].slice(-6))
    setDraft(next)
    setConfirmed(false)
    setInput('')
  }

  const reset = () => {
    setBubbles([])
    setDraft(emptyDraft())
    setConfirmed(false)
    setInput('/name = ')
  }

  const accent = channel === 'whatsapp' ? 'bg-[#dcf8c6]' : 'bg-[#e1f0ff]'

  return (
    <div className="grid items-start gap-5 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
      <div className="overflow-hidden rounded-2xl border border-border-subtle bg-white shadow-[0_24px_60px_-30px_rgba(15,23,42,0.45)]">
        <div className="flex items-center justify-between gap-2 border-b border-border-subtle px-4 py-3">
          <div className="flex items-center gap-2">
            <span className={cn('inline-flex size-8 items-center justify-center rounded-full text-white', channel === 'whatsapp' ? 'bg-emerald-500' : 'bg-sky-500')}>
              {channel === 'whatsapp' ? <MessageCircle className="size-4" /> : <Send className="size-4" />}
            </span>
            <div>
              <p className="text-sm font-semibold leading-tight">NetSlip Bot</p>
              <p className="text-[11px] text-ink-muted">{channel === 'whatsapp' ? 'WhatsApp' : 'Telegram'} · online</p>
            </div>
          </div>
          <SegmentedTabs size="sm" value={channel} onChange={setChannel} options={[{ value: 'whatsapp', label: 'WhatsApp' }, { value: 'telegram', label: 'Telegram' }]} />
        </div>

        <div className="flex h-[300px] flex-col gap-2 overflow-y-auto bg-[#f4f1ec] px-3 py-3" aria-live="polite">
          {bubbles.length === 0 && <p className="m-auto text-xs text-ink-muted">Type a /slash command below</p>}
          {bubbles.map((b, i) => (
            <div key={i} className={cn('max-w-[85%] rounded-xl px-3 py-1.5 text-[13px] shadow-sm animate-fade-up', b.from === 'vendor' ? cn('ml-auto rounded-br-sm', accent) : 'rounded-bl-sm bg-white')}>
              <span className="font-mono text-[12px] text-ink-primary">{b.text}</span>
              {b.from === 'vendor' && <CheckCheck className="ml-1 inline size-3 text-sky-500" aria-hidden />}
            </div>
          ))}
          {ready && (
            <div className="max-w-[90%] rounded-xl rounded-bl-sm bg-white px-3 py-2 text-[13px] shadow-sm animate-fade-up">
              <p className="font-semibold">🧾 Receipt for {draft.name}</p>
              <p className="tabular text-ink-secondary">Total {formatMoney(view.total)} · {view.lines.length} item(s)</p>
              <div className="mt-2 grid grid-cols-2 gap-1.5">
                <button type="button" onClick={() => setConfirmed(true)} disabled={confirmed} className="rounded-lg bg-netslip-primary px-2 py-1.5 text-xs font-semibold text-white transition disabled:opacity-50">
                  {confirmed ? '✓ Sent' : '✅ Confirm'}
                </button>
                <button type="button" onClick={reset} className="rounded-lg bg-surface-subtle px-2 py-1.5 text-xs font-semibold text-ink-secondary">Start over</button>
              </div>
              {!confirmed && <p className="mt-1.5 text-[11px] text-ink-muted">Only one message sent. Earlier fields were buffered free.</p>}
            </div>
          )}
        </div>

        <form onSubmit={send} className="flex items-center gap-2 border-t border-border-subtle px-3 py-2.5">
          <input value={input} onChange={(e) => setInput(e.target.value)} aria-label="Type a /slash command" placeholder="/item = 1x Bag @ 12000" className="h-10 min-w-0 flex-1 rounded-full bg-surface-subtle px-4 font-mono text-[13px] focus:outline-none focus:ring-2 focus:ring-netslip-primary/30" />
          <button type="submit" className="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-netslip-primary text-white transition hover:bg-netslip-primary-hover" aria-label="Send">
            <SendHorizontal className="size-4" />
          </button>
        </form>
      </div>

      <div className={cn('transition-all duration-500', confirmed ? 'scale-100 opacity-100' : 'scale-[0.98] opacity-90')}>
        <PaperSlipPreview slip={slip} compact />
        <p className="mt-3 text-center text-xs text-ink-muted">{confirmed ? 'Branded PDF sent back to the chat.' : 'Live preview · tap Confirm'}</p>
      </div>
    </div>
  )
}
