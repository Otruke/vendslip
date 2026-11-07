<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Models\SecurityAuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The business's own activity log: who did what in this dashboard and in chats, including anything
 * VendSlip support did during an owner-approved session. Owners only (settings.manage).
 */
final class ActivityController extends ApiController
{
    /** Readable sentences for the events people care most about; everything else is humanised. */
    private const array LABELS = [
        'auth.login' => 'Signed in', 'auth.logout' => 'Signed out', 'auth.login_failed' => 'Failed sign-in attempt',
        'auth.2fa_failed' => 'Wrong two-factor code entered', 'auth.password_reset' => 'Reset their password',
        'auth.registered' => 'Created the business account', 'account.password_changed' => 'Changed their password',
        'account.2fa_enabled' => 'Turned on two-factor sign-in', 'account.2fa_disabled' => 'Turned off two-factor sign-in',
        'account.profile_updated' => 'Updated their profile', 'account.avatar_updated' => 'Changed their photo',
        'settings.business_updated' => 'Updated business details', 'settings.branding_updated' => 'Updated branding',
        'settings.gateways_updated' => 'Changed payment gateway keys', 'settings.alerts_updated' => 'Changed alert settings',
        'settings.reminders_updated' => 'Changed invoice reminders',
        'payout_account.added' => 'Added a payout account', 'payout_account.changed' => 'Changed a payout account', 'payout_account.removed' => 'Removed a payout account',
        'team.invited' => 'Invited a team member', 'team.invitation_accepted' => 'Joined the team', 'team.member_updated' => 'Changed a team member',
        'team.member_removed' => 'Removed a team member', 'inventory.adjusted' => 'Adjusted stock',
        'document.created' => 'Issued a slip', 'document.updated' => 'Edited a slip', 'document.void' => 'Voided a slip',
        'document.credit_note' => 'Issued a credit note', 'document.record_payment' => 'Recorded a payment',
        'customer.created' => 'Added a customer', 'customer.updated' => 'Updated a customer', 'customer.deleted' => 'Deleted a customer',
        'inventory.created' => 'Added a product', 'inventory.updated' => 'Updated a product', 'inventory.deleted' => 'Deleted a product',
        'channel.link_code' => 'Created a chat link code', 'channel.unlink' => 'Unlinked a chat',
        'billing.checkout' => 'Started a payment for VendSlip', 'payments.successful' => 'Payment received',
        'support.code_sent' => 'VendSlip support asked for access', 'support.code_verified' => 'Owner approved VendSlip support access',
        'support.session_started' => 'VendSlip support opened the dashboard', 'support.session_ended' => 'VendSlip support session ended',
    ];

    /** Context keys safe to show the business (never secrets, never request bodies). */
    private const array SAFE_CONTEXT = ['summary', 'fields', 'changes', 'reason', 'role', 'email', 'recipients', 'expires_at', 'attempts', 'kind', 'path'];

    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request, Permission::SETTINGS_MANAGE);
        $merchant = $this->merchant($request);
        $filters = $request->validate([
            'category' => ['nullable', 'in:security,sales,settings,team,support'],
            'user_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $prefixes = match ($filters['category'] ?? null) {
            'security' => ['auth.', 'account.', 'payout_account.', 'webhook.'],
            'sales' => ['document.', 'customer.', 'inventory.', 'payments.'],
            'settings' => ['settings.', 'template.', 'channel.', 'billing.', 'onboarding.'],
            'team' => ['team.'],
            'support' => ['support.'],
            default => null,
        };

        $query = SecurityAuditLog::query()->with('actor:id,name')->where('merchant_id', $merchant->id)
            ->where('event', 'not like', 'admin.%')
            ->latest('created_at')->latest('id');
        if ($prefixes !== null) {
            $query->where(static function ($q) use ($prefixes): void {
                foreach ($prefixes as $prefix) {
                    $q->orWhere('event', 'like', $prefix.'%');
                }
            });
        }
        if (! empty($filters['user_id'])) {
            $query->where('actor_user_id', (int) $filters['user_id']);
        }
        $page = $query->paginate(30);

        $supportIds = collect($page->items())->map(static fn (SecurityAuditLog $l): ?int => isset($l->context['support_admin_id']) ? (int) $l->context['support_admin_id'] : null)->filter()->unique()->values();
        $supportNames = $supportIds->isEmpty() ? collect() : User::query()->whereIn('id', $supportIds)->pluck('name', 'id');

        return response()->json([
            'data' => collect($page->items())->map(static function (SecurityAuditLog $l) use ($supportNames): array {
                $context = is_array($l->context) ? $l->context : [];
                $supportId = isset($context['support_admin_id']) ? (int) $context['support_admin_id'] : null;

                return [
                    'id' => $l->id,
                    'event' => $l->event,
                    'label' => self::LABELS[$l->event] ?? Str::ucfirst(str_replace(['_', '.'], [' ', ' · '], $l->event)),
                    'severity' => $l->severity->value,
                    'actor' => $l->actor?->name,
                    'support_admin' => $supportId !== null ? ($supportNames[$supportId] ?? 'VendSlip support') : null,
                    'ip' => $l->ip,
                    'device' => self::device($l->user_agent),
                    'details' => array_intersect_key($context, array_flip(self::SAFE_CONTEXT)) ?: null,
                    'document_id' => str_starts_with($l->event, 'document.') ? ($context['subject']['id'] ?? null) : null,
                    'created_at' => $l->created_at->toIso8601String(),
                ];
            }),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /** "Chrome on Windows" from a user agent (best effort). */
    private static function device(?string $ua): ?string
    {
        if ($ua === null || $ua === '') {
            return null;
        }
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') => 'Opera',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari',
            default => null,
        };
        $os = match (true) {
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => null,
        };

        return $browser === null && $os === null ? 'Other device' : trim(($browser ?? 'Browser').($os !== null ? " on {$os}" : ''));
    }
}
