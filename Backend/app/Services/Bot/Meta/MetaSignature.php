<?php

declare(strict_types=1);

namespace App\Services\Bot\Meta;

use App\Services\Settings\PlatformSettings;
use Illuminate\Http\Request;

/**
 * Meta webhook authenticity.
 *
 *  GET  hub.mode=subscribe & hub.verify_token  → echo hub.challenge (one-time handshake)
 *  POST X-Hub-Signature-256: sha256=<hex HMAC of the raw body>
 *
 * The body is signed with the Meta app secret. Instagram-Login apps sign with their own
 * Instagram app secret, so that one is accepted too when it is configured.
 */
final class MetaSignature
{
    public function __construct(private readonly PlatformSettings $settings) {}

    public function challenge(Request $request): ?string
    {
        $expected = $this->settings->string('meta.verify_token');
        // PHP turns "hub.mode" into "hub_mode" in $_GET, so read the raw query string first.
        $raw = [];
        foreach (explode('&', $request->getQueryString() ?? '') as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $raw[urldecode($key)] = urldecode($value);
        }
        $param = static fn (string $name): string => (string) ($raw['hub.'.$name] ?? $request->query('hub_'.$name, ''));
        $mode = $param('mode');
        $token = $param('verify_token');
        $challenge = $param('challenge');

        if ($expected === null || $mode !== 'subscribe' || $token === '' || ! hash_equals($expected, $token)) {
            return null;
        }

        return preg_match('/^[A-Za-z0-9_\-]{1,256}$/', $challenge) === 1 ? $challenge : null;
    }

    public function isAuthentic(Request $request): bool
    {
        if (! config('vendslip.meta.validate_signature', true)) {
            return app()->environment('local', 'testing');
        }

        $header = (string) $request->header('X-Hub-Signature-256', '');
        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }
        $given = strtolower(substr($header, 7));
        $body = $request->getContent();

        foreach (['meta.app_secret', 'meta.instagram_app_secret'] as $key) {
            $secret = $this->settings->string($key);
            if ($secret !== null && hash_equals(hash_hmac('sha256', $body, $secret), $given)) {
                return true;
            }
        }

        return false;
    }
}
