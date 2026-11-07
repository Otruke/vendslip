<?php

declare(strict_types=1);

namespace VendSlip\Bots\Facebook;

use App\Services\Bot\Meta\AbstractMessagingSender;
use App\Services\Bot\Meta\MetaGraphClient;
use App\Services\Settings\PlatformSettings;

/**
 * Facebook Messenger through Meta's Messenger Platform (primary route).
 * Sends as the Page with its Page access token; replies are inside the 24h window (RESPONSE).
 */
final class MessengerGraphSender extends AbstractMessagingSender
{
    public function __construct(MetaGraphClient $graph, private readonly PlatformSettings $settings)
    {
        parent::__construct($graph, require dirname(__DIR__).'/config.php');
    }

    /** @return array{token: string|null, instagram_host: bool} */
    protected function credentials(): array
    {
        return ['token' => $this->settings->string('meta.page_token'), 'instagram_host' => false];
    }

    /** @return array<string, mixed> */
    protected function envelope(): array
    {
        return ['messaging_type' => 'RESPONSE'];
    }

    protected function fit(string $text): string
    {
        $max = (int) ($this->config['max_summary_chars'] ?? 1900);

        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1).'…' : $text;
    }
}
