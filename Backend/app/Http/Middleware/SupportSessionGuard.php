<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Security\SupportAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies to the signed-in web app API.
 *
 *  SupportSessionGuard::class            Ends a support session the moment its time runs out (or a Super-Admin
 *                           revoked it) and signs the browser out.
 *  SupportSessionGuard::class.':forbid'  On routes support may never use: the owner's password, email, two-factor,
 *                           team access, linked chats and payout gateways. Support can help, but can't
 *                           create a new way into the account or redirect its money.
 */
final class SupportSessionGuard
{
    public function __construct(private readonly SupportAccess $support) {}

    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $session = SupportAccess::current($request);
        if ($session === null) {
            return $next($request);
        }

        if (! $this->support->stillValid($session)) {
            $this->support->end($request, 'expired');
            Auth::guard('web')->logout();
            Auth::forgetGuards(); // drop any user cached on the Sanctum guard (long-running workers, tests)
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['message' => 'The support session has ended.', 'code' => 'support_session_ended'], 401);
        }

        if ($mode === 'forbid') {
            return response()->json([
                'message' => 'Not available during a support session. The business owner has to do this themselves.',
                'code' => 'support_session_restricted',
            ], 403);
        }

        return $next($request);
    }
}
