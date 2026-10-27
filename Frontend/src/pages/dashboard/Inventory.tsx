import { Minus, Package, Plus, Search } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Alert, Button, DataTable, EmptyState, Input, Modal, Money, PageHeader, SegmentedTabs, StatusBadge, ToggleSwitch } from '@/components/ui'
import { apiDelete, apiPost, apiPut, useInventory, useInvalidate } from '@/hooks/queries'
import { errorMessage } from '@/lib/api'
import { minorToInput, parseToMinor } from '@/lib/money'
import { useCan, useEntitlement } from '@/stores/auth'
import { toast } from '@/stores/ui'
import type { Sku } from '@/types/api'
import { FeatureGate } from './FeatureGate'

export default function Inventory() {
  const enabled = useEntitlement('inventory')
  const canManage = useCan('inventory.manage')
  const [q, setQ] = useState('')
  const [debounced, setDebounced] = useState('')
  const [low, setLow] = useState(false)
  const [page, setPage] = useState(1)
  const [editing, setEditing] = useState<Sku | 'new' | null>(null)
  const [adjusting, setAdjusting] = useState<Sku | null>(null)
  useEffect(() => { const t = setTimeout(() => { setDebounced(q); setPage(1) }, 300); return () => clearTimeout(t) }, [q])
  const { data, isLoading } = useInventory({ q: debounced, low_stock: low, page }, enabled)

  if (!enabled) return <FeatureGate icon={<Package />} title="Track stock as you sell" description="SKU inventory deducts automatically when slips are issued and restocks on voids." />

  return (
    <div className="space-y-6">
      <PageHeader title="Inventory" subtitle="Stock updates itself when you issue or void slips." actions={canManage && <Button leftIcon={<Plus className="size-4" />} onClick={() => setEditing('new')}>Add product</Button>} />
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <SegmentedTabs value={low ? 'low' : 'all'} onChange={(v) => { setLow(v === 'low'); setPage(1) }} options={[{ value: 'all', label: 'All products' }, { value: 'low', label: 'Low stock' }]} />
        <Input aria-label="Search products" placeholder="Name or SKU" value={q} onChange={(e) => setQ(e.target.value)} prefixIcon={<Search />} wrapperClassName="sm:w-80" />
      </div>
      <DataTable<Sku>
        data={data?.data}
        isLoading={isLoading}
        getRowKey={(s) => s.id}
        onRowClick={canManage ? (s) => setEditing(s) : undefined}
        emptyState={<EmptyState icon={<Package />} title="No products yet" description="Add products so /item = 2x SKU-CODE fills prices automatically." />}
        pagination={data ? { page: data.meta.current_page, lastPage: data.meta.last_page, total: data.meta.total, from: data.meta.from, to: data.meta.to, onPage: setPage } : undefined}
        columns={[
          { key: 'n', header: 'Product', cell: (s) => <span><span className="block font-semibold text-ink-primary">{s.name}</span><span className="font-mono text-xs text-ink-muted">{s.sku}</span></span> },
          { key: 'p', header: 'Price', align: 'right', cell: (s) => <Money amountMinor={s.unit_price_minor} size="sm" /> },
          { key: 'q', header: 'In stock', align: 'right', cell: (s) => s.track_stock ? <span className="tabular font-semibold">{s.stock_quantity}</span> : <span className="text-ink-muted">Not tracked</span> },
          { key: 's', header: 'Status', cell: (s) => !s.is_active ? <StatusBadge status="DRAFT" label="Inactive" size="sm" /> : s.is_low_stock ? <StatusBadge status="PARTIAL" label="Low stock" size="sm" /> : <StatusBadge status="PAID" label="In stock" size="sm" /> },
          { key: 'a', header: '', align: 'right', cell: (s) => canManage && s.track_stock ? <Button size="xs" variant="outline" onClick={(e) => { e.stopPropagation(); setAdjusting(s) }}>Adjust</Button> : null },
        ]}
      />
      <SkuForm key={editing === null ? 'sku-closed' : editing === 'new' ? 'sku-new' : `sku-${editing.id}`} sku={editing} onClose={() => setEditing(null)} />
      <AdjustModal key={adjusting ? `adjust-${adjusting.id}` : 'adjust-closed'} sku={adjusting} onClose={() => setAdjusting(null)} />
    </div>
  )
}

function SkuForm({ sku, onClose }: { sku: Sku | 'new' | null; onClose: () => void }) {
  const existing = sku !== 'new' ? sku : null
  const invalidate = useInvalidate()
  const [form, setForm] = useState({ sku: existing?.sku ?? '', name: existing?.name ?? '', description: existing?.description ?? '', price: minorToInput(existing?.unit_price_minor ?? null), stock: String(existing?.stock_quantity ?? 0), threshold: String(existing?.low_stock_threshold ?? 5), track: existing?.track_stock ?? true, active: existing?.is_active ?? true })
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  const save = async () => {
    setBusy(true)
    try {
      const body = { sku: form.sku.trim(), name: form.name.trim(), description: form.description.trim() || null, unit_price_minor: parseToMinor(form.price) ?? 0, stock_quantity: Number(form.stock) || 0, low_stock_threshold: Number(form.threshold) || 0, track_stock: form.track, is_active: form.active }
      if (existing) await apiPut(`/inventory/${existing.id}`, body)
      else await apiPost('/inventory', body)
      await invalidate('inventory', 'overview')
      toast('Product saved')
      onClose()
    } catch (e) { setError(errorMessage(e)) } finally { setBusy(false) }
  }
  const remove = async () => {
    if (!existing || !window.confirm(`Delete ${existing.name}?`)) return
    try { await apiDelete(`/inventory/${existing.id}`); await invalidate('inventory'); onClose() } catch (e) { setError(errorMessage(e)) }
  }

  return (
    <Modal isOpen={sku !== null} onClose={onClose} title={existing ? 'Edit product' : 'Add product'}
      footer={<>{existing && <Button variant="ghost" className="mr-auto text-tone-open" onClick={remove}>Delete</Button>}<Button variant="ghost" onClick={onClose}>Cancel</Button><Button isLoading={busy} disabled={!form.sku.trim() || !form.name.trim()} onClick={save}>Save</Button></>}>
      <div className="space-y-4">
        {error && <Alert tone="danger">{error}</Alert>}
        <div className="grid gap-4 sm:grid-cols-[140px_1fr]">
          <Input label="SKU code" value={form.sku} onChange={(e) => setForm({ ...form, sku: e.target.value.toUpperCase() })} placeholder="SKU-SNK-02" className="font-mono" />
          <Input label="Name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
        </div>
        <Input label="Description" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
        <div className="grid gap-4 sm:grid-cols-3">
          <Input label="Unit price" prefixIcon={<span>₦</span>} value={form.price} onChange={(e) => setForm({ ...form, price: e.target.value })} />
          <Input label="In stock" type="number" min={0} value={form.stock} onChange={(e) => setForm({ ...form, stock: e.target.value })} disabled={!!existing} helperText={existing ? 'Use Adjust to change stock' : undefined} />
          <Input label="Low-stock alert" type="number" min={0} value={form.threshold} onChange={(e) => setForm({ ...form, threshold: e.target.value })} />
        </div>
        <ToggleSwitch checked={form.track} onChange={(v) => setForm({ ...form, track: v })} label="Track stock" description="Deduct on sale, block overselling." />
        <ToggleSwitch checked={form.active} onChange={(v) => setForm({ ...form, active: v })} label="Active" description="Inactive products don’t auto-fill in /item." />
      </div>
    </Modal>
  )
}

function AdjustModal({ sku, onClose }: { sku: Sku | null; onClose: () => void }) {
  const invalidate = useInvalidate()
  const [delta, setDelta] = useState(0)
  const [reason, setReason] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const save = async () => {
    if (!sku) return
    setBusy(true)
    try {
      await apiPost(`/inventory/${sku.id}/adjust`, { delta, reason })
      await invalidate('inventory', 'overview')
      toast('Stock adjusted')
      onClose()
    } catch (e) { setError(errorMessage(e)) } finally { setBusy(false) }
  }
  return (
    <Modal isOpen={sku !== null} onClose={onClose} size="sm" title={`Adjust ${sku?.name ?? ''}`} description={`Currently ${sku?.stock_quantity ?? 0} in stock.`}
      footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button isLoading={busy} disabled={delta === 0 || !reason.trim()} onClick={save}>Apply</Button></>}>
      <div className="space-y-4">
        {error && <Alert tone="danger">{error}</Alert>}
        <div className="flex items-center justify-center gap-4">
          <Button variant="outline" onClick={() => setDelta((d) => d - 1)} aria-label="Decrease"><Minus className="size-4" /></Button>
          <span className={`tabular w-20 text-center text-3xl font-bold ${delta > 0 ? 'text-tone-settled' : delta < 0 ? 'text-tone-open' : ''}`}>{delta > 0 ? `+${delta}` : delta}</span>
          <Button variant="outline" onClick={() => setDelta((d) => d + 1)} aria-label="Increase"><Plus className="size-4" /></Button>
        </div>
        <Input label="Reason" value={reason} onChange={(e) => setReason(e.target.value)} placeholder="e.g. New shipment received" />
      </div>
    </Modal>
  )
}
