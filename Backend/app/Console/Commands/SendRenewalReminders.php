<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Billing\RenewalReminders;
use Illuminate\Console\Command;

final class SendRenewalReminders extends Command
{
    protected $signature = 'vendslip:renewal-reminders';

    protected $description = 'Remind business owners before (and just after) their VendSlip plan ends';

    public function handle(RenewalReminders $reminders): int
    {
        $count = $reminders->run();
        $this->info("Reminded {$count} business(es).");

        return self::SUCCESS;
    }
}
