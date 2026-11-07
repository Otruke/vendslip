<?php

declare(strict_types=1);

namespace App\Services\Bot\Meta;

use App\Services\Bot\Actions\AskConfirmation;
use App\Services\Bot\Actions\DeliverSlip;
use App\Services\Bot\SummaryFormatter;

/**
 * Messenger Platform send API, shared by Facebook Messenger and Instagram DMs:
 * POST /me/messages {recipient:{id}, message:{text, quick_replies[] | attachment}}.
 */
abstract class AbstractMessagingSender implements MetaChannelSender
{
    /** @param array<string, mixed> $config */
    public function __construct(
        protected readonly MetaGraphClient $graph,
        protected readonly array $config,
    ) {}

    /** @return array{token: string|null, instagram_host: bool} */
    abstract protected function credentials(): array;

    /**
     * Extra top-level fields (Messenger needs messaging_type).
     *
     * @return array<string, mixed>
     */
    abstract protected function envelope(): array;

    abstract protected function fit(string $text): string;

    public function text(string $to, string $text): bool
    {
        return $this->send($to, ['text' => $this->fit($text)]);
    }

    public function confirmation(string $to, AskConfirmation $action): bool
    {
        $buttons = (array) ($this->config['quick_replies'] ?? []);
        $replies = [];
        foreach (['confirm' => ButtonPayloads::CONFIRM, 'edit' => ButtonPayloads::EDIT, 'cancel' => ButtonPayloads::CANCEL] as $key => $payload) {
            $replies[] = ['content_type' => 'text', 'title' => (string) ($buttons[$key] ?? ucfirst($key)), 'payload' => $payload];
        }
        if ($action->canUseCleanCredit) {
            array_splice($replies, 1, 0, [['content_type' => 'text', 'title' => (string) ($buttons['clean'] ?? 'Use 1 credit'), 'payload' => ButtonPayloads::CONFIRM_CLEAN]]);
        }

        // Numbered choices stay in the text so a vendor can still reply "1" if the chips are dismissed.
        $sent = $this->send($to, ['text' => $this->fit(SummaryFormatter::text($action, true)), 'quick_replies' => $replies]);

        return $sent || $this->text($to, SummaryFormatter::text($action, true));
    }

    public function slip(string $to, DeliverSlip $action): bool
    {
        $this->text($to, $action->caption);
        if ((bool) ($this->config['attach_pdf'] ?? true)
            && $this->send($to, ['attachment' => ['type' => 'file', 'payload' => ['url' => $action->pdfUrl, 'is_reusable' => false]]])) {
            return true;
        }

        return $this->text($to, '📄 Download your slip: '.$action->pdfUrl);
    }

    /** @param array<string, mixed> $message */
    protected function send(string $to, array $message): bool
    {
        ['token' => $token, 'instagram_host' => $instagramHost] = $this->credentials();
        if ($token === null || $to === '') {
            return false;
        }

        return $this->graph->post('me/messages', [
            'recipient' => ['id' => $to],
            ...$this->envelope(),
            'message' => $message,
        ], $token, $instagramHost)['ok'];
    }
}
