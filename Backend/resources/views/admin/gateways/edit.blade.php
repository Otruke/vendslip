<x-admin.layout title="Gateways & Bots">
    <x-ui.page-header eyebrow="Super-Admin" title="Gateways & bot credentials" subtitle="Stored encrypted. Leave a field blank to keep its current value." />

    <x-ui.card>
        <x-ui.heading level="2" variant="card">Webhook URLs</x-ui.heading>
        <ul class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach ($webhooks as $label => $url)
                <li class="flex items-center justify-between gap-2 rounded-lg bg-surface-canvas px-3 py-2">
                    <span class="min-w-0"><span class="block text-xs font-semibold text-ink-muted">{{ $label }}</span><span class="block truncate font-mono text-xs">{{ $url }}</span></span>
                    <button type="button" data-copy="{{ $url }}" class="shrink-0 rounded-md border border-border-subtle bg-white px-2 py-1 text-xs font-semibold">Copy</button>
                </li>
            @endforeach
        </ul>
        <form method="POST" action="{{ route('admin.gateways.telegram') }}" class="mt-4">@csrf<x-ui.button variant="secondary" size="sm" icon="send">Register Telegram webhook</x-ui.button></form>
    </x-ui.card>

    <form method="POST" action="{{ route('admin.gateways.update') }}" class="space-y-6" data-confirm="Save gateway credentials? This is logged as a critical event.">
        @csrf @method('PUT')
        <div class="grid gap-6 lg:grid-cols-2">
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
                                    class="block h-10 w-full rounded-lg border border-slate-300 bg-white px-3 font-mono text-xs focus:border-netslip-primary focus:outline-none focus:ring-2 focus:ring-netslip-primary/20">
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
