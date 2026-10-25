<?php

declare(strict_types=1);

namespace App\Services\Bot\Twilio;

use App\Services\Settings\PlatformSettings;
use Illuminate\Http\Request;
use Throwable;
use Twilio\Rest\Client;
use Twilio\Security\RequestValidator;

/**
 * Shared Twilio Programmable Messaging plumbing for the WhatsApp, Instagram and Facebook bot modules.
 */
final class TwilioGateway
{
    public function __construct(private readonly PlatformSettings $settings) {}

    /** Validate X-Twilio-Signature against the exact public URL Twilio called. */
    public function isAuthentic(Request $request): bool
    {
        if (! config('netslip.twilio.validate_signature', true)) {
            return app()->environment('local', 'testing');
        }
        $token = $this->settings->string('twilio.auth_token');
        $signature = $request->header('X-Twilio-Signature');
        if ($token === null || $signature === null) {
            return false;
        }

        /** @var array<string, string> $params */
        $params = $request->request->all();

        return (new RequestValidator($token))->validate($signature, $request->fullUrl(), $params);
    }

    public function from(string $channelKey): ?string
    {
        return $this->settings->string("twilio.{$channelKey}_from");
    }

    /**
     * Send outside the webhook response (needed for WhatsApp Content API quick-reply templates).
     *
     * @param  array<string, mixed>  $options
     */
    public function send(string $to, array $options): bool
    {
        $sid = $this->settings->string('twilio.account_sid');
        $token = $this->settings->string('twilio.auth_token');
        if ($sid === null || $token === null) {
            return false;
        }
        try {
            (new Client($sid, $token))->messages->create($to, $options);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
