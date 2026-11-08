# VendSlip: testing payments and the chat bots

A step-by-step checklist for proving the two payment gateways, the Telegram bot and the WhatsApp bot
work end to end. Work through it top to bottom the first time; after that, jump to the part you changed.

**Contents**

- [Before you start: a public HTTPS address](#0-before-you-start-a-public-https-address)
- [1. Payment gateways](#1-payment-gateways)
  - [1A. Flutterwave (card, transfer, USSD)](#1a-flutterwave-card-transfer-ussd)
  - [1B. NOWPayments (crypto)](#1b-nowpayments-crypto)
  - [1C. Customer invoice payments (a business's own keys)](#1c-customer-invoice-payments-a-businesss-own-keys)
- [2. Telegram: full test](#2-telegram-full-test)
- [3. WhatsApp: full test](#3-whatsapp-full-test)
- [4. If something goes wrong](#4-if-something-goes-wrong)

---

## 0. Before you start: a public HTTPS address

Payment gateways and chat apps call VendSlip back over the internet, so `localhost` won't do.

1. Start VendSlip as usual (`start-vendslip.bat`): API, scheduler and web app. The **scheduler window
   must stay open**. It sends the queued alerts and emails every minute.
2. Run your Cloudflare Tunnel (or `ngrok http 5173`) so a public HTTPS address reaches the web app.
   The web app passes `/api/...` through to the API, so one address covers both.
3. In `Backend\.env`, set the public address and clear the cache:

   ```env
   APP_URL=https://your-tunnel-domain
   FRONTEND_URL=https://your-tunnel-domain
   ```

   ```bat
   cd Backend
   php artisan optimize:clear
   ```

   In `Frontend\.env`, set `VITE_PUBLIC_HOST=your-tunnel-domain` (no `https://`) and restart the web app window.
4. Check it: open `https://your-tunnel-domain/api/v1/public/legal` in a browser. You should see JSON
   with `"version"`. If you do, gateways and bots can reach you.

> Every time the tunnel address changes (free ngrok does this on each start), repeat step 3 and
> re-register the Telegram webhook and the Meta callback URL.

---

## 1. Payment gateways

### Does NOWPayments have a sandbox like Flutterwave?

**Yes, but it isn't something to rely on.** NOWPayments has a sandbox
(API `https://api-sandbox.nowpayments.io/v1`, with its own separate sandbox account and keys).
NOWPayments' own documentation now says that **since 2025 the sandbox is no longer actively
maintained or kept in step with the live API**, so it can behave differently from the real thing.
Its "pick an outcome" switch also only works on the plain payment API, not on the hosted invoices
VendSlip uses.

| | Flutterwave | NOWPayments |
|---|---|---|
| Test mode | Yes: **Test mode** toggle in the dashboard, keys start `FLWPUBK_TEST-` / `FLWSECK_TEST-` | Sandbox exists but is unmaintained |
| Same API address for test and live | Yes, the keys decide | No: sandbox has its own address and account |
| Fake money | Test cards and test bank accounts | No reliable way |
| How VendSlip tests it | Test keys + test cards | `vendslip:test-ipn` (below) + one small real payment |

So for crypto, the reliable path is:

1. **Test the plumbing with `php artisan vendslip:test-ipn`.** It sends VendSlip a payment
   notification signed with your real IPN secret, exactly as NOWPayments would, through your public
   address. It checks the tunnel, the signature check and what happens after payment, with no crypto
   spent. It refuses to run when `APP_ENV=production`.
2. **Then make one small real payment** with live keys (a few dollars of USDT on TRON is the cheapest)
   to prove the real NOWPayments flow.

### Two commands that help

```bat
cd Backend

:: Are my keys valid? Test or live? Which webhook URLs do I paste where?
php artisan vendslip:check-gateways

:: The same, for one business's own invoice-payment keys
php artisan vendslip:check-gateways --merchant=MERCHANT-ID

:: Testing only: pretend NOWPayments confirmed a pending crypto checkout
php artisan vendslip:test-ipn VS-XXXXXXXX
php artisan vendslip:test-ipn VS-XXXXXXXX --status=failed
php artisan vendslip:test-ipn VS-XXXXXXXX --amount=1 :: underpaid, must NOT activate
php artisan vendslip:test-ipn VS-XXXXXXXX --dry      :: just print the signed request
```

`check-gateways` makes read-only calls; no money moves.

---

### 1A. Flutterwave (card, transfer, USSD)

**Set up (test mode)**

1. Sign in at [dashboard.flutterwave.com](https://dashboard.flutterwave.com) and switch the
   **Test mode** toggle on.
2. **Settings → API keys**: copy the *test* public key, secret key and encryption key.
3. **Settings → Webhooks**:
   - URL: `https://your-tunnel-domain/api/v1/webhooks/flutterwave`
   - Secret hash: make up a long random string and type it here.
   - Tick the options to receive webhooks for successful and failed charges, then save.
4. VendSlip Super-Admin → **Gateways & Bots** → Flutterwave: paste the public key, secret key and the
   same secret hash. Save.
5. Run `php artisan vendslip:check-gateways`. Expect **TEST keys**, *Secret key: accepted*,
   *Secret hash: set*.

**Test 1: a successful plan purchase**

1. Sign in as a business owner (e.g. `owner@aminaluxury.test` / `password`) → **Billing & credits**.
2. Pick **Pro Merchant → Upgrade**. Tick the Terms box (the payment buttons stay disabled until you do),
   then **Card, transfer or USSD**.
3. On Flutterwave's page, pay with a test card from Flutterwave's
   [testing helpers](https://developer.flutterwave.com/docs/integration-guides/testing-helpers), for example:

   | Card | Number | Expiry | CVV | PIN | OTP |
   |---|---|---|---|---|---|
   | Mastercard (success) | 5438 8980 1456 0229 | 10/31 | 564 | 3310 | 12345 |
   | Verve (success) | 5061 4604 1012 0223 210 | 10/31 | 780 | 3310 | 12345 |
   | Insufficient funds | 5258 5859 2266 6506 | 09/31 | 883 | 3310 | 12345 |

   Or pay by bank transfer to Access Bank (044), account 0690000031, OTP 12345.
4. **Expect:**
   - you land back on Billing with **Payment successful · Your plan is active**;
   - the plan shows **Pro Merchant · Paid until …**;
   - the payment shows **Successful** in the history list;
   - Super-Admin → **Payments** shows the same reference as *Successful*;
   - Super-Admin → **Security Logs** has a `billing.*` entry.

**Test 2: a credit pack.** Same page → buy a credit pack. Expect the **Non-expiring credits** count to go up.

**Test 3: failure and cancel**

- Pay with the *insufficient funds* card. Expect **Payment not completed** and the payment marked **Failed**; plan unchanged.
- Start a payment and press **Cancel** on Flutterwave's page. Expect **Payment not completed**; plan unchanged.

**Test 4: the webhook alone.** VendSlip confirms a payment twice: when the buyer comes back, and when
the webhook arrives. Prove the webhook works on its own:

1. Start a payment, pay, and **close the tab** before it redirects back.
2. Within a minute, refresh Billing. It should show **Successful** anyway.
3. In Flutterwave → **Settings → Webhooks** (or the transaction's details), the delivery should show
   **200**. A **401** means the secret hash in VendSlip doesn't match Flutterwave's.

**Test 5: changing plan mid-term.** On Pro with days left, buy **Business Plus**. Expect a
note that unused days were carried over, and *Paid until* further out than one month.

**Going live:** switch the toggle off, copy the **live** keys, set the webhook again in live mode
(test and live have separate webhook settings), update Gateways & Bots, and run `check-gateways`
(expect **LIVE keys**). Make one small real payment.

---

### 1B. NOWPayments (crypto)

**Set up**

1. Create an account at [nowpayments.io](https://nowpayments.io) and add your payout wallet
   (where crypto is sent).
2. Dashboard → **Settings → Coins settings**: choose the coins you accept, e.g. USDT (TRC-20),
   USDT (BEP-20), USDC, BTC, TON.
3. Dashboard → **Settings → Payments → API keys**: create an API key.
4. Dashboard → **Settings → Payments → Instant payment notifications**: generate the **IPN secret
   key**. You don't paste a callback URL there: VendSlip sends it with each invoice.
5. VendSlip Super-Admin → **Gateways & Bots** → NOWPayments: paste the API key and IPN secret.
6. Run `php artisan vendslip:check-gateways`. Expect *API status: OK*, *API key: accepted*, your coin
   list, *IPN secret: set*.

**Test 1: the full flow without spending crypto**

1. As the business owner → **Billing** → choose a plan → tick Terms → **Crypto**.
   You're sent to NOWPayments' invoice page. Don't pay; go back to VendSlip.
2. Copy the payment reference (`VS-…`) from Billing's payment list, or from Super-Admin → Payments
   (status **Pending**).
3. Run:

   ```bat
   php artisan vendslip:test-ipn VS-XXXXXXXX
   ```

   Expect `HTTP 200` and `Payment is now: SUCCESSFUL`. Billing shows the new plan.

4. Repeat with a new checkout and these variations:

   | Command | Expect |
   |---|---|
   | `--status=failed` or `--status=expired` | Payment **Failed**, plan unchanged |
   | `--status=partially_paid` | Still **Pending** (only *finished* activates) |
   | `--amount=1` | **Failed** with *amount_or_currency_mismatch*, plan unchanged; a **critical** `payments.amount_mismatch` entry in Security Logs |

   `HTTP 401` means the IPN secret in VendSlip doesn't match the one used to sign.
   *Couldn't reach* means the tunnel or API isn't running.

**Test 2: one small real payment.** With live keys, buy the cheapest credit pack with **USDT TRC-20**.
Pay the exact amount shown, from a wallet or exchange. Crypto takes a few minutes. Expect the status
on NOWPayments' page to go *Waiting → Confirming → Finished*, then the credits appear in VendSlip.
Payments → the reference shows **Successful**.

> **Note:** NOWPayments sends "finished" only when the full amount has arrived. An underpayment
> stays *partially paid* in NOWPayments and is not activated in VendSlip; sort these out from the
> NOWPayments dashboard.

---

### 1C. Customer invoice payments (a business's own keys)

Businesses collect invoice payments with **their own** Flutterwave / NOWPayments accounts; the money
never passes through VendSlip.

1. As the owner → **Settings → Payment links**. Paste the business's own keys, test keys for
   Flutterwave. The page shows **this business's webhook URLs**,
   e.g. `…/api/v1/webhooks/flutterwave/merchants/<id>`. Put those in the business's Flutterwave
   webhook settings, with the same secret hash.
2. Run `php artisan vendslip:check-gateways --merchant=<id>`.
3. Create an **invoice** (Composer → type Invoice, or `/type = invoice` in a bot) and confirm it.
4. Open the invoice's public link (the **Pay** button / `…/pay/<code>`) in a private window, as the
   customer. Tick nothing (customers aren't account holders), press **Pay**, and use a test card.
5. **Expect:**
   - back on the pay page with **Payment received**;
   - the invoice is **Paid** in Receipts & Invoices, with the payment recorded;
   - the owner gets a notification.
6. For crypto invoices: start a crypto payment on the pay page, then
   `php artisan vendslip:test-ipn VS-XXXXXXXX` (it uses the business's IPN secret automatically).

---

## 2. Telegram: full test

**Set up (once)**

1. In Telegram, open **@BotFather** → `/newbot` → pick a name and a username ending in `bot`.
   Copy the **token**.
2. Optional, in @BotFather: `/setcommands` with:

   ```text
   help - How to make a slip
   preview - Show the current draft
   confirm - Issue the slip
   edit - Change the draft
   cancel - Cancel the draft
   credits - Plan and credit balance
   ```

3. VendSlip Super-Admin → **Gateways & Bots** → Telegram:
   - **Bot token**: from BotFather.
   - **Webhook secret token**: any long random string.
   - **Bot username**: without `@`.

   Save.
4. With `APP_URL` set to your public HTTPS address (section 0), press **Register Telegram webhook** on
   the same page (or run `php artisan vendslip:telegram-webhook`).
5. Check: open `https://api.telegram.org/bot<TOKEN>/getWebhookInfo` in a browser. Expect `"url"` =
   `https://your-tunnel-domain/api/v1/webhooks/telegram`, and no `last_error_message`.

**Test it**

| # | Do this | Expect |
|---|---|---|
| 1 | Send `/start` to the bot from a Telegram account that isn't linked | "Welcome to VendSlip! Link this chat first…" |
| 2 | In VendSlip, as the owner → **Bot channels → Link a chat**. Use **Open in Telegram**, or send `/link CODE` | "Linked ✅ … can now send slips for Amina Luxury Couture." The chat appears under Bot channels |
| 3 | `/help` | The list of commands (full reference: `BOT-COMMANDS.md`) |
| 4 | `/name = Ada Obi` | Nothing yet (silent until the draft is complete) |
| 5 | `/item = 2x Ankara @ 7500` | A summary table with **Confirm / Edit / Cancel** buttons |
| 6 | `/discount = 10%` then `/shipping = 1500` | The summary updates with the new total |
| 7 | `/item = Cap @ 4000 was 5000` then `/preview` | A second line showing the discount from the usual price |
| 7a | **Item discounts:** `/item = 2x Ankara @ 7500 less 500 \| Gele @ 5000 less 10% \| 2x Wrapper @ 3000 less 1000 total` | Each line shows the old price crossed out: Ankara 7,000 each, Gele 4,500, Wrapper 2,500 each. *Item discounts* line: 2,500 |
| 7b | **Whole-order discount:** `/discount = 2000` (an amount) or `/discount = 10%` | A separate *Discount* line under the subtotal |
| 7c | `/item = 3x Ankara @ 7500 less 1000 total` | A message saying 1,000 doesn't split evenly over 3; nothing is added |
| 8 | `/remove 2` | Item 2 is removed, the summary is shown again |
| 9 | Tap **Confirm** | "Rendering your slip…", then the **PDF receipt** and one short link (`…/s/Ab3dE9xQ2`). No `/api/` address anywhere |
| 10 | Tap **Confirm** again on the old message | Nothing happens (the button is disabled; no second slip) |
| 11 | Open the verification link; scan the PDF's QR with your phone | The public check page shows the slip as genuine |
| 12 | In the dashboard → **Receipts & Invoices** | The slip is there, channel **Telegram** |
| 13 | New invoice: `/type = invoice`, `/name = Tunde`, `/amount = 50000`, `/due = 7d`, `/paid = 20000`, then **Confirm** | PDF invoice showing ₦20,000 paid, ₦30,000 balance, due date, and a **pay link** |
| 14 | `/name = Test` then `/cancel` | "Draft cancelled…", no slip created |
| 15 | `/credits` | Plan, free slips left, paid credits |
| 16 | On a Free-plan business: `/confirm clean` | Uses 1 credit, PDF without watermark (if there are no credits, it says so) |
| 17 | `/currency = USD`, then a slip | The slip is in USD |
| 18 | **Groups:** add the bot to a group, link the group the same way, send a draft | Drafts are shown only to the person typing; the final PDF goes to the group |
| 19 | `php artisan vendslip:digests` | Linked chats get the daily sales summary (normally sent at 20:00) |
| 20 | `/unlink` | "This chat is unlinked." `/name = x` now gets the welcome/link message |

**Also check in the dashboard:** Overview → the **Messaging calls saved** count went up during steps 4–8.
Each silent update is one message VendSlip didn't send.

---

## 3. WhatsApp: full test

VendSlip has two WhatsApp routes, switched in **Gateways & Bots**: **Meta Cloud API** (primary, free
test number) and **Twilio** (secondary). Test the one you'll use.
Full setup guides with every field explained: **`META-SETUP.md`** and **`TWILIO-SETUP.md`**.

### 3A. Meta Cloud API (primary)

**Set up (once)**

1. [developers.facebook.com](https://developers.facebook.com) → **My Apps → Create app** → type **Business**.
2. **App settings → Basic** → copy the **App secret**.
3. Add the **WhatsApp** product → **API Setup**. Meta gives you a free **test number**. Copy:
   - **Phone number ID**;
   - the **temporary access token**. It lasts 24 hours; for longer tests, create a System User
     token with `whatsapp_business_messaging`.
4. On API Setup, under **To**, add **your own WhatsApp number** as a test recipient and confirm the code
   Meta sends. Up to 5 numbers can be added while the app is in development.
5. VendSlip Super-Admin → **Gateways & Bots**:
   - **App secret**: from Basic settings.
   - **Webhook verify token**: any long random string.
   - **WhatsApp phone number ID** and **WhatsApp access token**: from API Setup.
   - **WhatsApp display number**: the test number, e.g. `+1 555…`.
   - **Route**: Meta Cloud API.

   Save.
6. WhatsApp → **Configuration → Webhook → Edit**:
   - **Callback URL**: `https://your-tunnel-domain/api/v1/webhooks/meta`
   - **Verify token**: the same string as in VendSlip.

   Press **Verify and save**. A green tick means VendSlip answered Meta's check.
7. In the same section, **Manage** webhook fields → **subscribe to `messages`**.

**Test it** (from the phone number you added as a test recipient, message the test number)

| # | Do this | Expect |
|---|---|---|
| 1 | Send `hi` | "Welcome to VendSlip! Link this chat first…" |
| 2 | Dashboard → **Bot channels → Link a chat** → send `/link CODE` | "Linked ✅ …" and the number appears under Bot channels |
| 3 | `/name = Ada Obi` then `/item = 2x Ankara @ 7500` | No reply to the first; **one** summary with **Confirm / Edit / Cancel** buttons after the second |
| 4 | `/discount = 10%` | **No reply** (silent buffering saves message fees) |
| 5 | `/preview` | The summary again, with the discount |
| 6 | Tap **Confirm** | The **PDF receipt** as a WhatsApp document, plus the verification link |
| 7 | Invoice: `/type = invoice`, `/name = Tunde`, `/amount = 50000`, `/due = 7d` → **Confirm** | PDF invoice + **pay link**. Pay it with a Flutterwave test card (section 1C) and check it turns **Paid** |
| 8 | **Edit** button, then `/amount = 45000`, `/confirm` | The slip is issued with the new amount |
| 9 | `/cancel` on a new draft | "Draft cancelled…" |
| 10 | `/credits`, `/help` | As on Telegram |
| 11 | Dashboard → Receipts & Invoices | Slips with channel **WhatsApp**; Overview's **Messaging calls saved** went up |
| 12 | `/unlink` | Unlinked; the next message gets the welcome text |

**WhatsApp rules to know**

- VendSlip can reply freely only within **24 hours** of the person's last message. The bot always
  replies to a message, so this is fine for testing.
- The test number can only message the numbers you added. Before real merchants can use it, you need
  your own number, **business verification**, and Meta **App Review**.

### 3B. Twilio (secondary)

1. Twilio Console → **Messaging → Try it out → WhatsApp sandbox**. From your phone, send the
   `join <word>` message Twilio shows to the sandbox number.
2. Sandbox settings → **When a message comes in**: `https://your-tunnel-domain/api/v1/webhooks/whatsapp`
   (POST).
3. VendSlip **Gateways & Bots**:
   - **Account SID** and **Auth Token**;
   - **WhatsApp From**: `whatsapp:+14155238886` (the sandbox number);
   - **Route**: Twilio.
4. Run the same table as 3A. Without a quick-reply template, the bot asks
   "Reply 1 ✅ Confirm · 2 ✏️ Edit · 3 ❌ Cancel". Reply with the number.
5. Twilio checks signatures against the exact public URL, so `APP_URL` must be the tunnel address.
   A mismatch shows as **403** in Twilio's **Monitor → Logs**.

---

## 4. If something goes wrong

| Symptom | Likely cause and fix |
|---|---|
| Gateway or Meta says it can't reach the URL | Tunnel not running, or `APP_URL` not updated; open `/api/v1/public/legal` on the public address |
| Flutterwave webhook gets **401** | Secret hash differs between Flutterwave and VendSlip (also check test vs live webhook settings) |
| Payment stays **Pending** after paying | Webhook not delivered: check the gateway's webhook log; re-run `check-gateways` |
| `test-ipn` → **401** | IPN secret in VendSlip isn't the one in the NOWPayments dashboard |
| Payment **Failed** with *amount_or_currency_mismatch* | Underpaid or different currency; working as designed (see Security Logs) |
| Telegram silent | `getWebhookInfo` shows `last_error_message`; usually the tunnel changed: re-register the webhook |
| Telegram **401** in the API window | Webhook secret token in VendSlip changed after registering: re-register |
| Meta "Verify and save" fails | Verify token differs, or the route isn't public; the API window shows the GET request |
| WhatsApp messages arrive but no reply | Not subscribed to `messages`, wrong phone number ID, or the 24h temporary token expired |
| Alerts or emails never arrive | The **scheduler** window is closed (it sends the queue every minute); emails go to `storage\logs\laravel.log` while `MAIL_MAILER=log` |
| Every action says "accept the updated terms" | A new Terms version applies: accept it in the web app (it appears on sign-in) |

Logs: `Backend\storage\logs\laravel.log`. Every webhook problem with a bad signature is also in
Super-Admin → **Security Logs** as a *critical* entry.
