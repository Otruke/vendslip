<x-admin.layout :title="$merchant->name">
    <x-ui.page-header :eyebrow="'Merchant · '.$merchant->slug" :title="$merchant->name" :subtitle="collect([$merchant->email, $merchant->phone, $owner?->name ? 'Owner: '.$owner->name : null])->filter()->implode(' · ')">
        <x-slot:actions>
            <x-ui.status-badge :status="$merchant->status" />
            <x-ui.status-badge :status="$merchant->effectiveTier()" />
            <x-ui.button variant="outline" icon="file-edit" type="button" data-open="plan-modal">Change plan</x-ui.button>
            <x-ui.button variant="outline" icon="coins" type="button" data-open="credits-modal">Adjust credits</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($merchant->isSuspended())
        <x-ui.alert tone="danger">Suspended: {{ $merchant->suspension_reason }}</x-ui.alert>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card title="Slips issued" :value="number_format($stats['documents'])" icon="file-text" />
        <x-ui.stat-card title="Total billed" :value="\App\Support\Money::format($stats['billed'], $merchant->currency)" icon="trending-up" />
        <x-ui.stat-card title="Quota · credits" :value="($stats['quota'] === null ? 'Unlimited' : $stats['free_used'].' / '.$stats['quota']).' · '.$merchant->paid_credits_balance" hint="free used · paid credits" icon="coins" />
        <x-ui.stat-card title="API calls saved" :value="number_format($stats['saved_calls'])" hint="silent draft buffering" icon="message" />
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
        <x-ui.card variant="elevated" class="lg:col-span-3">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <x-ui.heading level="2" variant="card">Ledger mutability policy</x-ui.heading>
                    <x-ui.text variant="small" class="mt-1">Controls whether this merchant can edit slips after issue.</x-ui.text>
                </div>
                <x-ui.status-badge :status="$merchant->record_mutability_mode" />
            </div>
            <form method="POST" action="{{ route('admin.merchants.mutability', $merchant) }}" class="mt-5 space-y-5" data-confirm="Change this merchant's ledger policy? The change is audit-logged.">
                @csrf @method('PUT')
                <fieldset class="grid gap-3 sm:grid-cols-3">
                    <legend class="sr-only">Mode</legend>
                    @foreach ([
                        ['IMMUTABLE', 'Immutable', 'Locked after issue. Credit notes and voids only.', 'lock'],
                        ['MUTABLE_WITH_AUDIT', 'Mutable · audited', 'Edits need a reason. Before/after snapshot + IP.', 'history'],
                        ['MUTABLE_UNRESTRICTED', 'Unrestricted', 'Free edits. Still snapshotted for forensics.', 'file-edit'],
                    ] as [$value, $label, $hint, $icon])
                        <label class="relative flex cursor-pointer flex-col rounded-xl border border-border-subtle p-4 transition has-[:checked]:border-netslip-primary has-[:checked]:bg-netslip-tint">
                            <input type="radio" name="record_mutability_mode" value="{{ $value }}" class="sr-only" @checked($merchant->record_mutability_mode->value === $value)>
                            <x-ui.icon :name="$icon" class="size-5 text-netslip-deep" />
                            <span class="mt-2 text-sm font-semibold">{{ $label }}</span>
                            <span class="mt-1 text-xs text-ink-secondary">{{ $hint }}</span>
                        </label>
                    @endforeach
                </fieldset>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.toggle name="allow_staff_edits" label="Allow staff edits" description="Sales agents & accountants may edit, not just owners/managers." :checked="$merchant->allow_staff_edits" />
                    <x-ui.input name="mutable_window_hours" type="number" min="0" max="8760" label="Edit window (hours)" :value="$merchant->mutable_window_hours" helper="0 = no time limit (audited mode only)." />
                </div>
                <div class="flex justify-end"><x-ui.button icon="shield">Save policy</x-ui.button></div>
            </form>
        </x-ui.card>

        <x-ui.card class="lg:col-span-2">
            <x-ui.heading level="2" variant="card">Account</x-ui.heading>
            <dl class="mt-4 space-y-2.5 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-ink-secondary">Plan expires</dt><dd class="font-medium">{{ $merchant->plan_expires_at?->format('d M Y') ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-secondary">Billing cycle</dt><dd class="font-medium">{{ $merchant->billing_cycle?->label() ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-secondary">Team members</dt><dd class="tabular font-medium">{{ $members }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-secondary">VAT</dt><dd class="tabular font-medium">{{ rtrim(rtrim(number_format($merchant->vat_rate_bps / 100, 2), '0'), '.') }}% {{ $merchant->vat_inclusive ? 'incl.' : 'excl.' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-secondary">Gateways</dt><dd class="font-medium">{{ collect(['Flutterwave' => $merchant->hasFlutterwave(), 'Crypto' => $merchant->hasNowPayments()])->filter()->keys()->implode(', ') ?: 'None' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-secondary">Joined</dt><dd class="font-medium">{{ $merchant->created_at->format('d M Y') }}</dd></div>
            </dl>
            <form method="POST" action="{{ route('admin.merchants.status', $merchant) }}" class="mt-5 space-y-3 border-t border-border-subtle pt-5" data-confirm="Change this merchant's account status?">
                @csrf @method('PUT')
                @if ($merchant->isSuspended())
                    <input type="hidden" name="status" value="ACTIVE">
                    <x-ui.button variant="success" icon="play" full-width>Reactivate account</x-ui.button>
                @else
                    <input type="hidden" name="status" value="SUSPENDED">
                    <x-ui.input name="reason" label="Suspension reason" placeholder="e.g. chargeback investigation" />
                    <x-ui.button variant="danger" icon="pause" full-width>Suspend account</x-ui.button>
                @endif
            </form>
        </x-ui.card>
    </div>

    <div>
        <x-ui.heading level="2" variant="card" class="mb-3">Recent slips & forensic revisions</x-ui.heading>
        <x-ui.data-table :headers="['Number', 'Customer', 'Channel', ['label' => 'Total', 'align' => 'right'], 'Status', 'Rev', '']" empty="No slips yet.">
            @foreach ($documents as $doc)
                <tr class="hover:bg-surface-canvas">
                    <td class="px-4 py-3 font-mono text-xs font-semibold">{{ $doc->doc_number }}</td>
                    <td class="px-4 py-3">{{ $doc->customer_name }}</td>
                    <td class="px-4 py-3"><x-ui.status-badge :status="$doc->channel" size="sm" /></td>
                    <td class="px-4 py-3 text-right"><x-ui.money :amount-minor="$doc->total_minor" :currency="$doc->currency" size="sm" /></td>
                    <td class="px-4 py-3"><x-ui.status-badge :status="$doc->status" size="sm" /></td>
                    <td class="tabular px-4 py-3">{{ $doc->revision_number }}</td>
                    <td class="px-4 py-3 text-right">
                        @if ($doc->revisions_count > 0)
                            <x-ui.button variant="ghost" size="xs" icon="history" type="button" data-open="rev-{{ $doc->id }}">{{ $doc->revisions_count }} change(s)</x-ui.button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.data-table>
    </div>

    @foreach ($documents->where('revisions_count', '>', 0) as $doc)
        <x-ui.drawer :id="'rev-'.$doc->id" :title="'Revision history · '.$doc->doc_number">
            <ol class="space-y-5">
                @foreach ($doc->revisions as $rev)
                    <li class="rounded-xl border border-border-subtle p-4">
                        <div class="flex items-center justify-between gap-2">
                            <x-ui.status-badge :status="$rev->action === 'VOID' ? 'VOID' : ($rev->action === 'PAYMENT' ? 'PAID' : 'MUTABLE_WITH_AUDIT')" size="sm">{{ $rev->action }} · rev {{ $rev->revision_number }}</x-ui.status-badge>
                            <span class="text-xs text-ink-muted">{{ $rev->created_at->format('d M Y, H:i') }}</span>
                        </div>
                        <p class="mt-2 text-sm"><span class="font-semibold">{{ $rev->actor?->name ?? 'System' }}</span> <span class="text-ink-muted">· IP {{ $rev->actor_ip ?? '—' }}</span></p>
                        @if ($rev->edit_reason)<p class="mt-1 rounded-lg bg-surface-canvas px-3 py-2 text-sm text-ink-secondary">“{{ $rev->edit_reason }}”</p>@endif
                        @php $diff = $rev->diff(); @endphp
                        @if ($diff !== [])
                            <table class="mt-3 w-full text-xs">
                                <thead><tr class="text-left text-ink-muted"><th class="py-1 pr-2 font-semibold">Field</th><th class="py-1 pr-2 font-semibold">Before</th><th class="py-1 font-semibold">After</th></tr></thead>
                                <tbody class="divide-y divide-border-subtle">
                                    @foreach ($diff as $field => $change)
                                        <tr class="align-top">
                                            <td class="py-1.5 pr-2 font-mono">{{ $field }}</td>
                                            @php
                                                $show = static fn (mixed $v): string => str_ends_with($field, '_minor') && is_int($v)
                                                    ? \App\Support\Money::format($v, $doc->currency)
                                                    : (is_scalar($v) || $v === null ? (string) json_encode($v) : (string) json_encode($v, JSON_UNESCAPED_UNICODE));
                                            @endphp
                                            <td class="py-1.5 pr-2 text-rose-700 line-through decoration-rose-300">{{ $show($change['before']) }}</td>
                                            <td class="py-1.5 text-emerald-700">{{ $show($change['after']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </li>
                @endforeach
            </ol>
        </x-ui.drawer>
    @endforeach

    <x-ui.modal id="plan-modal" title="Override plan">
        <form method="POST" action="{{ route('admin.merchants.plan', $merchant) }}" class="space-y-4">
            @csrf @method('PUT')
            <x-ui.select name="plan_tier" label="Plan" :value="$merchant->plan_tier" :options="collect(\App\Enums\PlanTier::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()" />
            <x-ui.select name="billing_cycle" label="Billing cycle" :value="$merchant->billing_cycle" :options="['MONTHLY' => 'Monthly', 'YEARLY' => 'Yearly']" />
            <x-ui.input name="plan_expires_at" type="date" label="Expires on" :value="$merchant->plan_expires_at?->toDateString()" helper="Required for paid plans." />
            <x-ui.input name="reason" label="Reason (audit log)" placeholder="e.g. Partner promo" required />
            <div class="flex justify-end gap-2"><x-ui.button variant="ghost" type="button" data-close>Cancel</x-ui.button><x-ui.button>Save plan</x-ui.button></div>
        </form>
    </x-ui.modal>

    <x-ui.modal id="credits-modal" title="Adjust PAYG credits" size="sm">
        <form method="POST" action="{{ route('admin.merchants.credits', $merchant) }}" class="space-y-4">
            @csrf
            <x-ui.text>Current balance: <strong class="tabular">{{ $merchant->paid_credits_balance }}</strong></x-ui.text>
            <x-ui.input name="delta" type="number" label="Change (+/−)" placeholder="50" required />
            <x-ui.input name="reason" label="Reason (audit log)" placeholder="e.g. Goodwill credit" required />
            <div class="flex justify-end gap-2"><x-ui.button variant="ghost" type="button" data-close>Cancel</x-ui.button><x-ui.button>Apply</x-ui.button></div>
        </form>
    </x-ui.modal>
</x-admin.layout>
