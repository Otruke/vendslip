<?php

declare(strict_types=1);

namespace VendSlip\Bots\Telegram;

use App\Services\Settings\PlatformSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TelegramApiClient
{
    public function __construct(private readonly PlatformSettings $settings) {}

    public function isConfigured(): bool
    {
        return $this->settings->isConfigured('telegram.bot_token');
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{ok: bool, result?: mixed, description?: string, error_code?: int}
     */
    public function call(string $method, array $params): array
    {
        $token = $this->settings->string('telegram.bot_token');
        if ($token === null) {
            return ['ok' => false, 'description' => 'bot token not configured'];
        }
        $base = rtrim((string) config('vendslip.telegram.api_base', 'https://api.telegram.org'), '/');

        try {
            $response = Http::timeout(25)->acceptJson()->asJson()->post("{$base}/bot{$token}/{$method}", $params);
        } catch (ConnectionException $e) {
            Log::warning('telegram.connection_failed', ['method' => $method, 'error' => $e->getMessage()]);

            return ['ok' => false, 'description' => 'connection failed'];
        }

        /** @var array{ok: bool, result?: mixed, description?: string, error_code?: int} $json */
        $json = $response->json() ?? ['ok' => false, 'description' => 'invalid response'];
        if (! ($json['ok'] ?? false)) {
            Log::info('telegram.api_error', ['method' => $method, 'code' => $json['error_code'] ?? null, 'description' => $json['description'] ?? null]);
        }

        return $json;
    }

    /**
     * Sends a file by uploading its bytes (multipart), so Telegram doesn't need to reach our server
     * to fetch it. Nested values (reply_markup, …) are sent as JSON strings, as the Bot API expects.
     *
     * @param  array<string, mixed>  $params
     * @return array{ok: bool, result?: mixed, description?: string, error_code?: int}
     */
    public function upload(string $method, array $params, string $field, string $contents, string $filename): array
    {
        $token = $this->settings->string('telegram.bot_token');
        if ($token === null) {
            return ['ok' => false, 'description' => 'bot token not configured'];
        }
        $base = rtrim((string) config('vendslip.telegram.api_base', 'https://api.telegram.org'), '/');
        $fields = [];
        foreach ($params as $key => $value) {
            $fields[$key] = is_array($value) ? (string) json_encode($value) : (string) (is_bool($value) ? (int) $value : $value);
        }

        try {
            $response = Http::timeout(60)->acceptJson()->attach($field, $contents, $filename)->post("{$base}/bot{$token}/{$method}", $fields);
        } catch (ConnectionException $e) {
            Log::warning('telegram.connection_failed', ['method' => $method, 'error' => $e->getMessage()]);

            return ['ok' => false, 'description' => 'connection failed'];
        }

        /** @var array{ok: bool, result?: mixed, description?: string, error_code?: int} $json */
        $json = $response->json() ?? ['ok' => false, 'description' => 'invalid response'];
        if (! ($json['ok'] ?? false)) {
            Log::info('telegram.api_error', ['method' => $method, 'code' => $json['error_code'] ?? null, 'description' => $json['description'] ?? null]);
        }

        return $json;
    }
}
