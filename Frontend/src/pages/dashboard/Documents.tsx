import { Ban, Download, FilePen, FileText, History, Lock, ReceiptText, Search, Share2, Undo2, Wallet } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { ShareSheet } from '@/components/slip/ShareSheet'
import {
  Alert, Button, Card, DataTable, Drawer, EmptyState, Input, Modal, Money, PageHeader, SegmentedTabs, Select, Skeleton, StatusBadge, Textarea,
} from '@/components/ui'
import { apiPost, apiPut, useDocument, useDocuments, useInvalidate, useRefreshSession, useRevisions, type DocumentFilters } from '@/hooks/queries'
import { errorMessage } from '@/lib/api'
import { formatDate, formatDateTime } from '@/lib/format'
import { formatMoney, minorToInput, parseToMinor } from '@/lib/money'
import { useCan, useEntitlement } from '@/stores/auth'
import { toast } from '@/stores/ui'
import type { Doc, DocDetail, Revision } from '@/types/api'

type Tab = 'all' | 'RECEIPT' | 'INVOICE' | 'CREDIT_NOTE' | 'outstanding'

export default function Documents() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [params, setParams] = useSearchParams()
  const tab = (params.get('tab') as Tab | null) ?? 'all'
  const [q, setQ] = useState(params.get('q') ?? '')
  const page = Number(params.get('page') ?? '1')
  const canExport = useEntitlement('export')

  const filters: DocumentFilters = {
    type: tab === 'RECEIPT' || tab === 'INVOICE' || tab === 'CREDIT_NOTE' ? tab : undefined,
    outstanding: tab === 'outstanding' || undefined,
    status: params.get('status') ?? undefined,
    channel: params.get('channel') ?? undefined,
    customer_id: params.get('customer_id') ?? undefined,
    q: params.get('q') ?? undefined,
    page,
  }
  const { data, isLoading, isFetching } = useDocuments(filters)

  useEffect(() => {
    const t = setTimeout(() => {
      if ((params.get('q') ?? '') !== q) {
        const next = new URLSearchParams(params)
        if (q) next.set('q', q); else next.delete('q')
        next.delete('page')
        setParams(next, { replace: true })
      }
    }, 300)
    return () => clearTimeout(t)
  }, [q, params, setParams])

  const set = (key: string, value: string | null) => {
    const next = new URLSearchParams(params)
    if (value) next.set(key, value); else next.delete(key)
    if (key !== 'page') next.delete('page')
    setParams(next)
  }

  const exportUrl = `/api/v1/documents/export?${new URLSearchParams(Object.entries({ type: filters.type, status: filters.status, channel: filters.channel, q: filters.q }).filter(([, v]) => v) as [string, string][]).toString()}`

  return (
    <div className="space-y-6">
      <PageHeader
        title="Receipts & invoices"
        subtitle="Every slip you’ve issued, with live payment status."
        actions={<>
          {canExport && <Button href={exportUrl} variant="outline" leftIcon={<Download className="size-4" />}>Export CSV</Button>}
          <Button to="/dashboard/composer" leftIcon={<ReceiptText className="size-4" />}>New slip</Button>
        </>}
      />

      <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <SegmentedTabs<Tab> value={tab} onChange={(v) => set('tab', v === 'all' ? null : v)} options={[
          { value: 'all', label: 'All' }, { value: 'RECEIPT', label: 'Receipts' }, { value: 'INVOICE', label: 'Invoices' },
          { value: 'outstanding', label: 'Unpaid' }, { value: 'CREDIT_NOTE', label: 'Credit notes' },
        ]} />
        <div className="flex flex-col gap-2 sm:flex-row">
          <Input aria-label="Search" placeholder="Number, customer, phone, NSV code" value={q} onChange={(e) => setQ(e.target.value)} prefixIcon={<Search />} wrapperClassName="sm:w-72" />
          <Select aria-label="Status" value={params.get('status') ?? ''} onChange={(e) => set('status', e.target.value || null)} placeholder="Any status" options={['PAID', 'PARTIAL', 'UNPAID', 'OVERDUE', 'VOID'].map((s) => ({ value: s, label: s.charAt(0) + s.slice(1).toLowerCase() }))} />
          <Select aria-label="Channel" value={params.get('channel') ?? ''} onChange={(e) => set('channel', e.target.value || null)} placeholder="Any channel" options={[['WHATSAPP', 'WhatsApp'], ['TELEGRAM', 'Telegram'], ['INSTAGRAM', 'Instagram'], ['FACEBOOK', 'Facebook'], ['WEB', 'Web']].map(([value, label]) => ({ value: value ?? '', label: label ?? '' }))} />
        </div>
      </div>

      <DataTable<Doc>
        data={data?.data}
        isLoading={isLoading}
        getRowKey={(d) => d.id}
        onRowClick={(d) => navigate(`/dashboard/documents/${d.id}?${params.toString()}`)}
        className={isFetching && !isLoading ? 'opacity-80' : undefined}
        emptyState={<EmptyState icon={<FileText />} title="No slips match" description="Try another filter, or create your first slip." action={<Button to="/dashboard/composer" size="sm">New slip</Button>} />}
        pagination={data ? { page: data.meta.current_page, lastPage: data.meta.last_page, total: data.meta.total, from: data.meta.from, to: data.meta.to, onPage: (p) => set('page', String(p)) } : undefined}
        mobileCard={(d) => (
          <div className="flex items-center justify-between gap-3">
            <div className="min-w-0">
              <p className="tabular text-sm font-semibold">{d.doc_number}</p>
              <p className="truncate text-xs text-ink-muted">{d.customer_name} · {formatDate(d.issued_at)}</p>
            </div>
            <div className="text-right">
              <Money amountMinor={d.total_minor} currency={d.currency} size="sm" className="font-semibold" />
              <div className="mt-1"><StatusBadge status={d.status} size="sm" /></div>
            </div>
          </div>
        )}
        columns={[
          { key: 'n', header: 'Number', cell: (d) => <span className="tabular font-semibold text-ink-primary">{d.doc_number}</span> },
          { key: 'c', header: 'Customer', cell: (d) => <span className="block max-w-[220px] truncate">{d.customer_name}</span> },
          { key: 'd', header: 'Issued', cell: (d) => <span className="text-ink-secondary">{formatDate(d.issued_at)}</span>, hideOnMobile: true },
          { key: 'ch', header: 'Channel', cell: (d) => <StatusBadge status={d.channel} size="sm" />, hideOnMobile: true },
          { key: 't', header: 'Total', align: 'right', cell: (d) => <Money amountMinor={d.total_minor} currency={d.currency} size="sm" className="font-semibold" /> },
          { key: 'b', header: 'Balance', align: 'right', cell: (d) => d.balance_due_minor > 0 ? <Money amountMinor={d.balance_due_minor} currency={d.currency} size="sm" className="text-rose-600" /> : <span className="text-ink-muted">—</span>, hideOnMobile: true },
          { key: 's', header: 'Status', cell: (d) => <StatusBadge status={d.status} size="sm" /> },
          { key: 'r', header: 'Rev', align: 'center', cell: (d) => (d.revisions_count ?? 0) > 0 ? <span className="inline-flex items-center gap-1 text-xs text-sky-700"><History className="size-3.5" />{d.revision_number}</span> : <span className="text-ink-muted">{d.revision_number}</span>, hideOnMobile: true },
        ]}
      />

      <DocumentDrawer id={id ?? null} onClose={() => navigate(`/dashboard/documents?${params.toString()}`)} />
    </div>
  )
}

function DocumentDrawer({ id, onClose }: { id: string | null; onClose: () => void }) {
  const { data, isLoading } = useDocument(id)
  const doc = data?.data
  const [modal, setModal] = useState<null | 'edit' | 'void' | 'credit' | 'payment' | 'share' | 'history'>(null)

  return (
    <Drawer
      isOpen={id !== null}
      onClose={onClose}
      width="max-w-2xl"
      title={doc ? <span className="tabular">{doc.doc_number}</span> : 'Loading…'}
      subtitle={doc && <span className="flex flex-wrap items-center gap-2"><StatusBadge status={doc.doc_type} size="sm" /><StatusBadge status={doc.status} size="sm" /><StatusBadge status={data.meta.mutability_mode} size="sm" showIcon /></span>}
      footer={doc && <DocumentActions detail={data} onAction={setModal} />}
    >
      {isLoading || !doc ? <Skeleton className="h-96" /> : (
        <div className="space-y-6">
          {doc.status === 'VOID' && <Alert tone="danger" title="Voided">{doc.void_reason}</Alert>}
          <div className="grid grid-cols-2 gap-4 text-sm">
            <div><p className="text-ink-muted">Customer</p><p className="font-semibold">{doc.customer_name}</p><p className="text-ink-secondary">{doc.customer_phone}</p><p className="text-ink-secondary">{doc.customer_address}</p></div>
            <div className="text-right"><p className="text-ink-muted">Issued</p><p className="font-semibold">{formatDateTime(doc.issued_at)}</p>{doc.due_date && <p className="text-ink-secondary">Due {formatDate(doc.due_date)}</p>}<p className="text-ink-secondary">via {doc.channel.toLowerCase()}{doc.created_by ? ` · ${doc.created_by}` : ''}</p></div>
          </div>

          <Card padding="none">
            <ul className="divide-y divide-border-subtle text-sm">
              {doc.items?.map((it) => (
                <li key={it.id} className="flex items-center justify-between gap-3 px-4 py-3">
                  <div className="min-w-0"><p className="truncate font-medium">{it.description}</p><p className="tabular text-xs text-ink-muted">{it.quantity} × {formatMoney(it.unit_price_minor, doc.currency)}</p></div>
                  <Money amountMinor={it.line_total_minor} currency={doc.currency} size="sm" className="font-semibold" />
                </li>
              ))}
            </ul>
            <dl className="tabular space-y-1.5 border-t border-border-subtle px-4 py-3 text-sm">
              <div className="flex justify-between text-ink-secondary"><dt>Subtotal</dt><dd>{formatMoney(doc.subtotal_minor, doc.currency)}</dd></div>
              {doc.discount_minor > 0 && <div className="flex justify-between text-ink-secondary"><dt>Discount</dt><dd>-{formatMoney(doc.discount_minor, doc.currency)}</dd></div>}
              {doc.tax_minor > 0 && <div className="flex justify-between text-ink-secondary"><dt>VAT {(doc.vat_rate_bps / 100).toString()}%{doc.vat_inclusive ? ' (incl.)' : ''}</dt><dd>{formatMoney(doc.tax_minor, doc.currency)}</dd></div>}
              {doc.shipping_minor > 0 && <div className="flex justify-between text-ink-secondary"><dt>Shipping</dt><dd>{formatMoney(doc.shipping_minor, doc.currency)}</dd></div>}
              <div className="flex justify-between pt-1 text-base font-bold"><dt>Total</dt><dd>{formatMoney(doc.total_minor, doc.currency)}</dd></div>
              {doc.amount_paid_minor > 0 && <div className="flex justify-between text-emerald-700"><dt>Paid</dt><dd>{formatMoney(doc.amount_paid_minor, doc.currency)}</dd></div>}
              {data.meta.credited_minor > 0 && <div className="flex justify-between text-amber-700"><dt>Credited</dt><dd>{formatMoney(data.meta.credited_minor, doc.currency)}</dd></div>}
              {doc.balance_due_minor > 0 && <div className="flex justify-between font-bold text-rose-600"><dt>Balance due</dt><dd>{formatMoney(doc.balance_due_minor, doc.currency)}</dd></div>}
            </dl>
          </Card>

          {doc.notes && <p className="rounded-lg bg-surface-canvas px-4 py-3 text-sm text-ink-secondary">{doc.notes}</p>}

          {(doc.payments?.length ?? 0) > 0 && (
            <div>
              <h3 className="text-sm font-semibold">Payments</h3>
              <ul className="mt-2 divide-y divide-border-subtle rounded-xl border border-border-subtle text-sm">
                {doc.payments?.map((p) => (
                  <li key={p.id} className="flex items-center justify-between gap-3 px-4 py-2.5">
                    <span>{p.method.replace('_', ' ').toLowerCase()} {p.reference && <span className="text-ink-muted">· {p.reference}</span>}<span className="block text-xs text-ink-muted">{formatDateTime(p.paid_at)}</span></span>
                    <Money amountMinor={p.amount_minor} currency={doc.currency} size="sm" className="font-semibold text-emerald-700" />
                  </li>
                ))}
              </ul>
            </div>
          )}

          {(doc.children?.length ?? 0) > 0 && (
            <div>
              <h3 className="text-sm font-semibold">Linked documents</h3>
              <ul className="mt-2 flex flex-wrap gap-2">
                {doc.children?.map((c) => (
                  <li key={c.id}><Button to={`/dashboard/documents/${c.id}`} size="xs" variant="outline">{c.doc_number} · {formatMoney(c.total_minor, doc.currency)}</Button></li>
                ))}
              </ul>
            </div>
          )}
          {doc.parent && <p className="text-sm text-ink-secondary">Linked to <Button to={`/dashboard/documents/${doc.parent.id}`} size="xs" variant="ghost">{doc.parent.doc_number}</Button></p>}

          <div className="rounded-xl border border-border-subtle p-4 text-xs">
            <p className="font-semibold uppercase tracking-wider text-ink-muted">Verification</p>
            <p className="tabular mt-1 text-sm font-bold">{doc.verification_code}</p>
            <p className="mt-1 break-all font-mono text-[11px] text-ink-muted">{doc.verification_hash}</p>
            <div className="mt-2 flex gap-3">
              <a href={doc.verify_url} target="_blank" rel="noreferrer" className="font-semibold text-netslip-primary hover:underline">Open public check</a>
              {(doc.revisions_count ?? 0) > 0 && <button type="button" onClick={() => setModal('history')} className="font-semibold text-netslip-primary hover:underline">Revision history ({doc.revisions_count})</button>}
            </div>
          </div>
        </div>
      )}

      {doc && data && (
        <>
          <EditModal key={`e${doc.revision_number}${modal === 'edit'}`} detail={data} isOpen={modal === 'edit'} onClose={() => setModal(null)} />
          <VoidModal key={`v${modal === 'void'}`} doc={doc} isOpen={modal === 'void'} onClose={() => setModal(null)} />
          <CreditModal key={`c${data.meta.credited_minor}${modal === 'credit'}`} doc={doc} credited={data.meta.credited_minor} isOpen={modal === 'credit'} onClose={() => setModal(null)} />
          <PaymentModal key={`p${doc.balance_due_minor}${modal === 'payment'}`} doc={doc} isOpen={modal === 'payment'} onClose={() => setModal(null)} />
          <HistoryModal id={doc.id} isOpen={modal === 'history'} onClose={() => setModal(null)} currency={doc.currency} />
          <Modal isOpen={modal === 'share'} onClose={() => setModal(null)} title={`Share ${doc.doc_number}`}><ShareSheet documentId={doc.id} /></Modal>
        </>
      )}
    </Drawer>
  )
}

function DocumentActions({ detail, onAction }: { detail: DocDetail; onAction: (a: 'edit' | 'void' | 'credit' | 'payment' | 'share') => void }) {
  const doc = detail.data
  const canVoid = useCan('documents.void')
  const canPay = useCan('payments.record')
  const isVoid = doc.status === 'VOID'
  const primary = doc.doc_type !== 'CREDIT_NOTE' && doc.parent_document_id === null

  return (
    <div className="flex w-full flex-wrap items-center justify-between gap-2">
      <div className="flex flex-wrap gap-2">
        <Button href={`/api/v1/documents/${doc.id}/pdf`} target="_blank" variant="outline" size="sm" leftIcon={<Download className="size-4" />}>PDF</Button>
        <Button variant="outline" size="sm" leftIcon={<Share2 className="size-4" />} onClick={() => onAction('share')}>Share</Button>
      </div>
      {!isVoid && (
        <div className="flex flex-wrap gap-2">
          {doc.doc_type === 'INVOICE' && doc.balance_due_minor > 0 && canPay && <Button size="sm" variant="success" leftIcon={<Wallet className="size-4" />} onClick={() => onAction('payment')}>Record payment</Button>}
          {primary && detail.meta.can_edit && <Button size="sm" variant="secondary" leftIcon={<FilePen className="size-4" />} onClick={() => onAction('edit')}>Edit</Button>}
          {primary && !detail.meta.can_edit && detail.meta.mutability_mode === 'IMMUTABLE' && <span className="inline-flex items-center gap-1 text-xs text-ink-muted"><Lock className="size-3.5" />Locked record</span>}
          {primary && canVoid && <Button size="sm" variant="outline" leftIcon={<Undo2 className="size-4" />} onClick={() => onAction('credit')}>Credit note</Button>}
          {canVoid && <Button size="sm" variant="ghost" className="text-rose-600 hover:bg-rose-50" leftIcon={<Ban className="size-4" />} onClick={() => onAction('void')}>Void</Button>}
        </div>
      )}
    </div>
  )
}

function useDocMutation() {
  const invalidate = useInvalidate()
  const refreshSession = useRefreshSession()
  return async () => { await Promise.all([invalidate('document', 'documents', 'overview', 'revisions', 'customers'), refreshSession()]) }
}

function EditModal({ detail, isOpen, onClose }: { detail: DocDetail; isOpen: boolean; onClose: () => void }) {
  const doc = detail.data
  const refresh = useDocMutation()
  const [name, setName] = useState(doc.customer_name)
  const [phone, setPhone] = useState(doc.customer_phone ?? '')
  const [address, setAddress] = useState(doc.customer_address ?? '')
  const [items, setItems] = useState(doc.items?.map((i) => ({ description: i.description, quantity: i.quantity, price: minorToInput(i.unit_price_minor), sku_id: i.sku_id })) ?? [])
  const [discount, setDiscount] = useState(minorToInput(doc.discount_minor))
  const [shipping, setShipping] = useState(minorToInput(doc.shipping_minor))
  const [notes, setNotes] = useState(doc.notes ?? '')
  const [reason, setReason] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  const save = async () => {
    setBusy(true)
    setError(null)
    try {
      await apiPut(`/documents/${doc.id}`, {
        reason: reason || null,
        draft: {
          type: doc.doc_type, customer_name: name, customer_phone: phone || null, customer_email: doc.customer_email, customer_address: address || null,
          items: items.map((i) => ({ description: i.description, quantity: i.quantity, unit_price_minor: parseToMinor(i.price) ?? 0, sku_id: i.sku_id })),
          discount_minor: parseToMinor(discount) ?? 0, shipping_minor: parseToMinor(shipping) ?? 0,
          vat_rate_bps: doc.vat_rate_bps, vat_inclusive: doc.vat_inclusive, due_date: doc.due_date, notes: notes || null, template_id: doc.template_id,
        },
      })
      await refresh()
      toast(`${doc.doc_number} updated · revision ${doc.revision_number + 1}`)
      onClose()
    } catch (e) {
      setError(errorMessage(e))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Modal isOpen={isOpen} onClose={onClose} size="lg" title={`Edit ${doc.doc_number}`} description="A forensic snapshot of the current version is saved before your changes."
      footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button isLoading={busy} onClick={save} disabled={detail.meta.edit_reason_required && reason.trim().length < 5}>Save revision</Button></>}>
      <div className="space-y-4">
        {error && <Alert tone="danger">{error}</Alert>}
        <div className="grid gap-3 sm:grid-cols-3">
          <Input label="Customer" value={name} onChange={(e) => setName(e.target.value)} />
          <Input label="Phone" value={phone} onChange={(e) => setPhone(e.target.value)} />
          <Input label="Address" value={address} onChange={(e) => setAddress(e.target.value)} />
        </div>
        <div className="space-y-2">
          {items.map((it, i) => (
            <div key={i} className="grid grid-cols-[1fr_70px_120px] gap-2">
              <Input aria-label="Description" value={it.description} onChange={(e) => setItems((arr) => arr.map((x, j) => j === i ? { ...x, description: e.target.value } : x))} />
              <Input aria-label="Quantity" type="number" min={1} value={it.quantity} onChange={(e) => setItems((arr) => arr.map((x, j) => j === i ? { ...x, quantity: Math.max(1, Number(e.target.value) || 1) } : x))} />
              <Input aria-label="Unit price" prefixIcon={<span>₦</span>} value={it.price} onChange={(e) => setItems((arr) => arr.map((x, j) => j === i ? { ...x, price: e.target.value } : x))} />
            </div>
          ))}
        </div>
        <div className="grid gap-3 sm:grid-cols-2">
          <Input label="Discount" prefixIcon={<span>₦</span>} value={discount} onChange={(e) => setDiscount(e.target.value)} />
          <Input label="Shipping" prefixIcon={<span>₦</span>} value={shipping} onChange={(e) => setShipping(e.target.value)} />
        </div>
        <Textarea label="Notes" rows={2} value={notes} onChange={(e) => setNotes(e.target.value)} />
        <Textarea label={detail.meta.edit_reason_required ? 'Reason for this edit (required)' : 'Reason (optional)'} rows={2} value={reason} onChange={(e) => setReason(e.target.value)} placeholder="e.g. Customer changed shoe size" helperText="Stored with your name, IP and the before/after snapshot." />
      </div>
    </Modal>
  )
}

function VoidModal({ doc, isOpen, onClose }: { doc: Doc; isOpen: boolean; onClose: () => void }) {
  const refresh = useDocMutation()
  const [reason, setReason] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const submit = async () => {
    setBusy(true)
    try {
      await apiPost(`/documents/${doc.id}/void`, { reason })
      await refresh()
      toast(`${doc.doc_number} voided`)
      onClose()
    } catch (e) { setError(errorMessage(e)) } finally { setBusy(false) }
  }
  return (
    <Modal isOpen={isOpen} onClose={onClose} title={`Void ${doc.doc_number}?`} description="The slip stays in your ledger marked VOID. Stock is returned to inventory."
      footer={<><Button variant="ghost" onClick={onClose}>Keep it</Button><Button variant="danger" isLoading={busy} disabled={reason.trim().length < 5} onClick={submit}>Void slip</Button></>}>
      {error && <Alert tone="danger" className="mb-3">{error}</Alert>}
      <Textarea label="Reason" rows={3} value={reason} onChange={(e) => setReason(e.target.value)} placeholder="e.g. Customer cancelled the order" />
    </Modal>
  )
}

function CreditModal({ doc, credited, isOpen, onClose }: { doc: Doc; credited: number; isOpen: boolean; onClose: () => void }) {
  const refresh = useDocMutation()
  const max = doc.total_minor - credited
  const [amount, setAmount] = useState(minorToInput(max))
  const [reason, setReason] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const minor = parseToMinor(amount)
  const submit = async () => {
    setBusy(true)
    try {
      const res = await apiPost<{ data: Doc }>(`/documents/${doc.id}/credit-notes`, { amount_minor: minor, reason })
      await refresh()
      toast(`Credit note ${res.data.doc_number} issued`)
      onClose()
    } catch (e) { setError(errorMessage(e)) } finally { setBusy(false) }
  }
  return (
    <Modal isOpen={isOpen} onClose={onClose} title="Issue a credit note" description={`Up to ${formatMoney(max, doc.currency)} can be credited against ${doc.doc_number}.`}
      footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button isLoading={busy} disabled={!minor || minor > max || reason.trim().length < 5} onClick={submit}>Issue credit note</Button></>}>
      <div className="space-y-4">
        {error && <Alert tone="danger">{error}</Alert>}
        <Input label="Amount" prefixIcon={<span>₦</span>} value={amount} onChange={(e) => setAmount(e.target.value)} error={minor !== null && minor > max ? 'More than the creditable amount' : undefined} />
        <Textarea label="Reason" rows={2} value={reason} onChange={(e) => setReason(e.target.value)} placeholder="e.g. Returned one pair" />
      </div>
    </Modal>
  )
}

function PaymentModal({ doc, isOpen, onClose }: { doc: Doc; isOpen: boolean; onClose: () => void }) {
  const refresh = useDocMutation()
  const [amount, setAmount] = useState(minorToInput(doc.balance_due_minor))
  const [method, setMethod] = useState('BANK_TRANSFER')
  const [reference, setReference] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const minor = parseToMinor(amount)
  const submit = async () => {
    setBusy(true)
    try {
      const res = await apiPost<{ receipt: Doc | null }>(`/documents/${doc.id}/payments`, { amount_minor: minor, method, reference: reference || null })
      await refresh()
      toast(res.receipt ? `Payment recorded · receipt ${res.receipt.doc_number} issued` : 'Payment recorded')
      onClose()
    } catch (e) { setError(errorMessage(e)) } finally { setBusy(false) }
  }
  return (
    <Modal isOpen={isOpen} onClose={onClose} title="Record a payment" description={`Balance due: ${formatMoney(doc.balance_due_minor, doc.currency)}. A payment receipt is issued automatically.`}
      footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button variant="success" isLoading={busy} disabled={!minor || minor > doc.balance_due_minor} onClick={submit}>Record payment</Button></>}>
      <div className="space-y-4">
        {error && <Alert tone="danger">{error}</Alert>}
        <Input label="Amount" prefixIcon={<span>₦</span>} value={amount} onChange={(e) => setAmount(e.target.value)} />
        <Select label="Method" value={method} onChange={(e) => setMethod(e.target.value)} options={[['BANK_TRANSFER', 'Bank transfer'], ['CASH', 'Cash'], ['POS', 'POS'], ['CARD', 'Card'], ['CRYPTO', 'Crypto'], ['OTHER', 'Other']].map(([value, label]) => ({ value: value ?? '', label: label ?? '' }))} />
        <Input label="Reference (optional)" value={reference} onChange={(e) => setReference(e.target.value)} placeholder="e.g. Moniepoint TRF-88421" />
      </div>
    </Modal>
  )
}

function renderValue(field: string, value: unknown, currency: string): string {
  if (field.endsWith('_minor') && typeof value === 'number') return formatMoney(value, currency)
  if (field === 'items' && Array.isArray(value)) return value.map((i: { description?: string; quantity?: number }) => `${i.quantity ?? 1}× ${i.description ?? ''}`).join(', ')
  if (value === null || value === undefined || value === '') return '—'
  return typeof value === 'object' ? JSON.stringify(value) : String(value)
}

function HistoryModal({ id, isOpen, onClose, currency }: { id: string; isOpen: boolean; onClose: () => void; currency: string }) {
  const { data, isLoading } = useRevisions(id, isOpen)
  return (
    <Modal isOpen={isOpen} onClose={onClose} size="lg" title="Forensic revision history" description="Append-only. Captured at the moment of each change.">
      {isLoading ? <Skeleton className="h-48" /> : (
        <ol className="space-y-4">
          {data?.data.map((r: Revision) => (
            <li key={r.id} className="rounded-xl border border-border-subtle p-4">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <StatusBadge status={r.action === 'VOID' ? 'VOID' : r.action === 'PAYMENT' ? 'PAID' : r.action === 'CREDIT_NOTE' ? 'CREDIT_NOTE' : 'MUTABLE_WITH_AUDIT'} label={`${r.action.replace('_', ' ')} · rev ${r.revision_number}`} size="sm" />
                <span className="text-xs text-ink-muted">{formatDateTime(r.created_at)}</span>
              </div>
              <p className="mt-2 text-sm"><span className="font-semibold">{r.actor ?? 'System'}</span>{r.actor_ip && <span className="text-ink-muted"> · IP {r.actor_ip}</span>}</p>
              {r.edit_reason && <p className="mt-1 rounded-lg bg-surface-canvas px-3 py-2 text-sm italic text-ink-secondary">“{r.edit_reason}”</p>}
              {Object.keys(r.diff).length > 0 && (
                <table className="mt-3 w-full text-xs">
                  <tbody className="divide-y divide-border-subtle">
                    {Object.entries(r.diff).filter(([f]) => f !== 'verification_hash').map(([field, change]) => (
                      <tr key={field} className="align-top">
                        <td className="w-36 py-1.5 pr-2 font-mono text-ink-muted">{field}</td>
                        <td className="py-1.5 pr-2 text-rose-700 line-through decoration-rose-300">{renderValue(field, change.before, currency)}</td>
                        <td className="py-1.5 text-emerald-700">{renderValue(field, change.after, currency)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </li>
          ))}
        </ol>
      )}
    </Modal>
  )
}
