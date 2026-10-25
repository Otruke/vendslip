@props(['size' => 'xl'])
@php $max = ['sm' => 'max-w-3xl', 'md' => 'max-w-5xl', 'lg' => 'max-w-6xl', 'xl' => 'max-w-7xl', 'full' => 'max-w-[1440px]'][$size] ?? 'max-w-7xl'; @endphp
<div {{ $attributes->merge(['class' => "mx-auto w-full $max px-4 sm:px-6 lg:px-8"]) }}>{{ $slot }}</div>
