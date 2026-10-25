@props(['title', 'description' => null, 'icon' => 'file-text'])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center']) }}>
    <span class="inline-flex size-10 items-center justify-center rounded-xl bg-netslip-tint text-netslip-deep"><x-ui.icon :name="$icon" class="size-5" /></span>
    <p class="mt-3 text-sm font-semibold text-ink-primary">{{ $title }}</p>
    @if ($description)<p class="mt-1 max-w-sm text-sm text-ink-secondary">{{ $description }}</p>@endif
    @if (trim($slot) !== '')<div class="mt-4">{{ $slot }}</div>@endif
</div>
