@props(['variant' => 'default', 'padding' => 'md'])
@php
    $variants = [
        'default' => 'bg-white border border-border-subtle rounded-xl',
        'elevated' => 'bg-white border border-border-subtle rounded-2xl shadow-[0_1px_2px_rgba(15,23,42,0.04),0_8px_24px_-12px_rgba(15,23,42,0.12)]',
        'deep' => 'rounded-2xl bg-gradient-to-br from-netslip-deep to-netslip-obsidian text-white',
        'tinted' => 'rounded-xl bg-netslip-tint border border-[#d3e4e8]',
        'interactive' => 'bg-white border border-border-subtle rounded-xl transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md',
        'paper-slip' => 'relative bg-white rounded-t-xl border-t-4 border-netslip-deep shadow-md',
    ];
    $paddings = ['none' => '', 'sm' => 'p-4', 'md' => 'p-5 sm:p-6', 'lg' => 'p-6 sm:p-8'];
@endphp
<div {{ $attributes->merge(['class' => ($variants[$variant] ?? $variants['default']).' '.($paddings[$padding] ?? $paddings['md'])]) }}>
    {{ $slot }}
</div>
