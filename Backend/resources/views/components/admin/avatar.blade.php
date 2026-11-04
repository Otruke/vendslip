@props(['user', 'class' => 'size-9 rounded-lg text-sm'])
@php
    $initials = collect(preg_split('/\s+/', trim((string) $user?->name)) ?: [])->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') ?: 'A';
@endphp
@if ($user?->avatar_path)
    <img src="{{ route('admin.avatar', $user) }}?v={{ $user->avatar_updated_at?->getTimestamp() }}" alt="" {{ $attributes->merge(['class' => 'shrink-0 object-cover bg-vendslip-primary '.$class]) }}>
@else
    <span {{ $attributes->merge(['class' => 'flex shrink-0 items-center justify-center bg-vendslip-primary font-bold text-white '.$class]) }} aria-hidden="true">{{ $initials }}</span>
@endif
