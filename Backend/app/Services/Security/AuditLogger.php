<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Enums\AuditSeverity;
use App\Models\Merchant;
use App\Models\SecurityAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Throwable;

final class AuditLogger
{
    public function __construct(private readonly ?Request $request = null) {}

    /** @param array<string, mixed> $context */
    public function log(
        string $event,
        ?User $actor = null,
        Merchant|string|null $merchant = null,
        array $context = [],
        AuditSeverity $severity = AuditSeverity::INFO,
    ): void {
        try {
            SecurityAuditLog::query()->create([
                'actor_user_id' => $actor?->id,
                'merchant_id' => $merchant instanceof Merchant ? $merchant->id : ($merchant ?? $actor?->merchant_id),
                'event' => $event,
                'severity' => $severity,
                'ip' => $this->request?->ip(),
                'user_agent' => mb_substr((string) $this->request?->userAgent(), 0, 500) ?: null,
                'context' => ($context = $this->withSupport($context)) === [] ? null : $context,
            ]);
        } catch (Throwable $e) {
            // Audit logging must never break the business transaction; surface via the app log.
            report($e);
        }
    }

    /**
     * Anything done during a support session is stamped with the Super-Admin behind it, so the
     * business's audit trail never attributes support's actions to the owner alone.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function withSupport(array $context): array
    {
        $support = $this->request !== null ? SupportAccess::current($this->request) : null;

        return $support === null ? $context : $context + ['support_admin_id' => (int) $support['admin_id'], 'support_grant_id' => (int) $support['grant_id']];
    }
}
