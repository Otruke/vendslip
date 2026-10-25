<?php

declare(strict_types=1);

namespace App\Services\Bot\Actions;

/** Buffered silently: no outbound message, no per-message fee. */
final readonly class StaySilent implements BotAction
{
    public function __construct(public string $reason = 'buffered') {}
}
