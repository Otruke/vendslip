import { KeyRound, ShieldCheck } from 'lucide-react'
import { useEffect, useState, type CSSProperties, type ReactNode } from 'react'
import { useLocation } from 'react-router-dom'
import { Alert, Button, Card, Input, Modal, PageHeader, StatusBadge } from '@/components/ui'
import { apiDelete, apiPost, apiPut, useRefreshSession } from '@/hooks/queries'
import { ApiError, errorMessage } from '@/lib/api'
import { formatDateTime, initials } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'
import { toast } from '@/stores/ui'

function Section({ id, title, description, children, footer }: { id?: string; title: string; description?: string; children: ReactNode; footer?: ReactNode }) {
  return (
    <Card padding="none" id={id} className="scroll-mt-24">
      <div className="border-b border-border-subtle px-5 py-4"><h2 className="font-semibold">{title}</h2>{description && <p className="mt-0.5 text-sm text-ink-secondary">{description}</p>}</div>
      <div className="space-y-4 px-5 py-5">{children}</div>
      {footer && <div className="flex justify-end gap-2 border-t border-border-subtle px-5 py-3">{footer}</div>}
    </Card>
  )
}

/** "My profile": every team member (owner to sales agent) manages their own details, password and 2FA here. */
export default function Profile() {
  const session = useAuthStore((s) => s.session)
  const user = session?.user
  const merchant = session?.merchant
  const refresh = useRefreshSession()
  const { hash } = useLocation()

  // Menu links such as "Change password" point at #password / #two-factor.
  useEffect(() => {
    if (!hash) return
    const target = document.getElementById(hash.slice(1))
    if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }, [hash])

  const [profile, setProfile] = useState({ name: user?.name ?? '', phone: user?.phone ?? '' })
  const [pw, setPw] = useState({ current_password: '', password: '', password_confirmation: '' })
  const [pwErrors, setPwErrors] = useState<Record<string, string>>({})
  const [enroll, setEnroll] = useState<{ secret: string; qr_svg: string } | null>(null)
  const [code, setCode] = useState('')
  const [disablePw, setDisablePw] = useState('')
  const [showDisable, setShowDisable] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState<string | null>(null)

  if (!user) return null

  const run = async (key: string, fn: () => Promise<unknown>, message: string) => {
    setError(null)
    setBusy(key)
    try { await fn(); await refresh(); toast(message); return true } catch (e) { setError(errorMessage(e)); return false } finally { setBusy(null) }
  }

  const changePassword = async () => {
    setPwErrors({})
    setBusy('password')
    try {
      const res = await apiPut<{ other_sessions_ended: number }>('/account/password', pw)
      setPw({ current_password: '', password: '', password_confirmation: '' })
      const n = res.other_sessions_ended
      toast(n > 0 ? `Password changed. ${n} other session${n === 1 ? ' was' : 's were'} signed out.` : 'Password changed.')
    } catch (e) {
      if (e instanceof ApiError && Object.keys(e.fieldErrors).length > 0) setPwErrors(Object.fromEntries(Object.entries(e.fieldErrors).map(([k, v]) => [k, v[0] ?? ''])))
      else toast(errorMessage(e), 'error')
    } finally { setBusy(null) }
  }

  return (
    <div className="space-y-6">
      <PageHeader title="My profile" subtitle="Your details, password and sign-in security." />

      <div className="grid items-start gap-6 lg:grid-cols-[300px_minmax(0,1fr)]">
        <aside className="relative overflow-hidden rounded-2xl bg-netslip-obsidian p-6 text-white">
          <img src="/assets/brand/netslip-icon-white.png" alt="" className="pointer-events-none absolute -top-5 -right-5 h-28 w-auto opacity-[0.06]" />
          <span className="flex size-16 items-center justify-center rounded-2xl bg-netslip-primary text-2xl font-extrabold tracking-tight">{initials(user.name)}</span>
          <p className="mt-4 text-lg font-bold leading-tight">{user.name}</p>
          <p className="truncate text-sm text-[#cfe3e8]">{user.email}</p>
          <span className="ns-tag ns-tag--sm mt-3" style={{ '--t': '#cfe3e8', background: 'transparent' } as CSSProperties} data-fill="solid"><span className="ns-tag__mark" />{user.role_label ?? 'Member'}</span>
          <dl className="mt-6 space-y-3 border-t border-white/10 pt-4 text-sm">
            <div className="flex justify-between gap-3"><dt className="text-[#9fb9c0]">Business</dt><dd className="truncate text-right">{merchant?.name ?? '—'}</dd></div>
            <div className="flex justify-between gap-3"><dt className="text-[#9fb9c0]">Last sign-in</dt><dd className="tabular text-right">{user.last_login_at ? formatDateTime(user.last_login_at) : 'Now'}</dd></div>
            <div className="flex justify-between gap-3"><dt className="text-[#9fb9c0]">Two-factor</dt><dd>{user.two_factor_enabled ? 'On' : 'Off'}</dd></div>
          </dl>
        </aside>

        <div className="space-y-6">
          {error && <Alert tone="danger">{error}</Alert>}

          <Section title="Details" description="Shown to your team and on slips you issue." footer={
            <Button isLoading={busy === 'profile'} onClick={() => run('profile', () => apiPut('/account/profile', { name: profile.name, phone: profile.phone || null }), 'Profile saved')}>Save details</Button>
          }>
            <div className="grid gap-4 sm:grid-cols-2">
              <Input label="Full name" autoComplete="name" value={profile.name} onChange={(e) => setProfile({ ...profile, name: e.target.value })} />
              <Input label="Phone" autoComplete="tel" value={profile.phone} onChange={(e) => setProfile({ ...profile, phone: e.target.value })} />
            </div>
            <Input label="Email" value={user.email} disabled helperText="Ask your business owner to change the sign-in email." />
          </Section>

          <Section id="password" title="Change password" description="Your other devices are signed out when you change it." footer={
            <Button leftIcon={<KeyRound className="size-4" />} isLoading={busy === 'password'} disabled={!pw.current_password || !pw.password} onClick={changePassword}>Update password</Button>
          }>
            <Input label="Current password" type="password" autoComplete="current-password" value={pw.current_password} error={pwErrors.current_password} onChange={(e) => setPw({ ...pw, current_password: e.target.value })} />
            <div className="grid gap-4 sm:grid-cols-2">
              <Input label="New password" type="password" autoComplete="new-password" value={pw.password} error={pwErrors.password} helperText={pwErrors.password ? undefined : 'At least 8 characters.'} onChange={(e) => setPw({ ...pw, password: e.target.value })} />
              <Input label="Confirm new password" type="password" autoComplete="new-password" value={pw.password_confirmation} onChange={(e) => setPw({ ...pw, password_confirmation: e.target.value })} />
            </div>
          </Section>

          <Section id="two-factor" title="Two-factor authentication" description="A 6-digit code from your authenticator app at every sign-in.">
            {user.two_factor_enabled ? (
              <div className="flex flex-wrap items-center justify-between gap-3">
                <StatusBadge status="ACTIVE" label="Enabled" />
                <Button variant="outline" onClick={() => setShowDisable(true)}>Turn off</Button>
              </div>
            ) : enroll ? (
              <div className="grid items-center gap-5 sm:grid-cols-[176px_1fr]">
                <div className="rounded-xl border border-border-subtle bg-white p-2 [&_svg]:h-auto [&_svg]:w-full" dangerouslySetInnerHTML={{ __html: enroll.qr_svg }} />
                <div className="space-y-3">
                  <p className="text-sm text-ink-secondary">Scan with Google Authenticator, Authy or 1Password, or type this key:</p>
                  <p className="break-all rounded-lg bg-surface-canvas px-3 py-2 font-mono text-xs">{enroll.secret}</p>
                  <div className="flex gap-2">
                    <Input aria-label="6-digit code" inputMode="numeric" autoComplete="one-time-code" maxLength={6} placeholder="123456" value={code} onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))} wrapperClassName="w-36" className="tabular tracking-widest" />
                    <Button disabled={code.length !== 6} isLoading={busy === '2fa'} onClick={async () => { if (await run('2fa', () => apiPost('/account/two-factor/confirm', { code }), 'Two-factor is on')) setEnroll(null) }}>Confirm</Button>
                  </div>
                </div>
              </div>
            ) : (
              <Button leftIcon={<ShieldCheck className="size-4" />} onClick={async () => { try { setEnroll(await apiPost('/account/two-factor')) } catch (e) { setError(errorMessage(e)) } }}>Set up two-factor</Button>
            )}
          </Section>
        </div>
      </div>

      <Modal isOpen={showDisable} onClose={() => setShowDisable(false)} title="Turn off two-factor?" size="sm"
        footer={<><Button variant="ghost" onClick={() => setShowDisable(false)}>Cancel</Button><Button variant="danger" disabled={!disablePw} onClick={async () => { if (await run('2fa-off', () => apiDelete('/account/two-factor', { password: disablePw }), 'Two-factor is off')) { setShowDisable(false); setDisablePw('') } }}>Turn off</Button></>}>
        <Input label="Confirm with your password" type="password" autoComplete="current-password" value={disablePw} onChange={(e) => setDisablePw(e.target.value)} />
      </Modal>
    </div>
  )
}
