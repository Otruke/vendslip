<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\BotWebhookController;
use App\Http\Controllers\Api\V1\ChannelController;
use App\Http\Controllers\Api\V1\ComposerController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Controllers\Api\V1\PayoutAccountController;
use App\Http\Controllers\Api\V1\PublicController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\SupportTicketController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\TemplateController;
use App\Http\Middleware\AuditTrail;
use App\Http\Middleware\SupportSessionGuard;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // ── Public, unauthenticated ──────────────────────────────────────────────
    Route::middleware('throttle:public')->prefix('public')->group(function (): void {
        Route::get('plans', [PublicController::class, 'plans']);
        Route::get('locales', [PublicController::class, 'locales']);
        Route::get('merchants/{merchantId}/logo', [PublicController::class, 'logo'])->whereUuid('merchantId')->withoutMiddleware('throttle:public')->middleware('throttle:240,1,logos');
        Route::get('activity-feed', [PublicController::class, 'activityFeed']);
        Route::get('verify/{code}', [PublicController::class, 'verify']);
        Route::get('invoices/{uid}', [PublicController::class, 'invoice']);
        Route::post('invoices/{uid}/pay', [PublicController::class, 'payInvoice']);
        Route::get('documents/{uid}/pdf', [PublicController::class, 'pdf'])->middleware('signed')->name('documents.public-pdf');
    });

    Route::middleware('throttle:auth')->prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
    });
    Route::post('auth/two-factor', [AuthController::class, 'twoFactor'])->middleware('throttle:two-factor');
    Route::middleware('throttle:auth')->group(function (): void {
        Route::get('invitations/{token}', [InvitationController::class, 'show']);
        Route::post('invitations/{token}/accept', [InvitationController::class, 'accept']);
    });

    // ── Webhooks (signature-verified inside each handler) ────────────────────
    Route::middleware('throttle:webhooks')->prefix('webhooks')->group(function (): void {
        // Meta Cloud API + Messenger Platform (primary): WhatsApp, Messenger and Instagram in one app.
        Route::get('meta', [BotWebhookController::class, 'metaVerify']);
        Route::post('meta', [BotWebhookController::class, 'meta']);
        // Twilio (secondary).
        Route::post('whatsapp', [BotWebhookController::class, 'whatsapp']);
        Route::post('telegram', [BotWebhookController::class, 'telegram']);
        Route::post('instagram', [BotWebhookController::class, 'instagram']);
        Route::post('facebook', [BotWebhookController::class, 'facebook']);
        Route::post('flutterwave', [PaymentWebhookController::class, 'flutterwave']);
        Route::post('flutterwave/merchants/{merchantId}', [PaymentWebhookController::class, 'flutterwave'])->whereUuid('merchantId');
        Route::post('nowpayments', [PaymentWebhookController::class, 'nowPayments']);
        Route::post('nowpayments/merchants/{merchantId}', [PaymentWebhookController::class, 'nowPayments'])->whereUuid('merchantId');
    });
    Route::get('payments/flutterwave/return/{reference}', [PaymentWebhookController::class, 'flutterwaveReturn'])
        ->middleware('throttle:public')->name('payments.flutterwave.return');

    // ── Authenticated merchant workspace (Sanctum SPA session) ───────────────
    Route::post('auth/support-access', [AuthController::class, 'supportAccess'])->middleware('throttle:10,1,support-handoff');

    Route::middleware(['auth:sanctum', 'throttle:api', SupportSessionGuard::class, AuditTrail::class])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // The account holder's own security: never available to a support session.
        Route::middleware(SupportSessionGuard::class.':forbid')->group(function (): void {
            Route::put('account/profile', [AccountController::class, 'updateProfile']);
            Route::put('account/password', [AccountController::class, 'updatePassword']);
            Route::post('account/avatar', [AccountController::class, 'uploadAvatar'])->middleware('throttle:10,1');
            Route::delete('account/avatar', [AccountController::class, 'deleteAvatar']);
            Route::post('account/two-factor', [AccountController::class, 'enableTwoFactor']);
            Route::post('account/two-factor/confirm', [AccountController::class, 'confirmTwoFactor']);
            Route::delete('account/two-factor', [AccountController::class, 'disableTwoFactor']);
        });
        Route::get('users/{user}/avatar', [AccountController::class, 'showAvatar'])->whereNumber('user');

        // In-app notifications (the bell) — your own, so no merchant permission needed.
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
        Route::post('notifications/{id}/read', [NotificationController::class, 'read'])->whereUuid('id');

        Route::middleware('merchant')->group(function (): void {
            Route::get('dashboard/overview', [DashboardController::class, 'overview']);
            Route::post('composer/parse', [ComposerController::class, 'parse']);

            Route::get('documents/export', [DocumentController::class, 'export']);
            Route::get('documents', [DocumentController::class, 'index']);
            Route::post('documents', [DocumentController::class, 'store']);
            Route::get('documents/{id}', [DocumentController::class, 'show']);
            Route::put('documents/{id}', [DocumentController::class, 'update']);
            Route::post('documents/{id}/void', [DocumentController::class, 'void']);
            Route::post('documents/{id}/credit-notes', [DocumentController::class, 'creditNote']);
            Route::post('documents/{id}/payments', [DocumentController::class, 'recordPayment']);
            Route::get('documents/{id}/revisions', [DocumentController::class, 'revisions']);
            Route::get('documents/{id}/pdf', [DocumentController::class, 'pdf']);
            Route::get('documents/{id}/share', [DocumentController::class, 'share']);

            Route::apiResource('customers', CustomerController::class)->parameters(['customers' => 'id']);
            Route::apiResource('inventory', InventoryController::class)->except('show')->parameters(['inventory' => 'id']);
            Route::post('inventory/{id}/adjust', [InventoryController::class, 'adjust']);

            Route::get('templates', [TemplateController::class, 'index']);
            Route::get('templates/{id}/preview', [TemplateController::class, 'preview'])->whereNumber('id');
            Route::put('settings/templates', [TemplateController::class, 'setDefaults']);

            Route::put('settings/business', [SettingsController::class, 'updateBusiness']);
            Route::get('settings/alerts', [NotificationController::class, 'settings']);
            Route::put('settings/alerts', [NotificationController::class, 'updateSettings'])->middleware(SupportSessionGuard::class.':forbid');
            Route::get('settings/reminders', [NotificationController::class, 'reminders']);
            Route::put('settings/reminders', [NotificationController::class, 'updateReminders']);
            // Where customers pay you: password-confirmed, never in a support session, alerts the owners.
            Route::get('settings/payout-accounts', [PayoutAccountController::class, 'index']);
            Route::middleware([SupportSessionGuard::class.':forbid', 'throttle:20,10,payout-accounts'])->group(function (): void {
                Route::post('settings/payout-accounts', [PayoutAccountController::class, 'store']);
                Route::put('settings/payout-accounts/{id}', [PayoutAccountController::class, 'update'])->whereNumber('id');
                Route::delete('settings/payout-accounts/{id}', [PayoutAccountController::class, 'destroy'])->whereNumber('id');
            });
            Route::put('settings/branding', [SettingsController::class, 'updateBranding']);
            Route::post('settings/logo', [SettingsController::class, 'uploadLogo']);
            Route::delete('settings/logo', [SettingsController::class, 'deleteLogo']);
            Route::put('settings/gateways', [SettingsController::class, 'updateGateways'])->middleware(SupportSessionGuard::class.':forbid');
            Route::post('onboarding/complete', [SettingsController::class, 'completeOnboarding']);

            Route::get('activity', [ActivityController::class, 'index']);
            Route::get('support/tickets', [SupportTicketController::class, 'index']);
            Route::get('support/tickets/{reference}', [SupportTicketController::class, 'show']);
            Route::middleware(SupportSessionGuard::class.':forbid')->group(function (): void {
                Route::post('support/tickets', [SupportTicketController::class, 'store'])->middleware('throttle:5,10,support-tickets');
                Route::post('support/tickets/{reference}/messages', [SupportTicketController::class, 'reply'])->middleware('throttle:20,5,support-replies');
                Route::post('support/tickets/{reference}/close', [SupportTicketController::class, 'close']);
            });
            Route::get('team', [TeamController::class, 'index']);
            Route::middleware(SupportSessionGuard::class.':forbid')->group(function (): void {
                Route::post('team/invitations', [TeamController::class, 'invite']);
                Route::delete('team/invitations/{id}', [TeamController::class, 'revokeInvitation'])->whereNumber('id');
                Route::put('team/members/{id}', [TeamController::class, 'updateMember'])->whereNumber('id');
                Route::delete('team/members/{id}', [TeamController::class, 'removeMember'])->whereNumber('id');
            });

            Route::get('channels', [ChannelController::class, 'index']);
            Route::middleware(SupportSessionGuard::class.':forbid')->group(function (): void {
                Route::post('channels/link-code', [ChannelController::class, 'linkCode']);
                Route::delete('channels/identities/{id}', [ChannelController::class, 'unlink'])->whereNumber('id');
            });

            Route::get('billing', [BillingController::class, 'show']);
            Route::post('billing/checkout', [BillingController::class, 'checkout']);
            Route::get('billing/transactions/{reference}', [BillingController::class, 'transaction']);
        });
    });
});
