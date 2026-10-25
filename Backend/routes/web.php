<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GatewayController;
use App\Http\Controllers\Admin\MerchantController;
use App\Http\Controllers\Admin\MonetizationController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\SecurityLogController;
use App\Http\Controllers\Admin\TemplateController;
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

    Route::middleware(['auth', 'platform'])->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');

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

            Route::get('monetization', [MonetizationController::class, 'edit'])->name('monetization.edit');
            Route::put('monetization/plans/{plan}', [MonetizationController::class, 'updatePlan'])->name('monetization.plans.update');
            Route::post('monetization/packs', [MonetizationController::class, 'savePack'])->name('monetization.packs.store');
            Route::put('monetization/packs/{pack}', [MonetizationController::class, 'savePack'])->name('monetization.packs.update');
            Route::put('monetization/rules', [MonetizationController::class, 'updateRules'])->name('monetization.rules');

            Route::get('gateways', [GatewayController::class, 'edit'])->name('gateways.edit');
            Route::put('gateways', [GatewayController::class, 'update'])->name('gateways.update');
            Route::post('gateways/telegram-webhook', [GatewayController::class, 'registerTelegram'])->name('gateways.telegram');

            Route::get('admins', [AdminUserController::class, 'index'])->name('admins.index');
            Route::post('admins', [AdminUserController::class, 'store'])->name('admins.store');
            Route::put('admins/{admin}/toggle', [AdminUserController::class, 'toggle'])->name('admins.toggle');
        });
    });
});
