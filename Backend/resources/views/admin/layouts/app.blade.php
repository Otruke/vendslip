<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Admin' }} · VendSlip Control</title>
    <link rel="icon" type="image/svg+xml" href="/assets/brand/vendslip-mark.svg">
    <link rel="icon" type="image/png" href="/favicon.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
<x-ui.toasts />
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
    <aside class="hidden bg-vendslip-deep text-white lg:flex lg:flex-col">
        <div class="flex items-center gap-3 px-5 py-5">
            <img src="/assets/brand/vendslip-icon-white.png" alt="" class="h-8 w-auto">
            <div>
                <p class="text-base font-extrabold leading-tight">VendSlip</p>
                <p class="text-[11px] font-semibold uppercase tracking-widest text-[#cfe3e8]">Control</p>
            </div>
        </div>
        <nav class="flex-1 space-y-0.5 pl-3" aria-label="Admin">
            @foreach ($nav as [$route, $label, $icon, $superOnly])
                @continue($superOnly && ! $user?->isSuperAdmin())
                @php $active = request()->routeIs(str_replace(['.index', '.edit'], '', $route).'*'); @endphp
                <a href="{{ route($route) }}" @class([
                    'vs-nav flex items-center gap-3 rounded-l-lg px-3 py-2 text-sm font-medium transition-colors duration-300',
                    'text-white' => $active,
                    'text-[#cfe3e8] hover:text-white' => ! $active,
                ]) @if ($active) aria-current="page" @endif>
                    <x-ui.icon :name="$icon" />{{ $label }}
                </a>
            @endforeach
        </nav>
        <div class="border-t border-white/10 py-3 pl-3">
            <a href="{{ route('admin.profile.show') }}" @class([
                'vs-nav flex items-center gap-3 rounded-l-xl px-2 py-2 transition-colors duration-300',
                
            ]) title="My profile" @if (request()->routeIs('admin.profile.*')) aria-current="page" @endif>
                <x-admin.avatar :user="$user" />
                <span class="min-w-0">
                    <span class="block truncate text-sm font-semibold">{{ $user?->name }}</span>
                    <span class="block truncate text-xs text-[#cfe3e8]">{{ $user?->isSuperAdmin() ? 'Super Admin' : 'Admin' }} · My profile</span>
                </span>
            </a>
            <form method="POST" action="{{ route('admin.logout') }}" class="mt-1 px-2">@csrf
                <button class="inline-flex items-center gap-2 py-1.5 text-xs font-semibold text-[#cfe3e8] hover:text-white"><x-ui.icon name="log-out" class="size-3.5" />Sign out</button>
            </form>
        </div>
    </aside>

    <div class="min-w-0">
        <header class="sticky top-0 z-20 flex h-14 items-center justify-between border-b border-border-subtle bg-white/90 px-4 backdrop-blur sm:px-6 lg:hidden">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2"><img src="/assets/brand/vendslip-icon.png" alt="" class="h-6"><span class="font-extrabold text-vendslip-deep">VendSlip Control</span></a>
            <a href="{{ route('admin.profile.show') }}" title="My profile"><x-admin.avatar :user="$user" class="size-8 rounded-lg text-xs" /></a>
        </header>
        <main class="pt-6 pb-28 sm:pt-8 lg:pb-8">
            <x-ui.container size="full" class="space-y-6">
                {{ $slot }}
            </x-ui.container>
        </main>
    </div>
</div>

{{-- Phones and tablets: bottom tab bar (thumb reach) and a slide-in menu for everything else. --}}
@php
    $tabs = [
        ['admin.dashboard', 'Home', 'bar-chart'],
        ['admin.merchants.index', 'Merchants', 'store'],
        ['admin.payments.index', 'Payments', 'credit-card'],
        ['admin.profile.show', 'Profile', 'user'],
    ];
@endphp
<nav class="fixed inset-x-0 bottom-0 z-30 border-t border-border-subtle bg-white pb-[env(safe-area-inset-bottom)] lg:hidden" aria-label="Quick navigation">
    <div class="mx-auto grid h-16 max-w-xl grid-cols-5">
        @foreach ($tabs as [$route, $label, $icon])
            @php $active = $route === 'admin.dashboard' ? request()->routeIs('admin.dashboard') : request()->routeIs(str_replace(['.index', '.show'], '', $route).'*'); @endphp
            <a href="{{ route($route) }}" @class(['vs-tab flex flex-col items-center justify-center gap-1 pt-2 pb-1.5 text-[10.5px] font-semibold transition-colors [&>svg]:size-5', 'text-vendslip-deep' => $active, 'text-ink-muted hover:text-ink-primary' => ! $active]) @if ($active) aria-current="page" @endif>
                <x-ui.icon :name="$icon" />{{ $label }}
            </a>
        @endforeach
        <button type="button" data-open="admin-menu" class="vs-tab flex flex-col items-center justify-center gap-1 pt-2 pb-1.5 text-[10.5px] font-semibold text-ink-muted transition-colors hover:text-ink-primary [&>svg]:size-5" aria-label="Open the full menu">
            <x-ui.icon name="menu" />Menu
        </button>
    </div>
</nav>

<dialog id="admin-menu" class="vs-drawer m-0 mr-auto h-dvh max-h-dvh w-[290px] max-w-[85vw] border-0 bg-vendslip-deep p-0 text-white backdrop:bg-slate-900/50 lg:hidden" aria-label="Menu">
    <div class="flex h-full flex-col">
        <div class="flex items-center justify-between px-5 py-5">
            <div class="flex items-center gap-3">
                <img src="/assets/brand/vendslip-icon-white.png" alt="" class="h-8 w-auto">
                <div>
                    <p class="text-base font-extrabold leading-tight">VendSlip</p>
                    <p class="text-[11px] font-semibold uppercase tracking-widest text-[#cfe3e8]">Control</p>
                </div>
            </div>
            <button type="button" data-close class="rounded-lg p-1.5 text-white/80 hover:bg-white/10" aria-label="Close menu"><x-ui.icon name="x" class="size-5" /></button>
        </div>
        <nav class="flex-1 space-y-0.5 overflow-y-auto pl-3" aria-label="Admin menu">
            @foreach ($nav as [$route, $label, $icon, $superOnly])
                @continue($superOnly && ! $user?->isSuperAdmin())
                @php $active = request()->routeIs(str_replace(['.index', '.edit'], '', $route).'*'); @endphp
                <a href="{{ route($route) }}" @class(['vs-nav flex items-center gap-3 rounded-l-lg px-3 py-2.5 text-sm font-medium transition-colors duration-300', 'text-white' => $active, 'text-[#cfe3e8] hover:text-white' => ! $active]) @if ($active) aria-current="page" @endif>
                    <x-ui.icon :name="$icon" />{{ $label }}
                </a>
            @endforeach
            <a href="{{ route('admin.profile.show') }}" @class(['vs-nav flex items-center gap-3 rounded-l-lg px-3 py-2.5 text-sm font-medium transition-colors duration-300', 'text-white' => request()->routeIs('admin.profile.*'), 'text-[#cfe3e8] hover:text-white' => ! request()->routeIs('admin.profile.*')]) @if (request()->routeIs('admin.profile.*')) aria-current="page" @endif>
                <x-ui.icon name="user" />My profile
            </a>
        </nav>
        <form method="POST" action="{{ route('admin.logout') }}" class="border-t border-white/10 px-5 py-4 pb-[calc(1rem+env(safe-area-inset-bottom))]">@csrf
            <button class="inline-flex items-center gap-2 text-sm font-semibold text-[#cfe3e8] hover:text-white"><x-ui.icon name="log-out" class="size-4" />Sign out</button>
        </form>
    </div>
</dialog>
</body>
</html>
