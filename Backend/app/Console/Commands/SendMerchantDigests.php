<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Channel;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\ChannelIdentity;
use App\Models\Document;
use App\Models\Merchant;
use App\Services\Billing\PlanEntitlements;
use App\Support\Money;
use App\Support\Urls;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use NetSlip\Bots\Telegram\TelegramApiClient;

/**
 * Daily 8pm digests to linked Telegram chats (free to send):
 *  - Pro+: sales summary for the day (feature sales_summaries)
 *  - Business Plus: overdue debtors list with pay links (feature debt_reminders)
 * WhatsApp is not used here: messages outside the 24h window need pre-approved templates.
 */
final class SendMerchantDigests extends Command
{
    protected $signature = 'netslip:digests';

    protected $description = 'Send daily sales summaries and debt reminders to linked Telegram chats';

    public function handle(PlanEntitlements $entitlements, TelegramApiClient $telegram): int
    {
        if (! $telegram->isConfigured()) {
            $this->warn('Telegram is not configured; skipping digests.');

            return self::SUCCESS;
        }
        $tz = (string) config('netslip.timezone');
        $start = CarbonImmutable::now($tz)->startOfDay();
        $sent = 0;

        Merchant::query()->where('status', 'ACTIVE')->chunkById(100, function ($merchants) use ($entitlements, $telegram, $start, &$sent): void {
            foreach ($merchants as $merchant) {
                /** @var Merchant $merchant */
                $lines = [];
                if ($entitlements->has($merchant, PlanEntitlements::SALES_SUMMARIES)) {
                    $docs = Document::query()->forMerchant($merchant)->whereNull('parent_document_id')
                        ->where('doc_type', '!=', DocumentType::CREDIT_NOTE->value)
                        ->where('status', '!=', DocumentStatus::VOID->value)->where('issued_at', '>=', $start)->get();
                    $lines[] = "📊 Today at {$merchant->name}";
                    $lines[] = $docs->count().' slip(s) · '.Money::format((int) $docs->sum('total_minor'), $merchant->currency).' billed';
                    foreach (Channel::cases() as $channel) {
                        $n = $docs->where('channel', $channel)->count();
                        if ($n > 0) {
                            $lines[] = "· {$channel->label()}: {$n}";
                        }
                    }
                }
                if ($entitlements->has($merchant, PlanEntitlements::DEBT_REMINDERS)) {
                    $overdue = Document::query()->forMerchant($merchant)->where('status', DocumentStatus::OVERDUE->value)
                        ->orderByDesc('balance_due_minor')->limit(10)->get();
                    if ($overdue->isNotEmpty()) {
                        $lines[] = '';
                        $lines[] = '⏰ Overdue invoices (forward the pay link):';
                        foreach ($overdue as $doc) {
                            $lines[] = "· {$doc->doc_number} {$doc->customer_name} — ".Money::format($doc->balance_due_minor, $doc->currency).' '.Urls::pay($doc->public_uid);
                        }
                    }
                }
                if ($lines === []) {
                    continue;
                }
                $chats = ChannelIdentity::query()->forMerchant($merchant)->where('channel', Channel::TELEGRAM->value)->pluck('external_user_id')->unique();
                foreach ($chats as $chatId) {
                    $result = $telegram->call('sendMessage', ['chat_id' => $chatId, 'text' => implode("\n", $lines), 'link_preview_options' => ['is_disabled' => true]]);
                    $sent += $result['ok'] ? 1 : 0;
                }
            }
        });

        $this->info("Sent {$sent} digest message(s).");

        return self::SUCCESS;
    }
}
