<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Reminders\InvoiceReminders;
use Illuminate\Console\Command;

final class SendInvoiceReminders extends Command
{
    protected $signature = 'vendslip:invoice-reminders';

    protected $description = 'Email customers about invoices that are due soon, due today or overdue';

    public function handle(InvoiceReminders $reminders): int
    {
        $count = $reminders->run();
        $this->info("Queued {$count} invoice reminder(s).");

        return self::SUCCESS;
    }
}
