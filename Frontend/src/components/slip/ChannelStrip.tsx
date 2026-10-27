import { cn } from '@/lib/cn'

/** Where NetSlip works: typographic, no generic icons. Each channel keeps its own tone mark. */
const CHANNELS = [
  { name: 'WhatsApp', tone: 'var(--color-tone-whatsapp)' },
  { name: 'Telegram', tone: 'var(--color-tone-telegram)' },
  { name: 'Instagram DM', tone: 'var(--color-tone-instagram)' },
  { name: 'Messenger', tone: 'var(--color-tone-facebook)' },
]

export function ChannelStrip({ className, dark = false, label = 'Works in' }: { className?: string; dark?: boolean; label?: string | null }) {
  return (
    <div className={cn('flex flex-wrap items-center gap-x-5 gap-y-2 text-sm', className)}>
      {label && <span className={cn('text-[11px] font-bold uppercase tracking-[0.16em]', dark ? 'text-white/60' : 'text-ink-muted')}>{label}</span>}
      <ul className="flex flex-wrap items-center gap-x-5 gap-y-2">
        {CHANNELS.map((c) => (
          <li key={c.name} className={cn('inline-flex items-center gap-2 font-semibold', dark ? 'text-white/90' : 'text-ink-primary')}>
            <span className="size-2 rotate-45 rounded-[1px]" style={{ background: c.tone }} aria-hidden />
            {c.name}
          </li>
        ))}
      </ul>
    </div>
  )
}
