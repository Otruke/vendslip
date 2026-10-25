<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Support\Facades\DB;

/** Exactly-once guard for webhook deliveries (Twilio MessageSid, Telegram update_id ...). */
final class Idempotency
{
    /** Returns true the first time a (scope, key) pair is seen, false on every retry/duplicate. */
    public function claim(string $scope, string $key): bool
    {
        return DB::table('idempotency_keys')->insertOrIgnore([
            'scope' => mb_substr($scope, 0, 64),
            'key' => mb_substr($key, 0, 191),
            'created_at' => now(),
        ]) === 1;
    }
}
