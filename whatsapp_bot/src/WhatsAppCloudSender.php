<?php

declare(strict_types=1);

namespace VendSlip\Bots\WhatsApp;

use App\Services\Bot\Actions\AskConfirmation;
use App\Services\Bot\Actions\DeliverSlip;
use App\Services\Bot\Meta\ButtonPayloads;
use App\Services\Bot\Meta\MetaChannelSender;
use App\Services\Bot\Meta\MetaGraphClient;
use App\Services\Settings\PlatformSettings;

/**
 * WhatsApp through Meta's Cloud API (primary route): POST /{phone-number-id}/messages.
 *
 * Replies go out inside the 24h customer-service window, so no approved template is needed:
 * the summary carries native reply buttons (WhatsApp allows 3) and the slip goes out as a
 * real PDF document.
 */
final class WhatsAppCloudSender implements MetaChannelSender
{
    /** @var array<string, mixed> */
    private array $config;

    public function __construct(
        private readonly MetaGraphClient $graph,
        private readonly PlatformSettings $settings,
    ) {
        $this->config = require dirname(__DIR__).'/config.php';
    }

    public function text(string $to, string $text): bool
    {
        return $this->send($to, ['type' => 'text', 'text' => ['body' => mb_substr($text, 0, 4096), 'preview_url' => false]]);
    }

    public function confirmation(string $to, AskConfirmation $action): bool
    {
        $summary = new WhatsAppInteractiveSummary($this->config);
        $titles = (array) ($this->config['button_titles'] ?? []);
        $buttons = [];
        foreach (['confirm' => ButtonPayloads::CONFIRM, 'edit' => ButtonPayloads::EDIT, 'cancel' => ButtonPayloads::CANCEL] as $key => $id) {
            $buttons[] = ['type' => 'reply', 'reply' => ['id' => $id, 'title' => mb_substr((string) ($titles[$key] ?? ucfirst($key)), 0, 20)]];
        }

        $sent = $this->send($to, [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $summary->body($action, false)],
                'action' => ['buttons' => $buttons],
            ],
        ]);

        // Buttons refused (old app version, policy change): the numbered prompt still works.
        return $sent || $this->text($to, $summary->body($action, true));
    }

    public function slip(string $to, DeliverSlip $action): bool
    {
        if ((bool) ($this->config['attach_pdf'] ?? true) && $this->send($to, [
            'type' => 'document',
            'document' => ['link' => $action->pdfUrl, 'filename' => $action->filename, 'caption' => mb_substr($action->caption, 0, 1024)],
        ])) {
            return true;
        }

        return $this->text($to, $action->caption."\n📄 ".$action->pdfUrl);
    }

    /** @param array<string, mixed> $message */
    private function send(string $to, array $message): bool
    {
        $phoneNumberId = $this->settings->string('meta.whatsapp_phone_number_id');
        $token = $this->settings->string('meta.whatsapp_token');
        $to = (string) preg_replace('/\D/', '', $to);
        if ($phoneNumberId === null || $token === null || $to === '') {
            return false;
        }

        return $this->graph->post(rawurlencode($phoneNumberId).'/messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            ...$message,
        ], $token)['ok'];
    }
}
