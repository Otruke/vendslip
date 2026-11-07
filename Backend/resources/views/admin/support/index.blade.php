<x-admin.layout title="Support">
    <x-ui.page-header eyebrow="Support console" title="Support tickets" :subtitle="$me->isQaReviewer() ? 'Read every conversation, add internal notes and score the support given.' : 'Answer businesses, take tickets from the queue and resolve them.'" />

    @php
        $tabs = [
            'queue' => ['Waiting for support', $counts['queue']],
            'mine' => ['Assigned to me', $counts['mine']],
            'unassigned' => ['Unassigned', $counts['unassigned']],
            'unreviewed' => ['Needs QA review', $counts['unreviewed']],
            'resolved' => ['Resolved', null],
            'all' => ['All', null],
        ];
        if ($me->isQaReviewer()) {
            unset($tabs['mine']);
        }
    @endphp
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <nav class="flex flex-wrap gap-1.5" aria-label="Ticket views">
            @foreach ($tabs as $key => [$label, $count])
                <a href="{{ route('admin.support.index', ['view' => $key]) }}" @class([
                    'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium',
                    'border-vendslip-primary bg-vendslip-primary text-white' => $view === $key,
                    'border-border-subtle bg-white text-ink-secondary hover:border-vendslip-primary hover:text-ink-primary' => $view !== $key,
                ]) @if ($view === $key) aria-current="page" @endif>
                    {{ $label }}@if ($count !== null)<span @class(['tabular rounded-full px-1.5 text-xs', 'bg-white/20' => $view === $key, 'bg-surface-subtle' => $view !== $key])>{{ $count }}</span>@endif
                </a>
            @endforeach
        </nav>
        <form method="GET" class="flex gap-2">
            <input type="hidden" name="view" value="{{ $view }}">
            <x-ui.input name="q" :value="$filters['q'] ?? ''" placeholder="Reference, subject or business" aria-label="Search tickets" />
            <x-ui.button variant="outline" icon="search">Search</x-ui.button>
        </form>
    </div>

    <x-ui.data-table :headers="['Ticket', 'Business', 'Status', 'Priority', 'Assigned', 'QA', ['label' => 'Last activity', 'align' => 'right']]" :paginator="$tickets" empty="No tickets here.">
        @foreach ($tickets as $t)
            <tr class="hover:bg-surface-canvas">
                <td class="px-4 py-3">
                    <a href="{{ route('admin.support.show', $t) }}" class="font-semibold text-ink-primary hover:text-vendslip-primary">{{ $t->subject }}</a>
                    <span class="block font-mono text-xs text-ink-muted">{{ $t->reference }} · {{ \App\Models\SupportTicket::CATEGORIES[$t->category] ?? $t->category }}</span>
                </td>
                <td class="px-4 py-3">{{ $t->merchant?->name }}<span class="block text-xs text-ink-muted">{{ $t->openedBy?->name }}</span></td>
                <td class="px-4 py-3"><x-ui.status-badge :status="$t->status" size="sm" /></td>
                <td class="px-4 py-3"><x-ui.status-badge :status="$t->priority" size="sm" /></td>
                <td class="px-4 py-3 text-ink-secondary">{{ $t->assignee?->name ?? '—' }}</td>
                <td class="tabular px-4 py-3 text-ink-secondary">{{ $t->qa_score !== null ? $t->qa_score.'/5' : '—' }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-right text-ink-secondary" title="{{ $t->last_activity_at->format('d M Y, H:i') }}">{{ $t->last_activity_at->diffForHumans() }}</td>
            </tr>
        @endforeach
    </x-ui.data-table>
</x-admin.layout>
