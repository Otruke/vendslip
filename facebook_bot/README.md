# VendSlip · Facebook Messenger bot module

Namespace `VendSlip\Bots\Facebook\`. Shares `BotOrchestrator` with the other channels.

- `/slash` fields one-by-one or in one message → silent draft buffer (`bot_draft_sessions`).
- One consolidated confirmation (`1` Confirm · `2` Edit · `3` Cancel, or `/confirm`).
- Delivers the PDF slip as an attachment + verification link, and the hosted `/pay/{uid}` link for unpaid invoices.

## Setup: Meta Messenger Platform (primary)

Sender: `src/MessengerGraphSender.php` (quick-reply Confirm / Edit / Cancel chips, PDF as a file attachment).

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

### Messenger

1. In the app, add **Messenger** → *Messenger API settings* → connect your Facebook Page.
2. Generate the **Page access token** (needs `pages_messaging`) → *Page access token*.
3. *Facebook Page ID* → the Page's numeric id (Page → About → Page transparency, or the API settings list).
4. Subscribe the Page to the app's webhook (`messages`, `messaging_postbacks`).

## Setup: Twilio (secondary)

1. Connect your Facebook Page as a Messenger sender in Twilio.
2. Incoming-message webhook: `POST https://YOUR-API-DOMAIN/api/v1/webhooks/facebook`
3. `/admin/gateways` → *Facebook From* (e.g. `messenger:102345678901234`).
4. Vendors link once with `/link CODE`.

> Twilio's Messenger channel availability varies by account. The handler only depends on the
> standard Twilio webhook fields and signature validation.
