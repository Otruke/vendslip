<x-admin.layout :title="'Support access · '.$merchant->name">
    <div class="mx-auto max-w-lg py-6">
        <x-ui.card variant="elevated" padding="lg" class="text-center">
            <span class="mx-auto inline-flex size-12 items-center justify-center rounded-2xl bg-vendslip-deep text-white"><x-ui.icon name="check-circle" class="size-6" /></span>
            <h1 class="mt-4 text-xl font-extrabold text-ink-primary">Code accepted</h1>
            <p class="mt-2 text-sm text-ink-secondary">Open <strong>{{ $merchant->name }}</strong>'s dashboard as VendSlip support. This link works once and only for the next 2 minutes; the session then lasts up to {{ $minutes }} minutes.</p>
            <a href="{{ $url }}" rel="noreferrer noopener" class="mt-6 inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-vendslip-deep px-5 text-sm font-semibold text-white transition-colors hover:bg-vendslip-obsidian focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-vendslip-primary">
                <x-ui.icon name="eye" class="size-4" />Open dashboard as support
            </a>
            <p class="mt-4 text-xs text-ink-muted">Opening it signs that browser out of any other VendSlip business account first. Use <em>End support session</em> in the dashboard when you're done.</p>
            <div class="mt-5"><x-ui.button variant="ghost" icon="store" :href="route('admin.merchants.show', $merchant)">Back to {{ $merchant->name }}</x-ui.button></div>
        </x-ui.card>
    </div>
</x-admin.layout>
