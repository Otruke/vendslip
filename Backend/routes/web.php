<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GatewayController;
use App\Http\Controllers\Admin\MerchantController;
use App\Http\Controllers\Admin\MonetizationController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\SecurityLogController;
use App\Http\Controllers\Admin\SupportAccessController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Middleware\AuditTrail;
use App\Support\Urls;
use Illuminate\Support\Facades\Route;

// The public site and merchant dashboard are the React SPA (Frontend/).
Route::get('/', static fn () => redirect()->away(Urls::frontend('/')));

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('login', [AuthController::class, 'show'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('two-factor', [AuthController::class, 'twoFactorForm'])->name('two-factor');
    Route::post('two-factor', [AuthController::class, 'twoFactor'])->middleware('throttle:6,1')->name('two-factor.verify');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // Every platform staff member (support agents and QA reviewers included): home, own profile, tickets.
    Route::middleware(['auth', 'platform:staff', AuditTrail::class])->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [ProfileController::class, 'password'])->middleware('throttle:6,1')->name('profile.password');
        Route::post('profile/two-factor', [ProfileController::class, 'startTwoFactor'])->name('profile.two-factor.start');
        Route::post('profile/two-factor/confirm', [ProfileController::class, 'confirmTwoFactor'])->middleware('throttle:6,1')->name('profile.two-factor.confirm');
        Route::delete('profile/two-factor', [ProfileController::class, 'disableTwoFactor'])->middleware('throttle:6,1')->name('profile.two-factor.disable');
        Route::post('profile/avatar', [ProfileController::class, 'uploadAvatar'])->middleware('throttle:10,1')->name('profile.avatar');
        Route::delete('profile/avatar', [ProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');
        Route::get('avatars/{user}', [ProfileController::class, 'showAvatar'])->whereNumber('user')->name('avatar');

        Route::get('support', [SupportTicketController::class, 'index'])->name('support.index');
        Route::get('support/{ticket}', [SupportTicketController::class, 'show'])->name('support.show');
        Route::post('support/{ticket}/reply', [SupportTicketController::class, 'reply'])->middleware('throttle:30,1')->name('support.reply');
        Route::post('support/{ticket}/note', [SupportTicketController::class, 'note'])->middleware('throttle:30,1')->name('support.note');
        Route::put('support/{ticket}/status', [SupportTicketController::class, 'status'])->name('support.status');
        Route::put('support/{ticket}/assign', [SupportTicketController::class, 'assign'])->name('support.assign');
        Route::put('support/{ticket}/review', [SupportTicketController::class, 'review'])->name('support.review');

        // Platform admins only (not support agents or QA reviewers).
        Route::middleware('platform')->group(function (): void {
            Route::get('merchants', [MerchantController::class, 'index'])->name('merchants.index');
            Route::get('merchants/{merchant}', [MerchantController::class, 'show'])->name('merchants.show');
            Route::put('merchants/{merchant}/mutability', [MerchantController::class, 'updateMutability'])->name('merchants.mutability');
            Route::put('merchants/{merchant}/plan', [MerchantController::class, 'updatePlan'])->name('merchants.plan');
            Route::post('merchants/{merchant}/credits', [MerchantController::class, 'adjustCredits'])->name('merchants.credits');
            Route::put('merchants/{merchant}/status', [MerchantController::class, 'updateStatus'])->name('merchants.status');

            Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
            Route::get('security-logs', [SecurityLogController::class, 'index'])->name('security.index');
            Route::get('templates/{template}/preview', [TemplateController::class, 'preview'])->name('templates.preview');

            Route::middleware('platform:super')->group(function (): void {
                Route::get('templates', [TemplateController::class, 'index'])->name('templates.index');
                Route::get('templates/create', [TemplateController::class, 'create'])->name('templates.create');
                Route::post('templates', [TemplateController::class, 'store'])->name('templates.store');
                Route::get('templates/{template}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
                Route::put('templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
                Route::post('templates/{template}/default', [TemplateController::class, 'makeDefault'])->name('templates.default');
                Route::delete('templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');

                Route::get('monetization', [MonetizationController::class, 'edit'])->name('monetization.edit');
                Route::put('monetization/plans/{plan}', [MonetizationController::class, 'updatePlan'])->name('monetization.plans.update');
                Route::post('monetization/packs', [MonetizationController::class, 'savePack'])->name('monetization.packs.store');
                Route::put('monetization/packs/{pack}', [MonetizationController::class, 'savePack'])->name('monetization.packs.update');
                Route::put('monetization/rules', [MonetizationController::class, 'updateRules'])->name('monetization.rules');
                Route::put('monetization/marketing', [MonetizationController::class, 'updateMarketing'])->name('monetization.marketing');
                Route::post('monetization/fx-refresh', [MonetizationController::class, 'refreshRates'])->middleware('throttle:6,1')->name('monetization.fx-refresh');

                Route::get('gateways', [GatewayController::class, 'edit'])->name('gateways.edit');
                Route::put('gateways', [GatewayController::class, 'update'])->name('gateways.update');
                Route::put('gateways/provider', [GatewayController::class, 'provider'])->name('gateways.provider');
                Route::post('gateways/telegram-webhook', [GatewayController::class, 'registerTelegram'])->name('gateways.telegram');

                Route::post('merchants/{merchant}/support-access', [SupportAccessController::class, 'send'])->middleware('throttle:3,10,support-send')->name('merchants.support.send');
                Route::post('merchants/{merchant}/support-access/verify', [SupportAccessController::class, 'verify'])->middleware('throttle:6,1,support-verify')->name('merchants.support.verify');
                Route::delete('merchants/{merchant}/support-access/{grant}', [SupportAccessController::class, 'revoke'])->name('merchants.support.revoke');

                Route::get('admins', [AdminUserController::class, 'index'])->name('admins.index');
                Route::post('admins', [AdminUserController::class, 'store'])->name('admins.store');
                Route::get('admins/{admin}/edit', [AdminUserController::class, 'edit'])->name('admins.edit');
                Route::put('admins/{admin}', [AdminUserController::class, 'update'])->middleware('throttle:10,1')->name('admins.update');
                Route::put('admins/{admin}/toggle', [AdminUserController::class, 'toggle'])->name('admins.toggle');
            });
        });
    });
});
