import { ChevronLeft, ChevronRight } from 'lucide-react'
import { Button } from './Button'

export function Pagination({ page, lastPage, total, from, to, onPage }: { page: number; lastPage: number; total: number; from: number | null; to: number | null; onPage: (page: number) => void }) {
  return (
    <nav className="flex items-center justify-between gap-3 text-sm" aria-label="Pagination">
      <p className="text-ink-secondary">
        <span className="tabular font-semibold text-ink-primary">{from ?? 0}–{to ?? 0}</span> of <span className="tabular font-semibold text-ink-primary">{total}</span>
      </p>
      <div className="flex gap-2">
        <Button variant="outline" size="sm" leftIcon={<ChevronLeft className="size-4" />} disabled={page <= 1} onClick={() => onPage(page - 1)}>Prev</Button>
        <Button variant="outline" size="sm" rightIcon={<ChevronRight className="size-4" />} disabled={page >= lastPage} onClick={() => onPage(page + 1)}>Next</Button>
      </div>
    </nav>
  )
}
