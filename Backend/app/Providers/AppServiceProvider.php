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

        // Same strength everywhere (sign-up, invites, resets, changes). Production also refuses passwords
        // seen in public breaches (k-anonymity lookup: only a hash prefix leaves the server).
        Password::defaults(static function () {
            $rule = Password::min(10)->max(128)->letters()->mixedCase()->numbers()->symbols();

            return app()->isProduction() ? $rule->uncompromised() : $rule;
        });

        RateLimiter::for('api', static fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        // Sign-in, sign-up, password reset and invitations: per address, per account, and a slower hourly cap per account.
        RateLimiter::for('auth', static fn (Request $request) => [
            Limit::perMinute(10)->by('ip:'.$request->ip()),
            Limit::perMinute(5)->by('email:'.mb_strtolower((string) $request->input('email'))),
            Limit::perHour(20)->by('email-hour:'.mb_strtolower((string) $request->input('email'))),
        ]);
        // 6-digit codes: per browser session and per account waiting for its code.
        RateLimiter::for('two-factor', static fn (Request $request) => [
            Limit::perMinute(6)->by('session:'.($request->session()->getId() ?: $request->ip())),
            Limit::perMinute(6)->by('pending:'.(string) $request->session()->get('auth.2fa_user_id', $request->ip())),
            Limit::perHour(30)->by('pending-hour:'.(string) $request->session()->get('auth.2fa_user_id', $request->ip())),
        ]);
        RateLimiter::for('public', static fn (Request $request) => Limit::perMinute(40)->by($request->ip()));
        RateLimiter::for('webhooks', static fn (Request $request) => Limit::perMinute(600)->by($request->ip()));
    }
}
