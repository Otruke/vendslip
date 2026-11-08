<x-admin.layout title="Pricing Engine">
    <x-ui.page-header eyebrow="Super-Admin" title="Pricing Engine" subtitle="Plans, quotas, features and PAYG credit packs. Changes apply instantly." />
    @php
        $currencyOptions = collect($currencies)->mapWithKeys(fn ($c) => [$c['code'] => $c['code'].' · '.$c['name']])->all();
        $sym = fn (string $code) => trim(\App\Support\Money::symbol($code));
    @endphp

    <x-ui.card variant="tinted">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-2xl">
                <x-ui.heading level="2" variant="card">How prices work in other currencies</x-ui.heading>
                <p class="mt-1 text-sm text-ink-secondary">Each plan has a base price (USD recommended). A business sees and pays the fixed price you set for its currency; any other currency is converted from the base price at the latest rate and rounded up to a clean figure. Gateways that can't charge a currency charge USD instead.</p>
            </div>
            <form method="POST" action="{{ route('admin.monetization.fx-refresh') }}">@csrf<x-ui.button size="sm" variant="secondary" icon="refresh">Refresh rates now</x-ui.button></form>
        </div>
        <div class="mt-4 flex flex-wrap gap-2 text-xs">
            @forelse ($fxRates as $code => $rate)
                <span class="tabular rounded-full border border-border-subtle bg-white px-2.5 py-1">1 USD = {{ rtrim(rtrim(number_format($rate, $rate < 10 ? 4 : 2), '0'), '.') }} {{ $code }}</span>
            @empty
                <span class="text-ink-secondary">No rates saved yet. Refresh now, or they arrive with the scheduler within 6 hours.</span>
            @endforelse
        </div>
        @if ($fxFetchedAt)<p class="mt-2 text-xs text-ink-muted">Rates updated {{ \Illuminate\Support\Carbon::parse($fxFetchedAt)->diffForHumans() }} · refreshed automatically every 6 hours.</p>@endif
    </x-ui.card>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        @foreach ($plans as $plan)
            <x-ui.card variant="elevated">
                <form method="POST" action="{{ route('admin.monetization.plans.update', $plan) }}" class="space-y-4">
                    @csrf @method('PUT')
                    <div class="flex items-center justify-between"><x-ui.status-badge :status="$plan->tier" /><span class="text-xs text-ink-muted">{{ $plan->tier->value }}</span></div>
                    <x-ui.input name="name" label="Display name" :value="$plan->name" />
                    <x-ui.input name="tagline" label="Tagline" :value="$plan->tagline" />
                    <x-ui.select name="currency" label="Base currency" :options="$currencyOptions" :value="$plan->currency" />
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.input name="monthly_price" :label="'Monthly ('.$plan->currency.')'" :prefix="$sym($plan->currency)" :value="number_format($plan->monthly_price_minor / 100, 2, '.', '')" />
                        <x-ui.input name="yearly_price" :label="'Yearly ('.$plan->currency.')'" :prefix="$sym($plan->currency)" :value="number_format($plan->yearly_price_minor / 100, 2, '.', '')" />
                    </div>
                    <details class="rounded-xl border border-border-subtle" @if (! empty($plan->price_overrides)) open @endif>
                        <summary class="cursor-pointer px-3 py-2 text-sm font-medium">Fixed prices by currency <span class="font-normal text-ink-muted">({{ count($plan->price_overrides ?? []) }} set)</span></summary>
                        <div class="space-y-2 border-t border-border-subtle px-3 py-3">
                            <p class="text-xs text-ink-secondary">Leave blank to convert from the base price.</p>
                            @foreach ($overrideCurrencies as $code)
                                @continue($code === $plan->currency)
                                <div class="grid grid-cols-[52px_1fr_1fr] items-center gap-2">
                                    <span class="text-xs font-semibold">{{ $code }}</span>
                                    <input name="overrides[{{ $code }}][monthly]" value="{{ old('overrides.'.$code.'.monthly', isset($plan->price_overrides[$code]['monthly']) ? number_format($plan->price_overrides[$code]['monthly'] / 100, 2, '.', '') : '') }}" placeholder="Monthly" inputmode="decimal" aria-label="{{ $code }} monthly" class="h-9 w-full rounded-lg border border-slate-300 px-2 text-sm focus:border-vendslip-primary focus:outline-none focus:ring-2 focus:ring-vendslip-primary/20">
                                    <input name="overrides[{{ $code }}][yearly]" value="{{ old('overrides.'.$code.'.yearly', isset($plan->price_overrides[$code]['yearly']) ? number_format($plan->price_overrides[$code]['yearly'] / 100, 2, '.', '') : '') }}" placeholder="Yearly" inputmode="decimal" aria-label="{{ $code }} yearly" class="h-9 w-full rounded-lg border border-slate-300 px-2 text-sm focus:border-vendslip-primary focus:outline-none focus:ring-2 focus:ring-vendslip-primary/20">
                                </div>
                            @endforeach
                        </div>
                    </details>
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.input name="monthly_quota" type="number" min="0" label="Slips / month" :value="$plan->monthly_quota" helper="Blank = unlimited" />
                        <x-ui.input name="max_team_members" type="number" min="1" label="Team seats" :value="$plan->features['max_team_members'] ?? null" />
                    </div>
                    <fieldset>
                        <legend class="mb-2 text-sm font-medium">Features</legend>
                        <div class="grid grid-cols-1 gap-2">
                            @foreach ($features as $key => $label)
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="features[]" value="{{ $key }}" @checked($plan->features[$key] ?? false) class="size-4 rounded border-slate-300 text-vendslip-primary focus:ring-vendslip-primary/30">{{ ucfirst($label) }}</label>
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

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-ui.card>
            <x-ui.heading level="2" variant="card">Non-expiring PAYG credit packs</x-ui.heading>
            <div class="mt-4 space-y-4">
                @foreach ($packs as $pack)
                    <form method="POST" action="{{ route('admin.monetization.packs.update', $pack) }}" class="grid items-end gap-3 rounded-xl border border-border-subtle p-3 sm:grid-cols-6">
                        @csrf @method('PUT')
                        <div class="sm:col-span-4"><x-ui.input name="name" label="Name" :value="$pack->name" /></div>
                        <div class="sm:col-span-2"><x-ui.input name="credits" type="number" min="1" label="Credits" :value="$pack->credits" /></div>
                        <div class="sm:col-span-2"><x-ui.select name="currency" label="Currency" :options="array_combine(array_keys($currencies), array_keys($currencies))" :value="$pack->currency" /></div>
                        <div class="sm:col-span-2"><x-ui.input name="price" label="Price" :prefix="$sym($pack->currency)" :value="number_format($pack->price_minor / 100, 2, '.', '')" /></div>
                        <div class="flex items-center justify-end gap-2 sm:col-span-2"><input type="hidden" name="is_active" value="0"><label class="flex items-center gap-1 text-xs"><input type="checkbox" name="is_active" value="1" @checked($pack->is_active)>On</label><x-ui.button size="sm">Save</x-ui.button></div>
                        <div class="sm:col-span-6"><x-ui.input name="overrides" label="Fixed prices (optional)" placeholder="NGN 1000, KES 150" :value="collect($pack->price_overrides ?? [])->map(fn ($m, $c) => $c.' '.rtrim(rtrim(number_format($m / 100, 2, '.', ''), '0'), '.'))->implode(', ')" helper="Other currencies are converted from the price above." /></div>
                    </form>
                @endforeach
                <form method="POST" action="{{ route('admin.monetization.packs.store') }}" class="grid items-end gap-3 rounded-xl border border-dashed border-slate-300 p-3 sm:grid-cols-6">
                    @csrf <input type="hidden" name="is_active" value="1">
                    <div class="sm:col-span-4"><x-ui.input name="name" label="New pack" placeholder="Growth Pack" /></div>
                    <div class="sm:col-span-2"><x-ui.input name="credits" type="number" min="1" label="Credits" placeholder="120" /></div>
                    <div class="sm:col-span-2"><x-ui.select name="currency" label="Currency" :options="array_combine(array_keys($currencies), array_keys($currencies))" value="USD" /></div>
                    <div class="sm:col-span-2"><x-ui.input name="price" label="Price" placeholder="2.00" /></div>
                    <div class="flex justify-end sm:col-span-2"><x-ui.button size="sm" variant="secondary" icon="plus">Add pack</x-ui.button></div>
                </form>
            </div>
        </x-ui.card>

        <x-ui.card>
            <x-ui.heading level="2" variant="card">Billing rules</x-ui.heading>
            <form method="POST" action="{{ route('admin.monetization.rules') }}" class="mt-4 space-y-5">
                @csrf @method('PUT')
                <x-ui.toggle name="remove_watermark_on_paid_credit" label="Paid credit removes watermark" description="When a Free merchant spends a PAYG credit, the slip has no 'Powered by VendSlip' watermark." :checked="$removeWatermarkOnCredit" />
                <x-ui.input name="yearly_discount_label" label="Yearly toggle label" :value="$yearlyLabel" placeholder="Save 20%" />
                <x-ui.button>Save rules</x-ui.button>
            </form>
        </x-ui.card>
    </div>

    <x-ui.card>
        <div class="grid gap-6 lg:grid-cols-2">
            <form method="POST" action="{{ route('admin.monetization.renewal') }}" class="space-y-4">
                @csrf @method('PUT')
                <div>
                    <div class="flex items-center gap-2"><x-ui.heading level="2" variant="card">Plan renewal reminders</x-ui.heading><x-ui.info label="About renewal reminders">Plans are prepaid and don’t renew by themselves, so each Owner is reminded in the app and by email, with a one-tap renew link.</x-ui.info></div>
                </div>
                <x-ui.input name="renewal_reminder_days" label="Remind on these days" :value="old('renewal_reminder_days', $renewalDays)" placeholder="7, 3, 1, 0, -3"
                    info="Days before the plan ends. 0 is the day it ends; a negative number is days after it ended (-3 = three days later). Up to 8 days, or “off”." />
                <p class="text-sm"><span class="text-ink-secondary">Now:</span> <span class="font-medium">{{ $renewalText }}</span></p>
                <x-ui.button>Save reminder timing</x-ui.button>
            </form>
            <div>
                <div class="flex items-center gap-1.5"><p class="text-sm font-semibold">Businesses with their own timing</p><x-ui.info label="About own timing">Set from a business’s page (Merchants → open the business) when they ask for different timing. They keep it when you change the default.</x-ui.info></div>
                @if ($renewalOverrides->isEmpty())
                    <p class="mt-3 text-sm text-ink-secondary">None. Every business uses the timing on the left.</p>
                @else
                    <ul class="mt-3 divide-y divide-border-subtle rounded-xl border border-border-subtle text-sm">
                        @foreach ($renewalOverrides as $o)
                            <li class="flex items-center justify-between gap-3 px-3 py-2">
                                <a class="min-w-0 truncate font-medium hover:text-vendslip-primary" href="{{ route('admin.merchants.show', $o) }}">{{ $o->name }}</a>
                                <span class="shrink-0 text-xs text-ink-secondary">{{ \App\Services\Billing\RenewalReminders::describe(\App\Services\Billing\RenewalReminders::parse($o->renewal_reminder_days) ?? []) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </x-ui.card>

    <x-ui.card>
        <div class="grid gap-6 lg:grid-cols-2">
            <form method="POST" action="{{ route('admin.monetization.marketing') }}" class="space-y-4">
                @csrf @method('PUT')
                <div>
                    <x-ui.heading level="2" variant="card">Website activity notes</x-ui.heading>
                    <p class="mt-1 text-sm text-ink-secondary">Small notes on the public website such as “A business in Ikeja, Lagos just issued a receipt”. Only real events from the last 48 hours, with a city at most: never a business name, customer or amount. Businesses can opt out in their settings. Nothing is shown when there's no real activity.</p>
                </div>
                <x-ui.toggle name="enabled" label="Show activity notes" :checked="$popups['enabled']" />
                <x-ui.toggle name="show_stats" label="Include true platform stats" description="Real counts: slips issued this week and businesses on VendSlip." :checked="$popups['show_stats']" />
                <div class="grid grid-cols-3 gap-3">
                    <x-ui.input name="min_seconds" type="number" min="5" max="600" label="Every (min s)" :value="$popups['min_seconds']" />
                    <x-ui.input name="max_seconds" type="number" min="5" max="900" label="to (max s)" :value="$popups['max_seconds']" />
                    <x-ui.input name="max_per_visit" type="number" min="1" max="20" label="Per visit" :value="$popups['max_per_visit']" />
                </div>
                <p class="text-xs text-ink-muted">Each note appears after a random wait between the two times.</p>
                <x-ui.button>Save activity notes</x-ui.button>
            </form>
            <div>
                <p class="text-sm font-semibold">What visitors would see now</p>
                @if (! ($popupPreview['enabled'] ?? false))
                    <p class="mt-2 text-sm text-ink-secondary">Switched off.</p>
                @elseif (($popupPreview['events'] ?? []) === [])
                    <p class="mt-2 text-sm text-ink-secondary">No real activity in the last 48 hours from businesses with a location, so nothing is shown.</p>
                @else
                    <ul class="mt-2 space-y-2 text-sm">
                        @foreach (array_slice($popupPreview['events'], 0, 6) as $e)
                            <li class="rounded-lg border border-border-subtle px-3 py-2">{{ match ($e['kind']) { 'SIGNUP' => 'A new business in '.$e['place'].' joined VendSlip', 'UPGRADE' => 'A business in '.$e['place'].' upgraded to '.($e['plan'] ?? 'Pro'), 'INVOICE' => 'A business in '.$e['place'].' sent an invoice', default => 'A business in '.$e['place'].' issued a receipt' } }} <span class="text-ink-muted">· {{ $e['minutes_ago'] }} min ago</span></li>
                        @endforeach
                    </ul>
                @endif
                @if (! empty($popupPreview['stats']))
                    <p class="mt-3 text-xs text-ink-muted">Stats: {{ number_format($popupPreview['stats']['slips_this_week']) }} slips this week · {{ number_format($popupPreview['stats']['businesses']) }} businesses.</p>
                @endif
            </div>
        </div>
    </x-ui.card>
</x-admin.layout>
