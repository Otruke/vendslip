@props(['name', 'label' => null, 'options' => [], 'value' => null, 'error' => null, 'placeholder' => null])
@php
    $id = $attributes->get('id', 'f_'.str_replace(['[', ']', '.'], '_', $name));
    $error ??= $errors->first($name);
    $current = (string) old($name, $value instanceof \BackedEnum ? $value->value : $value);
@endphp
<div>
    @if ($label)<label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-ink-primary">{{ $label }}</label>@endif
    <select id="{{ $id }}" name="{{ $name }}" {{ $attributes->except('id')->merge(['class' => 'block h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-ink-primary focus:border-netslip-primary focus:outline-none focus:ring-2 focus:ring-netslip-primary/20']) }}>
        @if ($placeholder)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($current === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($error)<p class="mt-1 text-xs text-rose-600">{{ $error }}</p>@endif
</div>
