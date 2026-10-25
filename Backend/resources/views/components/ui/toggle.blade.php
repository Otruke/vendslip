@props(['name', 'label', 'checked' => false, 'description' => null])
@php $id = 't_'.str_replace(['[', ']', '.'], '_', $name); $isOn = (bool) old($name, $checked); @endphp
<label for="{{ $id }}" class="flex cursor-pointer items-start justify-between gap-4">
    <span>
        <span class="block text-sm font-medium text-ink-primary">{{ $label }}</span>
        @if ($description)<span class="mt-0.5 block text-xs text-ink-secondary">{{ $description }}</span>@endif
    </span>
    <span class="relative mt-0.5 inline-flex shrink-0">
        <input type="hidden" name="{{ $name }}" value="0">
        <input id="{{ $id }}" type="checkbox" name="{{ $name }}" value="1" @checked($isOn) class="peer sr-only" {{ $attributes }}>
        <span class="h-6 w-11 rounded-full bg-slate-300 transition peer-checked:bg-netslip-primary peer-focus-visible:ring-2 peer-focus-visible:ring-netslip-primary/40"></span>
        <span class="absolute left-0.5 top-0.5 size-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
    </span>
</label>
