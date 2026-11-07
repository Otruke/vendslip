<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Enums\AuditSeverity;
use App\Enums\Role;
use App\Exceptions\DomainRuleException;
use App\Models\Merchant;
use App\Models\SupportAccessGrant;
use App\Models\User;
use App\Notifications\SupportAccessCodeNotification;
use App\Services\Notifications\AlertCatalog;
use App\Services\Notifications\MerchantAlerts;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Super-Admin support access to a business's dashboard, with the business owner's consent.
 *
 *  1. request()  A 6-digit code goes to every active Owner of the business (never to the admin).
 *  2. verify()   The Super-Admin types the code the owner read out. Five tries, 10 minutes.
 *  3. redeem()   A one-time, 2-minute handoff token opens a support session in the web app,
 *                signed in as the business's first owner, for a limited time.
 *
 * Support sessions can't change the account's password, email, two-factor, team access or linked
 * chats (SupportSessionGuard), every audit entry made during one names the Super-Admin, and the
 * session ends on its own when time runs out.
 */
final class SupportAccess
{
    public const string SESSION_KEY = 'support_access';

    private const int MAX_ATTEMPTS = 5;

    public function __construct(private readonly AuditLogger $audit) {}

    public static function codeMinutes(): int
    {
        return max(1, (int) config('vendslip.support_access.code_minutes', 10));
    }

    public static function sessionMinutes(): int
    {
        return max(5, (int) config('vendslip.support_access.session_minutes', 60));
    }

    /** @return Collection<int, User> active owners, oldest first */
    public function owners(Merchant $merchant): Collection
    {
        return User::query()->role(Role::OWNER->value)
            ->where('merchant_id', $merchant->id)
            ->where('is_active', true)
            ->orderBy('created_at')->orderBy('id')
            ->get();
    }

    /**
     * Step 1. Returns the masked addresses the code went to.
     *
     * @return list<string>
     */
    public function request(Merchant $merchant, User $admin): array
    {
        $this->ensureSuperAdmin($admin);
        $owners = $this->owners($merchant);
        if ($owners->isEmpty()) {
            throw new DomainRuleException('This business has no active owner to approve support access.', 'no_owner', 422);
        }

        $code = (string) random_int(100000, 999999);
        DB::transaction(function () use ($merchant, $admin, $owners, $code): void {
            // Only the newest code counts.
            SupportAccessGrant::query()
                ->where('merchant_id', $merchant->id)->where('admin_user_id', $admin->id)
                ->whereNull('verified_at')->whereNull('ended_at')
                ->update(['ended_at' => now(), 'end_reason' => 'superseded']);

            SupportAccessGrant::query()->create([
                'merchant_id' => $merchant->id,
                'admin_user_id' => $admin->id,
                'recipients' => $owners->pluck('email')->values()->all(),
                'otp_hash' => $this->hash($code),
                'otp_expires_at' => now()->addMinutes(self::codeMinutes()),
            ]);
        });

        Notification::send($owners, new SupportAccessCodeNotification($code, $merchant->name, $admin->name, self::codeMinutes(), self::sessionMinutes()));
        $masked = array_values($owners->map(fn (User $u): string => self::maskEmail($u->email))->all());
        $this->audit->log('support.code_sent', $admin, $merchant, ['recipients' => $masked], AuditSeverity::CRITICAL);

        return $masked;
    }

    /** The code this admin is waiting on for this business, if any. */
    public function pending(Merchant $merchant, User $admin): ?SupportAccessGrant
    {
        $grant = SupportAccessGrant::query()
            ->where('merchant_id', $merchant->id)->where('admin_user_id', $admin->id)
            ->whereNull('verified_at')->whereNull('ended_at')
            ->latest('id')->first();

        return $grant?->isAwaitingCode() ? $grant : null;
    }

    /** Step 2. Returns the one-time handoff token for the web app. */
    public function verify(Merchant $merchant, User $admin, string $code): string
    {
        $this->ensureSuperAdmin($admin);
        $code = (string) preg_replace('/\D/', '', $code);

        // The transaction returns either the token or the error to raise AFTER commit, so a wrong
        // guess's attempt count (and the lockout) is saved instead of being rolled back.
        $result = DB::transaction(function () use ($merchant, $admin, $code): string|DomainRuleException {
            $grant = SupportAccessGrant::query()
                ->where('merchant_id', $merchant->id)->where('admin_user_id', $admin->id)
                ->whereNull('verified_at')->whereNull('ended_at')
                ->latest('id')->lockForUpdate()->first();
            if ($grant === null || ! $grant->isAwaitingCode()) {
                return new DomainRuleException('That code has expired. Send a new one.', 'code_expired', 422);
            }

            $grant->attempts++;
            if (strlen($code) !== 6 || ! hash_equals($grant->otp_hash, $this->hash($code))) {
                $locked = $grant->attempts >= self::MAX_ATTEMPTS;
                $grant->fill($locked ? ['ended_at' => now(), 'end_reason' => 'too_many_attempts'] : [])->save();
                $this->audit->log('support.code_failed', $admin, $merchant, ['attempts' => $grant->attempts], AuditSeverity::WARNING);

                return new DomainRuleException($locked ? 'Too many wrong codes. Send a new one.' : 'That code is not right. Check the latest email the owner received.', 'code_invalid', 422);
            }

            $owner = $this->owners($merchant)->first();
            if ($owner === null) {
                return new DomainRuleException('This business has no active owner to sign in as.', 'no_owner', 422);
            }
            $token = Str::random(64);
            $grant->fill([
                'verified_at' => now(),
                'target_user_id' => $owner->id,
                'handoff_token_hash' => $this->hash($token),
                'handoff_expires_at' => now()->addMinutes(2),
            ])->save();
            $this->audit->log('support.code_verified', $admin, $merchant, ['target_user_id' => $owner->id], AuditSeverity::CRITICAL);

            return $token;
        });

        if ($result instanceof DomainRuleException) {
            throw $result;
        }

        return $result;
    }

    /** Step 3 (web app). Signs this browser in as the business owner, as a support session. */
    public function redeem(Request $request, string $token): User
    {
        // Check first so a request without a browser session can't burn the one-time token.
        if (! $request->hasSession()) {
            throw new DomainRuleException('Open the support link in the VendSlip web app.', 'no_session', 400);
        }
        $grant = DB::transaction(function () use ($token): SupportAccessGrant {
            $grant = SupportAccessGrant::query()->where('handoff_token_hash', $this->hash($token))->lockForUpdate()->first();
            if ($grant === null || $grant->started_at !== null || $grant->ended_at !== null
                || $grant->handoff_expires_at === null || $grant->handoff_expires_at->isPast()) {
                throw new DomainRuleException('This support link has expired. Verify a new code from the admin portal.', 'handoff_expired', 422);
            }
            $grant->load(['admin', 'target', 'merchant']);
            $owner = $grant->target;
            if (! $grant->admin->is_active || ! $grant->admin->isSuperAdmin()) {
                throw new DomainRuleException('Only an active Super-Admin can open support sessions.', 'not_super_admin', 403);
            }
            if ($owner === null || ! $owner->is_active || $owner->merchant_id !== $grant->merchant_id || ! $owner->hasRole(Role::OWNER->value)) {
                throw new DomainRuleException('The business owner changed. Request a new code.', 'owner_changed', 422);
            }
            $grant->fill([
                'handoff_token_hash' => null, // single use
                'started_at' => now(),
                'expires_at' => now()->addMinutes(self::sessionMinutes()),
            ])->save();

            return $grant;
        });

        /** @var User $owner */
        $owner = $grant->target;
        Auth::guard('web')->login($owner); // no "remember me": the session dies with the browser or the timer
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, [
            'grant_id' => $grant->id,
            'admin_id' => $grant->admin_user_id,
            'admin_name' => $grant->admin->name,
            'merchant_id' => $grant->merchant_id,
            'expires_at' => $grant->expires_at?->getTimestamp(),
        ]);
        app(MerchantAlerts::class)->send(
            $grant->merchant,
            AlertCatalog::SUPPORT_SESSION,
            'VendSlip support is in your dashboard',
            "{$grant->admin->name} from VendSlip support opened your dashboard with the code you shared, until ".$grant->expires_at?->timezone(config('vendslip.timezone'))->format('H:i').'. Everything they do is in your Activity log.',
            '/dashboard/activity',
        );
        $this->audit->log('support.session_started', $grant->admin, $grant->merchant, [
            'target_user_id' => $owner->id, 'grant_id' => $grant->id, 'expires_at' => $grant->expires_at?->toIso8601String(),
        ], AuditSeverity::CRITICAL);

        return $owner;
    }

    /** @return array{grant_id: int, admin_id: int, admin_name: string, merchant_id: string, expires_at: int}|null */
    public static function current(Request $request): ?array
    {
        if (! $request->hasSession()) {
            return null;
        }
        $data = $request->session()->get(self::SESSION_KEY);
        if (! is_array($data) || ! isset($data['grant_id'], $data['admin_id'], $data['merchant_id'], $data['expires_at'])) {
            return null;
        }

        return [
            'grant_id' => (int) $data['grant_id'],
            'admin_id' => (int) $data['admin_id'],
            'admin_name' => (string) ($data['admin_name'] ?? 'VendSlip support'),
            'merchant_id' => (string) $data['merchant_id'],
            'expires_at' => (int) $data['expires_at'],
        ];
    }

    /**
     * True while a support session is valid; false when it has run out or was ended elsewhere.
     *
     * @param  array{grant_id: int, admin_id: int, admin_name: string, merchant_id: string, expires_at: int}  $support
     */
    public function stillValid(array $support): bool
    {
        if ($support['expires_at'] <= now()->getTimestamp()) {
            return false;
        }
        $grant = SupportAccessGrant::query()->find($support['grant_id']);

        return $grant !== null && $grant->isLive();
    }

    /** Ends the support session on this request (logout, timeout or revoked). */
    public function end(Request $request, string $reason): void
    {
        $support = self::current($request);
        if ($support === null) {
            return;
        }
        $grant = SupportAccessGrant::query()->with(['admin', 'merchant'])->find($support['grant_id']);
        if ($grant !== null && $grant->ended_at === null) {
            $grant->fill(['ended_at' => now(), 'end_reason' => $reason])->save();
            $this->audit->log('support.session_ended', $grant->admin, $grant->merchant, ['grant_id' => $grant->id, 'reason' => $reason], AuditSeverity::CRITICAL);
        }
        $request->session()->forget(self::SESSION_KEY);
    }

    /** Super-Admin ends a live session from the portal. */
    public function revoke(SupportAccessGrant $grant, User $admin): void
    {
        if ($grant->ended_at !== null) {
            return;
        }
        $grant->fill(['ended_at' => now(), 'end_reason' => 'revoked'])->save();
        $this->audit->log('support.session_ended', $admin, $grant->merchant_id, ['grant_id' => $grant->id, 'reason' => 'revoked'], AuditSeverity::CRITICAL);
    }

    public static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).str_repeat('•', max(2, min(6, mb_strlen($local) - 1))).'@'.$domain;
    }

    private function ensureSuperAdmin(User $admin): void
    {
        if (! $admin->is_active || ! $admin->isSuperAdmin()) {
            throw new DomainRuleException('Only a Super-Admin can open a business dashboard.', 'not_super_admin', 403);
        }
    }

    /** Keyed hash so a database leak doesn't reveal live codes or tokens. */
    private function hash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
