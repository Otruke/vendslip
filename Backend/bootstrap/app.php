<?php

declare(strict_types=1);

use App\Exceptions\DomainRuleException;
use App\Http\Middleware\EnsureMerchantMember;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA cookie auth for the React dashboard (same-site via Vite proxy / reverse proxy).
        $middleware->statefulApi();
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'merchant' => EnsureMerchantMember::class,
            'platform' => EnsurePlatformAdmin::class,
        ]);
        $middleware->redirectGuestsTo(static fn (Request $request): ?string => $request->is('admin', 'admin/*') ? route('admin.login') : null);
        // Only trust proxies you run (load balancer, ngrok in dev). '*' trusts all — never in production unless behind one.
        $proxies = (string) env('TRUSTED_PROXIES', '');
        if ($proxies !== '') {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (DomainRuleException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $e->render();
            }

            return back()->withInput()->with('error', $e->getMessage());
        });
    })->create();
