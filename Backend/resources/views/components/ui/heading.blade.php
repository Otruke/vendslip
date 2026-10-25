@props(['level' => 2, 'variant' => 'section'])
@php
    $tag = 'h'.max(1, min(6, (int) $level));
    $classes = match ($variant) {
        'display' => 'text-4xl sm:text-5xl font-extrabold tracking-tight text-ink-primary',
        'card' => 'text-base font-semibold text-ink-primary',
        'script' => 'font-script text-2xl text-netslip-deep',
        default => 'text-xl sm:text-2xl font-bold tracking-tight text-ink-primary',
    };
@endphp
<{{ $tag }} {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</{{ $tag }}>
