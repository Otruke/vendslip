import { Pause, Play, RotateCcw, Keyboard } from 'lucide-react'
import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent } from 'react'
import { cn } from '@/lib/cn'
import { applyParse, emptyDraft, missingFields, type DraftPayload } from '@/lib/draft'
import { formatMoney } from '@/lib/money'
import { parseSlash } from '@/lib/slash'
import { PaperSlipPreview } from '../PaperSlipPreview'
import { draftToSlipView } from '../toSlipView'
import { ChatScreen } from './ChatScreen'
import { THEMES, type ChannelKey, type ChatMessage, type NewChatMessage } from './chatModel'
import { PhoneShell } from './PhoneShell'

/** Two scripted sales: a WhatsApp receipt, then a Telegram invoice. The real parser runs on every line. */
const SCENARIOS: { channel: ChannelKey; shop: string; meta: string; lines: string[]; docNumber: string; code: string }[] = [
  {
    channel: 'whatsapp', shop: 'Ada’s Closet', meta: 'Lekki, Lagos', docNumber: 'RCP-2026-0108', code: 'NSV-7C1E-04B9-A2D3',
    lines: ['/item = 2x Ankara Dress @ 18,500', '/item = Gele Headwrap @ 6,500', '/discount = 10%', '/name = Ada Obi'],
  },
  {
    channel: 'telegram', shop: 'Kemi Fabrics', meta: 'Balogun Market, Lagos', docNumber: 'INV-2026-0047', code: 'NSV-41D0-9EB2-77FA',
    lines: ['/type = invoice', '/item = 3x Aso-oke Set @ 45,000', '/shipping = 3,500', '/due = 7d', '/name = Chief Emeka Nwosu'],
  },
]

type Cursor = { x: number; y: number; shown: boolean; pressed: boolean }

const brandFor = (s: (typeof SCENARIOS)[number]) => ({
  name: s.shop, meta: s.meta, primary: '#377581', secondary: '#265363', vatBps: 750, vatInclusive: false, currency: 'NGN', footer: 'Thank you for your patronage!',
})

const nowTime = () => new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit', hour12: false }).format(new Date())

/** Human caption for what a /slash line just changed on the slip. */
function captionFor(line: string, draft: DraftPayload): string {
  const [rawKey, ...rest] = line.replace(/^\//, '').split('=')
  const key = (rawKey ?? '').trim().toLowerCase()
  const value = rest.join('=').trim()
  if (key === 'item') {
    const last = draft.items[draft.items.length - 1]
    return last ? `Added ${last.quantity} × ${last.description}` : 'Item added'
  }
  if (key === 'name') return `Customer: ${value}`
  if (key === 'discount') return `Discount ${value} applied`
  if (key === 'shipping') return `Shipping ₦${value}`
  if (key === 'type') return 'Switched to an invoice'
  if (key === 'due') return 'Due date set: 7 days'
  return 'Slip updated'
}

class Stopped extends Error {}

export function HeroDemo() {
  const stageRef = useRef<HTMLDivElement>(null)
  const pausedRef = useRef(false)
  const visibleRef = useRef(true)
  const msgId = useRef(1)
  const cursorPos = useRef({ x: 0, y: 0 })

  const [scenarioIndex, setScenarioIndex] = useState(0)
  const [messages, setMessages] = useState<ChatMessage[]>([])
  const [draft, setDraft] = useState<DraftPayload>(emptyDraft)
  const [input, setInput] = useState('')
  const [inputActive, setInputActive] = useState(false)
  const [botTyping, setBotTyping] = useState(false)
  const [issued, setIssued] = useState(false)
  const [caption, setCaption] = useState<{ key: number; text: string } | null>(null)
  const [cursor, setCursor] = useState<Cursor>({ x: 0, y: 0, shown: false, pressed: false })
  const [ripple, setRipple] = useState<{ key: number; x: number; y: number } | null>(null)
  const [paused, setPaused] = useState(false)
  // With reduced motion the demo starts hands-on instead of playing itself.
  const [userMode, setUserMode] = useState(() => window.matchMedia('(prefers-reduced-motion: reduce)').matches)

  const scenario = SCENARIOS[scenarioIndex] ?? SCENARIOS[0]!
  const channel = scenario.channel
  const view = useMemo(() => draftToSlipView(draft, brandFor(scenario), { docNumber: issued ? scenario.docNumber : undefined }), [draft, scenario, issued])
  const slip = issued ? { ...view, verificationCode: scenario.code } : view
  const ready = missingFields(draft).length === 0

  const push = useCallback((m: NewChatMessage) => {
    const id = msgId.current++
    setMessages((list) => [...list.filter((x) => x.kind !== 'typing'), { ...m, id } as ChatMessage].slice(-9))
    return id
  }, [])

  /** Point the cursor at an element inside the stage. */
  const pointAt = useCallback((selector: string) => {
    const stage = stageRef.current
    const el = stage?.querySelector<HTMLElement>(selector)
    if (!stage || !el) return
    const s = stage.getBoundingClientRect()
    const r = el.getBoundingClientRect()
    const x = r.left - s.left + Math.min(r.width * 0.55, r.width - 12)
    const y = r.top - s.top + r.height * 0.58
    cursorPos.current = { x, y }
    setCursor((c) => ({ ...c, x, y, shown: true }))
  }, [])

  // ── The autoplay timeline ─────────────────────────────────────────────────────
  useEffect(() => {
    if (userMode) return
    let cancelled = false

    const sleep = (ms: number) => new Promise<void>((resolve, reject) => {
      let left = ms
      const tick = () => {
        if (cancelled) { reject(new Stopped()); return }
        if (!pausedRef.current && visibleRef.current && !document.hidden) left -= 50
        if (left <= 0) resolve()
        else setTimeout(tick, 50)
      }
      setTimeout(tick, 50)
    })

    const click = async () => {
      setCursor((c) => ({ ...c, pressed: true }))
      setRipple({ key: Date.now(), x: cursorPos.current.x, y: cursorPos.current.y })
      await sleep(140)
      setCursor((c) => ({ ...c, pressed: false }))
    }

    const play = async () => {
      await sleep(600)
      for (let round = 0; ; round++) {
        const index = round % SCENARIOS.length
        const sc = SCENARIOS[index]!
        // Reset the stage for this sale.
        setScenarioIndex(index)
        setMessages([{ id: msgId.current++, kind: 'note', text: 'Today' }])
        setDraft(emptyDraft())
        setIssued(false)
        setCaption(null)
        setInput('')
        await sleep(700)

        let current = emptyDraft()
        for (const [n, line] of sc.lines.entries()) {
          pointAt('[data-demo="input"]')
          await sleep(750)
          await click()
          setInputActive(true)
          for (let i = 1; i <= line.length; i++) {
            setInput(line.slice(0, i))
            await sleep(28 + Math.random() * 46)
          }
          await sleep(260)
          pointAt('[data-demo="send"]')
          await sleep(560)
          await click()
          setInputActive(false)
          setInput('')
          push({ kind: 'out', text: line, time: nowTime() })
          current = applyParse(current, parseSlash(line)).draft
          setDraft(current)
          setCaption({ key: Date.now(), text: captionFor(line, current) })
          if (n === 0) {
            await sleep(450)
            push({ kind: 'note', text: 'NetSlip holds partial details silently. No reply, no message cost, until the slip is complete.' })
          }
          await sleep(900)
        }

        // Draft complete → one summary message.
        setCursor((c) => ({ ...c, shown: false }))
        setBotTyping(true)
        push({ kind: 'typing' })
        await sleep(1100)
        setBotTyping(false)
        const totals = draftToSlipView(current, brandFor(sc))
        const label = totals.docType === 'INVOICE' ? 'Invoice' : 'Receipt'
        push({
          kind: 'summary',
          title: `${label} for ${current.name ?? ''}`,
          lines: [`${totals.lines.length} item${totals.lines.length === 1 ? '' : 's'} · Total ${formatMoney(totals.total)}`, totals.dueDate ? `Due ${totals.dueDate}` : 'Paid in full'],
          state: 'open',
          time: nowTime(),
        })
        await sleep(900)
        pointAt('[data-demo="confirm"]')
        await sleep(800)
        await click()
        setMessages((list) => list.map((m) => (m.kind === 'summary' ? { ...m, state: 'generating' } : m)))
        await sleep(1200)
        setMessages((list) => list.map((m) => (m.kind === 'summary' ? { ...m, state: 'sent' } : m)))
        setIssued(true)
        setCaption({ key: Date.now(), text: `${sc.docNumber} issued and signed` })
        push({
          kind: 'document',
          fileName: `${sc.docNumber}.pdf`,
          meta: '1 page · PDF · 46 KB',
          caption: totals.docType === 'INVOICE' ? `Verified ${sc.code}. Pay online: netslip.ng/pay/…` : `Verified ${sc.code}. Scan the QR to check it.`,
          time: nowTime(),
        })
        cursorPos.current = { x: cursorPos.current.x + 60, y: cursorPos.current.y + 70 }
        setCursor((c) => ({ ...c, ...cursorPos.current }))
        await sleep(900)
        setCursor((c) => ({ ...c, shown: false }))
        await sleep(4200)
      }
    }

    play().catch((e: unknown) => { if (!(e instanceof Stopped)) throw e })
    return () => { cancelled = true }
  }, [userMode, push, pointAt])

  // Only animate while the demo is on screen.
  useEffect(() => {
    const el = stageRef.current
    if (!el) return
    const io = new IntersectionObserver(([entry]) => { visibleRef.current = entry?.isIntersecting ?? true }, { threshold: 0.15 })
    io.observe(el)
    return () => io.disconnect()
  }, [])

  const togglePause = () => {
    pausedRef.current = !pausedRef.current
    setPaused(pausedRef.current)
  }

  /** Leaving "your turn" mode re-enters the timeline effect, which starts a fresh run. */
  const replay = () => {
    pausedRef.current = false
    setPaused(false)
    setUserMode(false)
  }

  const takeOver = () => {
    setUserMode(true) // cancels the running timeline (effect cleanup)
    setPaused(false)
    pausedRef.current = false
    setCursor((c) => ({ ...c, shown: false }))
    setInputActive(false)
    setBotTyping(false)
    setMessages([{ id: msgId.current++, kind: 'note', text: 'Your turn. Try /name = Tolu, then /item = 2x Shoes @ 15k' }])
    setDraft(emptyDraft())
    setIssued(false)
    setCaption(null)
    setInput('/name = ')
    window.setTimeout(() => stageRef.current?.querySelector<HTMLInputElement>('[data-demo="input"]')?.focus(), 50)
  }

  // ── Visitor typing their own commands ─────────────────────────────────────────
  const submit = (e: FormEvent) => {
    e.preventDefault()
    if (!userMode) return
    const line = input.trim()
    if (!line) return
    const { draft: next, errors } = applyParse(draft, parseSlash(line))
    setDraft(next)
    setIssued(false)
    push({ kind: 'out', text: line, time: nowTime() })
    if (errors[0]) push({ kind: 'note', text: errors[0] })
    else setCaption({ key: Date.now(), text: captionFor(line, next) })
    setInput('/')
    if (missingFields(next).length === 0 && !messages.some((m) => m.kind === 'summary' && m.state === 'open')) {
      const totals = draftToSlipView(next, brandFor(scenario))
      push({ kind: 'summary', title: `${totals.docType === 'INVOICE' ? 'Invoice' : 'Receipt'} for ${next.name ?? ''}`, lines: [`${totals.lines.length} item(s) · Total ${formatMoney(totals.total)}`], state: 'open', time: nowTime() })
    }
  }
  const confirmByUser = () => {
    setMessages((list) => list.map((m) => (m.kind === 'summary' ? { ...m, state: 'sent' } : m)))
    setIssued(true)
    push({ kind: 'document', fileName: `${scenario.docNumber}.pdf`, meta: '1 page · PDF', caption: `Verified ${scenario.code}. That's the whole flow.`, time: nowTime() })
  }

  return (
    <div className="relative">
      <div ref={stageRef} className="relative mx-auto grid max-w-[640px] items-start gap-6 sm:grid-cols-[minmax(0,300px)_minmax(0,1fr)] sm:gap-0">
        <div className="relative z-10">
          <PhoneShell tint={THEMES[channel].header} dark>
            <ChatScreen
              channel={channel}
              messages={messages}
              input={input}
              onInput={setInput}
              onSubmit={submit}
              interactive={userMode}
              onConfirm={confirmByUser}
              onEdit={() => setInput('/item = ')}
              typingName={botTyping}
              inputActive={inputActive}
            />
          </PhoneShell>
        </div>

        {/* The slip, updating live beside the phone */}
        <div className="relative sm:-ml-6 sm:mt-16 sm:rotate-[2.5deg]">
          <div className="relative">
            <PaperSlipPreview slip={slip} compact className={cn('transition-transform duration-500', ready && !issued && 'sm:-translate-y-1')} />
            {caption && (
              <span key={caption.key} className="absolute -top-3 left-3 rounded-md bg-netslip-obsidian px-2.5 py-1 text-[11px] font-semibold text-white shadow-lg animate-fade-up">
                {caption.text}
              </span>
            )}
            {issued && (
              <span className="ns-stamp pointer-events-none absolute bottom-[92px] right-4 rotate-[-10deg] rounded-md border-2 border-tone-settled px-2.5 py-1 text-center text-[10px] font-black uppercase leading-tight tracking-[0.18em] text-tone-settled" aria-hidden>
                Issued
                <span className="block text-[8.5px] tracking-[0.12em]">Signed · {scenario.code.slice(4, 8)}</span>
              </span>
            )}
          </div>
          <p className="mt-3 text-center text-xs text-ink-secondary sm:-rotate-[2.5deg]">
            {issued ? 'The branded PDF lands back in the chat.' : 'The slip builds itself as you type.'}
          </p>
        </div>

        {/* Animated cursor */}
        {!userMode && (
          <>
            <span
              className={cn('pointer-events-none absolute left-0 top-0 z-40 transition-[transform,opacity] duration-700 ease-[cubic-bezier(0.45,0.05,0.25,1)]', cursor.shown ? 'opacity-100' : 'opacity-0')}
              style={{ transform: `translate(${cursor.x}px, ${cursor.y}px)` }}
              aria-hidden
            >
              <svg viewBox="0 0 24 24" className={cn('size-7 -translate-x-[3px] -translate-y-[2px] drop-shadow-[0_3px_4px_rgba(0,0,0,0.35)] transition-transform duration-100', cursor.pressed && 'scale-90')}>
                <path d="M4 2.5v17.2l4.6-4.3 2.9 6.6 2.9-1.3-2.8-6.4h6.3z" fill="#fff" stroke="#0b0f11" strokeWidth="1.4" strokeLinejoin="round" />
              </svg>
            </span>
            {ripple && <span key={ripple.key} className="ns-ripple pointer-events-none absolute z-30 size-9 rounded-full border-2 border-netslip-primary" style={{ left: ripple.x - 18, top: ripple.y - 18 }} aria-hidden />}
          </>
        )}
      </div>

      {/* Controls */}
      <div className="mt-5 flex flex-wrap items-center justify-center gap-2 text-xs">
        {!userMode ? (
          <>
            <button type="button" onClick={togglePause} className="inline-flex items-center gap-1.5 rounded-md border border-border-subtle bg-white/90 px-2.5 py-1.5 font-semibold text-ink-secondary hover:text-ink-primary" aria-pressed={paused}>
              {paused ? <Play className="size-3.5" /> : <Pause className="size-3.5" />}{paused ? 'Play' : 'Pause'}
            </button>
            <button type="button" onClick={takeOver} className="inline-flex items-center gap-1.5 rounded-md bg-netslip-deep px-2.5 py-1.5 font-semibold text-white hover:bg-netslip-obsidian">
              <Keyboard className="size-3.5" />Type your own
            </button>
            <span className="ml-1 font-medium text-ink-secondary">Now showing: {THEMES[channel].label} {scenario.docNumber.startsWith('INV') ? 'invoice' : 'receipt'}</span>
          </>
        ) : (
          <button type="button" onClick={replay} className="inline-flex items-center gap-1.5 rounded-md border border-border-subtle bg-white/90 px-2.5 py-1.5 font-semibold text-ink-secondary hover:text-ink-primary">
            <RotateCcw className="size-3.5" />Back to the demo
          </button>
        )}
      </div>
    </div>
  )
}
