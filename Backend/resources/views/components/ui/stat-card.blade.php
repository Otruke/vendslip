@props(['title', 'value', 'trend' => null, 'icon' => null, 'hint' => null, 'variant' => 'default'])
@php $dark = in_array($variant, ['deep', 'obsidian', 'teal'], true); @endphp
<x-ui.card :variant="$variant" {{ $attributes->merge(['class' => $variant === 'default' ? 'rounded-2xl' : '']) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-xs font-semibold uppercase tracking-wider {{ $dark ? 'text-white/70' : 'text-ink-muted' }}">{{ $title }}</p>
        @if ($icon)
            <span class="{{ $dark ? 'text-white/60' : 'text-vendslip-primary/70' }}"><x-ui.icon :name="$icon" class="size-[18px]" /></span>
        @endif
    </div>
    <p class="tabular mt-3 text-2xl font-bold tracking-tight {{ $dark ? 'text-white' : 'text-ink-primary' }}">{{ $value }}</p>
    @if ($trend || $hint)
        <p class="mt-1 text-xs {{ $dark ? 'text-white/75' : 'text-ink-secondary' }}">
            @if ($trend)
                <span @class(['font-semibold', 'text-[#1d6b52]' => ! $dark && str_starts_with((string) $trend, '+'), 'text-[#a5341f]' => ! $dark && str_starts_with((string) $trend, '-'), 'text-[#8fd3b6]' => $dark && str_starts_with((string) $trend, '+'), 'text-[#f2a596]' => $dark && str_starts_with((string) $trend, '-')])>{{ $trend }}</span>
            @endif
            {{ $hint }}
        </p>
    @endif
</x-ui.card>
