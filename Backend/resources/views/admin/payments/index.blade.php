<x-admin.layout title="Payments">
    <x-ui.page-header title="Payment transactions" subtitle="Plans, credit packs and customer invoice payments across gateways." />
    <form method="GET" class="grid gap-3 rounded-xl border border-border-subtle bg-white p-4 sm:grid-cols-5">
        <x-ui.input name="q" :value="$filters['q'] ?? ''" placeholder="Reference" aria-label="Reference" />
        <x-ui.select name="status" :value="$filters['status'] ?? ''" placeholder="Any status" aria-label="Status" :options="collect(\App\Enums\PaymentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
        <x-ui.select name="purpose" :value="$filters['purpose'] ?? ''" placeholder="Any purpose" aria-label="Purpose" :options="collect(\App\Enums\PaymentPurpose::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
        <x-ui.select name="gateway" :value="$filters['gateway'] ?? ''" placeholder="Any gateway" aria-label="Gateway" :options="collect(\App\Enums\PaymentGateway::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
        <x-ui.button variant="outline" icon="search">Filter</x-ui.button>
    </form>
    <x-ui.data-table :headers="['Reference', 'Merchant', 'Purpose', 'Gateway', ['label' => 'Amount', 'align' => 'right'], 'Status', 'Created', 'Paid']" :paginator="$payments" empty="No transactions match.">
        @foreach ($payments as $p)
            <tr class="hover:bg-surface-canvas">
                <td class="px-4 py-3 font-mono text-xs">{{ $p->reference }}<span class="block text-ink-muted">{{ $p->gateway_reference }}</span></td>
                <td class="px-4 py-3">@if ($p->merchant)<a class="hover:text-netslip-primary" href="{{ route('admin.merchants.show', $p->merchant) }}">{{ $p->merchant->name }}</a>@else — @endif</td>
                <td class="px-4 py-3">{{ $p->purpose->label() }}@if ($p->credits) · {{ $p->credits }} cr @endif @if ($p->plan_tier) · {{ $p->plan_tier->label() }} @endif</td>
                <td class="px-4 py-3">{{ $p->gateway->label() }}</td>
                <td class="px-4 py-3 text-right"><x-ui.money :amount-minor="$p->amount_minor" :currency="$p->currency" size="sm" /></td>
                <td class="px-4 py-3"><x-ui.status-badge :status="$p->status" size="sm" /></td>
                <td class="whitespace-nowrap px-4 py-3 text-ink-secondary">{{ $p->created_at->format('d M, H:i') }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-ink-secondary">{{ $p->paid_at?->format('d M, H:i') ?? '—' }}</td>
            </tr>
        @endforeach
    </x-ui.data-table>
</x-admin.layout>
