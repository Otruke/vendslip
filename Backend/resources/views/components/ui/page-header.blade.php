@props(['title', 'subtitle' => null, 'eyebrow' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="min-w-0">
        @if ($eyebrow)<p class="text-xs font-semibold uppercase tracking-widest text-netslip-primary">{{ $eyebrow }}</p>@endif
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-ink-primary">{{ $title }}</h1>
        @if ($subtitle)<p class="mt-1 text-sm text-ink-secondary">{{ $subtitle }}</p>@endif
    </div>
    @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
</div>
