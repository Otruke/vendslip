<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Pdf\DompdfSlipRenderer;
use App\Services\Pdf\SlipRenderer;
use App\Support\Urls;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SlipRenderer::class, DompdfSlipRenderer::class);
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        ResetPassword::createUrlUsing(static fn ($user, string $token): string => Urls::frontend('reset-password?token='.$token.'&email='.urlencode((string) $user->getEmailForPasswordReset())));

        Password::defaults(static fn () => app()->isProduction()
            ? Password::min(10)->letters()->mixedCase()->numbers()->uncompromised()
            : Password::min(8)->letters()->numbers());

        RateLimiter::for('api', static fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('auth', static fn (Request $request) => [
            Limit::perMinute(10)->by('ip:'.$request->ip()),
            Limit::perMinute(5)->by('email:'.mb_strtolower((string) $request->input('email'))),
        ]);
        RateLimiter::for('two-factor', static fn (Request $request) => Limit::perMinute(6)->by($request->session()->getId() ?: $request->ip()));
        RateLimiter::for('public', static fn (Request $request) => Limit::perMinute(40)->by($request->ip()));
        RateLimiter::for('webhooks', static fn (Request $request) => Limit::perMinute(600)->by($request->ip()));
    }
}
