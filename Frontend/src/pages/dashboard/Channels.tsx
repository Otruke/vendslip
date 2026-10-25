import { Camera, CircleCheck, Copy, ExternalLink, Link2, MessageCircle, Send, Trash2, Users, Zap } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { Alert, Button, Card, Modal, PageHeader, Skeleton, StatCard, StatusBadge } from '@/components/ui'
import { apiDelete, apiPost, useChannels, useInvalidate } from '@/hooks/queries'
import { errorMessage } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { toast } from '@/stores/ui'
import type { Channel } from '@/types/generated'

const ICONS: Record<string, { icon: ReactNode; color: string; hint: string }> = {
  WHATSAPP: { icon: <MessageCircle />, color: 'bg-emerald-500', hint: 'Quick-reply confirm inside the free 24h window.' },
  TELEGRAM: { icon: <Send />, color: 'bg-sky-500', hint: 'Rich tables, ephemeral group drafts, PDF inline.' },
  INSTAGRAM: { icon: <Camera />, color: 'bg-pink-500', hint: 'DM orders with a signed PDF link.' },
  FACEBOOK: { icon: <Users />, color: 'bg-blue-600', hint: 'Messenger orders with the PDF attached.' },
}

type LinkCode = { code: string; expires_at: string; telegram_deep_link: string | null; whatsapp_deep_link: string | null }

export default function Channels() {
  const { data, isLoading } = useChannels()
  const invalidate = useInvalidate()
  const [code, setCode] = useState<LinkCode | null>(null)
  const [busy, setBusy] = useState(false)

  const getCode = async () => {
    setBusy(true)
    try { setCode(await apiPost<LinkCode>('/channels/link-code')) } catch (e) { toast(errorMessage(e), 'error') } finally { setBusy(false) }
  }
  const unlink = async (id: number) => {
    if (!window.confirm('Unlink this chat? It will stop creating slips for your business.')) return
    try { await apiDelete(`/channels/identities/${id}`); await invalidate('channels'); toast('Chat unlinked') } catch (e) { toast(errorMessage(e), 'error') }
  }

  if (isLoading || !data) return <Skeleton className="h-96 rounded-xl" />

  return (
    <div className="space-y-6">
      <PageHeader title="Bot channels" subtitle="Link your chat apps once. Then sell with /slash." actions={<Button leftIcon={<Link2 className="size-4" />} isLoading={busy} onClick={getCode}>Link a chat</Button>} />

      <div className="grid gap-4 sm:grid-cols-3">
        <StatCard title="Drafts · 30 days" value={data.stats.drafts_30d} icon={<MessageCircle />} />
        <StatCard title="Confirmed slips" value={data.stats.confirmed_30d} icon={<CircleCheck />} />
        <StatCard variant="deep" title="Messages saved" value={data.stats.api_calls_saved_30d} hint="silent draft buffering" icon={<Zap />} />
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        {data.channels.map((c) => {
          const meta = ICONS[c.channel]
          return (
            <Card key={c.channel} className="flex items-start gap-4">
              <span className={`inline-flex size-11 shrink-0 items-center justify-center rounded-xl text-white [&>svg]:size-5 ${meta?.color ?? 'bg-slate-400'}`}>{meta?.icon}</span>
              <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <p className="font-semibold">{c.label}</p>
                  <StatusBadge status={c.configured ? 'ACTIVE' : 'DRAFT'} label={c.configured ? 'Live' : 'Not configured'} size="sm" />
                </div>
                <p className="mt-1 text-sm text-ink-secondary">{meta?.hint}</p>
                <p className="tabular mt-2 text-xs text-ink-muted">{c.slips_30d} slips in 30 days</p>
                {c.channel === 'WHATSAPP' && data.handles.whatsapp_link && <Button href={data.handles.whatsapp_link} target="_blank" size="xs" variant="ghost" className="mt-2 -ml-2" rightIcon={<ExternalLink className="size-3.5" />}>{data.handles.whatsapp_number}</Button>}
                {c.channel === 'TELEGRAM' && data.handles.telegram_link && <Button href={data.handles.telegram_link} target="_blank" size="xs" variant="ghost" className="mt-2 -ml-2" rightIcon={<ExternalLink className="size-3.5" />}>@{data.handles.telegram_username?.replace(/^@/, '')}</Button>}
              </div>
            </Card>
          )
        })}
      </div>

      <div>
        <h2 className="mb-3 text-sm font-semibold">Linked chats</h2>
        <Card padding="none">
          {data.identities.length === 0 ? (
            <div className="px-5 py-10 text-center text-sm text-ink-secondary">No chats linked yet. Tap <strong>Link a chat</strong> to get a one-time code.</div>
          ) : (
            <ul className="divide-y divide-border-subtle">
              {data.identities.map((i) => (
                <li key={i.id} className="flex items-center justify-between gap-3 px-5 py-3">
                  <div className="flex items-center gap-3">
                    <StatusBadge status={i.channel as Channel} size="sm" />
                    <div><p className="text-sm font-medium">{i.display_name ?? i.external_user_id}</p><p className="text-xs text-ink-muted">{i.user_name} · linked {formatDate(i.linked_at)}</p></div>
                  </div>
                  <Button size="xs" variant="ghost" className="text-rose-600" leftIcon={<Trash2 className="size-3.5" />} onClick={() => unlink(i.id)}>Unlink</Button>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </div>

      <Modal isOpen={code !== null} onClose={() => { setCode(null); void invalidate('channels') }} title="Link your chat" description="Valid for 30 minutes, single use.">
        {code && (
          <div className="space-y-5">
            <div className="rounded-xl bg-netslip-obsidian px-4 py-5 text-center">
              <p className="text-xs uppercase tracking-widest text-[#9cc3cc]">Send this to the bot</p>
              <p className="mt-2 font-mono text-2xl font-bold tracking-[0.2em] text-white">/link {code.code}</p>
            </div>
            <div className="grid gap-2 sm:grid-cols-2">
              {code.telegram_deep_link && <Button href={code.telegram_deep_link} target="_blank" variant="outline" leftIcon={<Send className="size-4" />}>Open in Telegram</Button>}
              {code.whatsapp_deep_link && <Button href={code.whatsapp_deep_link} target="_blank" variant="success" leftIcon={<MessageCircle className="size-4" />}>Open in WhatsApp</Button>}
              <Button variant="ghost" leftIcon={<Copy className="size-4" />} onClick={() => { void navigator.clipboard.writeText(`/link ${code.code}`); toast('Copied') }}>Copy command</Button>
            </div>
            <Alert tone="info">Instagram and Messenger: DM your business account with the same command.</Alert>
          </div>
        )}
      </Modal>
    </div>
  )
}
