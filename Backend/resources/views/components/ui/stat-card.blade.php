@props(['title', 'value', 'trend' => null, 'icon' => null, 'hint' => null, 'variant' => 'default'])
@php $deep = $variant === 'deep'; @endphp
<x-ui.card :variant="$deep ? 'deep' : 'default'" {{ $attributes }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-xs font-semibold uppercase tracking-wider {{ $deep ? 'text-[#cfe3e8]' : 'text-ink-muted' }}">{{ $title }}</p>
        @if ($icon)
            <span class="inline-flex size-8 items-center justify-center rounded-lg {{ $deep ? 'bg-white/10 text-white' : 'bg-netslip-tint text-netslip-deep' }}"><x-ui.icon :name="$icon" /></span>
        @endif
    </div>
    <p class="tabular mt-3 text-2xl font-bold tracking-tight {{ $deep ? 'text-white' : 'text-ink-primary' }}">{{ $value }}</p>
    @if ($trend || $hint)
        <p class="mt-1 text-xs {{ $deep ? 'text-[#cfe3e8]' : 'text-ink-secondary' }}">
            @if ($trend)
                <span @class(['font-semibold', 'text-[#1d6b52]' => ! $deep && str_starts_with((string) $trend, '+'), 'text-[#a5341f]' => ! $deep && str_starts_with((string) $trend, '-')])>{{ $trend }}</span>
            @endif
            {{ $hint }}
        </p>
    @endif
</x-ui.card>
