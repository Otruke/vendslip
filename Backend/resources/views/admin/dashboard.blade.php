<x-admin.layout title="Command Center">
    <x-ui.page-header eyebrow="Super-Admin" title="Command Center" subtitle="Revenue, merchants and platform health at a glance." />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card variant="deep" title="Platform MRR" :value="\App\Support\Money::format($mrr)" :hint="'ARR '.\App\Support\Money::format($arr)" icon="trending-up" />
        <x-ui.stat-card title="Merchants" :value="number_format($merchantsTotal)" :trend="'+'.$merchantsNew30" hint="joined in 30 days" icon="store" />
        <x-ui.stat-card title="Credit packs · this month" :value="\App\Support\Money::format($packFiat + $packCrypto)" :hint="'Fiat '.\App\Support\Money::format($packFiat).' · Crypto '.\App\Support\Money::format($packCrypto)" icon="coins" />
        <x-ui.stat-card title="Slips · 30 days" :value="number_format($slips30)" :hint="'Queue: '.$queue['pending'].' pending · '.$queue['failed'].' failed'" icon="file-text" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card>
            <x-ui.heading level="2" variant="card">Merchants by plan</x-ui.heading>
            <ul class="mt-4 space-y-3">
                @foreach (\App\Enums\PlanTier::cases() as $tier)
                    @php $n = (int) ($tiers[$tier->value] ?? 0); $pct = $merchantsTotal > 0 ? round($n / $merchantsTotal * 100) : 0; @endphp
                    <li>
                        <div class="flex items-center justify-between text-sm"><x-ui.status-badge :status="$tier" size="sm" /><span class="tabular font-semibold">{{ $n }}</span></div>
                        <div class="mt-1.5 h-1.5 rounded-full bg-surface-subtle"><div class="h-1.5 rounded-full bg-netslip-primary" style="width: {{ $pct }}%"></div></div>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
        <x-ui.card>
            <x-ui.heading level="2" variant="card">Channel volume · 30 days</x-ui.heading>
            <ul class="mt-4 space-y-3">
                @forelse ($channels as $channel => $n)
                    @php $pct = $slips30 > 0 ? round($n / $slips30 * 100) : 0; @endphp
                    <li>
                        <div class="flex items-center justify-between text-sm"><x-ui.status-badge :status="$channel" size="sm" /><span class="tabular font-semibold">{{ number_format($n) }}</span></div>
                        <div class="mt-1.5 h-1.5 rounded-full bg-surface-subtle"><div class="h-1.5 rounded-full bg-netslip-deep" style="width: {{ $pct }}%"></div></div>
                    </li>
                @empty
                    <li class="text-sm text-ink-muted">No slips yet.</li>
                @endforelse
            </ul>
        </x-ui.card>
        <x-ui.card>
            <x-ui.heading level="2" variant="card">Security watch</x-ui.heading>
            <ul class="mt-4 divide-y divide-border-subtle">
                @forelse ($recentSecurity as $log)
                    <li class="flex items-start justify-between gap-3 py-2.5">
                        <div class="min-w-0"><p class="truncate font-mono text-xs text-ink-primary">{{ $log->event }}</p><p class="text-xs text-ink-muted">{{ $log->created_at->diffForHumans() }} · {{ $log->ip ?? '—' }}</p></div>
                        <x-ui.status-badge :status="$log->severity" size="sm" />
                    </li>
                @empty
                    <li class="py-2.5 text-sm text-ink-muted">All quiet.</li>
                @endforelse
            </ul>
            <a href="{{ route('admin.security.index') }}" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-netslip-primary">All logs <x-ui.icon name="chevron-right" /></a>
        </x-ui.card>
    </div>

    <div>
        <x-ui.heading level="2" variant="card" class="mb-3">Latest payments</x-ui.heading>
        <x-ui.data-table :headers="['Reference', 'Merchant', 'Purpose', 'Gateway', ['label' => 'Amount', 'align' => 'right'], 'Status', 'When']" empty="No payments yet.">
            @foreach ($recentPayments as $p)
                <tr class="hover:bg-surface-canvas">
                    <td class="px-4 py-3 font-mono text-xs">{{ $p->reference }}</td>
                    <td class="px-4 py-3">{{ $p->merchant?->name ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $p->purpose->label() }}</td>
                    <td class="px-4 py-3">{{ $p->gateway->label() }}</td>
                    <td class="px-4 py-3 text-right"><x-ui.money :amount-minor="$p->amount_minor" :currency="$p->currency" size="sm" /></td>
                    <td class="px-4 py-3"><x-ui.status-badge :status="$p->status" size="sm" /></td>
                    <td class="px-4 py-3 text-ink-secondary">{{ $p->created_at->diffForHumans() }}</td>
                </tr>
            @endforeach
        </x-ui.data-table>
    </div>
</x-admin.layout>
