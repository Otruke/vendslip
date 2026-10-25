import { Camera, MessageCircle, Send, Users } from 'lucide-react'
import { cn } from '@/lib/cn'

const CHANNELS = [
  { name: 'WhatsApp', icon: <MessageCircle />, color: 'text-emerald-600 bg-emerald-50' },
  { name: 'Telegram', icon: <Send />, color: 'text-sky-600 bg-sky-50' },
  { name: 'Instagram DM', icon: <Camera />, color: 'text-pink-600 bg-pink-50' },
  { name: 'Facebook Messenger', icon: <Users />, color: 'text-blue-600 bg-blue-50' },
]

export function ChannelStrip({ className, dark = false }: { className?: string; dark?: boolean }) {
  return (
    <ul className={cn('flex flex-wrap items-center gap-2', className)}>
      {CHANNELS.map((c) => (
        <li key={c.name} className={cn('inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-medium', dark ? 'border-white/15 text-white/90' : 'border-border-subtle bg-white text-ink-secondary')}>
          <span className={cn('inline-flex size-6 items-center justify-center rounded-full [&>svg]:size-3.5', c.color)}>{c.icon}</span>
          {c.name}
        </li>
      ))}
    </ul>
  )
}
