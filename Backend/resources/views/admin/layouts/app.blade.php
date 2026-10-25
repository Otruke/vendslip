<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Admin' }} · NetSlip Control</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
@php
    $user = auth()->user();
    $nav = [
        ['admin.dashboard', 'Command Center', 'bar-chart', false],
        ['admin.merchants.index', 'Merchants', 'store', false],
        ['admin.payments.index', 'Payments', 'credit-card', false],
        ['admin.security.index', 'Security Logs', 'shield', false],
        ['admin.templates.index', 'Template Studio', 'layout', true],
        ['admin.monetization.edit', 'Pricing Engine', 'coins', true],
        ['admin.gateways.edit', 'Gateways & Bots', 'key', true],
        ['admin.admins.index', 'Platform Admins', 'users', true],
    ];
@endphp
<div class="min-h-full lg:grid lg:grid-cols-[260px_1fr]">
    <aside class="hidden bg-netslip-deep text-white lg:flex lg:flex-col">
        <div class="flex items-center gap-3 px-5 py-5">
            <img src="/assets/brand/netslip-icon-white.png" alt="" class="h-8 w-auto">
            <div>
                <p class="text-base font-extrabold leading-tight">NetSlip</p>
                <p class="text-[11px] font-semibold uppercase tracking-widest text-[#cfe3e8]">Control</p>
            </div>
        </div>
        <nav class="flex-1 space-y-0.5 px-3" aria-label="Admin">
            @foreach ($nav as [$route, $label, $icon, $superOnly])
                @continue($superOnly && ! $user?->isSuperAdmin())
                @php $active = request()->routeIs(str_replace(['.index', '.edit'], '', $route).'*'); @endphp
                <a href="{{ route($route) }}" @class([
                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                    'bg-white/10 text-white' => $active,
                    'text-[#cfe3e8] hover:bg-white/5 hover:text-white' => ! $active,
                ]) @if ($active) aria-current="page" @endif>
                    <x-ui.icon :name="$icon" />{{ $label }}
                </a>
            @endforeach
        </nav>
        <div class="border-t border-white/10 px-5 py-4">
            <p class="truncate text-sm font-semibold">{{ $user?->name }}</p>
            <p class="truncate text-xs text-[#cfe3e8]">{{ $user?->isSuperAdmin() ? 'Super Admin' : 'Admin' }} · {{ $user?->email }}</p>
            <form method="POST" action="{{ route('admin.logout') }}" class="mt-3">@csrf
                <button class="inline-flex items-center gap-2 text-xs font-semibold text-[#cfe3e8] hover:text-white"><x-ui.icon name="log-out" class="size-3.5" />Sign out</button>
            </form>
        </div>
    </aside>

    <div class="min-w-0">
        <header class="sticky top-0 z-20 flex h-14 items-center justify-between border-b border-border-subtle bg-white/90 px-4 backdrop-blur sm:px-6 lg:hidden">
            <div class="flex items-center gap-2"><img src="/assets/brand/netslip-icon.png" alt="" class="h-6"><span class="font-extrabold text-netslip-deep">NetSlip Control</span></div>
            <details class="relative">
                <summary class="list-none rounded-lg border border-border-subtle px-3 py-1.5 text-sm font-medium">Menu</summary>
                <div class="absolute right-0 mt-2 w-56 rounded-xl border border-border-subtle bg-white p-2 shadow-lg">
                    @foreach ($nav as [$route, $label, $icon, $superOnly])
                        @continue($superOnly && ! $user?->isSuperAdmin())
                        <a href="{{ route($route) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-subtle"><x-ui.icon :name="$icon" />{{ $label }}</a>
                    @endforeach
                    <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-subtle"><x-ui.icon name="log-out" />Sign out</button></form>
                </div>
            </details>
        </header>
        <main class="py-6 sm:py-8">
            <x-ui.container size="full" class="space-y-6">
                @if (session('status'))<x-ui.alert tone="success">{{ session('status') }}</x-ui.alert>@endif
                @if (session('error'))<x-ui.alert tone="danger">{{ session('error') }}</x-ui.alert>@endif
                {{ $slot }}
            </x-ui.container>
        </main>
    </div>
</div>
</body>
</html>
