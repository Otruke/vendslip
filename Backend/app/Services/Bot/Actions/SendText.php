<?php

declare(strict_types=1);

namespace App\Services\Bot\Actions;

final readonly class SendText implements BotAction
{
    public function __construct(public string $text, public bool $isError = false) {}
}
