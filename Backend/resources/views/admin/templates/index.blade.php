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
                        <div><p class="font-semibold">{{ $t->name }}</p><p class="text-xs text-ink-muted">{{ $t->isSystem() ? 'System design' : 'Custom HTML' }} · from {{ $t->min_plan_tier->label() }}</p></div>
                        <div class="flex flex-col items-end gap-1">
                            @if ($t->isSystem())<x-ui.status-badge status="SYSTEM" size="sm" />@endif
                            @if ($t->is_default)<x-ui.status-badge status="ACTIVE" size="sm">Default</x-ui.status-badge>@endif
                            @unless ($t->is_active)<x-ui.status-badge status="DRAFT" size="sm">Hidden</x-ui.status-badge>@endunless
                        </div>
                    </div>
                    <p class="line-clamp-2 text-sm text-ink-secondary">{{ $t->description }}</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.button size="sm" variant="outline" icon="edit" :href="route('admin.templates.edit', $t)">Edit</x-ui.button>
                        <x-ui.button size="sm" variant="ghost" icon="eye" :href="route('admin.templates.preview', $t).'?type=INVOICE'" target="_blank">Invoice</x-ui.button>
                        @unless ($t->is_default)
                            <form method="POST" action="{{ route('admin.templates.default', $t) }}">@csrf<x-ui.button size="sm" variant="ghost" icon="check-circle">Make default</x-ui.button></form>
                        @endunless
                        @if ($t->isSystem())
                            <span class="ml-auto inline-flex items-center gap-1 text-xs text-ink-muted" title="System templates can be edited or hidden, not deleted"><x-ui.icon name="lock" class="size-3.5" />Protected</span>
                        @elseif ($t->is_default)
                            <span class="ml-auto text-xs text-ink-muted" title="Make another template the default before deleting this one">Default can't be deleted</span>
                        @else
                            <x-ui.button size="sm" variant="ghost" icon="trash" type="button" class="ml-auto text-[#a5341f] hover:bg-[#a5341f]/10 hover:text-[#a5341f]" data-open="delete-template-{{ $t->id }}">Delete</x-ui.button>
                        @endif
                    </div>
                </div>
            </x-ui.card>

            @unless ($t->isSystem() || $t->is_default)
                <x-ui.modal id="delete-template-{{ $t->id }}" title="Delete “{{ $t->name }}”?" size="sm">
                    <div class="space-y-3 text-sm text-ink-secondary">
                        <p>Merchants can no longer pick this design. Anyone using it as their default switches to the platform default.</p>
                        <p>Slips already issued with it keep their original look.</p>
                    </div>
                    <form method="POST" action="{{ route('admin.templates.destroy', $t) }}" class="mt-5 flex justify-end gap-2">
                        @csrf @method('DELETE')
                        <x-ui.button variant="ghost" type="button" data-close>Cancel</x-ui.button>
                        <x-ui.button variant="danger" icon="trash">Delete template</x-ui.button>
                    </form>
                </x-ui.modal>
            @endunless
        @endforeach
    </div>
</x-admin.layout>
