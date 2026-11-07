import { History, LifeBuoy, ShieldAlert } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Card, EmptyState, PageHeader, Pagination, SegmentedTabs, Skeleton } from '@/components/ui'
import { useActivity } from '@/hooks/queries'
import { cn } from '@/lib/cn'
import { useCan } from '@/stores/auth'

type Category = 'all' | 'sales' | 'security' | 'settings' | 'team' | 'support'

const when = (iso: string) => new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(iso))

function detailText(details: Record<string, unknown> | null): string | null {
  if (!details) return null
  const parts: string[] = []
  if (typeof details.summary === 'string') parts.push(details.summary)
  if (Array.isArray(details.fields) && details.fields.length > 0) parts.push(`Changed: ${details.fields.map(String).join(', ').replace(/_/g, ' ')}`)
  if (Array.isArray(details.changes) && details.changes.length > 0) parts.push(`Changed: ${details.changes.map(String).join(', ').replace(/_/g, ' ')}`)
  if (typeof details.reason === 'string') parts.push(`Reason: ${details.reason}`)
  if (typeof details.email === 'string') parts.push(details.email)
  if (typeof details.role === 'string') parts.push(details.role)
  return parts.length > 0 ? parts.join(' · ') : null
}

/** Who did what in this business, including anything VendSlip support did with the owner's approval. */
export default function Activity() {
  const canView = useCan('settings.manage')
  const [category, setCategory] = useState<Category>('all')
  const [page, setPage] = useState(1)
  const { data, isLoading } = useActivity({ category: category === 'all' ? undefined : category, page }, canView)

  if (!canView) return <Card padding="lg"><EmptyState icon={<History />} title="Activity log" description="Only the business’s Owners can see the activity log." /></Card>

  return (
    <div className="space-y-6">
      <PageHeader title="Activity log" subtitle="Every change made in this business: who, what, when and from where." />
      <SegmentedTabs<Category> value={category} onChange={(v) => { setCategory(v); setPage(1) }} size="sm" options={[
        { value: 'all', label: 'All' }, { value: 'sales', label: 'Sales' }, { value: 'security', label: 'Security' },
        { value: 'settings', label: 'Settings' }, { value: 'team', label: 'Team' }, { value: 'support', label: 'Support' },
      ]} />
      <Card padding="none">
        {isLoading || !data ? (
          <div className="space-y-3 p-5">{[0, 1, 2, 3].map((i) => <Skeleton key={i} className="h-12 w-full" />)}</div>
        ) : data.data.length === 0 ? (
          <EmptyState icon={<History />} title="Nothing here yet" description="Changes appear here as your team works." />
        ) : (
          <ul className="divide-y divide-border-subtle">
            {data.data.map((a) => {
              const detail = detailText(a.details)
              const warn = a.severity !== 'INFO'
              return (
                <li key={a.id} className="flex gap-3 px-5 py-3.5">
                  <span className={cn('mt-0.5 inline-flex size-8 shrink-0 items-center justify-center rounded-lg',
                    a.support_admin ? 'bg-vendslip-obsidian text-white' : warn ? 'bg-tone-partial/10 text-tone-partial' : 'bg-vendslip-tint text-vendslip-deep')}>
                    {a.support_admin ? <LifeBuoy className="size-4" /> : warn ? <ShieldAlert className="size-4" /> : <History className="size-4" />}
                  </span>
                  <div className="min-w-0 flex-1">
                    <p className="text-sm text-ink-primary">
                      <span className="font-semibold">{a.actor ?? 'System'}</span> · {a.label}
                      {a.document_id && <> · <Link to={`/dashboard/documents/${a.document_id}`} className="font-medium text-vendslip-primary hover:underline">Open</Link></>}
                    </p>
                    {detail && <p className="mt-0.5 break-words text-xs text-ink-secondary">{detail}</p>}
                    {a.support_admin && <p className="mt-0.5 text-xs font-medium text-vendslip-deep">Done by {a.support_admin} (VendSlip support) during an owner-approved session</p>}
                    <p className="tabular mt-1 text-[11px] text-ink-muted">{[when(a.created_at), a.device, a.ip].filter(Boolean).join(' · ')}</p>
                  </div>
                </li>
              )
            })}
          </ul>
        )}
      </Card>
      {data && data.meta.last_page > 1 && (
        <Pagination page={data.meta.current_page} lastPage={data.meta.last_page} total={data.meta.total} from={(data.meta.current_page - 1) * 30 + 1} to={Math.min(data.meta.current_page * 30, data.meta.total)} onPage={setPage} />
      )}
    </div>
  )
}
