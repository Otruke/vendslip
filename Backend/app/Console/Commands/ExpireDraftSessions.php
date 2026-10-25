<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DraftSessionStatus;
use App\Models\BotDraftSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ExpireDraftSessions extends Command
{
    protected $signature = 'netslip:expire-drafts';

    protected $description = 'Expire stale bot drafts and prune old idempotency keys / link codes';

    public function handle(): int
    {
        $expired = BotDraftSession::query()
            ->whereIn('status', [DraftSessionStatus::COLLECTING->value, DraftSessionStatus::AWAITING_CONFIRMATION->value])
            ->where('expires_at', '<=', now())
            ->update(['status' => DraftSessionStatus::EXPIRED->value, 'updated_at' => now()]);
        $keys = DB::table('idempotency_keys')->where('created_at', '<', now()->subDays(14))->delete();
        $codes = DB::table('channel_link_codes')->where('expires_at', '<', now()->subDay())->delete();

        $this->info("Expired {$expired} draft(s); pruned {$keys} idempotency key(s) and {$codes} link code(s).");

        return self::SUCCESS;
    }
}
