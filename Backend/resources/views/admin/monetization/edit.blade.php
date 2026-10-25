<x-admin.layout title="Pricing Engine">
    <x-ui.page-header eyebrow="Super-Admin" title="Pricing Engine" subtitle="Plans, quotas, features and PAYG credit packs. Changes apply instantly." />

    <div class="grid gap-6 xl:grid-cols-3">
        @foreach ($plans as $plan)
            <x-ui.card variant="elevated">
                <form method="POST" action="{{ route('admin.monetization.plans.update', $plan) }}" class="space-y-4">
                    @csrf @method('PUT')
                    <div class="flex items-center justify-between"><x-ui.status-badge :status="$plan->tier" /><span class="text-xs text-ink-muted">{{ $plan->tier->value }}</span></div>
                    <x-ui.input name="name" label="Display name" :value="$plan->name" />
                    <x-ui.input name="tagline" label="Tagline" :value="$plan->tagline" />
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.input name="monthly_price" label="Monthly (₦)" prefix="₦" :value="number_format($plan->monthly_price_minor / 100, 2, '.', '')" />
                        <x-ui.input name="yearly_price" label="Yearly (₦)" prefix="₦" :value="number_format($plan->yearly_price_minor / 100, 2, '.', '')" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.input name="monthly_quota" type="number" min="0" label="Slips / month" :value="$plan->monthly_quota" helper="Blank = unlimited" />
                        <x-ui.input name="max_team_members" type="number" min="1" label="Team seats" :value="$plan->features['max_team_members'] ?? null" />
                    </div>
                    <fieldset>
                        <legend class="mb-2 text-sm font-medium">Features</legend>
                        <div class="grid gap-2">
                            @foreach ($features as $key => $label)
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="features[]" value="{{ $key }}" @checked($plan->features[$key] ?? false) class="size-4 rounded border-slate-300 text-netslip-primary focus:ring-netslip-primary/30">{{ ucfirst($label) }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                    <x-ui.textarea name="highlights" label="Pricing card bullets (one per line)" :value="implode(PHP_EOL, $plan->highlights ?? [])" rows="5" />
                    <x-ui.toggle name="is_active" label="Offered publicly" :checked="$plan->is_active" />
                    <x-ui.button full-width>Save {{ $plan->name }}</x-ui.button>
                </form>
            </x-ui.card>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card>
            <x-ui.heading level="2" variant="card">Non-expiring PAYG credit packs</x-ui.heading>
            <div class="mt-4 space-y-4">
                @foreach ($packs as $pack)
                    <form method="POST" action="{{ route('admin.monetization.packs.update', $pack) }}" class="grid items-end gap-3 rounded-xl border border-border-subtle p-3 sm:grid-cols-[1.4fr_0.8fr_1fr_auto]">
                        @csrf @method('PUT')
                        <x-ui.input name="name" label="Name" :value="$pack->name" />
                        <x-ui.input name="credits" type="number" min="1" label="Credits" :value="$pack->credits" />
                        <x-ui.input name="price" label="Price" prefix="₦" :value="number_format($pack->price_minor / 100, 2, '.', '')" />
                        <div class="flex items-center gap-2"><input type="hidden" name="is_active" value="0"><label class="flex items-center gap-1 text-xs"><input type="checkbox" name="is_active" value="1" @checked($pack->is_active)>On</label><x-ui.button size="sm">Save</x-ui.button></div>
                    </form>
                @endforeach
                <form method="POST" action="{{ route('admin.monetization.packs.store') }}" class="grid items-end gap-3 rounded-xl border border-dashed border-slate-300 p-3 sm:grid-cols-[1.4fr_0.8fr_1fr_auto]">
                    @csrf <input type="hidden" name="is_active" value="1">
                    <x-ui.input name="name" label="New pack" placeholder="Growth Pack" />
                    <x-ui.input name="credits" type="number" min="1" label="Credits" placeholder="120" />
                    <x-ui.input name="price" label="Price" prefix="₦" placeholder="2200" />
                    <x-ui.button size="sm" variant="secondary" icon="plus">Add</x-ui.button>
                </form>
            </div>
        </x-ui.card>

        <x-ui.card>
            <x-ui.heading level="2" variant="card">Billing rules</x-ui.heading>
            <form method="POST" action="{{ route('admin.monetization.rules') }}" class="mt-4 space-y-5">
                @csrf @method('PUT')
                <x-ui.toggle name="remove_watermark_on_paid_credit" label="Paid credit removes watermark" description="When a Free merchant spends a PAYG credit, the slip has no 'Powered by NetSlip' watermark." :checked="$removeWatermarkOnCredit" />
                <x-ui.input name="yearly_discount_label" label="Yearly toggle label" :value="$yearlyLabel" placeholder="Save 20%" />
                <x-ui.button>Save rules</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</x-admin.layout>
