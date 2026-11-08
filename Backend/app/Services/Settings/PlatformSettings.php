<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\PlatformSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Runtime platform configuration editable by Super-Admins (/admin/gateways, /admin/monetization).
 * DB values override config/vendslip.php. Secrets are encrypted with APP_KEY at rest.
 */
final class PlatformSettings
{
    private const string CACHE_KEY = 'vendslip.platform_settings';

    /** key => [config fallback path|null, is_secret, type] */
    public const array DEFINITIONS = [
        'billing.remove_watermark_on_paid_credit' => [null, false, 'bool'],
        'billing.yearly_discount_label' => [null, false, 'string'],
        'flutterwave.public_key' => ['vendslip.flutterwave.public_key', false, 'string'],
        'flutterwave.secret_key' => ['vendslip.flutterwave.secret_key', true, 'string'],
        'flutterwave.secret_hash' => ['vendslip.flutterwave.secret_hash', true, 'string'],
        'nowpayments.api_key' => ['vendslip.nowpayments.api_key', true, 'string'],
        'nowpayments.ipn_secret' => ['vendslip.nowpayments.ipn_secret', true, 'string'],
        'telegram.bot_token' => ['vendslip.telegram.bot_token', true, 'string'],
        'telegram.webhook_secret' => ['vendslip.telegram.webhook_secret', true, 'string'],
        'telegram.bot_username' => ['vendslip.telegram.bot_username', false, 'string'],
        'channels.meta_provider' => ['vendslip.channels.meta_provider', false, 'string'],
        'marketing.popups.enabled' => [null, false, 'bool'],
        'marketing.popups.show_stats' => [null, false, 'bool'],
        'meta.app_secret' => ['vendslip.meta.app_secret', true, 'string'],
        'meta.verify_token' => ['vendslip.meta.verify_token', true, 'string'],
        'meta.graph_version' => ['vendslip.meta.graph_version', false, 'string'],
        'meta.whatsapp_phone_number_id' => ['vendslip.meta.whatsapp_phone_number_id', false, 'string'],
        'meta.whatsapp_token' => ['vendslip.meta.whatsapp_token', true, 'string'],
        'meta.whatsapp_number' => ['vendslip.meta.whatsapp_number', false, 'string'],
        'meta.page_id' => ['vendslip.meta.page_id', false, 'string'],
        'meta.page_token' => ['vendslip.meta.page_token', true, 'string'],
        'meta.instagram_account_id' => ['vendslip.meta.instagram_account_id', false, 'string'],
        'meta.instagram_username' => ['vendslip.meta.instagram_username', false, 'string'],
        'meta.instagram_token' => ['vendslip.meta.instagram_token', true, 'string'],
        'meta.instagram_app_secret' => ['vendslip.meta.instagram_app_secret', true, 'string'],
        'twilio.account_sid' => ['vendslip.twilio.account_sid', false, 'string'],
        'twilio.auth_token' => ['vendslip.twilio.auth_token', true, 'string'],
        'twilio.whatsapp_from' => ['vendslip.twilio.whatsapp_from', false, 'string'],
        'twilio.instagram_from' => ['vendslip.twilio.instagram_from', false, 'string'],
        'twilio.facebook_from' => ['vendslip.twilio.facebook_from', false, 'string'],
        'twilio.whatsapp_confirm_content_sid' => ['vendslip.twilio.whatsapp_confirm_content_sid', false, 'string'],
    ];

    private const array DEFAULTS = [
        'billing.remove_watermark_on_paid_credit' => true,
        'billing.yearly_discount_label' => 'Save 20%',
        'marketing.popups.enabled' => false,
        // Days before (positive) or after (negative) a plan ends to remind the owners.
        'billing.renewal_reminder_days' => '7,3,1,0,-3',
        'marketing.popups.show_stats' => true,
        'marketing.popups.min_seconds' => 15,
        'marketing.popups.max_seconds' => 40,
        'marketing.popups.max_per_visit' => 4,
    ];

    public function get(string $key, mixed $default = null): mixed
    {
        $stored = $this->all();
        if (array_key_exists($key, $stored) && $stored[$key] !== null && $stored[$key] !== '') {
            return $this->cast($key, $stored[$key]);
        }

        $definition = self::DEFINITIONS[$key] ?? null;
        if ($definition !== null && $definition[0] !== null) {
            $fromConfig = config($definition[0]);
            if ($fromConfig !== null && $fromConfig !== '') {
                return $fromConfig;
            }
        }

        return self::DEFAULTS[$key] ?? $default;
    }

    public function string(string $key): ?string
    {
        $value = $this->get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key, false);
    }

    public function set(string $key, mixed $value): void
    {
        $definition = self::DEFINITIONS[$key] ?? [null, false, 'string'];
        $isSecret = $definition[1];
        $serialised = match (true) {
            $value === null || $value === '' => null,
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };

        PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $serialised !== null && $isSecret ? Crypt::encryptString($serialised) : $serialised, 'is_secret' => $isSecret],
        );
        Cache::forget(self::CACHE_KEY);
    }

    public function isConfigured(string $key): bool
    {
        return $this->string($key) !== null;
    }

    /** Masked value for display ("••••••abcd"). */
    public function masked(string $key): ?string
    {
        $value = $this->string($key);
        if ($value === null) {
            return null;
        }

        return (self::DEFINITIONS[$key][1] ?? false) ? str_repeat('•', 8).substr($value, -4) : $value;
    }

    /** @return array<string, string|null> decrypted values */
    private function all(): array
    {
        /** @var array<string, string|null> $values */
        $values = Cache::remember(self::CACHE_KEY, 300, static function (): array {
            $out = [];
            foreach (PlatformSetting::query()->get() as $row) {
                $value = $row->value;
                if ($value !== null && $row->is_secret) {
                    try {
                        $value = Crypt::decryptString($value);
                    } catch (DecryptException) {
                        $value = null; // APP_KEY rotated: treat as unset rather than leak ciphertext
                    }
                }
                $out[$row->key] = $value;
            }

            return $out;
        });

        return $values;
    }

    private function cast(string $key, string $value): mixed
    {
        return (self::DEFINITIONS[$key][2] ?? 'string') === 'bool' ? $value === '1' : $value;
    }
}
