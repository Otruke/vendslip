# NetSlip · Facebook Messenger bot module

Namespace `NetSlip\Bots\Facebook\`. Shares `BotOrchestrator` with the other channels.

- `/slash` fields one-by-one or in one message → silent draft buffer (`bot_draft_sessions`).
- One consolidated confirmation (`1` Confirm · `2` Edit · `3` Cancel, or `/confirm`).
- Delivers the PDF slip as an attachment + verification link, and the hosted `/pay/{uid}` link for unpaid invoices.

## Setup

1. Connect your Facebook Page as a Messenger sender in Twilio.
2. Incoming-message webhook: `POST https://YOUR-API-DOMAIN/api/v1/webhooks/facebook`
3. `/admin/gateways` → *Facebook From* (e.g. `messenger:102345678901234`).
4. Vendors link once with `/link CODE`.

> Twilio's Messenger channel availability varies by account. The handler only depends on the
> standard Twilio webhook fields and signature validation.
