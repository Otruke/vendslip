<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Legal\LegalConsent;
use App\Services\Security\AuditLogger;
use App\Services\Security\SessionPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Accepting a newer version of the Terms of Service and Privacy Policy from the web app. */
final class LegalController extends Controller
{
    public function __construct(
        private readonly LegalConsent $legal,
        private readonly AuditLogger $audit,
        private readonly SessionPayload $payload,
    ) {}

    public function accept(Request $request): JsonResponse
    {
        $request->validate(
            ['accept_terms' => ['required', 'accepted']],
            ['accept_terms.required' => 'Please accept the Terms of Service and Privacy Policy.', 'accept_terms.accepted' => 'Please accept the Terms of Service and Privacy Policy.'],
        );
        /** @var User $user */
        $user = $request->user();

        if (! $this->legal->hasAcceptedCurrent($user)) {
            $this->legal->record($user, LegalConsent::UPDATE, $request);
            $this->audit->log('legal.terms_accepted', $user, $user->merchant_id, ['version' => LegalConsent::version()]);
        }

        return response()->json($this->payload->for($user));
    }
}
