<?php

declare(strict_types=1);

namespace NetSlip\Bots\Telegram;

use App\Services\Bot\InboundMessage;

/**
 * Bot API 10.3 ephemeral helpers:
 *  - EphemeralMessageParameters {receiver_user_id, callback_query_id?, replace_callback_query_message?}
 *    so order drafts typed in a busy sales group are only visible to the agent who typed them.
 *  - sendRichMessageDraft {chat_id, draft_id, rich_message, can_stop, keep_on_stop} to stream a
 *    "rendering…" preview in private chats while DOMPDF runs.
 */
final class TelegramEphemeralDraftService
{
    public function __construct(
        private readonly TelegramApiClient $api,
        private readonly TelegramRichMessageBuilder $builder,
        private readonly bool $ephemeralInGroups = true,
    ) {}

    /** @return array<string, mixed> extra send params (empty for private chats) */
    public function ephemeralParams(InboundMessage $message, bool $replaceCallbackMessage = false): array
    {
        if (! $message->isGroup || ! $this->ephemeralInGroups) {
            return [];
        }
        $params = ['receiver_user_id' => (int) $message->externalUserId];
        $callbackId = $message->meta['callback_query_id'] ?? null;
        // Must be false for callbacks that came from an ephemeral message (those are edited instead).
        if (is_string($callbackId) && ! ($message->meta['from_ephemeral'] ?? false)) {
            $params['callback_query_id'] = $callbackId;
            if ($replaceCallbackMessage) {
                $params['replace_callback_query_message'] = true;
            }
        }

        return ['ephemeral_message_parameters' => $params];
    }

    public function streamRendering(InboundMessage $message): void
    {
        if ($message->isGroup) {
            return; // sendRichMessageDraft targets private chats only
        }
        $this->api->call('sendRichMessageDraft', [
            'chat_id' => (int) $message->externalChatId,
            'draft_id' => max(1, crc32($message->messageId) & 0x7FFFFFFF),
            'rich_message' => $this->builder->renderingDraft(),
            'can_stop' => false,
            'keep_on_stop' => false,
        ]);
    }
}
