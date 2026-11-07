<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Merchant;
use App\Services\Notifications\AlertCatalog;
use App\Services\Notifications\MerchantAlerts;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class MarkOverdueInvoices extends Command
{
    protected $signature = 'vendslip:mark-overdue';

    protected $description = 'Flag unpaid / part-paid invoices past their due date as OVERDUE and tell each business';

    public function handle(MerchantAlerts $alerts): int
    {
        $today = CarbonImmutable::now(config('vendslip.timezone'))->toDateString();
        $total = 0;

        Document::query()
            ->where('doc_type', DocumentType::INVOICE->value)
            ->whereIn('status', [DocumentStatus::UNPAID->value, DocumentStatus::PARTIAL->value])
            ->where('balance_due_minor', '>', 0)
            ->whereDate('due_date', '<', $today)
            ->select(['id', 'merchant_id', 'doc_number', 'currency', 'balance_due_minor'])
            ->orderBy('merchant_id')->orderBy('id')
            ->get()
            ->groupBy('merchant_id')
            ->each(function ($invoices, $merchantId) use ($alerts, &$total): void {
                // Only rows still unpaid at update time count (a payment may land in between).
                $updated = Document::query()->whereIn('id', $invoices->pluck('id'))
                    ->whereIn('status', [DocumentStatus::UNPAID->value, DocumentStatus::PARTIAL->value])
                    ->update(['status' => DocumentStatus::OVERDUE->value, 'updated_at' => now()]);
                $total += $updated;
                $merchant = Merchant::query()->find($merchantId);
                if ($updated === 0 || $merchant === null) {
                    return;
                }
                $amounts = $invoices->groupBy('currency')
                    ->map(static fn ($group, $currency): string => Money::format((int) $group->sum('balance_due_minor'), (string) $currency))
                    ->implode(' + ');
                $number = (string) $invoices->first()?->doc_number;
                $alerts->send(
                    $merchant,
                    AlertCatalog::INVOICE_OVERDUE,
                    $updated === 1 ? "Invoice {$number} is now overdue" : "{$updated} invoices are now overdue",
                    "Still to collect: {$amounts}. Send a reminder or record a payment from Receipts & Invoices.",
                    '/dashboard/documents?status=OVERDUE',
                );
            });

        $this->info("{$total} invoice(s) marked overdue.");

        return self::SUCCESS;
    }
}
