<x-admin.layout title="Template Studio">
    <x-ui.page-header eyebrow="Super-Admin" title="Template Studio" subtitle="Receipt & invoice designs merchants can pick.">
        <x-slot:actions><x-ui.button icon="plus" :href="route('admin.templates.create')">New HTML template</x-ui.button></x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($templates as $t)
            <x-ui.card variant="interactive" padding="none" class="overflow-hidden">
                <div class="aspect-[4/3] bg-surface-subtle">
                    <iframe src="{{ route('admin.templates.preview', $t) }}#toolbar=0&view=FitH" title="{{ $t->name }} preview" class="pointer-events-none h-full w-full" loading="lazy"></iframe>
                </div>
                <div class="space-y-3 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div><p class="font-semibold">{{ $t->name }}</p><p class="text-xs text-ink-muted">{{ $t->engine === 'blade' ? 'Built-in' : 'Custom HTML' }} · from {{ $t->min_plan_tier->label() }}</p></div>
                        <div class="flex flex-col items-end gap-1">
                            @if ($t->is_default)<x-ui.status-badge status="ACTIVE" size="sm">Default</x-ui.status-badge>@endif
                            @unless ($t->is_active)<x-ui.status-badge status="DRAFT" size="sm">Hidden</x-ui.status-badge>@endunless
                        </div>
                    </div>
                    <p class="line-clamp-2 text-sm text-ink-secondary">{{ $t->description }}</p>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button size="sm" variant="outline" icon="edit" :href="route('admin.templates.edit', $t)">Edit</x-ui.button>
                        <x-ui.button size="sm" variant="ghost" icon="eye" :href="route('admin.templates.preview', $t).'?type=INVOICE'" target="_blank">Invoice</x-ui.button>
                        @unless ($t->is_default)
                            <form method="POST" action="{{ route('admin.templates.default', $t) }}">@csrf<x-ui.button size="sm" variant="ghost" icon="check-circle">Make default</x-ui.button></form>
                        @endunless
                    </div>
                </div>
            </x-ui.card>
        @endforeach
    </div>
</x-admin.layout>
