# VendSlip: Twilio setup (WhatsApp · Messenger)

Twilio is the **secondary** route for chat. Use it if Meta's own setup is blocked, or as a fallback.
VendSlip answers on **one** route at a time: switch it in **Super-Admin → Gateways & Bots → Route**.
Linked chats survive a switch.

| Channel | Through Twilio? |
|---|---|
| WhatsApp | Yes (sandbox for testing, your own sender for live) |
| Facebook Messenger | Yes, but it's a Twilio **public beta** (no SLA) |
| Instagram | **No.** Twilio doesn't offer Instagram messaging. Use Meta for Instagram |

Everything goes into **Super-Admin → Gateways & Bots → "Twilio · secondary"**.

**Contents**

- [What you'll fill in](#what-youll-fill-in)
- [Before you start](#before-you-start)
- [Part 1. Twilio account and keys](#part-1-twilio-account-and-keys)
- [Part 2. WhatsApp sandbox (testing)](#part-2-whatsapp-sandbox-testing)
- [Part 3. Confirm / Edit / Cancel buttons (optional)](#part-3-confirm--edit--cancel-buttons-optional)
- [Part 4. Your own WhatsApp number (live)](#part-4-your-own-whatsapp-number-live)
- [Part 5. Facebook Messenger (optional, beta)](#part-5-facebook-messenger-optional-beta)
- [Troubleshooting](#troubleshooting)

---

## What you'll fill in

| Field in VendSlip | Where it comes from | Example |
|---|---|---|
| **Account SID** | Twilio Console home → *Account Info* | `AC1a2b…` (34 characters) |
| **Auth token** | Same box → **Show** | 32 characters. Also used to check every webhook is really from Twilio |
| **WhatsApp from** | Sandbox number, or your approved sender | `whatsapp:+14155238886` |
| **WhatsApp confirm content SID** | Optional quick-reply template (Part 3) | `HX…` |
| **Messenger from** | Optional, Part 5 | `messenger:123456789012345` |
| **Instagram from** | Leave **blank** | – |

And **Route = Twilio**.

Webhook addresses to paste into Twilio:

```text
WhatsApp:  https://YOUR-DOMAIN/api/v1/webhooks/whatsapp
Messenger: https://YOUR-DOMAIN/api/v1/webhooks/facebook
```

---

## Before you start

- **A public HTTPS address** with `APP_URL` set to it **exactly** (see `TESTING.md`, section 0).
  Twilio signs each request using the full URL it called. If `APP_URL` differs (http vs https, a
  different domain, a trailing path), VendSlip rejects the request as forged.
- A phone with WhatsApp.

---

## Part 1. Twilio account and keys

1. Sign up at **[twilio.com/try-twilio](https://www.twilio.com/try-twilio)** and verify your email and phone.
   A trial account is enough for the sandbox.
2. Console home → **Account Info**:
   - **Account SID** → VendSlip **Account SID**;
   - **Auth Token → Show** → VendSlip **Auth token**.
3. VendSlip → **Save**. Don't switch the Route yet.

> If you ever **rotate** the Auth Token in Twilio, paste the new one into VendSlip straight away,
> or every webhook will be rejected.

---

## Part 2. WhatsApp sandbox (testing)

The sandbox is a shared Twilio WhatsApp number for testing. No Meta approval is needed.

1. Console → **Messaging → Try it out → Send a WhatsApp message**.
2. Twilio shows the sandbox number (usually **+1 415 523 8886**) and a code like **`join gentle-tiger`**.
   From your phone, send that exact message to the number. Twilio replies that you've joined.
   Every tester must do this.
3. Open the **Sandbox settings** tab:
   - **When a message comes in**: `https://YOUR-DOMAIN/api/v1/webhooks/whatsapp`, method **POST**.
   - Leave *status callback* empty → **Save**.
4. VendSlip → Gateways & Bots:
   - **WhatsApp from**: `whatsapp:+14155238886` (the sandbox number with the `whatsapp:` prefix and no spaces);
   - **Route**: **Twilio**.

   **Save**.
5. **Test**:
   - Send `hi` → expect *"Welcome to VendSlip! Link this chat first…"*.
   - Dashboard → **Bot channels → Link a chat** → send `/link CODE` → *"Linked ✅"*.
   - `/name = Ada` and `/item = 2x Ankara @ 7500` → one summary.
   - Reply **1** to confirm → you receive the PDF.
   - The full checklist is in `TESTING.md`, section 3.

> Sandbox sessions lapse after a few days of silence. If replies stop, send the `join …` message again.

---

## Part 3. Confirm / Edit / Cancel buttons (optional)

Without this, the bot asks *"Reply 1 ✅ Confirm · 2 ✏️ Edit · 3 ❌ Cancel"*, which works fine.
With it, people get tappable buttons.

1. Console → **Messaging → Content Template Builder → Create new**.
2. Name: `vendslip_confirm`, language **English**, type **Quick reply**.
3. **Body**: exactly `{{1}}`. VendSlip puts the whole order summary there.
4. Add three buttons. The **IDs must be exactly these**:

   | Button text | ID |
   |---|---|
   | ✅ Confirm | `NS_CONFIRM` |
   | ✏️ Edit | `NS_EDIT` |
   | ❌ Cancel | `NS_CANCEL` |

5. **Save and submit**. Inside the 24-hour reply window, quick-reply templates can be used without
   WhatsApp approval, so it works in the sandbox straight away.
6. Copy the template's **Content SID** (`HX…`) → VendSlip **WhatsApp confirm content SID** → **Save**.

---

## Part 4. Your own WhatsApp number (live)

The sandbox only reaches people who sent the `join` code. For real merchants you need your own sender.

1. Console → **Messaging → Senders → WhatsApp senders → Create new sender**.
2. Pick a **phone number**: one you buy in Twilio, or your own. It must **not** be active on the
   WhatsApp app; remove it there first.
3. Follow the **self sign-up** steps. It opens Meta in a pop-up:
   - log in with Facebook;
   - pick or create your **Meta Business portfolio** and **WhatsApp Business account**;
   - set the **display name** (e.g. *VendSlip*);
   - verify the number by SMS or call.
4. When the sender shows **Online**, open it → **Endpoint configuration / Webhook** →
   **Messaging webhook**: `https://YOUR-DOMAIN/api/v1/webhooks/whatsapp` (POST) → **Save**.
5. VendSlip → **WhatsApp from**: `whatsapp:+234XXXXXXXXXX` (your new number) → **Save**.
6. **Meta business verification** (business.facebook.com → Security centre) raises your limits and is
   needed for the display name to show properly.
7. Upgrade the Twilio account (add billing) so it can message numbers that aren't verified on the trial.

**Costs:** Twilio charges a small fee per message, plus Meta's fee where one applies. Replies to
messages people send you are free on Meta's side. VendSlip stays silent while a draft is incomplete,
so it sends only one summary and the final PDF per slip.

---

## Part 5. Facebook Messenger (optional, beta)

1. Console → **Messaging → Channels → Facebook Messenger → Install** → accept terms →
   **Connect with Facebook** → choose your **Facebook Page**.
2. Configure the Page as a sender:
   - **Callback URL**: `https://YOUR-DOMAIN/api/v1/webhooks/facebook` (POST) → **Save**.
3. Find your **Page ID** (Facebook Page → **About → Page transparency**).
4. VendSlip → **Messenger from**: `messenger:<PAGE_ID>`, e.g. `messenger:123456789012345` → **Save**.
5. Test: message the Page from Facebook → welcome text → `/link CODE`.

Messenger is user-initiated: the bot can only reply after someone messages the Page, which is how
VendSlip works anyway.

---

## Troubleshooting

Twilio's **Monitor → Logs → Errors** and **Messaging logs** show every request and what VendSlip answered.

| Problem | Fix |
|---|---|
| Twilio log shows **403** from VendSlip | Signature check failed: `APP_URL` isn't exactly the public address Twilio calls (https, same domain); or the **Auth token** in VendSlip is wrong or was rotated. Run `php artisan optimize:clear` after changing `.env` |
| Error **11200** (HTTP retrieval failure) | The tunnel is down or the URL is wrong; open `https://YOUR-DOMAIN/api/v1/public/legal` in a browser |
| Messages reach VendSlip but nothing comes back | **Route** isn't set to **Twilio**; or **WhatsApp from** isn't in the form `whatsapp:+…` |
| Error **63015** / sandbox stopped replying | Your phone left the sandbox: send `join <code>` again |
| Error **21608** (trial) | Trial accounts can only message verified numbers: verify the number in Twilio or upgrade |
| Buttons don't show | Content SID missing or wrong, or the button IDs aren't exactly `NS_CONFIRM` / `NS_EDIT` / `NS_CANCEL` |
| Text arrives but the PDF doesn't | Twilio downloads the PDF from your public address: check that `APP_URL` is public and the slip's link opens in a browser. To send a link instead of a file, set `attach_pdf` to `false` in `whatsapp_bot/config.php` |

Sources: Twilio [Messaging channels](https://www.twilio.com/docs/messaging/channels),
[Facebook Messenger (public beta)](https://www.twilio.com/docs/messaging/channels/facebook-messenger).
Twilio renames Console menus from time to time; if a label differs, look for the nearest match.
