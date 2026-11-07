<?php

declare(strict_types=1);

namespace App\Services\Bot;

use App\Enums\Channel;
use App\Services\Settings\PlatformSettings;

/**
 * Which route carries the three Meta-owned channels (WhatsApp, Messenger, Instagram).
 *
 *  - meta   (primary)   Meta Cloud API + Messenger Platform, webhook /api/v1/webhooks/meta
 *  - twilio (secondary) Twilio Programmable Messaging, webhooks /api/v1/webhooks/{whatsapp|facebook|instagram}
 *
 * Exactly one route is live. The other one acknowledges its webhooks and does nothing, so a
 * page or number still wired to both can never get a double reply. Chat ids use the same
 * "whatsapp:+234…", "messenger:<psid>", "instagram:<igsid>" shape on both routes, so linked
 * chats keep working after a switch.
 */
final class ChannelProviders
{
    public const string META = 'meta';

    public const string TWILIO = 'twilio';

    public const array OPTIONS = [self::META, self::TWILIO];

    public function __construct(private readonly PlatformSettings $settings) {}

    public function metaProvider(): string
    {
        $value = $this->settings->string('channels.meta_provider');

        return $value === self::TWILIO ? self::TWILIO : self::META;
    }

    public function usesMeta(): bool
    {
        return $this->metaProvider() === self::META;
    }

    public function usesTwilio(): bool
    {
        return $this->metaProvider() === self::TWILIO;
    }

    /** Can this channel take messages right now on the selected route? */
    public function isConfigured(Channel $channel): bool
    {
        $has = fn (string ...$keys): bool => array_reduce($keys, fn (bool $ok, string $k): bool => $ok && $this->settings->isConfigured($k), true);

        if ($channel === Channel::TELEGRAM) {
            return $has('telegram.bot_token');
        }
        if ($this->usesTwilio()) {
            return match ($channel) {
                Channel::WHATSAPP => $has('twilio.whatsapp_from'),
                Channel::FACEBOOK => $has('twilio.facebook_from'),
                Channel::INSTAGRAM => $has('twilio.instagram_from'),
                default => false,
            };
        }

        return $has('meta.app_secret') && match ($channel) {
            Channel::WHATSAPP => $has('meta.whatsapp_phone_number_id', 'meta.whatsapp_token'),
            Channel::FACEBOOK => $has('meta.page_id', 'meta.page_token'),
            Channel::INSTAGRAM => $has('meta.instagram_account_id')
                && ($this->settings->isConfigured('meta.instagram_token') || $this->settings->isConfigured('meta.page_token')),
            default => false,
        };
    }

    /** The WhatsApp number vendors message, in any format (normalised by the caller). */
    public function whatsappNumber(): ?string
    {
        return $this->usesMeta()
            ? $this->settings->string('meta.whatsapp_number')
            : $this->settings->string('twilio.whatsapp_from');
    }

    /** m.me link to the Facebook Page (Meta route only: Twilio's "messenger:<id>" is the same page id). */
    public function messengerLink(): ?string
    {
        $pageId = $this->usesMeta()
            ? $this->settings->string('meta.page_id')
            : preg_replace('/^messenger:/', '', (string) $this->settings->string('twilio.facebook_from'));

        return is_string($pageId) && preg_match('/^\d{5,25}$/', $pageId) === 1 ? 'https://m.me/'.$pageId : null;
    }

    public function instagramUsername(): ?string
    {
        $name = $this->settings->string('meta.instagram_username');
        $name = $name !== null ? ltrim(trim($name), '@') : null;

        return $name !== null && preg_match('/^[A-Za-z0-9._]{1,30}$/', $name) === 1 ? $name : null;
    }
}
