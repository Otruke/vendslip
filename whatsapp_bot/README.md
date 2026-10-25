# NetSlip · WhatsApp bot module

Twilio Programmable Messaging adapter. Namespace `NetSlip\Bots\WhatsApp\` (autoloaded by `Backend/composer.json`).

| File | Role |
| --- | --- |
| `src/WhatsAppWebhookHandler.php` | Validates `X-Twilio-Signature`, hands the message to `App\Services\Bot\BotOrchestrator`, renders TwiML |
| `src/WhatsAppInteractiveSummary.php` | The single consolidated pre-confirmation summary |
| `src/WhatsAppPayloadBuilder.php` | Quick-reply REST options and PDF slip TwiML |
| `config.php` | Button ids, summary length, PDF attachment toggle |

## How the cost saving works

1. Vendor sends `/name = Ada`, then `/item = 2x Shoe @ 15000` (or both in one message).
2. Incomplete drafts are stored in `bot_draft_sessions` and Twilio gets an empty `<Response/>`: **no outbound message, no fee**.
3. When the draft first becomes complete, NetSlip sends **one** summary with Confirm / Edit / Cancel.
4. Later field updates are buffered silently; `/preview` re-sends the summary on demand.
5. Confirm → branded PDF attached as WhatsApp media + verification link (+ pay link for invoices).

Every silent buffer increments `saved_outbound_calls_count`, shown as *API calls saved* on the dashboard.

## Setup

1. Twilio Console → Messaging → WhatsApp sender (or the Sandbox).
2. *A message comes in* webhook: `POST https://YOUR-API-DOMAIN/api/v1/webhooks/whatsapp`
3. Super-Admin → `/admin/gateways`: Account SID, Auth Token, WhatsApp From (`whatsapp:+1415…`).
4. **Optional quick-reply buttons**: Twilio Content Template Builder → *Quick reply*, body `{{1}}`,
   buttons with ids `NS_CONFIRM`, `NS_EDIT`, `NS_CANCEL`. Paste the `HX…` SID as *WhatsApp confirm content SID*.
   Without it the bot asks "Reply 1 ✅ Confirm · 2 ✏️ Edit · 3 ❌ Cancel".
5. Vendors link their number once: Dashboard → Channels → code → send `/link CODE` on WhatsApp.

Signature validation uses the exact public URL. Behind a proxy/tunnel, set `APP_URL` to the public
URL and trust the proxy so `$request->fullUrl()` matches what Twilio signed.
