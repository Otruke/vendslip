import { useEffect, useRef, type FormEvent, type ReactNode } from 'react'
import { cn } from '@/lib/cn'
import { THEMES, type ChannelKey, type ChatMessage } from './chatModel'

function Ticks({ color }: { color: string }) {
  return (
    <svg viewBox="0 0 18 11" className="ml-1 inline h-[9px] w-[14px] align-baseline" fill="none" stroke={color} strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
      <path d="m1 6 3 3 6-8" /><path d="m7.5 9 1 0.6 6-8.6" />
    </svg>
  )
}

/** File glyph for the PDF bubble: a small serrated slip (NetSlip's motif), not a generic icon. */
function SlipGlyph() {
  return (
    <span className="relative block h-10 w-8 shrink-0 drop-shadow-[0_2px_3px_rgba(0,0,0,0.12)]" aria-hidden>
      <span className="serrated absolute inset-0 rounded-t-[4px] bg-white [--tooth:6px]">
        <span className="absolute inset-x-0 top-0 h-3 rounded-t-[4px] bg-netslip-deep" />
        <span className="absolute left-1.5 right-2 top-4.5 h-[2px] rounded bg-slate-300" />
        <span className="absolute left-1.5 right-3.5 top-6.5 h-[2px] rounded bg-slate-300" />
        <span className="absolute bottom-2 right-1.5 text-[6px] font-black text-netslip-deep">PDF</span>
      </span>
    </span>
  )
}

export function ChatScreen({
  channel, messages, input, onInput, onSubmit, interactive, onConfirm, onEdit, typingName = false, inputActive = false, children,
}: {
  channel: ChannelKey
  messages: ChatMessage[]
  input: string
  onInput: (v: string) => void
  onSubmit: (e: FormEvent) => void
  interactive: boolean
  onConfirm: () => void
  onEdit: () => void
  typingName?: boolean
  inputActive?: boolean
  children?: ReactNode
}) {
  const t = THEMES[channel]
  const scroller = useRef<HTMLDivElement>(null)

  // Keep the newest message in view.
  useEffect(() => {
    const el = scroller.current
    if (el) el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' })
  }, [messages])

  return (
    <div className="flex h-[400px] flex-col text-[13px] sm:h-[min(540px,72vh)] sm:min-h-[440px]">
      {/* Chat header */}
      <div className="relative z-10 flex items-center gap-2.5 px-3 pb-2.5 pt-1 transition-colors duration-500" style={{ background: t.header, color: t.headerText }}>
        <svg viewBox="0 0 24 24" className="size-5 opacity-90" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" aria-hidden><path d="m15 18-6-6 6-6" /></svg>
        <span className="flex size-8 items-center justify-center rounded-full bg-white">
          <img src="/assets/brand/netslip-icon.png" alt="" className="h-[18px] w-auto" />
        </span>
        <div className="min-w-0 flex-1 leading-tight">
          <p className="truncate text-[13.5px] font-semibold">NetSlip</p>
          <p className="truncate text-[11px]" style={{ color: t.sub }}>{typingName ? 'typing…' : 'business account'}</p>
        </div>
      </div>

      {/* Conversation */}
      <div ref={scroller} className="no-scrollbar relative flex-1 space-y-1.5 overflow-y-auto px-2.5 py-3" style={{ background: t.wallpaper }} aria-live="polite" aria-label={`${t.label} conversation`}>
        {messages.map((m) => {
          if (m.kind === 'note') {
            return <p key={m.id} className="mx-auto my-1 w-fit max-w-[88%] rounded-md bg-white/80 px-2.5 py-1 text-center text-[10.5px] leading-snug text-[#4b5b60] shadow-sm animate-fade-up">{m.text}</p>
          }
          if (m.kind === 'typing') {
            return (
              <div key={m.id} className="w-fit rounded-xl rounded-tl-sm bg-white px-3 py-2.5 shadow-sm animate-fade-up" aria-label="NetSlip is typing">
                <span className="flex gap-1">{[0, 1, 2].map((i) => <span key={i} className="size-1.5 animate-bounce rounded-full bg-slate-400" style={{ animationDelay: `${i * 140}ms` }} />)}</span>
              </div>
            )
          }
          if (m.kind === 'out') {
            return (
              <div key={m.id} className="ml-auto w-fit max-w-[86%] rounded-xl rounded-tr-sm px-2.5 pb-1 pt-1.5 shadow-[0_1px_0.5px_rgba(0,0,0,0.13)] animate-fade-up" style={{ background: t.out }}>
                <span className="break-words font-mono text-[12px] leading-snug text-[#10221f]">{m.text}</span>
                <span className="float-right ml-2 mt-1.5 whitespace-nowrap text-[9.5px] text-[#667a75]">{m.time}<Ticks color={t.tick} /></span>
              </div>
            )
          }
          if (m.kind === 'summary') {
            return (
              <div key={m.id} className="w-[86%] animate-fade-up">
                <div className="rounded-xl rounded-tl-sm bg-white px-3 pb-1.5 pt-2 shadow-[0_1px_0.5px_rgba(0,0,0,0.13)]">
                  <p className="font-semibold text-[#10221f]">{m.title}</p>
                  {m.lines.map((l) => <p key={l} className="tabular text-[12px] leading-snug text-[#4b5b60]">{l}</p>)}
                  <p className="mt-0.5 text-right text-[9.5px] text-[#8a9894]">{m.time}</p>
                </div>
                <div className={cn('mt-0.5 grid gap-0.5', channel === 'whatsapp' ? 'grid-cols-1' : 'grid-cols-2')}>
                  <button type="button" data-demo="confirm" disabled={!interactive || m.state !== 'open'} onClick={onConfirm}
                    className={cn('rounded-lg bg-white/95 px-2 py-1.5 text-[12px] font-semibold shadow-[0_1px_0.5px_rgba(0,0,0,0.13)] transition', channel === 'telegram' && 'col-span-2', m.state === 'open' && interactive && 'hover:bg-white')}
                    style={{ color: t.button }}>
                    {m.state === 'open' ? 'Confirm & send slip' : m.state === 'generating' ? 'Generating slip…' : 'Slip sent'}
                  </button>
                  <button type="button" disabled={!interactive || m.state !== 'open'} onClick={onEdit} className="rounded-lg bg-white/95 px-2 py-1.5 text-[12px] font-semibold shadow-[0_1px_0.5px_rgba(0,0,0,0.13)] disabled:opacity-60" style={{ color: t.button }}>Edit</button>
                  <button type="button" disabled className="rounded-lg bg-white/95 px-2 py-1.5 text-[12px] font-semibold opacity-60 shadow-[0_1px_0.5px_rgba(0,0,0,0.13)]" style={{ color: t.button }}>Cancel</button>
                </div>
              </div>
            )
          }
          return (
            <div key={m.id} className="w-[86%] rounded-xl rounded-tl-sm bg-white p-1.5 shadow-[0_1px_0.5px_rgba(0,0,0,0.13)] animate-fade-up">
              <div className="flex items-center gap-2.5 rounded-lg bg-[#f2f5f4] px-2 py-2">
                <SlipGlyph />
                <div className="min-w-0">
                  <p className="tabular truncate text-[12px] font-semibold text-[#10221f]">{m.fileName}</p>
                  <p className="text-[10.5px] text-[#667a75]">{m.meta}</p>
                </div>
              </div>
              <p className="px-1 pt-1.5 text-[12px] leading-snug text-[#10221f]">{m.caption}</p>
              <p className="px-1 text-right text-[9.5px] text-[#8a9894]">{m.time}</p>
            </div>
          )
        })}
        {children}
      </div>

      {/* Composer */}
      <form onSubmit={onSubmit} className="flex items-center gap-1.5 px-2 pb-6 pt-2" style={{ background: channel === 'whatsapp' ? '#ece5dc' : '#ffffff' }}>
        <label className={cn('flex h-9 min-w-0 flex-1 items-center rounded-full bg-white px-3.5 shadow-sm transition', inputActive && 'ring-2')} style={inputActive ? { ['--tw-ring-color' as string]: `${t.send}55` } : undefined}>
          <span className="sr-only">Type a /slash command</span>
          <input
            data-demo="input"
            value={input}
            onChange={(e) => onInput(e.target.value)}
            readOnly={!interactive}
            placeholder="Message"
            aria-label="Type a /slash command"
            className="w-full min-w-0 bg-transparent font-mono text-[12px] text-[#10221f] placeholder:font-sans placeholder:text-[#8a9894] focus:outline-none"
          />
          {inputActive && !interactive && <span className="ml-0.5 h-3.5 w-px animate-pulse bg-[#10221f]" aria-hidden />}
        </label>
        <button type="submit" data-demo="send" aria-label="Send" className="flex size-9 shrink-0 items-center justify-center rounded-full text-white transition-transform active:scale-95" style={{ background: t.send }}>
          <svg viewBox="0 0 24 24" className="size-4 translate-x-[1px]" fill="currentColor" aria-hidden><path d="M3.4 20.4 21 12 3.4 3.6 3.4 10l12.6 2-12.6 2z" /></svg>
        </button>
      </form>
    </div>
  )
}
