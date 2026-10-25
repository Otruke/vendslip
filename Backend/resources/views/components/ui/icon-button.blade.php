@props(['icon', 'label', 'href' => null, 'type' => 'button'])
@php $classes = 'inline-flex size-8 items-center justify-center rounded-lg text-ink-secondary transition hover:bg-surface-subtle hover:text-ink-primary'; @endphp
@if ($href)
    <a href="{{ $href }}" title="{{ $label }}" aria-label="{{ $label }}" {{ $attributes->merge(['class' => $classes]) }}><x-ui.icon :name="$icon" /></a>
@else
    <button type="{{ $type }}" title="{{ $label }}" aria-label="{{ $label }}" {{ $attributes->merge(['class' => $classes]) }}><x-ui.icon :name="$icon" /></button>
@endif
