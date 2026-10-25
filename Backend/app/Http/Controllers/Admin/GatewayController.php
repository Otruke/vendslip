<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AuditSeverity;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\AuditLogger;
use App\Services\Settings\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

final class GatewayController extends Controller
{
    /** Form groups: key => [label, help] */
    public const array GROUPS = [
        'Flutterwave (plans & credit packs)' => [
            'flutterwave.public_key' => ['Public key', 'FLWPUBK-…'],
            'flutterwave.secret_key' => ['Secret key', 'FLWSECK-…'],
            'flutterwave.secret_hash' => ['Webhook secret hash', 'Must match Settings → Webhooks in Flutterwave'],
        ],
        'NOWPayments (crypto)' => [
            'nowpayments.api_key' => ['API key', ''],
            'nowpayments.ipn_secret' => ['IPN secret', 'Used to verify x-nowpayments-sig'],
        ],
        'Telegram bot (Bot API 10.3)' => [
            'telegram.bot_token' => ['Bot token', 'From @BotFather'],
            'telegram.webhook_secret' => ['Webhook secret token', 'Any long random string'],
            'telegram.bot_username' => ['Bot username', 'e.g. NetSlipBot'],
        ],
        'Twilio (WhatsApp · Instagram · Messenger)' => [
            'twilio.account_sid' => ['Account SID', 'AC…'],
            'twilio.auth_token' => ['Auth token', 'Used to validate X-Twilio-Signature'],
            'twilio.whatsapp_from' => ['WhatsApp from', 'whatsapp:+14155238886'],
            'twilio.whatsapp_confirm_content_sid' => ['WhatsApp confirm content SID', 'HX… quick-reply template (optional)'],
            'twilio.instagram_from' => ['Instagram from', 'instagram:<account id>'],
            'twilio.facebook_from' => ['Messenger from', 'messenger:<page id>'],
        ],
    ];

    public function __construct(
        private readonly PlatformSettings $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function edit(): View
    {
        $values = [];
        foreach (self::GROUPS as $fields) {
            foreach (array_keys($fields) as $key) {
                $values[$key] = [
                    'configured' => $this->settings->isConfigured($key),
                    'display' => $this->settings->masked($key),
                    'secret' => PlatformSettings::DEFINITIONS[$key][1] ?? false,
                ];
            }
        }

        return view('admin.gateways.edit', [
            'groups' => self::GROUPS,
            'values' => $values,
            'webhooks' => [
                'WhatsApp' => url('/api/v1/webhooks/whatsapp'),
                'Telegram' => url('/api/v1/webhooks/telegram'),
                'Instagram' => url('/api/v1/webhooks/instagram'),
                'Messenger' => url('/api/v1/webhooks/facebook'),
                'Flutterwave' => url('/api/v1/webhooks/flutterwave'),
                'NOWPayments IPN' => url('/api/v1/webhooks/nowpayments'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (self::GROUPS as $fields) {
            foreach (array_keys($fields) as $key) {
                $rules['settings.'.str_replace('.', '__', $key)] = ['nullable', 'string', 'max:500'];
            }
        }
        $rules['clear'] = ['nullable', 'array'];
        $data = $request->validate($rules);

        $changed = [];
        foreach ((array) ($data['settings'] ?? []) as $field => $value) {
            $key = str_replace('__', '.', (string) $field);
            if (! array_key_exists($key, PlatformSettings::DEFINITIONS) || $value === null || $value === '') {
                continue; // blank = keep the current value (secrets are never echoed back)
            }
            $this->settings->set($key, trim((string) $value));
            $changed[] = $key;
        }
        foreach ((array) ($data['clear'] ?? []) as $key) {
            if (is_string($key) && array_key_exists($key, PlatformSettings::DEFINITIONS)) {
                $this->settings->set($key, null);
                $changed[] = $key.' (cleared)';
            }
        }
        $user = $request->user();
        $this->audit->log('admin.gateways_updated', $user instanceof User ? $user : null, null, ['keys' => $changed], AuditSeverity::CRITICAL);

        return back()->with('status', $changed === [] ? 'Nothing changed.' : 'Saved '.count($changed).' setting(s).');
    }

    public function registerTelegram(): RedirectResponse
    {
        $code = Artisan::call('netslip:telegram-webhook');
        $output = trim(Artisan::output());

        return back()->with($code === 0 ? 'status' : 'error', 'Telegram: '.mb_substr($output, 0, 300));
    }
}
