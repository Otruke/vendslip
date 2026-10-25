@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'error' => null, 'helper' => null, 'prefix' => null])
@php
    $id = $attributes->get('id', 'f_'.str_replace(['[', ']', '.'], '_', $name));
    $error ??= $errors->first(str_replace(['[', ']'], ['.', ''], $name));
    $current = old(str_replace(['[', ']'], ['.', ''], $name), $value);
@endphp
<div>
    @if ($label)<label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-ink-primary">{{ $label }}</label>@endif
    <div class="relative">
        @if ($prefix)<span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-ink-muted">{{ $prefix }}</span>@endif
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" @if ($type !== 'password') value="{{ $current }}" @endif
            {{ $attributes->except('id')->merge(['class' => 'block h-10 w-full rounded-lg border bg-white px-3 text-sm text-ink-primary placeholder:text-ink-muted transition focus:outline-none focus:ring-2 focus:ring-netslip-primary/20 '.($prefix ? 'pl-8 ' : '').($error ? 'border-rose-400 focus:border-rose-500' : 'border-slate-300 focus:border-netslip-primary')]) }}
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}_error" @endif>
    </div>
    @if ($error)
        <p id="{{ $id }}_error" class="mt-1 text-xs text-rose-600">{{ $error }}</p>
    @elseif ($helper)
        <p class="mt-1 text-xs text-ink-muted">{{ $helper }}</p>
    @endif
</div>
