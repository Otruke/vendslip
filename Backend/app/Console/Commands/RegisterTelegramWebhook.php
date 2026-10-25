<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Settings\PlatformSettings;
use Illuminate\Console\Command;
use NetSlip\Bots\Telegram\TelegramApiClient;

final class RegisterTelegramWebhook extends Command
{
    protected $signature = 'netslip:telegram-webhook {--url= : Public HTTPS base URL (defaults to APP_URL)} {--delete : Remove the webhook}';

    protected $description = 'Register (or delete) the NetSlip Telegram webhook with secret_token';

    public function handle(TelegramApiClient $api, PlatformSettings $settings): int
    {
        if (! $api->isConfigured()) {
            $this->error('Set the Telegram bot token first (/admin/gateways or TELEGRAM_BOT_TOKEN).');

            return self::FAILURE;
        }
        if ($this->option('delete')) {
            $result = $api->call('deleteWebhook', ['drop_pending_updates' => false]);
            $this->line(json_encode($result) ?: '');

            return $result['ok'] ? self::SUCCESS : self::FAILURE;
        }

        $secret = $settings->string('telegram.webhook_secret');
        if ($secret === null) {
            $this->error('Set TELEGRAM_WEBHOOK_SECRET (or the webhook secret in /admin/gateways) first.');

            return self::FAILURE;
        }
        $base = rtrim((string) ($this->option('url') ?: config('app.url')), '/');
        if (! str_starts_with($base, 'https://')) {
            $this->error('Telegram requires an HTTPS webhook URL. Pass --url=https://your-domain');

            return self::FAILURE;
        }
        /** @var array<string, mixed> $config */
        $config = require base_path('../telegram_bot/config.php');

        $result = $api->call('setWebhook', [
            'url' => $base.'/api/v1/webhooks/telegram',
            'secret_token' => $secret,
            'allowed_updates' => $config['allowed_updates'],
            'drop_pending_updates' => false,
        ]);
        $this->line(json_encode($result) ?: '');

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
