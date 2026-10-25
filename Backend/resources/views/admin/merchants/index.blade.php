<x-admin.layout title="Merchants">
    <x-ui.page-header title="Merchants" subtitle="Plans, ledger policy and account status per client." />

    <form method="GET" class="grid gap-3 rounded-xl border border-border-subtle bg-white p-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="lg:col-span-2"><x-ui.input name="q" :value="$filters['q'] ?? ''" placeholder="Search name, email or slug" aria-label="Search" /></div>
        <x-ui.select name="tier" :value="$filters['tier'] ?? ''" placeholder="All plans" aria-label="Plan" :options="collect(\App\Enums\PlanTier::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()" />
        <x-ui.select name="mode" :value="$filters['mode'] ?? ''" placeholder="Any ledger policy" aria-label="Mutability" :options="collect(\App\Enums\MutabilityMode::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()" />
        <div class="flex gap-2"><x-ui.select name="status" :value="$filters['status'] ?? ''" placeholder="Any status" aria-label="Status" :options="['ACTIVE' => 'Active', 'SUSPENDED' => 'Suspended']" /><x-ui.button variant="outline" icon="search">Filter</x-ui.button></div>
    </form>

    <x-ui.data-table :headers="['Merchant', 'Plan', 'Ledger policy', ['label' => 'Slips', 'align' => 'right'], ['label' => 'Credits', 'align' => 'right'], 'Status', 'Joined', '']" :paginator="$merchants" empty="No merchants match.">
        @foreach ($merchants as $m)
            <tr class="hover:bg-surface-canvas">
                <td class="px-4 py-3"><a href="{{ route('admin.merchants.show', $m) }}" class="font-semibold text-ink-primary hover:text-netslip-primary">{{ $m->name }}</a><p class="text-xs text-ink-muted">{{ $m->email }}</p></td>
                <td class="px-4 py-3"><x-ui.status-badge :status="$m->effectiveTier()" size="sm" /></td>
                <td class="px-4 py-3"><x-ui.status-badge :status="$m->record_mutability_mode" size="sm" /></td>
                <td class="tabular px-4 py-3 text-right">{{ number_format($m->documents_count) }}</td>
                <td class="tabular px-4 py-3 text-right">{{ number_format($m->paid_credits_balance) }}</td>
                <td class="px-4 py-3"><x-ui.status-badge :status="$m->status" size="sm" /></td>
                <td class="px-4 py-3 text-ink-secondary">{{ $m->created_at->format('d M Y') }}</td>
                <td class="px-4 py-3 text-right"><x-ui.icon-button icon="chevron-right" label="Open" :href="route('admin.merchants.show', $m)" /></td>
            </tr>
        @endforeach
    </x-ui.data-table>
</x-admin.layout>
