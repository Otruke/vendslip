@props(['id', 'title', 'size' => 'md'])
@php $width = ['sm' => 'max-w-md', 'md' => 'max-w-lg', 'lg' => 'max-w-2xl'][$size] ?? 'max-w-lg'; @endphp
<dialog id="{{ $id }}" class="m-auto w-[calc(100%-2rem)] {{ $width }} rounded-2xl border border-border-subtle p-0 shadow-2xl backdrop:bg-slate-900/40 backdrop:backdrop-blur-sm" aria-labelledby="{{ $id }}_title">
    <div class="flex items-center justify-between border-b border-border-subtle px-5 py-4">
        <h2 id="{{ $id }}_title" class="text-base font-semibold text-ink-primary">{{ $title }}</h2>
        <x-ui.icon-button icon="x" label="Close" data-close />
    </div>
    <div class="px-5 py-5">{{ $slot }}</div>
</dialog>
