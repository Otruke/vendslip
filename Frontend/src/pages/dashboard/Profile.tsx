import { ImageUp, KeyRound, ShieldCheck, Trash2 } from 'lucide-react'
import { useEffect, useRef, useState, type CSSProperties, type ReactNode } from 'react'
import { useLocation } from 'react-router-dom'
import { Alert, Avatar, Button, Card, Input, Modal, PageHeader, PasswordChecklist, StatusBadge } from '@/components/ui'
import { apiDelete, apiPost, apiPut, useRefreshSession } from '@/hooks/queries'
import { api, ApiError, errorMessage } from '@/lib/api'
import { formatDateTime } from '@/lib/format'
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

  const [profile, setProfile] = useState({ name: user?.name ?? '', email: user?.email ?? '', phone: user?.phone ?? '', current_password: '' })
  const [profileErrors, setProfileErrors] = useState<Record<string, string>>({})
  const [pw, setPw] = useState({ current_password: '', password: '', password_confirmation: '' })
  const [pwErrors, setPwErrors] = useState<Record<string, string>>({})
  const [enroll, setEnroll] = useState<{ secret: string; qr_svg: string } | null>(null)
  const [code, setCode] = useState('')
  const [disablePw, setDisablePw] = useState('')
  const [showDisable, setShowDisable] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState<string | null>(null)
  const [preview, setPreview] = useState<string | null>(null)
  const fileRef = useRef<HTMLInputElement>(null)

  if (!user) return null

  const AVATAR_TYPES = ['image/jpeg', 'image/png', 'image/webp']
  const uploadAvatar = async (file: File) => {
    if (!AVATAR_TYPES.includes(file.type)) { toast('Choose a JPG, PNG or WebP photo.', 'error'); return }
    if (file.size > 5 * 1024 * 1024) { toast('That photo is over 5 MB. Choose a smaller one.', 'error'); return }
    const local = URL.createObjectURL(file)
    setPreview(local)
    setBusy('avatar')
    try {
      const body = new FormData()
      body.append('avatar', file)
      await api.post('/account/avatar', body)
      await refresh()
      toast('Profile photo updated')
    } catch (e) {
      toast(errorMessage(e), 'error')
    } finally {
      setBusy(null)
      setPreview(null)
      URL.revokeObjectURL(local)
    }
  }
  const removeAvatar = () => run('avatar-remove', () => apiDelete('/account/avatar'), 'Profile photo removed')

  const run = async (key: string, fn: () => Promise<unknown>, message: string) => {
    setError(null)
    setBusy(key)
    try { await fn(); await refresh(); toast(message); return true } catch (e) { setError(errorMessage(e)); return false } finally { setBusy(null) }
  }

  const saveDetails = async () => {
    setProfileErrors({})
    setError(null)
    setBusy('profile')
    try {
      const res = await apiPut<{ changed: string[] }>('/account/profile', { name: profile.name, email: profile.email, phone: profile.phone || null, current_password: profile.current_password })
      setProfile((p) => ({ ...p, current_password: '' }))
      await refresh()
      toast(res.changed.length > 0 ? 'Details saved' : 'Nothing changed')
    } catch (e) {
      if (e instanceof ApiError && Object.keys(e.fieldErrors).length > 0) setProfileErrors(Object.fromEntries(Object.entries(e.fieldErrors).map(([k, v]) => [k, v[0] ?? ''])))
      else toast(errorMessage(e), 'error')
    } finally { setBusy(null) }
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
        <aside className="relative overflow-hidden rounded-2xl bg-vendslip-obsidian p-6 text-white">
          <img src="/assets/brand/vendslip-icon-white.png" alt="" className="pointer-events-none absolute -top-5 -right-5 h-28 w-auto opacity-[0.06]" />
          <div className="flex items-end gap-4">
            <span className="relative">
              <Avatar name={user.name} src={preview ?? user.avatar_url} className="size-20 rounded-2xl bg-vendslip-primary text-2xl font-extrabold tracking-tight" />
              {busy === 'avatar' && <span className="absolute inset-0 flex items-center justify-center rounded-2xl bg-vendslip-obsidian/70 text-[11px] font-semibold">Saving…</span>}
            </span>
            <div className="flex flex-col items-start gap-1.5 pb-0.5">
              <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp" className="sr-only" aria-label="Choose a profile photo" onChange={(e) => { const f = e.target.files?.[0]; if (f) void uploadAvatar(f); e.target.value = '' }} />
              <button type="button" disabled={busy === 'avatar'} onClick={() => fileRef.current?.click()} className="inline-flex items-center gap-1.5 rounded-md bg-white px-2.5 py-1 text-xs font-semibold text-vendslip-deep hover:bg-[#e3eef1] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white disabled:opacity-60">
                <ImageUp className="size-3.5" />{user.avatar_url ? 'Change photo' : 'Upload photo'}
              </button>
              {user.avatar_url && (
                <button type="button" disabled={busy === 'avatar-remove'} onClick={() => void removeAvatar()} className="inline-flex items-center gap-1 text-xs font-semibold text-[#cfe3e8] hover:text-white disabled:opacity-60">
                  <Trash2 className="size-3" />Remove
                </button>
              )}
            </div>
          </div>
          <p className="mt-2 text-[11px] text-[#9fb9c0]">JPG, PNG or WebP, up to 5 MB. Only your team can see it.</p>
          <p className="mt-4 text-lg font-bold leading-tight">{user.name}</p>
          <p className="truncate text-sm text-[#cfe3e8]">{user.email}</p>
          <span className="vs-tag vs-tag--sm mt-3" style={{ '--t': '#cfe3e8', background: 'transparent' } as CSSProperties} data-fill="solid"><span className="vs-tag__mark" />{user.role_label ?? 'Member'}</span>
          <dl className="mt-6 space-y-3 border-t border-white/10 pt-4 text-sm">
            <div className="flex justify-between gap-3"><dt className="text-[#9fb9c0]">Business</dt><dd className="truncate text-right">{merchant?.name ?? '—'}</dd></div>
            <div className="flex justify-between gap-3"><dt className="text-[#9fb9c0]">Last sign-in</dt><dd className="tabular text-right">{user.last_login_at ? formatDateTime(user.last_login_at) : 'Now'}</dd></div>
            <div className="flex justify-between gap-3"><dt className="text-[#9fb9c0]">Two-factor</dt><dd>{user.two_factor_enabled ? 'On' : 'Off'}</dd></div>
          </dl>
        </aside>

        <div className="space-y-6">
          {session?.support && <Alert tone="info">You’re in a support session. Password, email, photo and two-factor belong to the owner and can’t be changed from here.</Alert>}
          {error && <Alert tone="danger">{error}</Alert>}

          <Section title="Details" description="Shown to your team and on slips you issue. Confirm with your current password to save." footer={
            <Button isLoading={busy === 'profile'} disabled={!profile.current_password} onClick={() => void saveDetails()}>Save details</Button>
          }>
            <div className="grid gap-4 sm:grid-cols-2">
              <Input label="Full name" autoComplete="name" value={profile.name} error={profileErrors.name} onChange={(e) => setProfile({ ...profile, name: e.target.value })} />
              <Input label="Phone" autoComplete="tel" value={profile.phone} error={profileErrors.phone} onChange={(e) => setProfile({ ...profile, phone: e.target.value })} />
            </div>
            <Input label="Email" type="email" autoComplete="email" value={profile.email} error={profileErrors.email} helperText={profileErrors.email ? undefined : 'You sign in with this email.'} onChange={(e) => setProfile({ ...profile, email: e.target.value })} />
            <Input label="Current password" type="password" autoComplete="current-password" value={profile.current_password} error={profileErrors.current_password} helperText={profileErrors.current_password ? undefined : 'Needed to save any change to your details.'} onChange={(e) => setProfile({ ...profile, current_password: e.target.value })} />
          </Section>

          <Section id="password" title="Change password" description="Your other devices are signed out when you change it." footer={
            <Button leftIcon={<KeyRound className="size-4" />} isLoading={busy === 'password'} disabled={!pw.current_password || !pw.password} onClick={changePassword}>Update password</Button>
          }>
            <Input label="Current password" type="password" autoComplete="current-password" value={pw.current_password} error={pwErrors.current_password} onChange={(e) => setPw({ ...pw, current_password: e.target.value })} />
            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <Input label="New password" type="password" autoComplete="new-password" value={pw.password} error={pwErrors.password} onChange={(e) => setPw({ ...pw, password: e.target.value })} />
                <PasswordChecklist value={pw.password} />
              </div>
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
