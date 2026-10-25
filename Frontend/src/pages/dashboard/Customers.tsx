import { Download, MessageCircle, Phone, Plus, Search, UsersRound } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import {
  Alert, Avatar, Button, Card, DataTable, Drawer, EmptyState, Input, Modal, Money, PageHeader, SegmentedTabs, Skeleton, StatusBadge, Textarea,
} from '@/components/ui'
import { apiDelete, apiPost, apiPut, useCustomer, useCustomers, useInvalidate } from '@/hooks/queries'
import { errorMessage } from '@/lib/api'
import { formatDate, timeAgo } from '@/lib/format'
import { formatMoney } from '@/lib/money'
import { useCan, useMerchant } from '@/stores/auth'
import { toast } from '@/stores/ui'
import type { Customer } from '@/types/api'

function waLink(phone: string | null, text: string): string | undefined {
  if (!phone) return undefined
  let digits = phone.replace(/\D/g, '')
  if (digits.startsWith('0')) digits = `234${digits.slice(1)}`
  return `https://wa.me/${digits}?text=${encodeURIComponent(text)}`
}

export default function Customers() {
  const [params, setParams] = useSearchParams()
  const [q, setQ] = useState('')
  const [debounced, setDebounced] = useState('')
  const debtors = params.get('debtors') === '1'
  const page = Number(params.get('page') ?? '1')
  const [selected, setSelected] = useState<string | null>(null)
  const [editing, setEditing] = useState<Customer | 'new' | null>(null)
  const merchant = useMerchant()
  const canManage = useCan('customers.manage')

  useEffect(() => { const t = setTimeout(() => setDebounced(q), 300); return () => clearTimeout(t) }, [q])
  const { data, isLoading } = useCustomers({ q: debounced, debtors, sort: debtors ? 'outstanding' : 'recent', page })
  const currency = merchant?.currency ?? 'NGN'

  return (
    <div className="space-y-6">
      <PageHeader title="Customers & debtors" subtitle="Everyone you’ve sold to, and who still owes you." actions={canManage && <Button leftIcon={<Plus className="size-4" />} onClick={() => setEditing('new')}>Add customer</Button>} />
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <SegmentedTabs value={debtors ? 'debtors' : 'all'} onChange={(v) => setParams(v === 'debtors' ? { debtors: '1' } : {})} options={[{ value: 'all', label: 'All customers' }, { value: 'debtors', label: 'Debtors' }]} />
        <Input aria-label="Search customers" placeholder="Name, phone or email" value={q} onChange={(e) => setQ(e.target.value)} prefixIcon={<Search />} wrapperClassName="sm:w-80" />
      </div>
      <DataTable<Customer>
        data={data?.data}
        isLoading={isLoading}
        getRowKey={(c) => c.id}
        onRowClick={(c) => setSelected(c.id)}
        emptyState={<EmptyState icon={<UsersRound />} title={debtors ? 'No debtors' : 'No customers yet'} description={debtors ? 'Every invoice is settled.' : 'Customers are added automatically when you issue slips.'} />}
        pagination={data ? { page: data.meta.current_page, lastPage: data.meta.last_page, total: data.meta.total, from: data.meta.from, to: data.meta.to, onPage: (p) => setParams({ ...(debtors ? { debtors: '1' } : {}), page: String(p) }) } : undefined}
        mobileCard={(c) => (
          <div className="flex items-center gap-3">
            <Avatar name={c.name} />
            <div className="min-w-0 flex-1"><p className="truncate text-sm font-semibold">{c.name}</p><p className="text-xs text-ink-muted">{c.phone ?? c.email ?? '—'}</p></div>
            {(c.outstanding_minor ?? 0) > 0 && <Money amountMinor={c.outstanding_minor ?? 0} currency={currency} size="sm" className="font-semibold text-rose-600" />}
          </div>
        )}
        columns={[
          { key: 'n', header: 'Customer', cell: (c) => <span className="flex items-center gap-3"><Avatar name={c.name} className="size-8" /><span><span className="block font-semibold text-ink-primary">{c.name}</span><span className="text-xs text-ink-muted">{c.email}</span></span></span> },
          { key: 'p', header: 'Phone', cell: (c) => <span className="tabular">{c.phone ?? '—'}</span>, hideOnMobile: true },
          { key: 'd', header: 'Slips', align: 'right', cell: (c) => <span className="tabular">{c.documents_count ?? 0}</span> },
          { key: 'b', header: 'Total billed', align: 'right', cell: (c) => <Money amountMinor={c.total_billed_minor ?? 0} currency={currency} size="sm" /> },
          { key: 'o', header: 'Outstanding', align: 'right', cell: (c) => (c.outstanding_minor ?? 0) > 0 ? <Money amountMinor={c.outstanding_minor ?? 0} currency={currency} size="sm" className="font-semibold text-rose-600" /> : <span className="text-ink-muted">—</span> },
          { key: 'l', header: 'Last purchase', cell: (c) => <span className="text-ink-secondary">{c.last_purchase_at ? timeAgo(c.last_purchase_at) : '—'}</span>, hideOnMobile: true },
        ]}
      />
      <CustomerDrawer id={selected} onClose={() => setSelected(null)} onEdit={(c) => setEditing(c)} currency={currency} merchantName={merchant?.name ?? ''} />
      <CustomerForm key={editing === null ? 'closed' : editing === 'new' ? 'new' : editing.id} customer={editing} onClose={() => setEditing(null)} />
    </div>
  )
}

function CustomerDrawer({ id, onClose, onEdit, currency, merchantName }: { id: string | null; onClose: () => void; onEdit: (c: Customer) => void; currency: string; merchantName: string }) {
  const { data, isLoading } = useCustomer(id)
  const navigate = useNavigate()
  const canManage = useCan('customers.manage')
  const c = data?.data
  const outstanding = data?.summary.outstanding_minor ?? 0
  const reminder = c ? `Hi ${c.name.split(' ')[0]}, a friendly reminder from ${merchantName}: your outstanding balance is ${formatMoney(outstanding, currency)}. Thank you!` : ''

  const statementCsv = () => {
    if (!data) return
    const rows = [['Number', 'Type', 'Date', 'Status', 'Total', 'Paid', 'Balance'], ...data.documents.map((d) => [d.doc_number, d.doc_type, d.issued_at.slice(0, 10), d.status, (d.total_minor / 100).toFixed(2), (d.amount_paid_minor / 100).toFixed(2), (d.balance_due_minor / 100).toFixed(2)])]
    const blob = new Blob([rows.map((r) => r.map((v) => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n')], { type: 'text/csv' })
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = `statement-${c?.name.replace(/\W+/g, '-').toLowerCase() ?? 'customer'}.csv`
    a.click()
    URL.revokeObjectURL(a.href)
  }

  return (
    <Drawer isOpen={id !== null} onClose={onClose} title={c?.name ?? 'Customer'} subtitle={c?.phone ?? c?.email}
      footer={c && <>
        {canManage && <Button variant="outline" size="sm" onClick={() => onEdit(c)}>Edit</Button>}
        <Button variant="outline" size="sm" leftIcon={<Download className="size-4" />} onClick={statementCsv}>Statement</Button>
        {outstanding > 0 && c.phone && <Button size="sm" variant="success" href={waLink(c.phone, reminder) ?? '#'} target="_blank" leftIcon={<MessageCircle className="size-4" />}>Send reminder</Button>}
      </>}>
      {isLoading || !data || !c ? <Skeleton className="h-80" /> : (
        <div className="space-y-6">
          <div className="grid grid-cols-2 gap-3">
            <Card padding="sm"><p className="text-xs text-ink-muted">Total billed</p><Money amountMinor={data.summary.total_billed_minor} currency={currency} size="lg" /></Card>
            <Card padding="sm" className={outstanding > 0 ? 'border-rose-200 bg-rose-50' : ''}><p className="text-xs text-ink-muted">Outstanding</p><Money amountMinor={outstanding} currency={currency} size="lg" className={outstanding > 0 ? 'text-rose-600' : ''} /></Card>
          </div>
          <dl className="space-y-1.5 text-sm">
            {c.phone && <div className="flex items-center gap-2"><Phone className="size-4 text-ink-muted" /><a className="tabular hover:underline" href={`tel:${c.phone}`}>{c.phone}</a></div>}
            {c.address && <div className="text-ink-secondary">{c.address}</div>}
            {c.notes && <p className="rounded-lg bg-surface-canvas px-3 py-2 text-ink-secondary">{c.notes}</p>}
          </dl>
          <div>
            <h3 className="text-sm font-semibold">History</h3>
            <ul className="mt-2 divide-y divide-border-subtle rounded-xl border border-border-subtle">
              {data.documents.length === 0 && <li className="px-4 py-6 text-center text-sm text-ink-muted">No slips yet.</li>}
              {data.documents.map((d) => (
                <li key={d.id}>
                  <button type="button" onClick={() => navigate(`/dashboard/documents/${d.id}`)} className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left hover:bg-surface-canvas">
                    <span><span className="tabular block text-sm font-semibold">{d.doc_number}</span><span className="text-xs text-ink-muted">{formatDate(d.issued_at)}</span></span>
                    <span className="text-right"><Money amountMinor={d.total_minor} currency={d.currency} size="sm" /><span className="mt-1 block"><StatusBadge status={d.status} size="sm" /></span></span>
                  </button>
                </li>
              ))}
            </ul>
          </div>
        </div>
      )}
    </Drawer>
  )
}

function CustomerForm({ customer, onClose }: { customer: Customer | 'new' | null; onClose: () => void }) {
  const existing = customer !== 'new' ? customer : null
  const [form, setForm] = useState({ name: existing?.name ?? '', phone: existing?.phone ?? '', email: existing?.email ?? '', address: existing?.address ?? '', notes: existing?.notes ?? '' })
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const invalidate = useInvalidate()

  const save = async () => {
    setBusy(true)
    try {
      const body = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v.trim() || null]))
      if (existing) await apiPut(`/customers/${existing.id}`, body)
      else await apiPost('/customers', body)
      await invalidate('customers', 'customer')
      toast('Customer saved')
      onClose()
    } catch (e) { setError(errorMessage(e)) } finally { setBusy(false) }
  }
  const remove = async () => {
    if (!existing || !window.confirm(`Delete ${existing.name}? Their slips stay in your ledger.`)) return
    try {
      await apiDelete(`/customers/${existing.id}`)
      await invalidate('customers')
      toast('Customer deleted')
      onClose()
    } catch (e) { setError(errorMessage(e)) }
  }

  return (
    <Modal isOpen={customer !== null} onClose={onClose} title={existing ? 'Edit customer' : 'Add customer'}
      footer={<>{existing && <Button variant="ghost" className="mr-auto text-rose-600" onClick={remove}>Delete</Button>}<Button variant="ghost" onClick={onClose}>Cancel</Button><Button isLoading={busy} disabled={!form.name.trim()} onClick={save}>Save</Button></>}>
      <div className="space-y-4">
        {error && <Alert tone="danger">{error}</Alert>}
        <Input label="Name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
        <div className="grid gap-4 sm:grid-cols-2">
          <Input label="Phone" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
          <Input label="Email" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
        </div>
        <Input label="Address" value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} />
        <Textarea label="Notes" rows={2} value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} />
      </div>
    </Modal>
  )
}
