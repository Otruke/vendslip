<?php

declare(strict_types=1);

namespace App\Services\Reminders;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Merchant;
use App\Notifications\InvoiceReminderNotification;
use App\Services\Billing\PlanEntitlements;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Emails a business's customers before an invoice is due, on the day, and at intervals once it's
 * overdue. Each stage is claimed in invoice_reminders first, so a reminder is never sent twice even
 * if the scheduler runs again or overlaps. Customer emails are a Business Plus feature (debt
 * reminders); the business's own team hears about overdue invoices through alerts on every plan.
 */
final class InvoiceReminders
{
    public const array DEFAULTS = [
        'customer_emails' => true,
        'days_before' => 3,
        'on_due_date' => true,
        'overdue_every_days' => 7,
        'overdue_max' => 3,
    ];

    public function __construct(private readonly PlanEntitlements $entitlements) {}

    /** @return array{customer_emails: bool, days_before: int, on_due_date: bool, overdue_every_days: int, overdue_max: int} */
    public static function settings(Merchant $merchant): array
    {
        $own = is_array($merchant->reminder_settings) ? $merchant->reminder_settings : [];

        return [
            'customer_emails' => (bool) ($own['customer_emails'] ?? self::DEFAULTS['customer_emails']),
            'days_before' => max(0, min(14, (int) ($own['days_before'] ?? self::DEFAULTS['days_before']))),
            'on_due_date' => (bool) ($own['on_due_date'] ?? self::DEFAULTS['on_due_date']),
            'overdue_every_days' => max(0, min(30, (int) ($own['overdue_every_days'] ?? self::DEFAULTS['overdue_every_days']))),
            'overdue_max' => max(0, min(6, (int) ($own['overdue_max'] ?? self::DEFAULTS['overdue_max']))),
        ];
    }

    /**
     * Which reminder (if any) an invoice is due for today.
     *
     * @param  array{customer_emails: bool, days_before: int, on_due_date: bool, overdue_every_days: int, overdue_max: int}  $s
     */
    public static function stageFor(int $daysUntilDue, array $s): ?string
    {
        if ($daysUntilDue > 0) {
            return $s['days_before'] > 0 && $daysUntilDue <= $s['days_before'] ? 'BEFORE_DUE' : null;
        }
        if ($daysUntilDue === 0) {
            return $s['on_due_date'] ? 'DUE_TODAY' : null;
        }
        if ($s['overdue_every_days'] <= 0 || $s['overdue_max'] <= 0) {
            return null;
        }
        $round = intdiv(-$daysUntilDue, $s['overdue_every_days']);

        // Only the latest round: a business that turns reminders on never sends a backlog at once.
        return $round >= 1 ? 'OVERDUE_'.min($round, $s['overdue_max']) : null;
    }

    /** Sends today's reminders. Returns how many emails were queued. */
    public function run(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::now(config('vendslip.timezone'))->startOfDay();
        $sent = 0;

        Document::query()
            ->with('merchant')
            ->where('doc_type', DocumentType::INVOICE->value)
            ->whereIn('status', [DocumentStatus::UNPAID->value, DocumentStatus::PARTIAL->value, DocumentStatus::OVERDUE->value])
            ->where('balance_due_minor', '>', 0)
            ->whereNotNull('due_date')
            ->whereNotNull('customer_email')
            ->whereBetween('due_date', [$today->subDays(30 * 6 + 1)->toDateString(), $today->addDays(14)->toDateString()])
            ->orderBy('id')
            ->chunkById(200, function ($invoices) use ($today, &$sent): void {
                foreach ($invoices as $invoice) {
                    /** @var Document $invoice */
                    $sent += $this->remind($invoice, $today) ? 1 : 0;
                }
            });

        return $sent;
    }

    private function remind(Document $invoice, CarbonImmutable $today): bool
    {
        $merchant = $invoice->merchant;
        if ($merchant->isSuspended() || $invoice->due_date === null) {
            return false;
        }
        $settings = self::settings($merchant);
        if (! $settings['customer_emails'] || ! $this->entitlements->has($merchant, PlanEntitlements::DEBT_REMINDERS)) {
            return false;
        }
        $email = trim((string) $invoice->customer_email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }
        $due = CarbonImmutable::parse($invoice->due_date->format('Y-m-d'), config('vendslip.timezone'))->startOfDay();
        $days = (int) $today->diffInDays($due, false);
        $stage = self::stageFor($days, $settings);
        if ($stage === null) {
            return false;
        }

        // Claim the stage first: a duplicate run finds the row and skips.
        $claimed = DB::table('invoice_reminders')->insertOrIgnore([
            'document_id' => $invoice->id, 'stage' => $stage, 'recipient' => $email, 'sent_at' => now(),
        ]);
        if ($claimed === 0) {
            return false;
        }

        try {
            Notification::route('mail', $email)->notify(new InvoiceReminderNotification(
                stage: $stage,
                merchantName: $merchant->name,
                merchantEmail: $merchant->email,
                merchantPhone: $merchant->phone,
                customerName: $invoice->customer_name,
                docNumber: $invoice->doc_number,
                balance: Money::format($invoice->balance_due_minor, $invoice->currency),
                dueDate: $due->format('d M Y'),
                days: max(0, $days),
                payUrl: $invoice->shareUrl(),
            ));
        } catch (Throwable $e) {
            DB::table('invoice_reminders')->where('document_id', $invoice->id)->where('stage', $stage)->delete();
            Log::warning('reminders.send_failed', ['document_id' => $invoice->id, 'error' => $e->getMessage()]);

            return false;
        }

        return true;
    }
}
