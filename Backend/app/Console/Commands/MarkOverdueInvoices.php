<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class MarkOverdueInvoices extends Command
{
    protected $signature = 'netslip:mark-overdue';

    protected $description = 'Flag unpaid / part-paid invoices past their due date as OVERDUE';

    public function handle(): int
    {
        $today = CarbonImmutable::now(config('netslip.timezone'))->toDateString();
        $count = Document::query()
            ->where('doc_type', DocumentType::INVOICE->value)
            ->whereIn('status', [DocumentStatus::UNPAID->value, DocumentStatus::PARTIAL->value])
            ->where('balance_due_minor', '>', 0)
            ->whereDate('due_date', '<', $today)
            ->update(['status' => DocumentStatus::OVERDUE->value, 'updated_at' => now()]);

        $this->info("{$count} invoice(s) marked overdue.");

        return self::SUCCESS;
    }
}
