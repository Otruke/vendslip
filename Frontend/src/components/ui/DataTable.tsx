import type { ReactNode } from 'react'
import { cn } from '@/lib/cn'
import { Skeleton } from './Feedback'
import { Pagination } from './Pagination'

export type Column<T> = {
  key: string
  header: ReactNode
  cell: (row: T) => ReactNode
  align?: 'left' | 'right' | 'center'
  className?: string
  /** Hide on small screens (mobile card view shows `mobileLabel` rows instead). */
  hideOnMobile?: boolean
}

export function DataTable<T>({ columns, data, isLoading, emptyState, getRowKey, onRowClick, pagination, mobileCard, className }: {
  columns: Column<T>[]
  data: T[] | undefined
  isLoading?: boolean
  emptyState?: ReactNode
  getRowKey: (row: T) => string | number
  onRowClick?: (row: T) => void
  pagination?: { page: number; lastPage: number; total: number; from: number | null; to: number | null; onPage: (page: number) => void }
  /** Optional compact renderer for < md screens. */
  mobileCard?: (row: T) => ReactNode
  className?: string
}) {
  const rows = data ?? []
  const align = (a?: string) => (a === 'right' ? 'text-right' : a === 'center' ? 'text-center' : 'text-left')

  return (
    <div className={cn('overflow-hidden rounded-xl border border-border-subtle bg-white', className)}>
      {mobileCard && (
        <ul className="divide-y divide-border-subtle md:hidden">
          {isLoading && Array.from({ length: 4 }, (_, i) => <li key={i} className="p-4"><Skeleton className="h-12" /></li>)}
          {!isLoading && rows.map((row) => (
            <li key={getRowKey(row)} className={cn('p-4', onRowClick && 'cursor-pointer active:bg-surface-canvas')} onClick={onRowClick ? () => onRowClick(row) : undefined}>
              {mobileCard(row)}
            </li>
          ))}
        </ul>
      )}
      <div className={cn('overflow-x-auto', mobileCard && 'hidden md:block')}>
        <table className="min-w-full divide-y divide-border-subtle text-sm">
          <thead className="sticky top-0 z-[1] bg-surface-canvas">
            <tr>
              {columns.map((c) => (
                <th key={c.key} scope="col" className={cn('whitespace-nowrap px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-ink-muted', align(c.align), c.hideOnMobile && 'hidden lg:table-cell', c.className)}>
                  {c.header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-border-subtle">
            {isLoading &&
              Array.from({ length: 6 }, (_, i) => (
                <tr key={i}>{columns.map((c) => <td key={c.key} className={cn('px-4 py-3.5', c.hideOnMobile && 'hidden lg:table-cell')}><Skeleton className="h-4 w-full max-w-[140px]" /></td>)}</tr>
              ))}
            {!isLoading && rows.map((row) => (
              <tr
                key={getRowKey(row)}
                onClick={onRowClick ? () => onRowClick(row) : undefined}
                className={cn('transition-colors hover:bg-surface-canvas', onRowClick && 'cursor-pointer')}
              >
                {columns.map((c) => (
                  <td key={c.key} className={cn('whitespace-nowrap px-4 py-3', align(c.align), c.hideOnMobile && 'hidden lg:table-cell', c.className)}>{c.cell(row)}</td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {!isLoading && rows.length === 0 && <div className="px-4 py-12">{emptyState ?? <p className="text-center text-sm text-ink-muted">Nothing here yet.</p>}</div>}
      {pagination && pagination.lastPage > 1 && (
        <div className="border-t border-border-subtle px-4 py-3"><Pagination {...pagination} /></div>
      )}
    </div>
  )
}
