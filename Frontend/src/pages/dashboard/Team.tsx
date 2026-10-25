import { Copy, Mail, ShieldCheck, UserPlus, Users } from 'lucide-react'
import { useState } from 'react'
import { Alert, Avatar, Button, Card, Input, Modal, PageHeader, Select, Skeleton, StatusBadge } from '@/components/ui'
import { apiDelete, apiPost, apiPut, useInvalidate, useTeam } from '@/hooks/queries'
import { errorMessage } from '@/lib/api'
import { formatDate, timeAgo } from '@/lib/format'
import { useAuthStore, useCan } from '@/stores/auth'
import { toast } from '@/stores/ui'
import { FeatureGate } from './FeatureGate'

export default function Team() {
  const { data, isLoading } = useTeam()
  const canManage = useCan('team.manage')
  const me = useAuthStore((s) => s.session?.user.id)
  const invalidate = useInvalidate()
  const [inviting, setInviting] = useState(false)
  const [email, setEmail] = useState('')
  const [role, setRole] = useState('SalesAgent')
  const [inviteUrl, setInviteUrl] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  if (isLoading || !data) return <Skeleton className="h-96 rounded-xl" />
  if (!data.seats.can_invite && data.members.length <= 1) {
    return <FeatureGate icon={<Users />} title="Sell as a team" description="Invite managers, sales agents and accountants with their own roles and audit trail." />
  }

  const invite = async () => {
    setBusy(true)
    setError(null)
    try {
      const res = await apiPost<{ invite_url: string }>('/team/invitations', { email, role })
      setInviteUrl(res.invite_url)
      await invalidate('team')
    } catch (e) { setError(errorMessage(e)) } finally { setBusy(false) }
  }
  const update = async (id: number, body: Record<string, unknown>) => {
    try { await apiPut(`/team/members/${id}`, body); await invalidate('team'); toast('Team updated') } catch (e) { toast(errorMessage(e), 'error') }
  }
  const remove = async (id: number, name: string) => {
    if (!window.confirm(`Remove ${name} from your team? Their past slips stay in the ledger.`)) return
    try { await apiDelete(`/team/members/${id}`); await invalidate('team'); toast(`${name} removed`) } catch (e) { toast(errorMessage(e), 'error') }
  }
  const revoke = async (id: number) => {
    try { await apiDelete(`/team/invitations/${id}`); await invalidate('team') } catch (e) { toast(errorMessage(e), 'error') }
  }

  return (
    <div className="space-y-6">
      <PageHeader title="Team" subtitle={`${data.seats.used} of ${data.seats.max ?? '∞'} seats used.`} actions={canManage && data.seats.can_invite && <Button leftIcon={<UserPlus className="size-4" />} onClick={() => { setInviting(true); setInviteUrl(null); setEmail('') }}>Invite teammate</Button>} />

      <Card padding="none">
        <ul className="divide-y divide-border-subtle">
          {data.members.map((m) => (
            <li key={m.id} className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
              <div className="flex items-center gap-3">
                <Avatar name={m.name} />
                <div>
                  <p className="font-semibold text-ink-primary">{m.name} {m.id === me && <span className="text-xs font-normal text-ink-muted">(you)</span>}</p>
                  <p className="text-sm text-ink-secondary">{m.email} · last seen {timeAgo(m.last_login_at)}</p>
                </div>
              </div>
              <div className="flex flex-wrap items-center gap-2">
                {m.two_factor_enabled && <StatusBadge status="PAID" label="2FA" size="sm" showIcon={false} />}
                {!m.is_active && <StatusBadge status="SUSPENDED" label="Disabled" size="sm" />}
                {canManage && m.role !== 'Owner' && m.id !== me ? (
                  <>
                    <Select aria-label="Role" value={m.role ?? ''} onChange={(e) => update(m.id, { role: e.target.value })} options={data.roles} className="h-8 text-xs" />
                    <Button size="xs" variant="outline" onClick={() => update(m.id, { is_active: !m.is_active })}>{m.is_active ? 'Disable' : 'Enable'}</Button>
                    <Button size="xs" variant="ghost" className="text-rose-600" onClick={() => remove(m.id, m.name)}>Remove</Button>
                  </>
                ) : <StatusBadge status={m.role === 'Owner' ? 'BUSINESS_PLUS' : 'PRO'} label={m.role_label ?? m.role ?? ''} size="sm" />}
              </div>
            </li>
          ))}
        </ul>
      </Card>

      {data.invitations.length > 0 && (
        <div>
          <h2 className="mb-3 text-sm font-semibold">Pending invitations</h2>
          <Card padding="none">
            <ul className="divide-y divide-border-subtle">
              {data.invitations.map((i) => (
                <li key={i.id} className="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                  <span className="flex items-center gap-2"><Mail className="size-4 text-ink-muted" />{i.email} · {i.role_label}</span>
                  <span className="flex items-center gap-3"><span className="text-xs text-ink-muted">expires {formatDate(i.expires_at)}</span>{canManage && <Button size="xs" variant="ghost" onClick={() => revoke(i.id)}>Revoke</Button>}</span>
                </li>
              ))}
            </ul>
          </Card>
        </div>
      )}

      <Card variant="tinted" padding="sm" className="flex gap-3 text-sm text-netslip-deep">
        <ShieldCheck className="mt-0.5 size-4 shrink-0" />
        <p><strong>Owners</strong> control everything. <strong>Managers</strong> issue, edit and void. <strong>Sales agents</strong> issue slips and record payments. <strong>Accountants</strong> view, void and record payments.</p>
      </Card>

      <Modal isOpen={inviting} onClose={() => setInviting(false)} title="Invite a teammate"
        footer={inviteUrl ? <Button onClick={() => setInviting(false)}>Done</Button> : <><Button variant="ghost" onClick={() => setInviting(false)}>Cancel</Button><Button isLoading={busy} disabled={!email} onClick={invite}>Send invite</Button></>}>
        {inviteUrl ? (
          <div className="space-y-3">
            <Alert tone="success" title="Invitation sent">We emailed {email}. You can also share this link (expires in 7 days):</Alert>
            <div className="flex gap-2">
              <Input aria-label="Invite link" readOnly value={inviteUrl} wrapperClassName="flex-1" className="font-mono text-xs" />
              <Button variant="outline" leftIcon={<Copy className="size-4" />} onClick={() => { void navigator.clipboard.writeText(inviteUrl); toast('Link copied') }}>Copy</Button>
            </div>
          </div>
        ) : (
          <div className="space-y-4">
            {error && <Alert tone="danger">{error}</Alert>}
            <Input label="Email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} />
            <Select label="Role" value={role} onChange={(e) => setRole(e.target.value)} options={data.roles} />
          </div>
        )}
      </Modal>
    </div>
  )
}
