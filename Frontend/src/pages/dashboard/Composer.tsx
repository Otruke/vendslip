import { CircleCheck, Coins, Eraser, FileText, Keyboard, LayoutList, Plus, Receipt, Sparkles, Terminal, Trash2, TriangleAlert } from 'lucide-react'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { PaperSlipPreview } from '@/components/slip/PaperSlipPreview'
import { ShareSheet } from '@/components/slip/ShareSheet'
import { draftToSlipView } from '@/components/slip/toSlipView'
import {
  Alert, Button, Card, Input, Modal, Money, PageHeader, SegmentedTabs, Select, SplitWorkspace, StatusBadge, Textarea, ToggleSwitch,
} from '@/components/ui'
import { apiPost, useInventory, useInvalidate, useRefreshSession, useTemplates } from '@/hooks/queries'
import { ApiError, errorMessage } from '@/lib/api'
import { draftToRequest, missingFields, PAID_FULL } from '@/lib/draft'
import { formatMoney, minorToInput, parseToMinor, percentToBps } from '@/lib/money'
import { SHORTCUTS } from '@/lib/slash'
import { useAuthStore, useCan, useEntitlement } from '@/stores/auth'
import { useComposerStore } from '@/stores/composer'
import type { Doc } from '@/types/api'

/** Money input: free text while focused, committed to minor units on blur. */
function MoneyField({ label, value, onChange, placeholder }: { label: string; value: number | null; onChange: (v: number | null) => void; placeholder?: string }) {
  const [editing, setEditing] = useState<string | null>(null)
  return (
    <Input
      label={label}
      inputMode="decimal"
      prefixIcon={<span className="text-sm">₦</span>}
      placeholder={placeholder}
      value={editing ?? minorToInput(value)}
      onFocus={() => setEditing(minorToInput(value))}
      onChange={(e) => setEditing(e.target.value)}
      onBlur={() => {
        const text = editing ?? ''
        setEditing(null)
        onChange(text.trim() === '' ? null : parseToMinor(text))
      }}
    />
  )
}

function SlashPane() {
  const { text, setText, appendShortcut, errors } = useComposerStore()
  return (
    <div className="overflow-hidden rounded-xl border border-netslip-obsidian/20 bg-netslip-obsidian shadow-sm">
      <div className="flex items-center justify-between gap-2 px-4 py-2.5">
        <div className="flex items-center gap-2">
          <span className="flex gap-1.5" aria-hidden><span className="size-2.5 rounded-full bg-rose-400" /><span className="size-2.5 rounded-full bg-amber-400" /><span className="size-2.5 rounded-full bg-emerald-400" /></span>
          <span className="ml-2 font-mono text-xs text-[#9cc3cc]">netslip ~ /slash</span>
        </div>
        <button type="button" onClick={() => setText('')} className="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs text-[#cfe3e8] hover:bg-white/10"><Eraser className="size-3.5" />Clear</button>
      </div>
      <textarea
        value={text}
        onChange={(e) => setText(e.target.value)}
        rows={9}
        spellCheck={false}
        aria-label="Slash commands"
        placeholder={'/name = Chief Emeka Nwosu\n/item = 2x Leather Sneakers @ 28,500\n/address = 42 Wuse II, Abuja'}
        className="block w-full resize-y border-0 bg-[#0f2a33] px-4 py-3 font-mono text-[13px] leading-6 text-emerald-100 placeholder:text-[#5f8792] focus:outline-none focus:ring-0"
      />
      <div className="flex flex-wrap gap-1.5 border-t border-white/10 px-4 py-3">
        {SHORTCUTS.map((s) => (
          <button key={s} type="button" onClick={() => appendShortcut(s)} className="rounded-md bg-white/10 px-2 py-1 font-mono text-[11px] text-[#cfe3e8] transition hover:bg-white/20">+ {s.trim()}</button>
        ))}
      </div>
      {errors.length > 0 && (
        <ul className="space-y-1 border-t border-white/10 bg-rose-500/10 px-4 py-2.5 text-xs text-rose-200">
          {errors.slice(0, 4).map((e) => <li key={e}>• {e}</li>)}
        </ul>
      )}
    </div>
  )
}

function FormPane({ inventoryEnabled }: { inventoryEnabled: boolean }) {
  const { draft, update, setItem, addItem, removeItem } = useComposerStore()
  const skus = useInventory({ page: 1 }, inventoryEnabled)
  const type = draft.type ?? 'RECEIPT'

  const pickSku = (index: number, description: string) => {
    const sku = skus.data?.data.find((s) => s.name.toLowerCase() === description.toLowerCase() || s.sku.toLowerCase() === description.toLowerCase())
    setItem(index, sku ? { description: sku.name, sku_id: sku.id, unit_price_minor: draft.items[index]?.unit_price_minor ?? sku.unit_price_minor } : { description, sku_id: null })
  }

  return (
    <div className="space-y-5">
      <Card padding="sm" className="space-y-4">
        <SegmentedTabs value={type} onChange={(v) => update({ type: v })} options={[{ value: 'RECEIPT', label: 'Receipt', icon: <Receipt /> }, { value: 'INVOICE', label: 'Invoice', icon: <FileText /> }]} />
        <div className="grid gap-4 sm:grid-cols-2">
          <Input label="Customer name" value={draft.name ?? ''} onChange={(e) => update({ name: e.target.value || null })} placeholder="Chief Emeka Nwosu" />
          <Input label="Phone" type="tel" value={draft.phone ?? ''} onChange={(e) => update({ phone: e.target.value || null })} placeholder="0803 998 1122" />
          <Input label="Email" type="email" value={draft.email ?? ''} onChange={(e) => update({ email: e.target.value || null })} />
          <Input label="Address" value={draft.address ?? ''} onChange={(e) => update({ address: e.target.value || null })} />
        </div>
      </Card>

      <Card padding="sm">
        <div className="flex items-center justify-between">
          <h3 className="text-sm font-semibold">Items</h3>
          <Button size="xs" variant="secondary" leftIcon={<Plus className="size-3.5" />} onClick={() => addItem()}>Add item</Button>
        </div>
        {inventoryEnabled && <datalist id="sku-list">{skus.data?.data.map((s) => <option key={s.id} value={s.name}>{s.sku} · {formatMoney(s.unit_price_minor)} · {s.stock_quantity} in stock</option>)}</datalist>}
        {draft.items.length === 0 ? (
          <div className="mt-3 space-y-2">
            <p className="text-sm text-ink-secondary">No items yet. Add items, or bill a single amount:</p>
            <MoneyField label="Lump-sum amount" value={draft.amount_minor} onChange={(v) => update({ amount_minor: v })} placeholder="30,000" />
          </div>
        ) : (
          <ul className="mt-3 space-y-3">
            {draft.items.map((item, i) => (
              <li key={i} className="grid grid-cols-[1fr_72px_120px_auto] items-end gap-2">
                <Input label={i === 0 ? 'Description' : undefined} aria-label="Description" list={inventoryEnabled ? 'sku-list' : undefined} value={item.description} onChange={(e) => pickSku(i, e.target.value)} placeholder="Leather sneakers" />
                <Input label={i === 0 ? 'Qty' : undefined} aria-label="Quantity" type="number" min={1} value={item.quantity} onChange={(e) => setItem(i, { quantity: Math.max(1, Number(e.target.value) || 1) })} />
                <MoneyField label={i === 0 ? 'Unit price' : ''} value={item.unit_price_minor} onChange={(v) => setItem(i, { unit_price_minor: v })} />
                <button type="button" onClick={() => removeItem(i)} className="mb-1 inline-flex size-8 items-center justify-center rounded-lg text-ink-muted hover:bg-rose-50 hover:text-rose-600" aria-label="Remove item"><Trash2 className="size-4" /></button>
              </li>
            ))}
          </ul>
        )}
      </Card>

      <Card padding="sm" className="grid gap-4 sm:grid-cols-2">
        <MoneyField label="Discount" value={draft.discount_bps !== null ? null : draft.discount_minor} onChange={(v) => update({ discount_minor: v, discount_bps: null })} placeholder="0" />
        <MoneyField label="Shipping" value={draft.shipping_minor} onChange={(v) => update({ shipping_minor: v })} placeholder="0" />
        <Select
          label="VAT"
          value={draft.vat_rate_bps === null ? 'default' : String(draft.vat_rate_bps)}
          onChange={(e) => update({ vat_rate_bps: e.target.value === 'default' ? null : percentToBps(e.target.value) ?? 0 })}
          options={[{ value: 'default', label: 'Business default' }, { value: '0', label: 'No VAT' }, { value: '750', label: '7.5%' }, { value: '500', label: '5%' }, { value: '1000', label: '10%' }]}
        />
        <Select
          label="VAT mode"
          value={draft.vat_inclusive === null ? 'default' : draft.vat_inclusive ? 'incl' : 'excl'}
          onChange={(e) => update({ vat_inclusive: e.target.value === 'default' ? null : e.target.value === 'incl' })}
          options={[{ value: 'default', label: 'Business default' }, { value: 'excl', label: 'Added on top' }, { value: 'incl', label: 'Included in prices' }]}
        />
        <MoneyField label={type === 'RECEIPT' ? 'Amount paid (blank = full)' : 'Deposit received'} value={draft.paid === PAID_FULL ? null : draft.paid} onChange={(v) => update({ paid: v })} />
        {type === 'INVOICE' && <Input label="Due date" type="date" value={draft.due_date ?? ''} onChange={(e) => update({ due_date: e.target.value || null })} />}
        <Textarea label="Note" wrapperClassName="sm:col-span-2" rows={2} value={draft.note ?? ''} onChange={(e) => update({ note: e.target.value || null })} placeholder="Thanks for your order!" />
      </Card>
    </div>
  )
}

export default function Composer() {
  const session = useAuthStore((s) => s.session)
  const merchant = session?.merchant
  const usage = session?.usage
  const canIssue = useCan('documents.issue')
  const inventoryEnabled = useEntitlement('inventory')
  const planClean = useEntitlement('remove_watermark')
  const { mode, setMode, draft, templateId, setTemplateId, useCredit, setUseCredit, reset } = useComposerStore()
  const templates = useTemplates()
  const refreshSession = useRefreshSession()
  const invalidate = useInvalidate()
  const navigate = useNavigate()
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [issued, setIssued] = useState<Doc | null>(null)

  const type = draft.type ?? 'RECEIPT'
  const available = templates.data?.data.filter((t) => !t.is_locked && (type === 'INVOICE' ? t.supports_invoice : t.supports_receipt)) ?? []
  const defaultId = type === 'INVOICE' ? templates.data?.defaults.invoice : templates.data?.defaults.receipt
  const activeTemplate = available.find((t) => t.id === (templateId ?? defaultId)) ?? available.find((t) => t.is_default) ?? available[0]
  const missing = missingFields(draft)
  const freeLeft = usage?.free_remaining
  const credits = usage?.paid_credits ?? 0
  const canClean = !planClean && credits > 0
  const watermark = !planClean && !(useCredit && credits > 0) && !(freeLeft === 0 && credits > 0)

  const slip = draftToSlipView(draft, {
    name: merchant?.name ?? 'Your business',
    meta: [merchant?.address, merchant?.phone].filter(Boolean).join(' · ') || null,
    logoUrl: merchant?.logo_url,
    primary: merchant?.primary_color ?? '#377581',
    secondary: merchant?.secondary_color ?? '#265363',
    vatBps: merchant?.vat_rate_bps ?? 750,
    vatInclusive: merchant?.vat_inclusive ?? false,
    currency: merchant?.currency ?? 'NGN',
    footer: merchant?.receipt_footer,
    bank: merchant?.bank_account_number ? { name: merchant.bank_name, number: merchant.bank_account_number, accountName: merchant.bank_account_name ?? merchant.name } : null,
  }, { variant: activeTemplate?.slug === 'netslip-minimalist-social' ? 'minimal' : 'classic', watermark })

  const quotaBlocked = freeLeft === 0 && credits === 0

  const submit = async () => {
    setSubmitting(true)
    setError(null)
    try {
      const res = await apiPost<{ data: Doc }>('/documents', draftToRequest(draft, activeTemplate?.id ?? null, useCredit && canClean))
      setIssued(res.data)
      reset()
      await Promise.all([refreshSession(), invalidate('documents', 'overview', 'customers')])
    } catch (e) {
      setError(e instanceof ApiError && e.code === 'quota_exceeded' ? 'quota' : errorMessage(e))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow="Quick dispatch"
        title="/Slash Composer"
        subtitle="Type like you do in chat. The slip updates as you go."
        actions={<SegmentedTabs value={mode} onChange={setMode} options={[{ value: 'slash', label: '/slash', icon: <Terminal /> }, { value: 'form', label: 'Form', icon: <LayoutList /> }]} />}
      />

      {!canIssue && <Alert tone="warning">Your role can view slips but not issue them.</Alert>}

      <SplitWorkspace
        left={
          <div className="space-y-5">
            {mode === 'slash' ? <SlashPane /> : <FormPane inventoryEnabled={inventoryEnabled} />}

            {mode === 'slash' && (
              <Card variant="tinted" padding="sm" className="flex gap-3">
                <Keyboard className="mt-0.5 size-4 shrink-0 text-netslip-deep" />
                <p className="text-sm text-netslip-deep">Same syntax as the WhatsApp and Telegram bots. Use <code className="font-mono">|</code> to add several items on one line.</p>
              </Card>
            )}

            <Card padding="sm" className="space-y-4">
              <div className="grid gap-4 sm:grid-cols-2">
                <Select
                  label="Template"
                  value={activeTemplate ? String(activeTemplate.id) : ''}
                  onChange={(e) => setTemplateId(Number(e.target.value))}
                  options={available.map((t) => ({ value: String(t.id), label: t.name }))}
                />
                <div className="flex flex-col justify-end">
                  <p className="text-sm font-medium text-ink-primary">Total</p>
                  <Money amountMinor={slip.total} currency={slip.currency} size="lg" className="mt-1" />
                </div>
              </div>
              {canClean && (
                <ToggleSwitch checked={useCredit} onChange={setUseCredit} label={<span className="inline-flex items-center gap-1.5"><Coins className="size-4 text-netslip-primary" />Spend 1 credit for a clean slip</span>} description={`No watermark. ${credits} credit(s) left.`} />
              )}
              {missing.length > 0 && (
                <p className="flex items-start gap-2 text-sm text-ink-secondary"><TriangleAlert className="mt-0.5 size-4 shrink-0 text-amber-500" />Still needed: {missing.join(', ')}</p>
              )}
              {error === 'quota' ? (
                <Alert tone="warning" title="You’ve used this month’s free slips" action={<Button to="/dashboard/billing" size="sm">Top up</Button>}>Buy a credit pack or upgrade to keep sending.</Alert>
              ) : error ? <Alert tone="danger">{error}</Alert> : null}
              {quotaBlocked && !error && <Alert tone="warning" title="Free slips used up" action={<Button to="/dashboard/billing" size="sm">Top up</Button>}>Add credits to issue more this month.</Alert>}
              <div className="flex flex-wrap gap-2">
                <Button size="lg" className="flex-1" leftIcon={<Sparkles className="size-5" />} isLoading={submitting} disabled={!canIssue || missing.length > 0} onClick={submit}>
                  Confirm & generate {type === 'INVOICE' ? 'invoice' : 'receipt'}
                </Button>
                <Button size="lg" variant="ghost" onClick={reset}>Reset</Button>
              </div>
            </Card>
          </div>
        }
        right={
          <div className="lg:sticky lg:top-24">
            <div className="mb-3 flex items-center justify-between">
              <StatusBadge status={type} />
              <span className="text-xs font-semibold uppercase tracking-wider text-ink-muted">Live preview</span>
            </div>
            <PaperSlipPreview slip={slip} />
            {watermark && <p className="mt-3 text-center text-xs text-ink-muted">Free slips carry a small “Powered by NetSlip” watermark.</p>}
          </div>
        }
      />

      <Modal
        isOpen={issued !== null}
        onClose={() => setIssued(null)}
        title={<span className="inline-flex items-center gap-2"><CircleCheck className="size-5 text-emerald-600" />{issued?.doc_type === 'INVOICE' ? 'Invoice' : 'Receipt'} {issued?.doc_number} is ready</span>}
        description={issued ? `${issued.customer_name} · ${formatMoney(issued.total_minor, issued.currency)} · ${issued.verification_code}` : undefined}
        footer={<><Button variant="ghost" onClick={() => setIssued(null)}>New slip</Button><Button onClick={() => issued && navigate(`/dashboard/documents/${issued.id}`)}>View in ledger</Button></>}
      >
        {issued && <ShareSheet documentId={issued.id} />}
      </Modal>
    </div>
  )
}

