# VendSlip · Instagram DM bot module

Namespace `VendSlip\Bots\Instagram\`. Same conversation engine as WhatsApp (`BotOrchestrator`), rendered for Instagram:

- Single-message (`/name = Ada, /amount = 15000`) or field-by-field input, buffered silently.
- One numbered summary (`Reply 1 ✅ Confirm · 2 ✏️ Edit · 3 ❌ Cancel`).
- Meta route: quick-reply chips, slip sent as a PDF file (falls back to the signed link) plus the verification link.
- Twilio route: numbered prompt and signed PDF link.

## Setup: Meta (primary)

Sender: `src/InstagramGraphSender.php`.

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

### Instagram (pick one)

**A. Through the Facebook Page (simplest if Messenger is already set up)**
1. Link the Instagram professional account to the Facebook Page (Instagram → Settings → Business → Connect a Facebook Page).
2. Add **Instagram** to the app, subscribe `messages` on the webhook.
3. *Instagram account ID*: the linked account's id. Leave *Instagram access token* blank: the Page token is used.

**B. Instagram Login (no Facebook Page needed)**
1. Add *Instagram → API setup with Instagram login*, add the account, generate a token
   (`instagram_business_basic`, `instagram_business_manage_messages`) → *Instagram access token*.
2. Copy the **Instagram app secret** → *Instagram app secret* (these webhooks are signed with it).
3. *Instagram account ID*: the account's id shown there.

Either way, set *Instagram username* (no @) so vendors get an ig.me link. In Instagram → Settings →
Messages, allow access to messages for connected tools.

## Setup: Twilio (secondary, dormant)

Twilio does not offer Instagram today. The handler is kept so the switch stays symmetrical.

1. Connect your Instagram professional account as a messaging sender in Twilio.
2. Incoming-message webhook: `POST https://YOUR-API-DOMAIN/api/v1/webhooks/instagram`
3. `/admin/gateways` → *Instagram From* (e.g. `instagram:17841400000000000`).
4. Vendors link once with `/link CODE` from Dashboard → Channels.

> Availability of Instagram messaging through Twilio depends on your Twilio account and region.
> The adapter only relies on Twilio's standard webhook fields (`From`, `Body`, `MessageSid`) and
> signature validation, so it also works behind any Twilio-compatible Instagram integration.
