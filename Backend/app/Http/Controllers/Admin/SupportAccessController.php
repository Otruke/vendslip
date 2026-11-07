<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\SupportAccessGrant;
use App\Models\User;
use App\Services\Security\SupportAccess;
use App\Support\Urls;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Super-Admin → "Open this business's dashboard", approved by a code sent to the business owner. */
final class SupportAccessController extends Controller
{
    public function __construct(private readonly SupportAccess $support) {}

    public function send(Request $request, Merchant $merchant): RedirectResponse
    {
        try {
            $sentTo = $this->support->request($merchant, $this->admin($request));
        } catch (DomainRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.merchants.show', [$merchant, '_fragment' => 'support-access'])
            ->with('status', 'Code sent to '.implode(', ', $sentTo).'. Ask the owner to read it to you.');
    }

    /** Correct code → a page with a single-use link that opens the web app as a support session. */
    public function verify(Request $request, Merchant $merchant): View|RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);
        try {
            $token = $this->support->verify($merchant, $this->admin($request), (string) $data['code']);
        } catch (DomainRuleException $e) {
            return redirect()->route('admin.merchants.show', [$merchant, '_fragment' => 'support-access'])->with('error', $e->getMessage());
        }

        // The token rides in the URL fragment, which browsers never send to any server or in a Referer.
        return view('admin.merchants.support-ready', [
            'merchant' => $merchant,
            'url' => Urls::frontend('support-access').'#token='.$token,
            'minutes' => SupportAccess::sessionMinutes(),
        ]);
    }

    public function revoke(Request $request, Merchant $merchant, SupportAccessGrant $grant): RedirectResponse
    {
        abort_unless($grant->merchant_id === $merchant->id, 404);
        $this->support->revoke($grant, $this->admin($request));

        return back()->with('status', 'Support session ended. That browser is signed out on its next action.');
    }

    private function admin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isSuperAdmin(), 403);

        return $user;
    }
}
