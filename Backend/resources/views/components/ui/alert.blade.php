@props(['tone' => 'info'])
@php
    $styles = [
        'info' => 'bg-netslip-tint text-netslip-deep border-[#cfe3e8]',
        'success' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
        'warning' => 'bg-amber-50 text-amber-800 border-amber-200',
        'danger' => 'bg-rose-50 text-rose-800 border-rose-200',
    ];
    $icons = ['info' => 'alert-triangle', 'success' => 'check-circle', 'warning' => 'alert-triangle', 'danger' => 'alert-triangle'];
@endphp
<div role="status" {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-xl border px-4 py-3 text-sm '.($styles[$tone] ?? $styles['info'])]) }}>
    <x-ui.icon :name="$icons[$tone] ?? 'alert-triangle'" class="mt-0.5 size-4 shrink-0" />
    <div>{{ $slot }}</div>
</div>
