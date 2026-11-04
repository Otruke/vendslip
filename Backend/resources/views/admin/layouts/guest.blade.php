<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Sign in' }} · VendSlip Control</title>
    <link rel="icon" type="image/svg+xml" href="/assets/brand/vendslip-mark.svg">
    <link rel="icon" type="image/png" href="/favicon.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
<div class="grid min-h-full lg:grid-cols-2">
    <div class="flex items-center justify-center px-4 py-12 sm:px-8">
        <div class="w-full max-w-sm">
            <img src="/assets/brand/vendslip-icon.png" alt="VendSlip" class="h-10">
            {{ $slot }}
        </div>
    </div>
    <div class="relative isolate hidden overflow-hidden bg-vendslip-obsidian lg:flex lg:flex-col lg:justify-start">
        <img src="/assets/images/lobby.webp" alt="The VendSlip sign in the lobby" class="absolute inset-0 -z-10 h-full w-full object-cover">
        <div class="absolute inset-0 -z-10 bg-vendslip-obsidian/45"></div>
        <div class="relative m-10 max-w-sm rounded-xl bg-vendslip-obsidian px-6 py-5">
            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-[#9fd0da]">VendSlip Control</p>
            <p class="mt-2 text-sm text-[#cfe3e8]">Platform controls for pricing, templates, gateways and ledger policy.</p>
        </div>
    </div>

</div>
</body>
</html>
