<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\DomainRuleException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every /api/v1 merchant route requires an active user attached to a merchant.
 * Suspended merchants keep read access (to export their records) but cannot write.
 */
final class EnsureMerchantMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->is_active || $user->merchant_id === null || $user->merchant === null) {
            return response()->json(['message' => 'No merchant workspace is attached to this account.', 'code' => 'no_merchant'], 403);
        }

        if ($user->merchant->isSuspended() && ! $request->isMethodSafe()) {
            throw DomainRuleException::suspended();
        }

        return $next($request);
    }
}
