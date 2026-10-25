<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Sign in' }} · NetSlip Control</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
<div class="grid min-h-full lg:grid-cols-2">
    <div class="flex items-center justify-center px-4 py-12 sm:px-8">
        <div class="w-full max-w-sm">
            <img src="/assets/brand/netslip-icon.png" alt="NetSlip" class="h-10">
            {{ $slot }}
        </div>
    </div>
    <div class="relative hidden overflow-hidden bg-netslip-deep lg:flex lg:flex-col lg:items-center lg:justify-center">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(255,255,255,0.08),transparent_55%)]"></div>
        <img src="/assets/brand/netslip-logo-white.png" alt="" class="relative w-72">
        <p class="relative mt-8 max-w-xs text-center text-sm text-[#cfe3e8]">Platform controls for pricing, templates, gateways and ledger policy.</p>
    </div>
</div>
</body>
</html>
