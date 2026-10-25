<x-admin.layout :title="$template->exists ? $template->name : 'New template'">
    <x-ui.page-header eyebrow="Template Studio" :title="$template->exists ? $template->name : 'New HTML template'" subtitle="Tokens are filled per slip. Scripts and remote assets are stripped." />

    <div class="grid gap-6 xl:grid-cols-2">
        <x-ui.card>
            <form method="POST" action="{{ $template->exists ? route('admin.templates.update', $template) : route('admin.templates.store') }}" class="space-y-4">
                @csrf @if ($template->exists) @method('PUT') @endif
                <x-ui.input name="name" label="Name" :value="$template->name" required />
                <x-ui.input name="description" label="Short description" :value="$template->description" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select name="min_plan_tier" label="Available from" :value="$template->min_plan_tier" :options="collect(\App\Enums\PlanTier::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()" />
                    <x-ui.input name="sort_order" type="number" label="Sort order" :value="$template->sort_order ?? 10" />
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.toggle name="supports_receipt" label="Receipts" :checked="$template->supports_receipt" />
                    <x-ui.toggle name="supports_invoice" label="Invoices" :checked="$template->supports_invoice" />
                    <x-ui.toggle name="is_active" label="Visible" :checked="$template->is_active" />
                </div>
                @if ($template->engine === 'html' || ! $template->exists)
                    <x-ui.textarea name="html" label="HTML" :value="$template->html" rows="18" class="font-mono text-xs" spellcheck="false" />
                @else
                    <x-ui.alert>Built-in template: layout lives in <code class="font-mono">resources/views/{{ str_replace('.', '/', (string) $template->blade_view) }}.blade.php</code>.</x-ui.alert>
                @endif
                <div class="flex justify-end gap-2">
                    <x-ui.button variant="ghost" :href="route('admin.templates.index')">Back</x-ui.button>
                    <x-ui.button icon="check-circle">Save template</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <div class="space-y-4">
            <x-ui.card padding="sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Tokens</p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach ($tokens as $token)
                        @php $tokenLabel = str_repeat('{', 2).$token.str_repeat('}', 2); @endphp
                        <button type="button" data-copy="{{ $tokenLabel }}" class="rounded-md bg-netslip-tint px-2 py-1 font-mono text-[11px] text-netslip-deep hover:bg-[#dbe9ed]">{{ $tokenLabel }}</button>
                    @endforeach
                </div>
            </x-ui.card>
            @if ($template->exists)
                <x-ui.card padding="none" class="overflow-hidden">
                    <div class="flex items-center justify-between border-b border-border-subtle px-4 py-2.5">
                        <p class="text-sm font-semibold">Live preview (saved version)</p>
                        <a class="text-sm font-semibold text-netslip-primary" target="_blank" href="{{ route('admin.templates.preview', $template) }}?watermark=1">With watermark</a>
                    </div>
                    <iframe src="{{ route('admin.templates.preview', $template) }}" title="Preview" class="h-[720px] w-full"></iframe>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-admin.layout>
