<?php

declare(strict_types=1);

namespace VendSlip\Bots\Instagram;

use App\Services\Bot\Meta\AbstractMessagingSender;
use App\Services\Bot\Meta\MetaGraphClient;
use App\Services\Settings\PlatformSettings;

/**
 * Instagram DMs through Meta directly (primary route). Two ways to authenticate:
 *  - Instagram Login: an Instagram user token (meta.instagram_token) → graph.instagram.com
 *  - Facebook Login:  the linked Facebook Page's token (meta.page_token) → graph.facebook.com
 * Instagram caps a text message at 1000 bytes (not characters).
 */
final class InstagramGraphSender extends AbstractMessagingSender
{
    public function __construct(MetaGraphClient $graph, private readonly PlatformSettings $settings)
    {
        parent::__construct($graph, require dirname(__DIR__).'/config.php');
    }

    /** @return array{token: string|null, instagram_host: bool} */
    protected function credentials(): array
    {
        $igToken = $this->settings->string('meta.instagram_token');

        return $igToken !== null
            ? ['token' => $igToken, 'instagram_host' => true]
            : ['token' => $this->settings->string('meta.page_token'), 'instagram_host' => false];
    }

    /** @return array<string, mixed> */
    protected function envelope(): array
    {
        return [];
    }

    protected function fit(string $text): string
    {
        $max = (int) ($this->config['max_text_bytes'] ?? 1000);
        if (strlen($text) <= $max) {
            return $text;
        }

        // Cut on a character boundary, leaving room for the 3-byte ellipsis.
        return mb_strcut($text, 0, $max - 3, 'UTF-8').'…';
    }
}
