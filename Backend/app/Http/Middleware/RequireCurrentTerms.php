<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Legal\LegalConsent;
use App\Services\Security\SupportAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Once a new version of the Terms and Privacy Policy has taken effect, a person who hasn't accepted it
 * can still look around (reads) but can't change anything until they accept it in the web app.
 *
 * Support sessions are let through: VendSlip staff can't accept on the owner's behalf, and blocking
 * them would stop them helping. Turned off with VENDSLIP_LEGAL_REQUIRE_CURRENT=false.
 */
final class RequireCurrentTerms
{
    public function __construct(private readonly LegalConsent $legal) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (
            $request->isMethodSafe()
            || $request->is('api/v1/billing/checkout') // accepting is part of every checkout
            || ! config('vendslip.legal.require_current', true)
            || ! $user instanceof User
            || SupportAccess::current($request) !== null
            || ! $this->legal->mustAccept($user)
        ) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Our Terms of Service and Privacy Policy have been updated. Please review and accept them to continue.',
            'code' => 'terms_update_required',
            'version' => LegalConsent::version(),
        ], 409);
    }
}
