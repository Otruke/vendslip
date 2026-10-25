import { ArrowRight, Coins, FileText, MessageCircle, Package, TriangleAlert, Wallet } from 'lucide-react'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Area, AreaChart, CartesianGrid, Cell, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { Button, Card, DataTable, EmptyState, Money, PageHeader, SegmentedTabs, Skeleton, StatCard, StatusBadge } from '@/components/ui'
import { useOverview } from '@/hooks/queries'
import { formatDate } from '@/lib/format'
import { formatMoney, formatMoneyCompact } from '@/lib/money'
import { useAuthStore } from '@/stores/auth'
import type { Doc } from '@/types/api'

const CHANNEL_COLORS: Record<string, string> = { WHATSAPP: '#10b981', TELEGRAM: '#0ea5e9', INSTAGRAM: '#ec4899', FACEBOOK: '#2563eb', WEB: '#94a3b8' }

export default function Overview() {
  const [range, setRange] = useState<'7d' | '30d' | '90d' | '12m'>('30d')
  const { data, isLoading } = useOverview(range)
  const merchant = useAuthStore((s) => s.session?.merchant)
  const user = useAuthStore((s) => s.session?.user)
  const navigate = useNavigate()
  const currency = merchant?.currency ?? 'NGN'
  const k = data?.kpis
  const channelTotal = data?.channels.reduce((s, c) => s + c.count, 0) ?? 0

  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow={new Intl.DateTimeFormat('en-GB', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date())}
        title={`Hello, ${user?.name.split(' ')[0] ?? 'there'}`}
        subtitle="Here’s how your sales are moving."
        actions={<SegmentedTabs value={range} onChange={setRange} size="sm" options={[{ value: '7d', label: '7D' }, { value: '30d', label: '30D' }, { value: '90d', label: '90D' }, { value: '12m', label: '12M' }]} />}
      />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {isLoading || !k ? Array.from({ length: 4 }, (_, i) => <Skeleton key={i} className="h-[132px] rounded-xl" />) : (
          <>
            <StatCard variant="deep" title="Settled revenue" value={formatMoney(k.settled_revenue_minor, currency)} trend={k.settled_revenue_change_pct} hint="vs previous period" icon={<Wallet />} />
            <StatCard title="Unpaid receivables" value={formatMoney(k.receivables_minor, currency)} hint={k.overdue_count > 0 ? <span className="font-semibold text-rose-600">{k.overdue_count} overdue</span> : 'Nothing overdue'} icon={<TriangleAlert />} />
            <StatCard title={k.monthly_quota === null ? 'Plan' : 'Free slips left'} value={k.monthly_quota === null ? 'Unlimited' : `${k.free_remaining} / ${k.monthly_quota}`} hint={`${k.paid_credits} non-expiring credits`} icon={<Coins />} />
            <StatCard title="Messaging calls saved" value={k.api_calls_saved.toLocaleString()} hint={`${k.slips_issued} slips issued`} icon={<MessageCircle />} />
          </>
        )}
      </div>

      <div className="grid gap-6 xl:grid-cols-3">
        <Card className="xl:col-span-2">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <h2 className="font-semibold">Revenue vs. invoiced</h2>
            <div className="flex gap-4 text-xs text-ink-secondary">
              <span className="inline-flex items-center gap-1.5"><span className="size-2 rounded-full bg-netslip-primary" />Cash received</span>
              <span className="inline-flex items-center gap-1.5"><span className="size-2 rounded-full bg-amber-400" />Invoiced</span>
            </div>
          </div>
          <div className="mt-4 h-72">
            {isLoading || !data ? <Skeleton className="h-full" /> : (
              <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={data.series} margin={{ left: 4, right: 8, top: 8, bottom: 0 }}>
                  <defs>
                    <linearGradient id="rev" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stopColor="#377581" stopOpacity={0.35} /><stop offset="100%" stopColor="#377581" stopOpacity={0} /></linearGradient>
                    <linearGradient id="inv" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stopColor="#fbbf24" stopOpacity={0.25} /><stop offset="100%" stopColor="#fbbf24" stopOpacity={0} /></linearGradient>
                  </defs>
                  <CartesianGrid stroke="#e2e8f0" strokeDasharray="3 3" vertical={false} />
                  <XAxis dataKey="bucket" tickFormatter={(v: string) => formatDate(v, range === '12m' ? { month: 'short' } : { day: 'numeric', month: 'short' })} tick={{ fontSize: 11, fill: '#94a3b8' }} axisLine={false} tickLine={false} minTickGap={24} />
                  <YAxis tickFormatter={(v: number) => formatMoneyCompact(v, currency)} tick={{ fontSize: 11, fill: '#94a3b8', fontFamily: 'Inter' }} axisLine={false} tickLine={false} width={56} />
                  <Tooltip
                    formatter={(value, name) => [formatMoney(Number(value), currency), name === 'revenue_minor' ? 'Cash received' : 'Invoiced']}
                    labelFormatter={(label) => formatDate(String(label))}
                    contentStyle={{ borderRadius: 12, border: '1px solid #e2e8f0', fontSize: 12 }}
                  />
                  <Area type="monotone" dataKey="invoiced_minor" stroke="#f59e0b" strokeWidth={2} fill="url(#inv)" />
                  <Area type="monotone" dataKey="revenue_minor" stroke="#377581" strokeWidth={2.5} fill="url(#rev)" />
                </AreaChart>
              </ResponsiveContainer>
            )}
          </div>
        </Card>

        <Card>
          <h2 className="font-semibold">Sales by channel</h2>
          {isLoading || !data ? <Skeleton className="mt-4 h-64" /> : channelTotal === 0 ? (
            <EmptyState icon={<MessageCircle />} title="No slips yet" description="Link WhatsApp or Telegram to start." action={<Button to="/dashboard/channels" size="sm" variant="secondary">Connect a channel</Button>} />
          ) : (
            <>
              <div className="relative mx-auto mt-2 h-44 w-44">
                <ResponsiveContainer width="100%" height="100%">
                  <PieChart>
                    <Pie data={data.channels.filter((c) => c.count > 0)} dataKey="count" nameKey="label" innerRadius={52} outerRadius={80} paddingAngle={2} stroke="none">
                      {data.channels.filter((c) => c.count > 0).map((c) => <Cell key={c.channel} fill={CHANNEL_COLORS[c.channel] ?? '#94a3b8'} />)}
                    </Pie>
                    <Tooltip formatter={(v) => [`${String(v)} slips`, '']} contentStyle={{ borderRadius: 12, border: '1px solid #e2e8f0', fontSize: 12 }} />
                  </PieChart>
                </ResponsiveContainer>
                <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                  <span className="tabular text-2xl font-bold">{channelTotal}</span><span className="text-xs text-ink-muted">slips</span>
                </div>
              </div>
              <ul className="mt-4 space-y-2">
                {data.channels.map((c) => (
                  <li key={c.channel} className="flex items-center justify-between text-sm">
                    <span className="inline-flex items-center gap-2"><span className="size-2.5 rounded-full" style={{ background: CHANNEL_COLORS[c.channel] }} />{c.label}</span>
                    <span className="tabular text-ink-secondary">{c.count} · {formatMoneyCompact(c.total_minor, currency)}</span>
                  </li>
                ))}
              </ul>
            </>
          )}
        </Card>
      </div>

      <div className="grid gap-6 xl:grid-cols-3">
        <div className="xl:col-span-2">
          <div className="mb-3 flex items-center justify-between">
            <h2 className="font-semibold">Recent slips</h2>
            <Button to="/dashboard/documents" variant="ghost" size="sm" rightIcon={<ArrowRight className="size-4" />}>View ledger</Button>
          </div>
          <DataTable<Doc>
            data={data?.recent}
            isLoading={isLoading}
            getRowKey={(d) => d.id}
            onRowClick={(d) => navigate(`/dashboard/documents/${d.id}`)}
            emptyState={<EmptyState icon={<FileText />} title="Your first slip is one message away" action={<Button to="/dashboard/composer" size="sm">Create a slip</Button>} />}
            mobileCard={(d) => (
              <div className="flex items-center justify-between gap-3">
                <div className="min-w-0"><p className="tabular text-sm font-semibold">{d.doc_number}</p><p className="truncate text-xs text-ink-muted">{d.customer_name}</p></div>
                <div className="text-right"><Money amountMinor={d.total_minor} currency={d.currency} size="sm" className="font-semibold" /><div className="mt-1"><StatusBadge status={d.status} size="sm" /></div></div>
              </div>
            )}
            columns={[
              { key: 'n', header: 'Number', cell: (d) => <span className="tabular font-semibold">{d.doc_number}</span> },
              { key: 'c', header: 'Customer', cell: (d) => d.customer_name },
              { key: 'ch', header: 'Channel', cell: (d) => <StatusBadge status={d.channel} size="sm" />, hideOnMobile: true },
              { key: 't', header: 'Total', align: 'right', cell: (d) => <Money amountMinor={d.total_minor} currency={d.currency} size="sm" className="font-semibold" /> },
              { key: 's', header: 'Status', cell: (d) => <StatusBadge status={d.status} size="sm" /> },
            ]}
          />
        </div>
        <div className="space-y-6">
          <Card>
            <div className="flex items-center justify-between"><h2 className="font-semibold">Top debtors</h2><Button to="/dashboard/customers?debtors=1" variant="ghost" size="xs">All</Button></div>
            {isLoading ? <Skeleton className="mt-4 h-32" /> : data && data.debtors.length > 0 ? (
              <ul className="mt-3 divide-y divide-border-subtle">
                {data.debtors.map((d) => (
                  <li key={`${d.customer_name}${d.customer_phone}`} className="flex items-center justify-between gap-3 py-2.5">
                    <div className="min-w-0"><p className="truncate text-sm font-medium">{d.customer_name}</p><p className="text-xs text-ink-muted">{d.invoices} invoice(s)</p></div>
                    <Money amountMinor={d.outstanding_minor} currency={currency} size="sm" className="font-semibold text-rose-600" />
                  </li>
                ))}
              </ul>
            ) : <p className="mt-3 text-sm text-ink-muted">Nobody owes you. 🎉</p>}
          </Card>
          {data && data.low_stock.length > 0 && (
            <Card>
              <h2 className="flex items-center gap-2 font-semibold"><Package className="size-4 text-amber-500" />Low stock</h2>
              <ul className="mt-3 space-y-2">
                {data.low_stock.map((s) => (
                  <li key={s.id} className="flex items-center justify-between text-sm"><span className="truncate">{s.name}</span><StatusBadge status="PARTIAL" label={`${s.stock_quantity} left`} size="sm" /></li>
                ))}
              </ul>
            </Card>
          )}
        </div>
      </div>
    </div>
  )
}
