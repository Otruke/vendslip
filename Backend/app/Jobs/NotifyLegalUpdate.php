<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\MerchantStatus;
use App\Models\Merchant;
use App\Services\Notifications\AlertCatalog;
use App\Services\Notifications\MerchantAlerts;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Tells every active business's Owners (in the app and by email) about a new Terms / Privacy Policy version. */
final class NotifyLegalUpdate implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $version, public readonly string $summary) {}

    public function handle(MerchantAlerts $alerts): void
    {
        $date = CarbonImmutable::parse($this->version)->format('j F Y');
        $inForce = $this->version <= now(config('vendslip.timezone'))->toDateString();
        $title = $inForce ? 'We’ve updated our Terms and Privacy Policy' : "Our Terms and Privacy Policy change on {$date}";
        $body = $this->summary.' '.($inForce
            ? 'Please review and accept them the next time you sign in.'
            : "Please review them before {$date}. You can accept now, and you’ll be asked to before carrying on after that date.");

        Merchant::query()->where('status', MerchantStatus::ACTIVE->value)
            ->chunkById(200, function ($merchants) use ($alerts, $title, $body): void {
                foreach ($merchants as $merchant) {
                    $alerts->send($merchant, AlertCatalog::LEGAL_UPDATE, $title, $body, '/terms');
                }
            });
    }
}
