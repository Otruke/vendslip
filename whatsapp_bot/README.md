# VendSlip · WhatsApp bot module

Namespace `VendSlip\Bots\WhatsApp\` (autoloaded by `Backend/composer.json`). Two routes, switchable at `/admin/gateways`:

- **Primary: Meta Cloud API** (`WhatsAppCloudSender`, webhook `/api/v1/webhooks/meta`): native reply buttons, PDF as a document, no template needed inside the 24h window.
- **Secondary: Twilio** (`WhatsAppWebhookHandler`, webhook `/api/v1/webhooks/whatsapp`).

| File | Role |
| --- | --- |
| `src/WhatsAppCloudSender.php` | Meta route: text, reply-button summary, PDF document (falls back to the link) |
| `src/WhatsAppWebhookHandler.php` | Twilio route: validates `X-Twilio-Signature`, hands the message to `App\Services\Bot\BotOrchestrator`, renders TwiML |
| `src/WhatsAppInteractiveSummary.php` | The single consolidated pre-confirmation summary |
| `src/WhatsAppPayloadBuilder.php` | Quick-reply REST options and PDF slip TwiML |
| `config.php` | Button ids, summary length, PDF attachment toggle |

## How the cost saving works

1. Vendor sends `/name = Ada`, then `/item = 2x Shoe @ 15000` (or both in one message).
2. Incomplete drafts are stored in `bot_draft_sessions` and Twilio gets an empty `<Response/>`: **no outbound message, no fee**.
3. When the draft first becomes complete, VendSlip sends **one** summary with Confirm / Edit / Cancel.
4. Later field updates are buffered silently; `/preview` re-sends the summary on demand.
5. Confirm → branded PDF attached as WhatsApp media + verification link (+ pay link for invoices).

Every silent buffer increments `saved_outbound_calls_count`, shown as *API calls saved* on the dashboard.

## Setup: Meta Cloud API (primary)

### Meta app (one-time, shared by WhatsApp, Messenger and Instagram)

1. developers.facebook.com → **My Apps → Create app** → type *Business*.
2. App settings → Basic → copy **App secret** → `/admin/gateways` → *App secret*.
3. Make up a long random **verify token** (e.g. `php -r "echo bin2hex(random_bytes(24));"`) →
   *Webhook verify token*.
4. For each product below, set the webhook **Callback URL** to
   `https://YOUR-API-DOMAIN/api/v1/webhooks/meta` with the same verify token, then subscribe the
   `messages` field (Messenger/Instagram: also `messaging_postbacks`).
5. `/admin/gateways` → *WhatsApp · Messenger · Instagram route* → **Meta Cloud API** (default).

While the app is in development mode only people with a role on the app (and, for WhatsApp, up to
five verified test numbers) can message it. Business verification and App Review are needed
before the public can.

### WhatsApp

1. In the app, add **WhatsApp** → *API Setup*. Meta gives you a free test number.
2. Copy **Phone number ID** → *WhatsApp phone number ID*.
3. Business settings → System users → add one, assign the app and the WhatsApp account, generate a
   token with `whatsapp_business_messaging` → *WhatsApp access token* (the 24h temporary token on
   API Setup works for a first test).
4. *WhatsApp display number*: the number vendors message, e.g. `+2348012345678`.
5. Add your own phone as a test recipient on API Setup, then send `/link CODE` from it.

## Setup: Twilio (secondary)

1. Twilio Console → Messaging → WhatsApp sender (or the Sandbox).
2. *A message comes in* webhook: `POST https://YOUR-API-DOMAIN/api/v1/webhooks/whatsapp`
3. Super-Admin → `/admin/gateways`: Account SID, Auth Token, WhatsApp From (`whatsapp:+1415…`).
4. **Optional quick-reply buttons**: Twilio Content Template Builder → *Quick reply*, body `{{1}}`,
   buttons with ids `NS_CONFIRM`, `NS_EDIT`, `NS_CANCEL`. Paste the `HX…` SID as *WhatsApp confirm content SID*.
   Without it the bot asks "Reply 1 ✅ Confirm · 2 ✏️ Edit · 3 ❌ Cancel".
5. Vendors link their number once: Dashboard → Channels → code → send `/link CODE` on WhatsApp.

Signature validation uses the exact public URL. Behind a proxy/tunnel, set `APP_URL` to the public
URL and trust the proxy so `$request->fullUrl()` matches what Twilio signed.
