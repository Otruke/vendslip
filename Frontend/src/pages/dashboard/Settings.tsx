import { Building2, CreditCard, ImageUp, KeyRound, Palette, ShieldCheck, Trash2 } from 'lucide-react'
import { useRef, useState, type ReactNode } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Alert, Button, Card, Input, Modal, PageHeader, SegmentedTabs, Select, StatusBadge, Textarea, ToggleSwitch } from '@/components/ui'
import { apiDelete, apiPost, apiPut, useRefreshSession } from '@/hooks/queries'
import { api, errorMessage } from '@/lib/api'
import { bpsToPercentLabel, percentToBps } from '@/lib/money'
import { useAuthStore, useCan, useEntitlement } from '@/stores/auth'
import { toast } from '@/stores/ui'
import type { Merchant } from '@/types/api'
import { FeatureGate } from './FeatureGate'

type Tab = 'business' | 'branding' | 'payments' | 'security'

function Section({ title, description, children, footer }: { title: string; description?: string; children: ReactNode; footer?: ReactNode }) {
  return (
    <Card padding="none">
      <div className="border-b border-border-subtle px-5 py-4"><h2 className="font-semibold">{title}</h2>{description && <p className="mt-0.5 text-sm text-ink-secondary">{description}</p>}</div>
      <div className="space-y-4 px-5 py-5">{children}</div>
      {footer && <div className="flex justify-end gap-2 border-t border-border-subtle px-5 py-3">{footer}</div>}
    </Card>
  )
}

export default function Settings() {
  const [params, setParams] = useSearchParams()
  const tab = (params.get('tab') as Tab | null) ?? 'business'
  return (
    <div className="space-y-6">
      <PageHeader title="Settings" subtitle="Your business details, brand and security." />
      <SegmentedTabs<Tab> value={tab} onChange={(v) => setParams({ tab: v })} options={[
        { value: 'business', label: 'Business', icon: <Building2 /> },
        { value: 'branding', label: 'Branding', icon: <Palette /> },
        { value: 'payments', label: 'Payment links', icon: <CreditCard /> },
        { value: 'security', label: 'Security', icon: <ShieldCheck /> },
      ]} />
      <div className="max-w-3xl">
        {tab === 'business' && <BusinessSettings />}
        {tab === 'branding' && <BrandingSettings />}
        {tab === 'payments' && <PaymentSettings />}
        {tab === 'security' && <SecuritySettings />}
      </div>
    </div>
  )
}

export function BusinessSettings({ onSaved, compact = false }: { onSaved?: () => void; compact?: boolean }) {
  const merchant = useAuthStore((s) => s.session?.merchant) as Merchant
  const canManage = useCan('settings.manage')
  const refresh = useRefreshSession()
  const [form, setForm] = useState(() => ({
    name: merchant.name, email: merchant.email ?? '', phone: merchant.phone ?? '', address: merchant.address ?? '', rc_number: merchant.rc_number ?? '', tax_id: merchant.tax_id ?? '',
    currency: merchant.currency, vat: bpsToPercentLabel(merchant.vat_rate_bps).replace('%', ''), vat_inclusive: merchant.vat_inclusive,
    bank_name: merchant.bank_name ?? '', bank_account_number: merchant.bank_account_number ?? '', bank_account_name: merchant.bank_account_name ?? '',
    invoice_due_days: String(merchant.invoice_due_days), receipt_footer: merchant.receipt_footer ?? '',
  }))
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const set = (k: keyof typeof form, v: string | boolean) => setForm((f) => ({ ...f, [k]: v }))

  const save = async () => {
    const bps = percentToBps(form.vat)
    if (bps === null) { setError('VAT must be a percentage like 7.5'); return }
    setBusy(true)
    setError(null)
    try {
      await apiPut('/settings/business', {
        name: form.name, email: form.email || null, phone: form.phone || null, address: form.address || null, rc_number: form.rc_number || null, tax_id: form.tax_id || null,
        currency: form.currency, vat_rate_bps: bps, vat_inclusive: form.vat_inclusive, bank_name: form.bank_name || null, bank_account_number: form.bank_account_number || null,
        bank_account_name: form.bank_account_name || null, invoice_due_days: Number(form.invoice_due_days) || 0, receipt_footer: form.receipt_footer || null,
      })
      await refresh()
      toast('Business details saved')
      onSaved?.()
    } catch (e) { setError(errorMessage(e)) } finally { setBusy(false) }
  }

  return (
    <Section title="Business profile" description="Printed on every slip." footer={canManage && <Button isLoading={busy} onClick={save}>{compact ? 'Save & continue' : 'Save changes'}</Button>}>
      {error && <Alert tone="danger">{error}</Alert>}
      <div className="grid gap-4 sm:grid-cols-2">
        <Input label="Business name" value={form.name} onChange={(e) => set('name', e.target.value)} disabled={!canManage} />
        <Input label="Phone" value={form.phone} onChange={(e) => set('phone', e.target.value)} disabled={!canManage} />
        <Input label="Email" type="email" value={form.email} onChange={(e) => set('email', e.target.value)} disabled={!canManage} />
        <Input label="Address" value={form.address} onChange={(e) => set('address', e.target.value)} disabled={!canManage} />
        {!compact && <Input label="RC number" value={form.rc_number} onChange={(e) => set('rc_number', e.target.value)} disabled={!canManage} />}
        {!compact && <Input label="TIN" value={form.tax_id} onChange={(e) => set('tax_id', e.target.value)} disabled={!canManage} />}
      </div>
      <div className="grid gap-4 sm:grid-cols-3">
        <Select label="Currency" value={form.currency} onChange={(e) => set('currency', e.target.value)} disabled={!canManage} options={['NGN', 'USD', 'GBP', 'EUR', 'GHS', 'KES', 'ZAR'].map((c) => ({ value: c, label: c }))} />
        <Input label="VAT %" inputMode="decimal" value={form.vat} onChange={(e) => set('vat', e.target.value)} disabled={!canManage} />
        <Input label="Invoice due (days)" type="number" min={0} value={form.invoice_due_days} onChange={(e) => set('invoice_due_days', e.target.value)} disabled={!canManage} />
      </div>
      <ToggleSwitch checked={form.vat_inclusive} onChange={(v) => set('vat_inclusive', v)} disabled={!canManage} label="Prices already include VAT" description="VAT is extracted from item prices instead of added on top." />
      <div className="grid gap-4 sm:grid-cols-3">
        <Input label="Bank" value={form.bank_name} onChange={(e) => set('bank_name', e.target.value)} disabled={!canManage} placeholder="Providus Bank" />
        <Input label="Account number" inputMode="numeric" value={form.bank_account_number} onChange={(e) => set('bank_account_number', e.target.value)} disabled={!canManage} />
        <Input label="Account name" value={form.bank_account_name} onChange={(e) => set('bank_account_name', e.target.value)} disabled={!canManage} />
      </div>
      {!compact && <Textarea label="Slip footer" rows={2} maxLength={280} value={form.receipt_footer} onChange={(e) => set('receipt_footer', e.target.value)} disabled={!canManage} placeholder="Thank you for your patronage!" />}
    </Section>
  )
}

export function BrandingSettings() {
  const merchant = useAuthStore((s) => s.session?.merchant) as Merchant
  const enabled = useEntitlement('custom_branding')
  const refresh = useRefreshSession()
  const fileRef = useRef<HTMLInputElement>(null)
  const [primary, setPrimary] = useState(merchant.primary_color)
  const [secondary, setSecondary] = useState(merchant.secondary_color)
  const [busy, setBusy] = useState(false)

  if (!enabled) return <FeatureGate icon={<Palette />} plan="Pro" title="Make every slip yours" description="Upload your logo and set brand colours on Pro and Business Plus." />

  const saveColors = async () => {
    setBusy(true)
    try { await apiPut('/settings/branding', { primary_color: primary, secondary_color: secondary }); await refresh(); toast('Brand colours saved') } catch (e) { toast(errorMessage(e), 'error') } finally { setBusy(false) }
  }
  const upload = async (file: File) => {
    const body = new FormData()
    body.append('logo', file)
    try { await api.post('/settings/logo', body); await refresh(); toast('Logo uploaded') } catch (e) { toast(errorMessage(e), 'error') }
  }
  const removeLogo = async () => {
    try { await apiDelete('/settings/logo'); await refresh() } catch (e) { toast(errorMessage(e), 'error') }
  }

  return (
    <div className="space-y-6">
      <Section title="Logo" description="PNG, JPG or WebP up to 1 MB.">
        <div className="flex items-center gap-4">
          <div className="flex size-20 items-center justify-center overflow-hidden rounded-xl border border-border-subtle bg-surface-canvas">{merchant.logo_url ? <img src={merchant.logo_url} alt="Logo" className="max-h-full max-w-full object-contain" /> : <ImageUp className="size-6 text-ink-muted" />}</div>
          <div className="flex gap-2">
            <input ref={fileRef} type="file" accept="image/png,image/jpeg,image/webp" className="sr-only" onChange={(e) => { const f = e.target.files?.[0]; if (f) void upload(f); e.target.value = '' }} />
            <Button variant="outline" leftIcon={<ImageUp className="size-4" />} onClick={() => fileRef.current?.click()}>Upload</Button>
            {merchant.logo_url && <Button variant="ghost" className="text-rose-600" leftIcon={<Trash2 className="size-4" />} onClick={removeLogo}>Remove</Button>}
          </div>
        </div>
      </Section>
      <Section title="Brand colours" description="Used for headers, tables and totals on your slips." footer={<Button isLoading={busy} onClick={saveColors}>Save colours</Button>}>
        <div className="grid gap-4 sm:grid-cols-2">
          {[['Accent', primary, setPrimary], ['Header', secondary, setSecondary]].map(([label, value, setter]) => (
            <label key={label as string} className="flex items-center gap-3 rounded-xl border border-border-subtle p-3">
              <input type="color" value={value as string} onChange={(e) => (setter as (v: string) => void)(e.target.value)} className="size-10 cursor-pointer rounded-lg border-0 bg-transparent" />
              <span><span className="block text-sm font-medium">{label as string}</span><span className="font-mono text-xs text-ink-muted">{value as string}</span></span>
            </label>
          ))}
        </div>
        <Button variant="ghost" size="sm" onClick={() => { setPrimary('#377581'); setSecondary('#265363') }}>Reset to NetSlip teal</Button>
      </Section>
    </div>
  )
}

function PaymentSettings() {
  const merchant = useAuthStore((s) => s.session?.merchant) as Merchant
  const enabled = useEntitlement('payment_links')
  const refresh = useRefreshSession()
  const [form, setForm] = useState({ flutterwave_public_key: merchant.gateways.flutterwave_public_key ?? '', flutterwave_secret_key: '', flutterwave_secret_hash: '', nowpayments_api_key: '', nowpayments_ipn_secret: '' })
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  if (!enabled) return <FeatureGate icon={<CreditCard />} title="Get paid from the slip" description="Customers pay invoices by card, transfer, USSD or crypto straight into your account." />

  const save = async (clear?: 'flutterwave' | 'nowpayments') => {
    setBusy(true)
    setError(null)
    try {
      await apiPut('/settings/gateways', clear ? { clear: [clear] } : Object.fromEntries(Object.entries(form).filter(([, v]) => v.trim() !== '')))
      await refresh()
      setForm((f) => ({ ...f, flutterwave_secret_key: '', flutterwave_secret_hash: '', nowpayments_api_key: '', nowpayments_ipn_secret: '' }))
      toast(clear ? 'Gateway disconnected' : 'Payment keys saved')
    } catch (e) { setError(errorMessage(e)) } finally { setBusy(false) }
  }
  const webhookBase = `${window.location.origin}/api/v1/webhooks`

  return (
    <div className="space-y-6">
      {error && <Alert tone="danger">{error}</Alert>}
      <Section title="Flutterwave" description="Card, bank transfer and USSD. Money settles to your Flutterwave account." footer={<>{merchant.gateways.flutterwave && <Button variant="ghost" className="text-rose-600" onClick={() => save('flutterwave')}>Disconnect</Button>}<Button isLoading={busy} onClick={() => save()}>Save</Button></>}>
        <StatusBadge status={merchant.gateways.flutterwave ? 'ACTIVE' : 'DRAFT'} label={merchant.gateways.flutterwave ? 'Connected' : 'Not connected'} />
        <Input label="Public key" value={form.flutterwave_public_key} onChange={(e) => setForm({ ...form, flutterwave_public_key: e.target.value })} placeholder="FLWPUBK-…" className="font-mono text-xs" />
        <Input label="Secret key" type="password" autoComplete="off" value={form.flutterwave_secret_key} onChange={(e) => setForm({ ...form, flutterwave_secret_key: e.target.value })} placeholder={merchant.gateways.flutterwave ? '•••••••• saved' : 'FLWSECK-…'} className="font-mono text-xs" />
        <Input label="Webhook secret hash" type="password" autoComplete="off" value={form.flutterwave_secret_hash} onChange={(e) => setForm({ ...form, flutterwave_secret_hash: e.target.value })} helperText={`Set the same hash in Flutterwave → Settings → Webhooks, URL: ${webhookBase}/flutterwave/merchants/${merchant.id}`} />
      </Section>
      <Section title="Crypto (NOWPayments)" description="USDT, USDC, BTC, TON and more." footer={<>{merchant.gateways.nowpayments && <Button variant="ghost" className="text-rose-600" onClick={() => save('nowpayments')}>Disconnect</Button>}<Button isLoading={busy} onClick={() => save()}>Save</Button></>}>
        <StatusBadge status={merchant.gateways.nowpayments ? 'ACTIVE' : 'DRAFT'} label={merchant.gateways.nowpayments ? 'Connected' : 'Not connected'} />
        <Input label="API key" type="password" autoComplete="off" value={form.nowpayments_api_key} onChange={(e) => setForm({ ...form, nowpayments_api_key: e.target.value })} placeholder={merchant.gateways.nowpayments ? '•••••••• saved' : ''} />
        <Input label="IPN secret" type="password" autoComplete="off" value={form.nowpayments_ipn_secret} onChange={(e) => setForm({ ...form, nowpayments_ipn_secret: e.target.value })} helperText={`IPN callback URL: ${webhookBase}/nowpayments/merchants/${merchant.id}`} />
      </Section>
      <p className="text-xs text-ink-muted">Keys are encrypted at rest and never shown again after saving.</p>
    </div>
  )
}

function SecuritySettings() {
  const user = useAuthStore((s) => s.session?.user)
  const refresh = useRefreshSession()
  const [profile, setProfile] = useState({ name: user?.name ?? '', phone: user?.phone ?? '' })
  const [pw, setPw] = useState({ current_password: '', password: '', password_confirmation: '' })
  const [enroll, setEnroll] = useState<{ secret: string; qr_svg: string } | null>(null)
  const [code, setCode] = useState('')
  const [disablePw, setDisablePw] = useState('')
  const [showDisable, setShowDisable] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const run = async (fn: () => Promise<unknown>, message: string) => {
    setError(null)
    try { await fn(); await refresh(); toast(message); return true } catch (e) { setError(errorMessage(e)); return false }
  }

  return (
    <div className="space-y-6">
      {error && <Alert tone="danger">{error}</Alert>}
      <Section title="Your profile" footer={<Button onClick={() => run(() => apiPut('/account/profile', { name: profile.name, phone: profile.phone || null }), 'Profile saved')}>Save</Button>}>
        <div className="grid gap-4 sm:grid-cols-2">
          <Input label="Name" value={profile.name} onChange={(e) => setProfile({ ...profile, name: e.target.value })} />
          <Input label="Phone" value={profile.phone} onChange={(e) => setProfile({ ...profile, phone: e.target.value })} />
        </div>
        <Input label="Email" value={user?.email ?? ''} disabled />
      </Section>

      <Section title="Two-factor authentication" description="Protect your ledger with an authenticator app.">
        {user?.two_factor_enabled ? (
          <div className="flex flex-wrap items-center justify-between gap-3">
            <StatusBadge status="PAID" label="Enabled" showIcon />
            <Button variant="outline" onClick={() => setShowDisable(true)}>Turn off</Button>
          </div>
        ) : enroll ? (
          <div className="grid items-center gap-5 sm:grid-cols-[180px_1fr]">
            <div className="rounded-xl border border-border-subtle bg-white p-2 [&_svg]:h-auto [&_svg]:w-full" dangerouslySetInnerHTML={{ __html: enroll.qr_svg }} />
            <div className="space-y-3">
              <p className="text-sm text-ink-secondary">Scan with Google Authenticator, Authy or 1Password. Or enter this key:</p>
              <p className="break-all rounded-lg bg-surface-canvas px-3 py-2 font-mono text-xs">{enroll.secret}</p>
              <div className="flex gap-2">
                <Input aria-label="6-digit code" inputMode="numeric" maxLength={6} placeholder="123456" value={code} onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))} wrapperClassName="w-36" className="tabular tracking-widest" />
                <Button disabled={code.length !== 6} onClick={async () => { if (await run(() => apiPost('/account/two-factor/confirm', { code }), 'Two-factor enabled')) setEnroll(null) }}>Confirm</Button>
              </div>
            </div>
          </div>
        ) : (
          <Button leftIcon={<KeyRound className="size-4" />} onClick={async () => { try { setEnroll(await apiPost('/account/two-factor')) } catch (e) { setError(errorMessage(e)) } }}>Set up 2FA</Button>
        )}
      </Section>

      <Section title="Password" footer={<Button disabled={!pw.current_password || !pw.password} onClick={async () => { if (await run(() => apiPut('/account/password', pw), 'Password changed')) setPw({ current_password: '', password: '', password_confirmation: '' }) }}>Update password</Button>}>
        <Input label="Current password" type="password" autoComplete="current-password" value={pw.current_password} onChange={(e) => setPw({ ...pw, current_password: e.target.value })} />
        <div className="grid gap-4 sm:grid-cols-2">
          <Input label="New password" type="password" autoComplete="new-password" value={pw.password} onChange={(e) => setPw({ ...pw, password: e.target.value })} />
          <Input label="Confirm" type="password" autoComplete="new-password" value={pw.password_confirmation} onChange={(e) => setPw({ ...pw, password_confirmation: e.target.value })} />
        </div>
      </Section>

      <Modal isOpen={showDisable} onClose={() => setShowDisable(false)} title="Turn off two-factor?" size="sm"
        footer={<><Button variant="ghost" onClick={() => setShowDisable(false)}>Cancel</Button><Button variant="danger" disabled={!disablePw} onClick={async () => { if (await run(() => apiDelete('/account/two-factor', { password: disablePw }), 'Two-factor disabled')) { setShowDisable(false); setDisablePw('') } }}>Turn off</Button></>}>
        <Input label="Confirm with your password" type="password" value={disablePw} onChange={(e) => setDisablePw(e.target.value)} />
      </Modal>
    </div>
  )
}
