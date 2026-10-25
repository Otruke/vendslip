@props(['variant' => 'body', 'tone' => 'secondary', 'as' => 'p'])
@php
    $size = match ($variant) {
        'lead' => 'text-lg leading-relaxed',
        'small' => 'text-sm',
        'caption' => 'text-xs',
        'mono' => 'font-mono text-xs',
        default => 'text-sm leading-relaxed',
    };
    $color = match ($tone) {
        'primary' => 'text-ink-primary',
        'muted' => 'text-ink-muted',
        'brand' => 'text-netslip-primary',
        'danger' => 'text-rose-600',
        default => 'text-ink-secondary',
    };
@endphp
<{{ $as }} {{ $attributes->merge(['class' => "$size $color"]) }}>{{ $slot }}</{{ $as }}>
