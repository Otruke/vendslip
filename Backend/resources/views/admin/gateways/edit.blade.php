<x-admin.layout title="Gateways & Bots">
    <x-ui.page-header eyebrow="Super-Admin" title="Gateways & bot credentials" subtitle="Stored encrypted. Leave a field blank to keep its current value." />

    @php
        $routes = [
            'meta' => ['Meta Cloud API', '', 'Direct to Meta: WhatsApp buttons, PDFs, Instagram DMs.'],
            'twilio' => ['Twilio', '', 'Backup route for WhatsApp and Messenger.'],
        ];
    @endphp
    <x-ui.card >
        <div class="flex flex-col gap-4 md:items-center md:justify-between">
            <div class="min-w-0">
                <x-ui.heading level="2" variant="card">WhatsApp · Messenger · Instagram route</x-ui.heading>
            </div>
            <div class="grid shrink-0 grid-cols-2 gap-1 rounded-xl border border-border-subtle bg-surface-canvas p-1" role="group" aria-label="Messaging route">
                @foreach ($routes as $value => [$name, $rank, $hint])
                    @php $on = $provider === $value; @endphp
                    <form method="POST" action="{{ route('admin.gateways.provider') }}" @if (! $on) data-confirm="Switch WhatsApp, Messenger and Instagram to {{ $name }} ({{ strtolower($rank) }})? This is logged as a critical event." @endif>
                        @csrf @method('PUT')
                        <input type="hidden" name="provider" value="{{ $value }}">
                        <button type="submit" title="{{ $hint }}" aria-pressed="{{ $on ? 'true' : 'false' }}" @class([
                            'flex w-full min-w-[8.5rem] flex-col items-start rounded-lg px-3 py-2 text-left transition-colors duration-200',
                            'bg-vendslip-deep text-white' => $on,
                            'text-ink-secondary hover:bg-white hover:text-ink-primary' => ! $on,
                        ])>
                            <span class="flex items-center gap-1.5 text-sm font-semibold">
                                <span @class(['size-2 rounded-full', 'bg-emerald-300' => $on, 'bg-slate-300' => ! $on]) aria-hidden="true"></span>{{ $name }}
                            </span>
                            <span @class(['text-[11px] font-semibold uppercase tracking-wider', 'text-[#cfe3e8]' => $on, 'text-ink-muted' => ! $on])>{{ $rank }}{{ $on ? ' live' : '' }}</span>
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
    </x-ui.card>

    <x-ui.card>
        <x-ui.heading level="2" variant="card">Webhook URLs</x-ui.heading>
        <p class="mt-1 text-xs text-ink-muted">Meta: use the Meta app URL as the callback URL for WhatsApp, Messenger and Instagram, with the webhook verify token below. Telegram registers itself with the button.</p>
        <ul class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
            @foreach ($webhooks as $label => $url)
                <li class="flex min-w-0 items-center justify-between gap-2 rounded-lg bg-surface-canvas px-3 py-2">
                    <span class="min-w-0"><span class="block text-xs font-semibold text-ink-muted">{{ $label }}</span><span class="block truncate font-mono text-xs">{{ $url }}</span></span>
                    <button type="button" data-copy="{{ $url }}" class="shrink-0 rounded-md border border-border-subtle bg-white px-2 py-1 text-xs font-semibold">Copy</button>
                </li>
            @endforeach
        </ul>
        <form method="POST" action="{{ route('admin.gateways.telegram') }}" class="mt-4">@csrf<x-ui.button variant="secondary" size="sm" icon="send">Register Telegram webhook</x-ui.button></form>
    </x-ui.card>

    <form method="POST" action="{{ route('admin.gateways.update') }}" class="space-y-6" data-confirm="Save gateway credentials? This is logged as a critical event.">
        @csrf @method('PUT')
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            @foreach ($groups as $group => $fields)
                <x-ui.card>
                    <x-ui.heading level="2" variant="card">{{ $group }}</x-ui.heading>
                    <div class="mt-4 space-y-4">
                        @foreach ($fields as $key => [$label, $help])
                            @php $v = $values[$key]; @endphp
                            <div>
                                <div class="mb-1.5 flex items-center justify-between gap-2">
                                    <label for="s_{{ $key }}" class="text-sm font-medium">{{ $label }}</label>
                                    @if ($v['configured'])
                                        <span class="flex items-center gap-2"><x-ui.status-badge status="ACTIVE" size="sm">Set</x-ui.status-badge><label class="flex items-center gap-1 text-xs text-ink-muted"><input type="checkbox" name="clear[]" value="{{ $key }}">Clear</label></span>
                                    @else
                                        <x-ui.status-badge status="DRAFT" size="sm">Not set</x-ui.status-badge>
                                    @endif
                                </div>
                                <input id="s_{{ $key }}" name="settings[{{ str_replace('.', '__', $key) }}]" type="{{ $v['secret'] ? 'password' : 'text' }}" autocomplete="off"
                                    placeholder="{{ $v['configured'] ? ($v['display'] ?? '') : $help }}" value="{{ $v['secret'] ? '' : ($v['display'] ?? '') }}"
                                    class="block h-10 w-full rounded-lg border border-slate-300 bg-white px-3 font-mono text-xs focus:border-vendslip-primary focus:outline-none focus:ring-2 focus:ring-vendslip-primary/20">
                                @if ($help)<p class="mt-1 text-xs text-ink-muted">{{ $help }}</p>@endif
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            @endforeach
        </div>
        <div class="flex justify-end"><x-ui.button icon="key" size="lg">Save credentials</x-ui.button></div>
    </form>
</x-admin.layout>
