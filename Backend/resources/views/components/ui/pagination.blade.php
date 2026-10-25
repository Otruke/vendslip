@if ($paginator->hasPages())
    <nav class="flex items-center justify-between gap-3 text-sm" aria-label="Pagination">
        <p class="text-ink-secondary">Showing <span class="tabular font-semibold">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span> of <span class="tabular font-semibold">{{ $paginator->total() }}</span></p>
        <div class="flex gap-2">
            @if ($paginator->onFirstPage())
                <span class="rounded-lg border border-border-subtle px-3 py-1.5 text-ink-muted">Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="rounded-lg border border-slate-300 px-3 py-1.5 font-medium hover:bg-surface-canvas">Previous</a>
            @endif
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="rounded-lg border border-slate-300 px-3 py-1.5 font-medium hover:bg-surface-canvas">Next</a>
            @else
                <span class="rounded-lg border border-border-subtle px-3 py-1.5 text-ink-muted">Next</span>
            @endif
        </div>
    </nav>
@endif
