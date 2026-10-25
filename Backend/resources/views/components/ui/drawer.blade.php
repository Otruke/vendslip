@props(['id', 'title'])
<dialog id="{{ $id }}" class="ml-auto mr-0 h-dvh max-h-dvh w-full max-w-xl rounded-none border-l border-border-subtle p-0 shadow-2xl backdrop:bg-slate-900/40" aria-labelledby="{{ $id }}_title">
    <div class="sticky top-0 z-10 flex items-center justify-between border-b border-border-subtle bg-white px-5 py-4">
        <h2 id="{{ $id }}_title" class="text-base font-semibold text-ink-primary">{{ $title }}</h2>
        <x-ui.icon-button icon="x" label="Close" data-close />
    </div>
    <div class="px-5 py-5">{{ $slot }}</div>
</dialog>
