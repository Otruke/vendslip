<?php

declare(strict_types=1);

namespace App\Services\Bot;

use App\Enums\Channel;

/** Channel-neutral inbound chat message, produced by each bot module's webhook handler. */
final readonly class InboundMessage
{
    /**
     * @param  string|null  $action  Normalised button tap: confirm | confirm_clean | edit | cancel
     * @param  array<string, mixed>  $meta  Channel extras (telegram callback_query_id, message ids ...)
     */
    public function __construct(
        public Channel $channel,
        public string $messageId,
        public string $externalChatId,
        public string $externalUserId,
        public ?string $displayName,
        public string $text,
        public ?string $action = null,
        public bool $isGroup = false,
        public array $meta = [],
    ) {}
}
