@props(['amountMinor', 'currency' => 'NGN', 'size' => 'md'])
@php
    $sizes = ['sm' => 'text-sm', 'md' => 'text-base', 'lg' => 'text-xl font-bold', 'xl' => 'text-3xl font-extrabold tracking-tight'];
@endphp
<span {{ $attributes->merge(['class' => 'tabular whitespace-nowrap '.($sizes[$size] ?? $sizes['md'])]) }}>{{ \App\Support\Money::format((int) $amountMinor, $currency) }}</span>
