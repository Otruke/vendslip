<x-admin.layout title="Security Logs">
    <x-ui.page-header title="Security audit log" subtitle="Append-only trail of sign-ins, policy changes and webhook rejections." />
    <form method="GET" class="grid gap-3 rounded-xl border border-border-subtle bg-white p-4 sm:grid-cols-4">
        <x-ui.select name="event" :value="$filters['event'] ?? ''" placeholder="All events" aria-label="Event" :options="$events->mapWithKeys(fn ($e) => [$e => $e])->all()" />
        <x-ui.select name="severity" :value="$filters['severity'] ?? ''" placeholder="Any severity" aria-label="Severity" :options="['INFO' => 'Info', 'WARNING' => 'Warning', 'CRITICAL' => 'Critical']" />
        <x-ui.input name="merchant" :value="$filters['merchant'] ?? ''" placeholder="Merchant ID" aria-label="Merchant" />
        <x-ui.button variant="outline" icon="search">Filter</x-ui.button>
    </form>
    <x-ui.data-table :headers="['When', 'Event', 'Severity', 'Actor', 'Merchant', 'IP', 'Context']" :paginator="$logs" empty="No events match.">
        @foreach ($logs as $log)
            <tr class="align-top hover:bg-surface-canvas">
                <td class="whitespace-nowrap px-4 py-3 text-ink-secondary">{{ $log->created_at->format('d M, H:i:s') }}</td>
                <td class="px-4 py-3 font-mono text-xs">{{ $log->event }}</td>
                <td class="px-4 py-3"><x-ui.status-badge :status="$log->severity" size="sm" /></td>
                <td class="px-4 py-3">{{ $log->actor?->email ?? '—' }}</td>
                <td class="px-4 py-3">@if ($log->merchant)<a class="text-netslip-primary hover:underline" href="{{ route('admin.merchants.show', $log->merchant) }}">{{ $log->merchant->name }}</a>@else — @endif</td>
                <td class="px-4 py-3 font-mono text-xs">{{ $log->ip ?? '—' }}</td>
                <td class="max-w-xs px-4 py-3"><code class="block truncate text-[11px] text-ink-secondary" title="{{ json_encode($log->context) }}">{{ $log->context ? json_encode($log->context) : '' }}</code></td>
            </tr>
        @endforeach
    </x-ui.data-table>
</x-admin.layout>
