# NetSlip · Instagram DM bot module

Namespace `NetSlip\Bots\Instagram\`. Same conversation engine as WhatsApp (`BotOrchestrator`), rendered for Instagram:

- Single-message (`/name = Ada, /amount = 15000`) or field-by-field input, buffered silently.
- One numbered summary (`Reply 1 ✅ Confirm · 2 ✏️ Edit · 3 ❌ Cancel`).
- Slip delivered as a **signed PDF link** (Instagram DMs do not accept PDF attachments) plus the verification link.

## Setup

1. Connect your Instagram professional account as a messaging sender in Twilio.
2. Incoming-message webhook: `POST https://YOUR-API-DOMAIN/api/v1/webhooks/instagram`
3. `/admin/gateways` → *Instagram From* (e.g. `instagram:17841400000000000`).
4. Vendors link once with `/link CODE` from Dashboard → Channels.

> Availability of Instagram messaging through Twilio depends on your Twilio account and region.
> The adapter only relies on Twilio's standard webhook fields (`From`, `Body`, `MessageSid`) and
> signature validation, so it also works behind any Twilio-compatible Instagram integration.
