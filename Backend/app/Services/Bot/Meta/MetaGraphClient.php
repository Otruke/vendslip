<?php

declare(strict_types=1);

namespace App\Services\Bot\Meta;

use App\Services\Settings\PlatformSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Outbound calls to Meta's Graph API (WhatsApp Cloud API, Messenger Platform, Instagram).
 *
 * Tokens travel in the Authorization header, never in the URL, and are never logged. Failures
 * are logged with Meta's error code / fbtrace_id so they can be traced, and returned as
 * ['ok' => false] so the bot can fall back (e.g. PDF link instead of attachment).
 */
final class MetaGraphClient
{
    public function __construct(private readonly PlatformSettings $settings) {}

    /**
     * @param  array<string, mixed>  $body
     * @return array{ok: bool, status: int, json: array<string, mixed>}
     */
    public function post(string $path, array $body, string $token, bool $instagramHost = false): array
    {
        $base = rtrim((string) config($instagramHost ? 'vendslip.meta.instagram_base' : 'vendslip.meta.graph_base'), '/');
        $version = $this->settings->string('meta.graph_version') ?? 'v24.0';
        if (preg_match('/^v\d{1,3}\.\d$/', $version) !== 1) {
            $version = 'v24.0';
        }
        $url = $base.'/'.$version.'/'.ltrim($path, '/');

        try {
            $response = Http::withToken($token)
                ->timeout((int) config('vendslip.meta.timeout', 15))
                ->acceptJson()
                ->asJson()
                ->post($url, $body);
        } catch (ConnectionException $e) {
            Log::warning('meta.connection_failed', ['path' => $path, 'error' => $e->getMessage()]);

            return ['ok' => false, 'status' => 0, 'json' => []];
        }

        /** @var array<string, mixed> $json */
        $json = is_array($response->json()) ? $response->json() : [];
        $ok = $response->successful() && ! isset($json['error']);
        if (! $ok) {
            $error = is_array($json['error'] ?? null) ? $json['error'] : [];
            Log::info('meta.api_error', [
                'path' => $path,
                'status' => $response->status(),
                'code' => $error['code'] ?? null,
                'subcode' => $error['error_subcode'] ?? null,
                'message' => isset($error['message']) ? mb_substr((string) $error['message'], 0, 300) : null,
                'fbtrace_id' => $error['fbtrace_id'] ?? null,
            ]);
        }

        return ['ok' => $ok, 'status' => $response->status(), 'json' => $json];
    }
}
