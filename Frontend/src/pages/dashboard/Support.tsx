import { ArrowLeft, LifeBuoy, MessageSquarePlus, Send } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { Alert, Button, Card, EmptyState, Input, Modal, PageHeader, Select, Skeleton, Textarea } from '@/components/ui'
import { apiPost, useSupportTicket, useSupportTickets } from '@/hooks/queries'
import { errorMessage } from '@/lib/api'
import { cn } from '@/lib/cn'
import { useAuthStore } from '@/stores/auth'
import { toast } from '@/stores/ui'
import type { SupportTicket } from '@/types/api'
import { useQueryClient } from '@tanstack/react-query'

const when = (iso: string) => new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date(iso))

function StatusPill({ ticket }: { ticket: Pick<SupportTicket, 'status' | 'status_label'> }) {
  const tone = ticket.status === 'AWAITING_CUSTOMER' ? 'bg-tone-partial/10 text-tone-partial' : ticket.status === 'OPEN' ? 'bg-vendslip-tint text-vendslip-deep' : 'bg-surface-subtle text-ink-secondary'
  return <span className={cn('inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium', tone)}>{ticket.status_label}</span>
}

/** Help & support: the business's tickets with VendSlip support. */
export default function Support() {
  const { reference } = useParams()
  return reference ? <TicketThread reference={reference} /> : <TicketList />
}

function TicketList() {
  const { data, isLoading } = useSupportTickets()
  const [creating, setCreating] = useState(false)
  const inSupport = useAuthStore((s) => !!s.session?.support)

  return (
    <div className="space-y-6">
      <PageHeader title="Help & support" subtitle="Ask VendSlip support anything. Replies come here and to your email."
        actions={!inSupport && <Button leftIcon={<MessageSquarePlus className="size-4" />} onClick={() => setCreating(true)}>New ticket</Button>} />
      <Card padding="none">
        {isLoading || !data ? (
          <div className="space-y-3 p-5">{[0, 1, 2].map((i) => <Skeleton key={i} className="h-12 w-full" />)}</div>
        ) : data.data.length === 0 ? (
          <EmptyState icon={<LifeBuoy />} title="No tickets yet" description="Stuck on something? Open a ticket and a person from VendSlip support will reply." />
        ) : (
          <ul className="divide-y divide-border-subtle">
            {data.data.map((t) => (
              <li key={t.reference}>
                <Link to={`/dashboard/support/${t.reference}`} className="flex items-center gap-3 px-5 py-3.5 hover:bg-surface-subtle">
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold text-ink-primary">{t.subject}</p>
                    <p className="text-xs text-ink-muted"><span className="font-mono">{t.reference}</span> · {t.category_label}{t.opened_by ? ` · ${t.opened_by}` : ''} · {when(t.last_activity_at)}</p>
                  </div>
                  <StatusPill ticket={t} />
                </Link>
              </li>
            ))}
          </ul>
        )}
      </Card>
      {creating && data && <NewTicketModal categories={data.categories} onClose={() => setCreating(false)} />}
    </div>
  )
}

function NewTicketModal({ categories, onClose }: { categories: Record<string, string>; onClose: () => void }) {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [form, setForm] = useState({ subject: '', category: 'TECHNICAL', body: '' })
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const submit = async () => {
    setBusy(true)
    setError(null)
    try {
      const res = await apiPost<{ data: SupportTicket }>('/support/tickets', form)
      await queryClient.invalidateQueries({ queryKey: ['support-tickets'] })
      toast(`Ticket ${res.data.reference} sent`)
      navigate(`/dashboard/support/${res.data.reference}`)
    } catch (e) { setError(errorMessage(e)) } finally { setBusy(false) }
  }
  return (
    <Modal isOpen onClose={onClose} title="New support ticket" description="Tell us what happened and what you expected. Slip numbers help."
      footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button isLoading={busy} disabled={form.subject.trim().length < 4 || form.body.trim().length < 10} onClick={submit}>Send ticket</Button></>}>
      <div className="space-y-4">
        {error && <Alert tone="danger">{error}</Alert>}
        <Input label="Subject" value={form.subject} maxLength={160} onChange={(e) => setForm({ ...form, subject: e.target.value })} placeholder="e.g. Telegram bot isn’t replying" />
        <Select label="Topic" value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })} options={Object.entries(categories).map(([value, label]) => ({ value, label }))} />
        <Textarea label="Details" rows={6} value={form.body} maxLength={5000} onChange={(e) => setForm({ ...form, body: e.target.value })} helperText="Never include passwords or full card numbers." />
      </div>
    </Modal>
  )
}

function TicketThread({ reference }: { reference: string }) {
  const { data, isLoading, error } = useSupportTicket(reference)
  const queryClient = useQueryClient()
  const inSupport = useAuthStore((s) => !!s.session?.support)
  const [body, setBody] = useState('')
  const [busy, setBusy] = useState<'reply' | 'close' | null>(null)
  const [err, setErr] = useState<string | null>(null)

  const act = async (kind: 'reply' | 'close') => {
    setBusy(kind)
    setErr(null)
    try {
      const res = kind === 'reply'
        ? await apiPost<{ data: SupportTicket }>(`/support/tickets/${reference}/messages`, { body })
        : await apiPost<{ data: SupportTicket }>(`/support/tickets/${reference}/close`)
      queryClient.setQueryData(['support-ticket', reference], res)
      void queryClient.invalidateQueries({ queryKey: ['support-tickets'] })
      if (kind === 'reply') setBody('')
      toast(kind === 'reply' ? 'Reply sent' : 'Ticket closed')
    } catch (e) { setErr(errorMessage(e)) } finally { setBusy(null) }
  }

  if (isLoading) return <Skeleton className="h-64 w-full" />
  if (error || !data) return <Alert tone="danger">{errorMessage(error)}</Alert>
  const t = data.data
  const closed = t.status === 'CLOSED'

  return (
    <div className="space-y-6">
      <Link to="/dashboard/support" className="inline-flex items-center gap-1.5 text-sm font-medium text-vendslip-primary hover:underline"><ArrowLeft className="size-4" />All tickets</Link>
      <PageHeader title={t.subject} subtitle={<span className="flex flex-wrap items-center gap-2"><span className="font-mono">{t.reference}</span> · {t.category_label} <StatusPill ticket={t} /></span>}
        actions={!closed && !inSupport && <Button variant="ghost" isLoading={busy === 'close'} onClick={() => act('close')}>Close ticket</Button>} />
      <ol className="space-y-3" aria-label="Conversation">
        {t.messages.map((m) => m.kind === 'SYSTEM' ? (
          <li key={m.id} className="text-center text-xs text-ink-muted">{m.body} · {when(m.created_at)}</li>
        ) : (
          <li key={m.id} className={cn('rounded-xl border p-4', m.kind === 'AGENT' ? 'border-vendslip-primary/30 bg-vendslip-tint sm:mr-12' : 'border-border-subtle bg-white sm:ml-12')}>
            <div className="flex flex-wrap items-center justify-between gap-2 text-xs">
              <span className="font-semibold text-ink-primary">{m.author ?? 'You'}</span>
              <time className="text-ink-muted" dateTime={m.created_at}>{when(m.created_at)}</time>
            </div>
            <p className="mt-2 whitespace-pre-line break-words text-sm text-ink-primary">{m.body}</p>
          </li>
        ))}
      </ol>
      {closed ? (
        <Alert tone="info">This ticket is closed. Open a new ticket and mention {t.reference} if you still need help.</Alert>
      ) : !inSupport && (
        <Card padding="sm" className="space-y-3">
          {err && <Alert tone="danger">{err}</Alert>}
          <Textarea label="Your reply" rows={4} value={body} maxLength={5000} onChange={(e) => setBody(e.target.value)} />
          <div className="flex justify-end"><Button isLoading={busy === 'reply'} disabled={body.trim().length < 2} leftIcon={<Send className="size-4" />} onClick={() => act('reply')}>Send reply</Button></div>
        </Card>
      )}
    </div>
  )
}
