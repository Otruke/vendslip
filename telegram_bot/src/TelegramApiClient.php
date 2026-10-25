<?php

declare(strict_types=1);

namespace NetSlip\Bots\Telegram;

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
        $base = rtrim((string) config('netslip.telegram.api_base', 'https://api.telegram.org'), '/');

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
}
