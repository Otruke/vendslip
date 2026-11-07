import { Bell, Bitcoin, Building2, CreditCard, ImageUp, Landmark, Lock, Mail, Palette, Pencil, Plus, ShieldCheck, Star, Trash2 } from 'lucide-react'
import { useQueryClient } from '@tanstack/react-query'
import { useEffect, useRef, useState, type ReactNode } from 'react'
import { Link, Navigate, useSearchParams } from 'react-router-dom'
import { Alert, Button, Card, InfoTip, Input, Modal, PageHeader, SegmentedTabs, Select, StatusBadge, Textarea, ToggleSwitch } from '@/components/ui'
import { apiDelete, apiPost, apiPut, useAlertSettings, useLocales, usePayoutAccounts, useRefreshSession, useReminderSettings } from '@/hooks/queries'
import { api, ApiError, errorMessage } from '@/lib/api'
import { cn } from '@/lib/cn'
import { bpsToPercentLabel, percentToBps } from '@/lib/money'
import { useAuthStore, useCan, useEntitlement } from '@/stores/auth'
import { toast } from '@/stores/ui'
import type { AlertSettings, Merchant, PayoutAccount, RegistrationType, ReminderSettings } from '@/types/api'
import { FeatureGate } from './FeatureGate'

type Tab = 'business' | 'payouts' | 'branding' | 'payments' | 'alerts'

function Section({ title, description, children, footer }: { title: string; description?: string; children: ReactNode; footer?: ReactNode }) {
  return (
    <Card padding="none">
      <div className="flex items-center gap-2 border-b border-border-subtle px-5 py-4"><h2 className="font-semibold">{title}</h2>{description && <InfoTip label={`About ${title}`}>{description}</InfoTip>}</div>
      <div className="space-y-4 px-5 py-5">{children}</div>
      {footer && <div className="flex justify-end gap-2 border-t border-border-subtle px-5 py-3">{footer}</div>}
    </Card>
  )
}

export default function Settings() {
  const [params, setParams] = useSearchParams()
  const requested = params.get('tab')
  // Personal security moved to "My profile"; keep old ?tab=security links working.
  if (requested === 'security') return <Navigate to="/dashboard/profile#two-factor" replace />
  const tab = (['business', 'payouts', 'branding', 'payments', 'alerts'].includes(requested ?? '') ? requested : 'business') as Tab
  return (
    <div className="space-y-6">
      <PageHeader title="Settings" subtitle="Your business details, brand, payment links and alerts." />
      <SegmentedTabs<Tab> value={tab} onChange={(v) => setParams({ tab: v })} options={[
        { value: 'business', label: 'Business', icon: <Building2 /> },
        { value: 'payouts', label: 'Bank & crypto', icon: <Landmark /> },
        { value: 'branding', label: 'Branding', icon: <Palette /> },
        { value: 'payments', label: 'Payment links', icon: <CreditCard /> },
        { value: 'alerts', label: 'Alerts', icon: <Bell /> },
      ]} />
      <p className="text-sm text-ink-secondary">Your own name, password and two-factor live in <Link to="/dashboard/profile" className="font-semibold text-vendslip-primary hover:underline">My profile</Link>.</p>
      <div className="max-w-3xl">
        {tab === 'business' && <BusinessSettings />}
        {tab === 'payouts' && <PayoutSettings />}
        {tab === 'branding' && <BrandingSettings />}
        {tab === 'payments' && <PaymentSettings />}
        {tab === 'alerts' && <div className="space-y-6"><AlertSettingsPanel /><ReminderPanel /></div>}
      </div>
    </div>
  )
}

const REGISTRATION: { value: RegistrationType; label: string; number: string; hint: string }[] = [
  { value: 'RC', label: 'Registered company', number: 'RC number', hint: 'Limited company (RC)' },
  { value: 'BN', label: 'Registered business name', number: 'BN number', hint: 'Business name (BN)' },
  { value: 'UNREGISTERED', label: 'Not registered yet', number: '', hint: 'Trading informally' },
]

export function BusinessSettings({ onSaved, compact = false }: { onSaved?: () => void; compact?: boolean }) {
  const merchant = useAuthStore((s) => s.session?.merchant) as Merchant
  const canManage = useCan('settings.manage')
  const refresh = useRefreshSession()
  const locales = useLocales()
  const [form, setForm] = useState(() => ({
    name: merchant.name, email: merchant.email ?? '', phone: merchant.phone ?? '', address: merchant.address ?? '',
    registration_type: (merchant.registration_type ?? (merchant.rc_number ? 'RC' : '')) as RegistrationType | '',
    rc_number: merchant.rc_number ?? '', tax_id: merchant.tax_id ?? '',
    country_code: merchant.country_code ?? '', state: merchant.state ?? '', city: merchant.city ?? '', show_in_activity_feed: merchant.show_in_activity_feed ?? true,
    currency: merchant.currency, vat: bpsToPercentLabel(merchant.vat_rate_bps).replace('%', ''), vat_inclusive: merchant.vat_inclusive,
    invoice_due_days: String(merchant.invoice_due_days), receipt_footer: merchant.receipt_footer ?? '',
  }))
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const set = (k: keyof typeof form, v: string | boolean) => setForm((f) => ({ ...f, [k]: v }))
  const registration = REGISTRATION.find((r) => r.value === form.registration_type)
  const country = locales.data?.countries.find((c) => c.code === form.country_code)
  const presetBps = country?.vat != null ? Math.round(country.vat * 100) : null

  /** Picking a country suggests its currency and standard VAT rate; both stay editable. */
  const pickCountry = (code: string) => {
    const c = locales.data?.countries.find((x) => x.code === code)
    setForm((f) => ({
      ...f,
      country_code: code,
      currency: c?.currency && locales.data?.currencies.some((x) => x.code === c.currency) ? c.currency : f.currency,
      vat: c?.vat != null ? String(c.vat) : f.vat,
    }))
  }

  const save = async () => {
    const bps = percentToBps(form.vat)
    if (bps === null) { setError('VAT must be a percentage like 7.5'); return }
    setBusy(true)
    setError(null)
    try {
      await apiPut('/settings/business', {
        name: form.name, email: form.email || null, phone: form.phone || null, address: form.address || null,
        registration_type: form.registration_type || null,
        rc_number: form.registration_type === 'UNREGISTERED' ? null : form.rc_number.trim() || null, tax_id: form.tax_id || null,
        country_code: form.country_code || null, state: form.state.trim() || null, city: form.city.trim() || null, show_in_activity_feed: form.show_in_activity_feed,
        currency: form.currency, vat_rate_bps: bps, vat_inclusive: form.vat_inclusive,
        invoice_due_days: Number(form.invoice_due_days) || 0, receipt_footer: form.receipt_footer || null,
      })
      await refresh()
      toast('Business details saved')
      onSaved?.()
    } catch (e) { setError(errorMessage(e)) } finally { setBusy(false) }
  }

  const currencyOptions = locales.data
    ? locales.data.currencies.map((c) => ({ value: c.code, label: `${c.code} · ${c.name}` }))
    : [{ value: form.currency, label: form.currency }]

  return (
    <Section title="Business profile" description="Printed on every slip." footer={canManage && <Button isLoading={busy} onClick={save}>{compact ? 'Save & continue' : 'Save changes'}</Button>}>
      {error && <Alert tone="danger">{error}</Alert>}
      {!compact && <LogoRow />}
      <div className="grid gap-4 sm:grid-cols-2">
        <Input label="Business name" value={form.name} onChange={(e) => set('name', e.target.value)} disabled={!canManage} />
        <Input label="Phone" value={form.phone} onChange={(e) => set('phone', e.target.value)} disabled={!canManage} />
        <Input label="Email" type="email" value={form.email} onChange={(e) => set('email', e.target.value)} disabled={!canManage} />
        <Input label="Street address" value={form.address} onChange={(e) => set('address', e.target.value)} disabled={!canManage} />
      </div>
      <div className="grid gap-4 sm:grid-cols-3">
        <Select label="Country" value={form.country_code} onChange={(e) => pickCountry(e.target.value)} disabled={!canManage || !locales.data} placeholder="Choose a country"
          options={(locales.data?.countries ?? []).map((c) => ({ value: c.code, label: c.name }))} />
        <Input label="State / region" value={form.state} onChange={(e) => set('state', e.target.value)} disabled={!canManage} maxLength={80} placeholder="Lagos" />
        <Input label="City" value={form.city} onChange={(e) => set('city', e.target.value)} disabled={!canManage} maxLength={80} placeholder="Ikeja" />
      </div>
      {!compact && (
        <div className="grid gap-4 sm:grid-cols-3">
          <Select label="Registration" value={form.registration_type} onChange={(e) => set('registration_type', e.target.value)} disabled={!canManage} placeholder="Choose one"
            options={REGISTRATION.map((r) => ({ value: r.value, label: r.label }))} />
          {registration && registration.value !== 'UNREGISTERED'
            ? <Input label={registration.number} value={form.rc_number} onChange={(e) => set('rc_number', e.target.value.toUpperCase())} disabled={!canManage} maxLength={64} placeholder={registration.value === 'RC' ? 'RC 1234567' : 'BN 3456789'} />
            : <div className="hidden sm:block" />}
          <Input label="Tax ID (TIN)" value={form.tax_id} onChange={(e) => set('tax_id', e.target.value)} disabled={!canManage} />
        </div>
      )}
      <div className="grid gap-4 sm:grid-cols-3">
        <Select label="Currency" value={form.currency} onChange={(e) => set('currency', e.target.value)} disabled={!canManage} options={currencyOptions} info="New slips use this currency. Change it for one slip with /currency = USD." />
        <Input label="VAT %" inputMode="decimal" value={form.vat} onChange={(e) => set('vat', e.target.value)} disabled={!canManage}
          info={presetBps !== null && country ? `The standard rate in ${country.name} is ${country.vat}%. Use 0 if you don’t charge VAT.` : 'Use 0 if you don’t charge VAT.'} />
        <Input label="Invoice due (days)" type="number" min={0} value={form.invoice_due_days} onChange={(e) => set('invoice_due_days', e.target.value)} disabled={!canManage} />
      </div>
      <ToggleSwitch checked={form.vat_inclusive} onChange={(v) => set('vat_inclusive', v)} disabled={!canManage} label="Prices already include VAT" info="VAT is worked out from inside your item prices instead of being added on top." />
      {!compact && <p className="text-sm text-ink-secondary">Bank accounts and crypto wallets for your slips are in <Link to="/dashboard/settings?tab=payouts" className="font-semibold text-vendslip-primary hover:underline">Bank &amp; crypto</Link>.</p>}
      {!compact && <ToggleSwitch checked={form.show_in_activity_feed} onChange={(v) => set('show_in_activity_feed', v)} disabled={!canManage} label="Appear in VendSlip’s live activity" info="Anonymous notes on the VendSlip website, like “A business in Ikeja, Lagos just issued a receipt”. Never your name, customers or amounts." />}
      {!compact && <Textarea label="Slip footer" rows={2} maxLength={280} value={form.receipt_footer} onChange={(e) => set('receipt_footer', e.target.value)} disabled={!canManage} placeholder="Thank you for your patronage!" />}
    </Section>
  )
}

/** The logo is visible on every plan: upload on Pro and up, an upgrade link otherwise. */
function LogoRow() {
  const merchant = useAuthStore((s) => s.session?.merchant) as Merchant
  const enabled = useEntitlement('custom_branding')
  const canManage = useCan('settings.manage')
  const refresh = useRefreshSession()
  const fileRef = useRef<HTMLInputElement>(null)
  const [busy, setBusy] = useState(false)
  // The chosen file shows straight away, before the upload finishes.
  const [preview, setPreview] = useState<string | null>(null)
  const [brokenUrl, setBrokenUrl] = useState<string | null>(null)
  const src = preview ?? merchant.logo_url
  const broken = !preview && src !== null && brokenUrl === src

  useEffect(() => {
    return () => { if (preview) URL.revokeObjectURL(preview) }
  }, [preview])

  const upload = async (file: File) => {
    setPreview(URL.createObjectURL(file))
    const body = new FormData()
    body.append('logo', file)
    setBusy(true)
    try {
      await api.post('/settings/logo', body)
      await refresh()
      toast('Logo uploaded')
    } catch (e) {
      toast(errorMessage(e), 'error')
    } finally {
      setPreview(null)
      setBusy(false)
    }
  }

  return (
    <div className="flex flex-wrap items-center gap-4 rounded-xl border border-border-subtle p-3">
      <div className="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border-subtle bg-surface-canvas">
        {src && !broken
          ? <img key={src} src={src} alt="Your logo" className={cn('max-h-full max-w-full object-contain transition-opacity', busy && 'opacity-60')} onError={() => setBrokenUrl(src)} />
          : <ImageUp className="size-5 text-ink-muted" aria-hidden />}
      </div>
      <div className="min-w-0 flex-1">
        <p className="flex items-center gap-1.5 text-sm font-semibold text-ink-primary">
          Logo
          <InfoTip label="About the logo">PNG, JPG or WebP up to 1 MB. Shown on your slips, invoices and the invoice pay page.</InfoTip>
        </p>
        {!enabled && <p className="text-xs text-ink-secondary">Available on Pro and Business Plus.</p>}
        {broken && <p className="text-xs text-tone-open">Saved, but it can’t be loaded right now. Try uploading it again.</p>}
        {busy && <p className="text-xs text-ink-secondary">Uploading…</p>}
      </div>
      {enabled && canManage && (
        <>
          <input ref={fileRef} type="file" accept="image/png,image/jpeg,image/webp" className="sr-only" onChange={(e) => { const f = e.target.files?.[0]; if (f) void upload(f); e.target.value = '' }} />
          <Button size="sm" variant="outline" isLoading={busy} leftIcon={<ImageUp className="size-4" />} onClick={() => fileRef.current?.click()}>{merchant.logo_url ? 'Replace' : 'Upload'}</Button>
        </>
      )}
      {!enabled && <Button size="sm" variant="outline" to="/dashboard/billing" leftIcon={<Lock className="size-4" />}>Upgrade</Button>}
    </div>
  )
}

export function BrandingSettings() {
  const merchant = useAuthStore((s) => s.session?.merchant) as Merchant
  const enabled = useEntitlement('custom_branding')
  const refresh = useRefreshSession()
  const fileRef = useRef<HTMLInputElement>(null)
  const [preview, setPreview] = useState<string | null>(null)
  useEffect(() => {
    return () => { if (preview) URL.revokeObjectURL(preview) }
  }, [preview])
  const [primary, setPrimary] = useState(merchant.primary_color)
  const [secondary, setSecondary] = useState(merchant.secondary_color)
  const [busy, setBusy] = useState(false)

  const logoSrc = preview ?? merchant.logo_url
  if (!enabled) return <FeatureGate icon={<Palette />} plan="Pro" title="Make every slip yours" description="Upload your logo and set brand colours on Pro and Business Plus." />

  const saveColors = async () => {
    setBusy(true)
    try { await apiPut('/settings/branding', { primary_color: primary, secondary_color: secondary }); await refresh(); toast('Brand colours saved') } catch (e) { toast(errorMessage(e), 'error') } finally { setBusy(false) }
  }
  const upload = async (file: File) => {
    setPreview(URL.createObjectURL(file)) // show it at once, before the upload finishes
    const body = new FormData()
    body.append('logo', file)
    try { await api.post('/settings/logo', body); await refresh(); toast('Logo uploaded') } catch (e) { toast(errorMessage(e), 'error') } finally { setPreview(null) }
  }
  const removeLogo = async () => {
    try { await apiDelete('/settings/logo'); await refresh() } catch (e) { toast(errorMessage(e), 'error') }
  }

  return (
    <div className="space-y-6">
      <Section title="Logo" description="PNG, JPG or WebP up to 1 MB. Shown on slips and invoices.">
        <div className="flex items-center gap-4">
          <div className="flex size-20 items-center justify-center overflow-hidden rounded-xl border border-border-subtle bg-surface-canvas">{logoSrc ? <img key={logoSrc} src={logoSrc} alt="Logo" className="max-h-full max-w-full object-contain" /> : <ImageUp className="size-6 text-ink-muted" />}</div>
          <div className="flex gap-2">
            <input ref={fileRef} type="file" accept="image/png,image/jpeg,image/webp" className="sr-only" onChange={(e) => { const f = e.target.files?.[0]; if (f) void upload(f); e.target.value = '' }} />
            <Button variant="outline" leftIcon={<ImageUp className="size-4" />} onClick={() => fileRef.current?.click()}>Upload</Button>
            {merchant.logo_url && <Button variant="ghost" className="text-tone-open" leftIcon={<Trash2 className="size-4" />} onClick={removeLogo}>Remove</Button>}
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
        <Button variant="ghost" size="sm" onClick={() => { setPrimary('#377581'); setSecondary('#265363') }}>Reset to VendSlip teal</Button>
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
      <Section title="Flutterwave" description="Card, bank transfer and USSD. Money settles to your Flutterwave account." footer={<>{merchant.gateways.flutterwave && <Button variant="ghost" className="text-tone-open" onClick={() => save('flutterwave')}>Disconnect</Button>}<Button isLoading={busy} onClick={() => save()}>Save</Button></>}>
        <StatusBadge status={merchant.gateways.flutterwave ? 'ACTIVE' : 'DRAFT'} label={merchant.gateways.flutterwave ? 'Connected' : 'Not connected'} />
        <Input label="Public key" value={form.flutterwave_public_key} onChange={(e) => setForm({ ...form, flutterwave_public_key: e.target.value })} placeholder="FLWPUBK-…" className="font-mono text-xs" />
        <Input label="Secret key" type="password" autoComplete="off" value={form.flutterwave_secret_key} onChange={(e) => setForm({ ...form, flutterwave_secret_key: e.target.value })} placeholder={merchant.gateways.flutterwave ? '•••••••• saved' : 'FLWSECK-…'} className="font-mono text-xs" />
        <Input label="Webhook secret hash" type="password" autoComplete="off" value={form.flutterwave_secret_hash} onChange={(e) => setForm({ ...form, flutterwave_secret_hash: e.target.value })} helperText={<>Set the same hash in Flutterwave → Settings → Webhooks, URL: <span className="break-all font-mono">{`${webhookBase}/flutterwave/merchants/${merchant.id}`}</span></>} />
      </Section>
      <Section title="Crypto (NOWPayments)" description="USDT, USDC, BTC, TON and more." footer={<>{merchant.gateways.nowpayments && <Button variant="ghost" className="text-tone-open" onClick={() => save('nowpayments')}>Disconnect</Button>}<Button isLoading={busy} onClick={() => save()}>Save</Button></>}>
        <StatusBadge status={merchant.gateways.nowpayments ? 'ACTIVE' : 'DRAFT'} label={merchant.gateways.nowpayments ? 'Connected' : 'Not connected'} />
        <Input label="API key" type="password" autoComplete="off" value={form.nowpayments_api_key} onChange={(e) => setForm({ ...form, nowpayments_api_key: e.target.value })} placeholder={merchant.gateways.nowpayments ? '•••••••• saved' : ''} />
        <Input label="IPN secret" type="password" autoComplete="off" value={form.nowpayments_ipn_secret} onChange={(e) => setForm({ ...form, nowpayments_ipn_secret: e.target.value })} helperText={<>IPN callback URL: <span className="break-all font-mono">{`${webhookBase}/nowpayments/merchants/${merchant.id}`}</span></>} />
      </Section>
      <p className="flex items-center gap-1.5 text-xs text-ink-muted">Keys are stored encrypted <InfoTip label="About key storage">Keys are encrypted at rest and never shown again after saving. Paste a new key to replace one.</InfoTip></p>
    </div>
  )
}

type AlertRow = AlertSettings['events'][number]

/** Who hears about what. Security alerts always reach every owner by email and can't be turned off. */
function AlertSettingsPanel() {
  const { data, isLoading, error: loadError } = useAlertSettings()
  const canManage = useCan('settings.manage')
  const inSupport = useAuthStore((s) => !!s.session?.support)
  const queryClient = useQueryClient()
  const [rows, setRows] = useState<AlertRow[] | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const current = rows ?? data?.events ?? []
  const editable = canManage && !inSupport

  const toggleRole = (event: string, role: string) => setRows(current.map((r) => r.event !== event ? r : {
    ...r, roles: r.roles.includes(role) ? r.roles.filter((x) => x !== role) : [...r.roles, role],
  }))
  const toggleEmail = (event: string) => setRows(current.map((r) => r.event === event ? { ...r, email: !r.email } : r))

  const save = async () => {
    setBusy(true)
    setError(null)
    try {
      const events = Object.fromEntries(current.map((r) => [r.event, { roles: r.roles, email: r.email }]))
      const fresh = await apiPut<AlertSettings>('/settings/alerts', { events })
      queryClient.setQueryData(['alert-settings'], fresh)
      setRows(null)
      toast('Alert settings saved')
    } catch (e) { setError(errorMessage(e)) } finally { setBusy(false) }
  }

  if (isLoading) return <Card padding="lg"><p className="text-sm text-ink-secondary">Loading alert settings…</p></Card>
  if (loadError || !data) return <Alert tone="danger">{errorMessage(loadError)}</Alert>

  const groups = [...new Set(current.map((r) => r.group))]
  return (
    <Section
      title="Alerts"
      description="Choose which roles see each alert in the bell, and whether it’s also emailed. Security alerts (shield) are always emailed; locked roles always get them, and you can add others."
      footer={editable && <><Button variant="ghost" disabled={!rows} onClick={() => setRows(null)}>Reset</Button><Button isLoading={busy} disabled={!rows} onClick={save}>Save alerts</Button></>}
    >
      {error && <Alert tone="danger">{error}</Alert>}
      {inSupport && <Alert tone="info">Alert settings can only be changed by the business, not during a support session.</Alert>}
      {!canManage && !inSupport && <Alert tone="info">Only an Owner can change who gets alerts.</Alert>}
      {groups.map((group) => (
        <div key={group}>
          <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-ink-muted">{group}</p>
          <ul className="divide-y divide-border-subtle rounded-xl border border-border-subtle">
            {current.filter((r) => r.group === group).map((r) => (
              <li key={r.event} className="space-y-3 p-4">
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <p className="text-sm font-semibold text-ink-primary">
                      {r.mandatory && <ShieldCheck className="mr-1.5 inline size-4 align-[-3px] text-vendslip-primary" aria-label="Security alert" />}{r.label}{' '}
                      <InfoTip className="ml-0.5 align-[-2px]" label={`About ${r.label}`}>{r.description}{r.mandatory ? ' Security alert: always emailed, and locked roles always get it.' : ''}</InfoTip>
                    </p>
                  </div>
                  <button
                    type="button"
                    disabled={!editable || r.mandatory}
                    onClick={() => toggleEmail(r.event)}
                    aria-pressed={r.email}
                    title={r.mandatory ? 'Security alerts are always emailed' : undefined}
                    className={cn('inline-flex shrink-0 items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-medium',
                      r.email ? 'border-vendslip-primary bg-vendslip-tint text-vendslip-deep' : 'border-border-subtle text-ink-muted',
                      (!editable || r.mandatory) ? 'cursor-not-allowed opacity-80' : 'hover:border-vendslip-primary')}
                  >
                    <Mail className="size-3.5" />{r.email ? 'Email on' : 'Email off'}
                  </button>
                </div>
                <div className="flex flex-wrap gap-2">
                  {data.roles.map((role) => {
                    const on = r.roles.includes(role.value)
                    const locked = r.locked_roles.includes(role.value)
                    return (
                      <button
                        key={role.value}
                        type="button"
                        disabled={!editable || locked}
                        onClick={() => toggleRole(r.event, role.value)}
                        aria-pressed={on}
                        className={cn('inline-flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-medium',
                          on ? 'border-vendslip-primary bg-vendslip-primary text-white' : 'border-border-subtle bg-white text-ink-secondary',
                          (!editable || locked) ? 'cursor-not-allowed' : on ? 'hover:bg-vendslip-primary-hover' : 'hover:border-vendslip-primary')}
                      >
                        {locked && <Lock className="size-3" aria-hidden />}{role.label}
                      </button>
                    )
                  })}
                </div>
              </li>
            ))}
          </ul>
        </div>
      ))}
    </Section>
  )
}

type PayoutForm = {
  kind: 'BANK' | 'CRYPTO'; label: string; bank_name: string; account_number: string; account_name: string; currency: string
  asset: string; network: string; wallet_address: string; show_on_slips: boolean; is_default: boolean; current_password: string
}

function payoutForm(a: PayoutAccount | null, kind: 'BANK' | 'CRYPTO', merchantName: string): PayoutForm {
  return {
    kind: a?.kind ?? kind, label: a?.label ?? '', bank_name: a?.bank_name ?? '', account_number: a?.account_number ?? '', account_name: a?.account_name ?? (a ? '' : merchantName),
    currency: a?.currency ?? '', asset: a?.asset ?? 'USDT', network: a?.network ?? 'TRC20', wallet_address: a?.wallet_address ?? '',
    show_on_slips: a?.show_on_slips ?? true, is_default: a?.is_default ?? false, current_password: '',
  }
}

/** Several bank accounts and crypto wallets on slips. Every change needs a password and alerts Owners and Managers. */
function PayoutSettings() {
  const { data, isLoading, refetch } = usePayoutAccounts()
  const merchant = useAuthStore((s) => s.session?.merchant) as Merchant
  const canManage = useCan('settings.manage')
  const inSupport = useAuthStore((s) => !!s.session?.support)
  const refresh = useRefreshSession()
  const [editing, setEditing] = useState<{ account: PayoutAccount | null; kind: 'BANK' | 'CRYPTO' } | null>(null)
  const [removing, setRemoving] = useState<PayoutAccount | null>(null)
  const editable = canManage && !inSupport
  const accounts = data?.data ?? []
  const full = data ? accounts.length >= data.max : false

  const done = async () => { setEditing(null); setRemoving(null); await Promise.all([refetch(), refresh()]) }

  return (
    <div className="space-y-6">
      <Section
        title="Bank & crypto"
        description="Where customers pay you, shown on slips and the invoice pay page. For your safety, every change asks for your password and is emailed to the business’s Owners and Managers."
        footer={editable && <>
          <Button variant="outline" disabled={full} leftIcon={<Bitcoin className="size-4" />} onClick={() => setEditing({ account: null, kind: 'CRYPTO' })}>Add wallet</Button>
          <Button disabled={full} leftIcon={<Plus className="size-4" />} onClick={() => setEditing({ account: null, kind: 'BANK' })}>Add bank account</Button>
        </>}
      >
        {inSupport && <Alert tone="warning">Payout accounts can&rsquo;t be changed during a support session.</Alert>}
        {isLoading ? <p className="text-sm text-ink-secondary">Loading…</p> : accounts.length === 0 ? (
          <p className="rounded-xl border border-dashed border-border-subtle px-4 py-8 text-center text-sm text-ink-secondary">No payout accounts yet. Add a bank account so customers know where to pay.</p>
        ) : (
          <ul className="divide-y divide-border-subtle rounded-xl border border-border-subtle">
            {accounts.map((a) => (
              <li key={a.id} className="flex flex-wrap items-start gap-3 p-4">
                <span className="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-vendslip-tint text-vendslip-deep">{a.kind === 'BANK' ? <Landmark className="size-4" /> : <Bitcoin className="size-4" />}</span>
                <div className="min-w-0 flex-1">
                  <p className="flex flex-wrap items-center gap-2 text-sm font-semibold text-ink-primary">
                    {a.kind === 'BANK' ? a.bank_name : `${a.asset} on ${a.network}`}
                    {a.label && <span className="font-normal text-ink-secondary">· {a.label}</span>}
                    {a.is_default && <span className="inline-flex items-center gap-1 rounded-full bg-vendslip-tint px-2 py-0.5 text-[11px] font-medium text-vendslip-deep"><Star className="size-3" />Default</span>}
                    {!a.show_on_slips && <span className="rounded-full bg-surface-subtle px-2 py-0.5 text-[11px] font-medium text-ink-secondary">Hidden on slips</span>}
                  </p>
                  {a.kind === 'BANK'
                    ? <p className="tabular text-sm text-ink-secondary">{a.account_number} · {a.account_name}{a.currency ? ` · ${a.currency}` : ''}</p>
                    : <p className="break-all font-mono text-xs text-ink-secondary">{a.wallet_address}</p>}
                </div>
                {editable && (
                  <div className="flex shrink-0 gap-1">
                    <Button size="xs" variant="ghost" leftIcon={<Pencil className="size-3.5" />} onClick={() => setEditing({ account: a, kind: a.kind })}>Edit</Button>
                    <Button size="xs" variant="ghost" className="text-tone-open" leftIcon={<Trash2 className="size-3.5" />} onClick={() => setRemoving(a)}>Remove</Button>
                  </div>
                )}
              </li>
            ))}
          </ul>
        )}
        {full && <p className="text-xs text-ink-muted">You&rsquo;ve reached {data?.max} accounts. Remove one to add another.</p>}
      </Section>
      {editing && data && <PayoutModal account={editing.account} kind={editing.kind} crypto={data.crypto} merchantName={merchant.name} onClose={() => setEditing(null)} onSaved={done} />}
      {removing && <RemovePayoutModal account={removing} onClose={() => setRemoving(null)} onRemoved={done} />}
    </div>
  )
}

function PayoutModal({ account, kind, crypto, merchantName, onClose, onSaved }: { account: PayoutAccount | null; kind: 'BANK' | 'CRYPTO'; crypto: Record<string, string[]>; merchantName: string; onClose: () => void; onSaved: () => Promise<void> }) {
  const locales = useLocales()
  const [form, setForm] = useState(() => payoutForm(account, kind, merchantName))
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const set = <K extends keyof PayoutForm>(k: K, v: PayoutForm[K]) => setForm((f) => ({ ...f, [k]: v }))
  const bank = form.kind === 'BANK'
  const networks = crypto[form.asset] ?? []

  const save = async () => {
    setBusy(true)
    setError(null)
    setErrors({})
    const body = bank
      ? { kind: 'BANK', label: form.label || null, bank_name: form.bank_name, account_number: form.account_number, account_name: form.account_name, currency: form.currency || null, show_on_slips: form.show_on_slips, is_default: form.is_default, current_password: form.current_password }
      : { kind: 'CRYPTO', label: form.label || null, asset: form.asset, network: form.network, wallet_address: form.wallet_address.trim(), show_on_slips: form.show_on_slips, is_default: form.is_default, current_password: form.current_password }
    try {
      if (account) await apiPut(`/settings/payout-accounts/${account.id}`, body)
      else await apiPost('/settings/payout-accounts', body)
      toast(account ? 'Payout account updated' : 'Payout account added')
      await onSaved()
    } catch (e) {
      if (e instanceof ApiError && Object.keys(e.fieldErrors).length > 0) setErrors(Object.fromEntries(Object.entries(e.fieldErrors).map(([k, v]) => [k, v[0] ?? ''])))
      else setError(errorMessage(e))
    } finally { setBusy(false) }
  }

  return (
    <Modal isOpen onClose={onClose} title={`${account ? 'Edit' : 'Add'} ${bank ? 'bank account' : 'crypto wallet'}`} description="Double-check every character: customers will send money here."
      footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button isLoading={busy} disabled={form.current_password === ''} onClick={save}>{account ? 'Save changes' : 'Add account'}</Button></>}>
      <div className="space-y-4">
        {error && <Alert tone="danger">{error}</Alert>}
        {bank ? (
          <>
            <div className="grid gap-4 sm:grid-cols-2">
              <Input label="Bank" value={form.bank_name} onChange={(e) => set('bank_name', e.target.value)} error={errors.bank_name} placeholder="Providus Bank" maxLength={120} />
              <Input label="Account number" value={form.account_number} onChange={(e) => set('account_number', e.target.value)} error={errors.account_number} inputMode="numeric" className="tabular" maxLength={34} />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <Input label="Account name" value={form.account_name} onChange={(e) => set('account_name', e.target.value)} error={errors.account_name} maxLength={160} />
              <Select label="Account currency (optional)" value={form.currency} onChange={(e) => set('currency', e.target.value)} placeholder="Same as slips" error={errors.currency}
                options={(locales.data?.currencies ?? []).map((c) => ({ value: c.code, label: `${c.code} · ${c.name}` }))} />
            </div>
          </>
        ) : (
          <>
            <div className="grid gap-4 sm:grid-cols-2">
              <Select label="Coin" value={form.asset} disabled={!!account} onChange={(e) => { const asset = e.target.value; setForm((f) => ({ ...f, asset, network: crypto[asset]?.[0] ?? '' })) }} options={Object.keys(crypto).map((c) => ({ value: c, label: c }))} error={errors.asset} />
              <Select label="Network" value={form.network} onChange={(e) => set('network', e.target.value)} options={networks.map((n) => ({ value: n, label: n }))} error={errors.network} />
            </div>
            <Input label="Wallet address" value={form.wallet_address} onChange={(e) => set('wallet_address', e.target.value)} error={errors.wallet_address} className="font-mono text-xs" autoComplete="off" spellCheck={false} maxLength={128} info={`Only ${form.asset} sent on ${form.network} arrives safely. Coins sent on another network can be lost.`} />
          </>
        )}
        <Input label="Label (optional)" value={form.label} onChange={(e) => set('label', e.target.value)} error={errors.label} maxLength={60} placeholder={bank ? 'e.g. Dollar account' : 'e.g. Main wallet'} />
        <ToggleSwitch checked={form.show_on_slips} onChange={(v) => set('show_on_slips', v)} label="Show on slips" info="Printed on receipts and invoices, and on the invoice pay page." />
        <ToggleSwitch checked={form.is_default} disabled={account?.is_default} onChange={(v) => set('is_default', v)} label={`Default ${bank ? 'bank account' : 'wallet'}`} info="Listed first on slips." />
        <Input label="Your password" type="password" autoComplete="current-password" value={form.current_password} onChange={(e) => set('current_password', e.target.value)} error={errors.current_password} info="Needed to change where customers pay you, so nobody can quietly redirect your money." />
      </div>
    </Modal>
  )
}

function RemovePayoutModal({ account, onClose, onRemoved }: { account: PayoutAccount; onClose: () => void; onRemoved: () => Promise<void> }) {
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const remove = async () => {
    setBusy(true)
    setError(null)
    try {
      await api.delete(`/settings/payout-accounts/${account.id}`, { data: { current_password: password } })
      toast('Payout account removed')
      await onRemoved()
    } catch (e) {
      setError(e instanceof ApiError ? e.firstFieldError('current_password') ?? e.message : errorMessage(e))
    } finally { setBusy(false) }
  }
  return (
    <Modal isOpen onClose={onClose} title="Remove this payout account?" description={account.kind === 'BANK' ? `${account.bank_name} · ${account.account_number}` : `${account.asset} on ${account.network}`}
      footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button variant="danger" isLoading={busy} disabled={password === ''} onClick={remove}>Remove</Button></>}>
      <div className="space-y-4">
        {error && <Alert tone="danger">{error}</Alert>}
        <p className="text-sm text-ink-secondary">New slips stop showing it. Slips already issued keep the details they were printed with.</p>
        <Input label="Your password" type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} />
      </div>
    </Modal>
  )
}

/** Due-date reminder emails to customers (Business Plus). */
function ReminderPanel() {
  const { data } = useReminderSettings()
  const canManage = useCan('settings.manage')
  const queryClient = useQueryClient()
  const [form, setForm] = useState<ReminderSettings['settings'] | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  if (!data) return null
  const current = form ?? data.settings
  const editable = canManage && data.available
  const set = <K extends keyof ReminderSettings['settings']>(k: K, v: ReminderSettings['settings'][K]) => setForm({ ...current, [k]: v })

  const save = async () => {
    setBusy(true)
    setError(null)
    try {
      const fresh = await apiPut<ReminderSettings>('/settings/reminders', current)
      queryClient.setQueryData(['reminder-settings'], fresh)
      setForm(null)
      toast('Reminder settings saved')
    } catch (e) { setError(errorMessage(e)) } finally { setBusy(false) }
  }

  return (
    <Section
      title="Invoice reminders to customers"
      description="Polite emails to customers who have an email on the invoice, with a link to view and pay. Reminders stop as soon as an invoice is paid or voided, and each one is sent once, around 9:00."
      footer={editable && <Button isLoading={busy} disabled={!form} onClick={save}>Save reminders</Button>}
    >
      {error && <Alert tone="danger">{error}</Alert>}
      {!data.available && (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border-subtle p-3">
          <p className="text-sm text-ink-secondary">Automatic customer reminders come with Business Plus. Your team still gets overdue alerts on every plan.</p>
          <Button size="sm" variant="outline" to="/dashboard/billing" leftIcon={<Lock className="size-4" />}>Upgrade</Button>
        </div>
      )}
      <ToggleSwitch checked={current.customer_emails} disabled={!editable} onChange={(v) => set('customer_emails', v)} label="Email customers about due invoices" info="Replies go to your business email." />
      <div className="grid gap-4 sm:grid-cols-3">
        <Select label="Before the due date" value={String(current.days_before)} disabled={!editable || !current.customer_emails} onChange={(e) => set('days_before', Number(e.target.value))}
          options={[{ value: '0', label: 'Don’t remind' }, { value: '1', label: '1 day before' }, { value: '2', label: '2 days before' }, { value: '3', label: '3 days before' }, { value: '5', label: '5 days before' }, { value: '7', label: '7 days before' }]} />
        <Select label="When overdue" value={String(current.overdue_every_days)} disabled={!editable || !current.customer_emails} onChange={(e) => set('overdue_every_days', Number(e.target.value))}
          options={[{ value: '0', label: 'Don’t remind' }, { value: '3', label: 'Every 3 days' }, { value: '7', label: 'Every week' }, { value: '14', label: 'Every 2 weeks' }]} />
        <Select label="Overdue reminders, at most" value={String(current.overdue_max)} disabled={!editable || !current.customer_emails || current.overdue_every_days === 0} onChange={(e) => set('overdue_max', Number(e.target.value))}
          options={[1, 2, 3, 4, 6].map((n) => ({ value: String(n), label: `${n} ${n === 1 ? 'time' : 'times'}` }))} />
      </div>
      <ToggleSwitch checked={current.on_due_date} disabled={!editable || !current.customer_emails} onChange={(v) => set('on_due_date', v)} label="Remind on the due date" />
    </Section>
  )
}
