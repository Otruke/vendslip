@props(['name', 'label' => null, 'value' => null, 'error' => null, 'helper' => null, 'rows' => 4])
@php
    $id = $attributes->get('id', 'f_'.str_replace(['[', ']', '.'], '_', $name));
    $error ??= $errors->first($name);
@endphp
<div>
    @if ($label)<label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-ink-primary">{{ $label }}</label>@endif
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" {{ $attributes->except('id')->merge(['class' => 'block w-full rounded-lg border bg-white px-3 py-2 text-sm text-ink-primary placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-netslip-primary/20 '.($error ? 'border-[#c2412b]' : 'border-slate-300 focus:border-netslip-primary')]) }}>{{ old($name, $value) }}</textarea>
    @if ($error)<p class="mt-1 text-xs text-[#a5341f]">{{ $error }}</p>@elseif ($helper)<p class="mt-1 text-xs text-ink-muted">{{ $helper }}</p>@endif
</div>
