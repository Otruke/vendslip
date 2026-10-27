@props(['tone' => 'info'])
@php
    // Inline notice: white card with a tone rule. Page-level feedback uses floating toasts instead.
    $tones = ['info' => 'var(--tone-brand)', 'success' => 'var(--tone-settled)', 'warning' => 'var(--tone-partial)', 'danger' => 'var(--tone-open)'];
    $icons = ['info' => 'info', 'success' => 'check-circle', 'warning' => 'alert-triangle', 'danger' => 'alert-triangle'];
@endphp
<div role="{{ $tone === 'danger' ? 'alert' : 'status' }}" {{ $attributes->merge(['class' => 'ns-note']) }} style="--t: {{ $tones[$tone] ?? $tones['info'] }}">
    <x-ui.icon :name="$icons[$tone] ?? 'info'" class="mt-0.5 size-4 shrink-0" />
    <div>{{ $slot }}</div>
</div>
