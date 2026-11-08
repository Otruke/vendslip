<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\NotifyLegalUpdate;
use App\Models\LegalAcceptance;
use App\Models\User;
use App\Services\Legal\LegalConsent;
use App\Services\Security\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Super-Admin: publish a new version of the Terms and Privacy Policy and see who has accepted it. */
final class LegalController extends Controller
{
    public function __construct(
        private readonly LegalConsent $legal,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): View
    {
        $latest = $this->legal->latest();
        $people = User::query()->whereNotNull('merchant_id')->where('is_active', true);
        $total = (clone $people)->count();
        $accepted = (clone $people)->whereIn('id', LegalAcceptance::query()->select('user_id')
            ->where('document', LegalAcceptance::TERMS)->where('version', '>=', $latest['version']))->count();

        return view('admin.legal.index', [
            'latest' => $latest,
            'inForce' => $this->legal->inForce(),
            'history' => $this->legal->history(),
            'total' => $total,
            'accepted' => $accepted,
            'today' => self::today(),
            'appUrl' => rtrim((string) config('vendslip.frontend_url', config('app.url')), '/'),
        ]);
    }

    public function publish(Request $request): RedirectResponse
    {
        $today = self::today();
        $latest = $this->legal->latest()['version'];
        $data = $request->validate([
            'effective_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today, 'before_or_equal:'.CarbonImmutable::parse($today)->addDays(90)->toDateString(), 'after:'.$latest],
            'summary' => ['required', 'string', 'min:10', 'max:600'],
            'live' => ['accepted'],
            'notify' => ['nullable', 'boolean'],
        ], [
            'effective_on.after' => 'Pick a date after the current version ('.CarbonImmutable::parse($latest)->format('j M Y').').',
            'effective_on.after_or_equal' => 'The date can’t be in the past.',
            'effective_on.before_or_equal' => 'Pick a date within the next 90 days.',
            'live.accepted' => 'Update the wording on the Terms and Privacy pages first, then publish.',
        ]);

        /** @var User $admin */
        $admin = $request->user();
        $summary = trim((string) $data['summary']);
        $this->legal->publish($data['effective_on'], $summary, $admin);
        $this->audit->log('admin.legal_published', $admin, null, ['version' => $data['effective_on'], 'summary' => $summary]);
        if ($request->boolean('notify')) {
            NotifyLegalUpdate::dispatch($data['effective_on'], $summary);
        }

        $when = $data['effective_on'] === $today ? 'today' : 'on '.CarbonImmutable::parse($data['effective_on'])->format('j M Y');

        return back()->with('status', "Version {$data['effective_on']} published. It applies {$when}".($request->boolean('notify') ? '; business owners are being told by email and in the app.' : '.'));
    }

    private static function today(): string
    {
        return now(config('vendslip.timezone'))->toDateString();
    }
}
