# NetSlip · Telegram bot module (Bot API 10.3)

Namespace `NetSlip\Bots\Telegram\`. Uses the Bot API features released through **10.3 (24 Aug 2026)**:

| Feature | Where | Why |
| --- | --- | --- |
| `sendRichMessage` + `InputRichBlockTable` (`is_compact: true`) | `TelegramRichMessageBuilder::summary()` | Itemised order preview as a real table |
| `InputRichBlockExpandableBlockQuotation` | summary + delivery | Delivery address / HMAC proof without clutter |
| `InputRichBlockDocument` (`InputMediaDocument` by URL) | `TelegramRichMessageBuilder::delivery()` | PDF slip embedded in the rich message |
| `EphemeralMessageParameters` (`receiver_user_id`, `callback_query_id`, `replace_callback_query_message`) | `TelegramEphemeralDraftService` | In sales groups, drafts are visible only to the agent |
| `InlineKeyboardButton.disabled` (DisabledButton) | `disableButtons()` | Confirm can't be tapped twice → no double credit spend |
| `sendRichMessageDraft` (`can_stop`, `keep_on_stop`) | `streamRendering()` | "Rendering your slip…" preview while DOMPDF runs |
| `editMessageText` with `rich_message` | `sendSummary()` | Summary updates in place when fields change |

Every rich call falls back to `sendMessage` / `sendDocument` if Telegram rejects it, so the bot keeps
working on older clients or if `TELEGRAM_RICH_MESSAGES=false`.

## Setup

1. Create a bot with @BotFather; note the token and username.
2. `/admin/gateways` (or `.env`): `TELEGRAM_BOT_TOKEN`, `TELEGRAM_WEBHOOK_SECRET` (any long random string), `TELEGRAM_BOT_USERNAME`.
3. Register the webhook: `php artisan netslip:telegram-webhook` (uses `APP_URL`, must be HTTPS).
4. Vendors link via Dashboard → Channels → *Open in Telegram* (deep link `t.me/<bot>?start=CODE`) or `/link CODE`.

The webhook rejects any request whose `X-Telegram-Bot-Api-Secret-Token` header doesn't match.
