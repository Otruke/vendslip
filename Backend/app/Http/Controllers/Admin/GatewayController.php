<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AuditSeverity;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Bot\ChannelProviders;
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
            'telegram.bot_username' => ['Bot username', 'e.g. VendSlipBot'],
        ],
        'Meta Cloud API · primary (WhatsApp · Messenger · Instagram)' => [
            'meta.app_secret' => ['App secret', 'App settings → Basic. Verifies X-Hub-Signature-256'],
            'meta.verify_token' => ['Webhook verify token', 'Any long random string; paste the same value in the Meta webhook setup'],
            'meta.graph_version' => ['Graph API version', 'e.g. v24.0 (default)'],
            'meta.whatsapp_phone_number_id' => ['WhatsApp phone number ID', 'WhatsApp → API Setup → Phone number ID'],
            'meta.whatsapp_token' => ['WhatsApp access token', 'System-user token with whatsapp_business_messaging'],
            'meta.whatsapp_number' => ['WhatsApp display number', 'e.g. +2348012345678 (shown to vendors)'],
            'meta.page_id' => ['Facebook Page ID', 'The Page that receives Messenger DMs'],
            'meta.page_token' => ['Page access token', 'pages_messaging; also sends Instagram DMs when the account is linked to this Page'],
            'meta.instagram_account_id' => ['Instagram account ID', 'Instagram professional account ID'],
            'meta.instagram_username' => ['Instagram username', 'e.g. vendslip (no @), used for the ig.me link'],
            'meta.instagram_token' => ['Instagram access token', 'Optional: Instagram Login token. Blank = use the Page token'],
            'meta.instagram_app_secret' => ['Instagram app secret', 'Optional: only for Instagram Login apps'],
        ],
        'Twilio · secondary (WhatsApp · Messenger · Instagram)' => [
            'twilio.account_sid' => ['Account SID', 'AC…'],
            'twilio.auth_token' => ['Auth token', 'Used to validate X-Twilio-Signature'],
            'twilio.whatsapp_from' => ['WhatsApp from', 'whatsapp:+14155238886'],
            'twilio.whatsapp_confirm_content_sid' => ['WhatsApp confirm content SID', 'HX… quick-reply template (optional)'],
            'twilio.facebook_from' => ['Messenger from', 'messenger:<page id>'],
            'twilio.instagram_from' => ['Instagram from', 'Not offered by Twilio yet: leave blank'],
        ],
    ];

    public function __construct(
        private readonly PlatformSettings $settings,
        private readonly AuditLogger $audit,
        private readonly ChannelProviders $providers,
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
            'provider' => $this->providers->metaProvider(),
            'webhooks' => [
                'Meta app (WhatsApp · Messenger · Instagram)' => url('/api/v1/webhooks/meta'),
                'Telegram' => url('/api/v1/webhooks/telegram'),
                'Twilio WhatsApp' => url('/api/v1/webhooks/whatsapp'),
                'Twilio Messenger' => url('/api/v1/webhooks/facebook'),
                'Twilio Instagram' => url('/api/v1/webhooks/instagram'),
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

    /** Primary/secondary switch for the Meta-owned channels. Only the selected route acts on messages. */
    public function provider(Request $request): RedirectResponse
    {
        $data = $request->validate(['provider' => ['required', 'string', 'in:'.implode(',', ChannelProviders::OPTIONS)]]);
        $before = $this->providers->metaProvider();
        $this->settings->set('channels.meta_provider', $data['provider']);

        $user = $request->user();
        $this->audit->log('admin.channel_provider_switched', $user instanceof User ? $user : null, null, ['from' => $before, 'to' => $data['provider']], AuditSeverity::CRITICAL);

        $label = $data['provider'] === ChannelProviders::META ? 'Meta Cloud API (primary)' : 'Twilio (secondary)';

        return back()->with('status', $before === $data['provider'] ? "Already on {$label}." : "WhatsApp, Messenger and Instagram now run on {$label}.");
    }

    public function registerTelegram(): RedirectResponse
    {
        $code = Artisan::call('vendslip:telegram-webhook');
        $output = trim(Artisan::output());

        return back()->with($code === 0 ? 'status' : 'error', 'Telegram: '.mb_substr($output, 0, 300));
    }
}
