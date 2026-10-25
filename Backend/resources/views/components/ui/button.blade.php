@props(['variant' => 'primary', 'size' => 'md', 'icon' => null, 'href' => null, 'type' => 'submit', 'fullWidth' => false, 'loading' => false])
@php
    $variants = [
        'primary' => 'bg-netslip-primary text-white shadow-sm hover:bg-netslip-primary-hover',
        'deep' => 'bg-netslip-deep text-white shadow-sm hover:bg-netslip-obsidian',
        'secondary' => 'bg-netslip-tint text-netslip-deep hover:bg-[#dbe9ed]',
        'outline' => 'border border-slate-300 bg-white text-ink-primary hover:bg-surface-canvas',
        'ghost' => 'text-ink-secondary hover:bg-surface-subtle hover:text-ink-primary',
        'danger' => 'bg-rose-600 text-white shadow-sm hover:bg-rose-700',
        'success' => 'bg-emerald-600 text-white shadow-sm hover:bg-emerald-700',
    ];
    $sizes = [
        'xs' => 'h-7 px-2.5 text-xs gap-1 rounded-md',
        'sm' => 'h-8 px-3 text-sm gap-1.5 rounded-lg',
        'md' => 'h-10 px-4 text-sm gap-2 rounded-lg',
        'lg' => 'h-12 px-6 text-base gap-2 rounded-xl',
    ];
    $classes = 'inline-flex items-center justify-center font-semibold transition-colors duration-150 disabled:opacity-50 disabled:pointer-events-none '
        .($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']).($fullWidth ? ' w-full' : '');
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-ui.icon :name="$icon" />@endif{{ $slot }}
    </a>
@else
    <button type="{{ $type }}" @disabled($loading) {{ $attributes->merge(['class' => $classes]) }}>
        @if ($loading)
            <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3"/></svg>
        @elseif ($icon)
            <x-ui.icon :name="$icon" />
        @endif
        {{ $slot }}
    </button>
@endif
