# VendSlip: Meta setup (WhatsApp · Messenger · Instagram)

This is the **primary** route for the three chat apps. One Meta app serves all three, and they all
call back to one VendSlip address. Set up WhatsApp first; add Messenger and Instagram when you want them.

Every value you collect goes into **Super-Admin → Gateways & Bots → "Meta Cloud API · primary"**.

**Contents**

- [What you'll fill in](#what-youll-fill-in)
- [Before you start](#before-you-start)
- [Part 1. The Meta app (once)](#part-1-the-meta-app-once)
- [Part 2. WhatsApp](#part-2-whatsapp)
- [Part 3. Messenger (Facebook Page)](#part-3-messenger-facebook-page)
- [Part 4. Instagram](#part-4-instagram)
- [Part 5. Tokens that don't expire](#part-5-tokens-that-dont-expire)
- [Part 6. Going live for real merchants](#part-6-going-live-for-real-merchants)
- [Troubleshooting](#troubleshooting)

---

## What you'll fill in

| Field in VendSlip | Where it comes from | Needed for |
|---|---|---|
| **App secret** | App settings → Basic → App secret | All three (checks every webhook is really from Meta) |
| **Webhook verify token** | You make it up | All three |
| **Graph API version** | Leave blank (v24.0) or type e.g. `v25.0` | All three |
| **WhatsApp phone number ID** | WhatsApp → API Setup | WhatsApp |
| **WhatsApp access token** | API Setup (24 h test token) or a System User token (Part 5) | WhatsApp |
| **WhatsApp display number** | The number merchants message, e.g. `+2348012345678` | WhatsApp |
| **Facebook Page ID** | Your Page → About → Page transparency, or Messenger API Setup | Messenger |
| **Page access token** | Messenger API Setup → Generate token, or a System User token | Messenger (and Instagram, option A) |
| **Instagram account ID** | Instagram API setup, or the Graph API (Part 4) | Instagram |
| **Instagram username** | Your Instagram handle, no `@` | Instagram (for the `ig.me` link) |
| **Instagram access token** | Only for option B (Instagram Login) | Instagram, option B |
| **Instagram app secret** | Only for option B | Instagram, option B |

And on the same page: **Route = Meta Cloud API**.

---

## Before you start

1. **A public HTTPS address** for VendSlip (your Cloudflare Tunnel or live domain), with `APP_URL` set
   to it. See section 0 of `TESTING.md`. Meta can't reach `localhost`.
   Your callback URL for everything below is:

   ```text
   https://YOUR-DOMAIN/api/v1/webhooks/meta
   ```

2. **Make your verify token now** and keep it in a note. It is any long random string; to generate one:

   ```bat
   php -r "echo bin2hex(random_bytes(24));"
   ```

3. A **Facebook account**, a **Meta Business portfolio** (business.facebook.com; created during
   step 1 if you don't have one), and for WhatsApp, a phone with WhatsApp to test from.

---

## Part 1. The Meta app (once)

1. Go to **[developers.facebook.com/apps](https://developers.facebook.com/apps)** → **Create app**.
2. Name it (e.g. *VendSlip*), add your email → **Next**.
3. **Use case**: pick **Connect with customers through WhatsApp**. You can add the Messenger and
   Instagram use cases later from the dashboard. → **Next**.
4. Pick your **Business portfolio**, or create one → **Next** → **Create app**.
5. Left menu → **App settings → Basic**:
   - **App secret** → **Show** → copy → VendSlip **App secret**.
   - Fill in **Privacy policy URL** (`https://YOUR-DOMAIN/privacy`) and **Terms of Service URL**
     (`https://YOUR-DOMAIN/terms`). Meta requires them before the app can go live, and VendSlip
     already has both pages.
   - **Save changes**.
6. In VendSlip → **Gateways & Bots**, paste the **App secret** and your **Webhook verify token**.
   Leave **Graph API version** blank. Set **Route** to **Meta Cloud API**. **Save**.

---

## Part 2. WhatsApp

### 2.1 Test number and IDs

1. App dashboard → **Use cases → Connect on WhatsApp → Quickstart → Start using the API**.
   This opens **API Setup**.
2. If asked, connect a **WhatsApp Business account**, either an existing one or **Create**. Meta gives
   you a free **test phone number**.
3. On **API Setup**, copy:
   - **Phone number ID** (under the *From* number) → VendSlip **WhatsApp phone number ID**.
     It's a long number like `1234567890123456`. It is **not** the phone number itself.
   - The test number itself (e.g. `+1 555 …`) → VendSlip **WhatsApp display number**.
   - Optional: **WhatsApp Business Account ID**. You only need it for the fix in *Troubleshooting*.
4. **To**: add **your own WhatsApp number** → Meta sends it a code → confirm. You can add up to
   **5** test numbers. While testing, only these numbers can talk to the bot.
5. **Generate access token** → copy → VendSlip **WhatsApp access token**.
   It expires in about **24 hours**: fine for a first test; replace it with a permanent one (Part 5).
6. VendSlip → **Save**.

### 2.2 Webhook

1. Left menu → **WhatsApp → Configuration** → **Webhook → Edit**.
2. **Callback URL**: `https://YOUR-DOMAIN/api/v1/webhooks/meta`
3. **Verify token**: the same string as in VendSlip → **Verify and save**.
   - Green tick: VendSlip answered Meta's check.
   - Error: see *Troubleshooting*.
4. Under **Webhook fields → Manage**, turn on **`messages`** → **Done**.

### 2.3 Test

1. From your phone, send **`hi`** to the test number. Expect: *"Welcome to VendSlip! Link this chat first…"*
2. In the VendSlip dashboard (as a business owner) → **Bot channels → Link a chat** → send
   `/link CODE` on WhatsApp → expect *"Linked ✅"*.
3. Run the full WhatsApp checklist in **`TESTING.md` → section 3A**.

---

## Part 3. Messenger (Facebook Page)

You need a **Facebook Page** you manage, for example *VendSlip*. Merchants message that Page.

1. App dashboard → **Use cases → Add use case** → **Engage with customers on Messenger from Meta**
   → add it → **Customize** → **Messenger API Setup**.
2. **Configure webhooks**:
   - **Callback URL**: `https://YOUR-DOMAIN/api/v1/webhooks/meta`
   - **Verify token**: same as before → **Verify and save**.
3. **Generate access tokens → Connect** → log in → select your Page → **Continue** → **Save** → **Got it**.
4. Next to your Page:
   - **Generate** → copy the token → VendSlip **Page access token**. This token can expire; for a
     permanent one, see Part 5.
   - The Page's **ID** is shown next to its name → VendSlip **Facebook Page ID**. Also found on the
     Page → **About → Page transparency**.
5. **Add subscriptions** (next to the Page) → tick **`messages`** and **`messaging_postbacks`** → **Confirm**.
6. VendSlip → **Save**.
7. **Test**: from a Facebook account that has a role on the app (you), message the Page `hi`.
   Expect the welcome text. Then `/link CODE`, then a slip.

> In development mode, only people with a role on the app (**App roles → Roles**: admins,
> developers, testers) get replies. Add your testers there.

---

## Part 4. Instagram

You need an **Instagram professional account** (Business or Creator). Pick **one** option.

### Option A: through your Facebook Page (recommended)

Uses the Page token from Part 3, so there's one token less to manage.

1. In Instagram: **Settings → Business tools / Account type** → make sure it's a professional account →
   **Connect or create a Facebook Page** → choose the Page from Part 3.
2. In Instagram: **Settings → Messages and story replies → Message controls → Connected tools →
   Allow access to messages** → **On**.
3. App dashboard → **Add use case → Manage messaging & content on Instagram**. Choose the setup
   **with Facebook Login** (through the Page).
4. Configure its webhook with the same **Callback URL** and **Verify token**, and subscribe **`messages`**
   (and **`messaging_postbacks`** if offered).
5. Find the **Instagram account ID**. The easiest way is **Tools → Graph API Explorer**:
   - pick your app;
   - choose the **Page token**;
   - run `GET /<PAGE_ID>?fields=instagram_business_account`;
   - the `id` inside is the Instagram account ID (starts `1784…`).
6. VendSlip:
   - **Instagram account ID** and **Instagram username** (no `@`);
   - leave **Instagram access token** and **Instagram app secret** **blank**, so the Page token is used.

   **Save**.

### Option B: Instagram Login (no Facebook Page)

1. In Instagram: switch to a professional account and turn on **Allow access to messages** (step 2 above).
2. App dashboard → **Add use case → Manage messaging & content on Instagram** → setup with
   **Instagram login** → **API setup with Instagram business login**.
3. **Generate token** next to your account → log in to Instagram → copy → VendSlip
   **Instagram access token**. It lasts **60 days**: put a reminder in your calendar to generate a new one.
4. On the same page, copy the **Instagram app secret** → VendSlip **Instagram app secret**. Webhooks
   for this setup are signed with it, not the main App secret.
5. **Configure webhooks** there with the same **Callback URL** and **Verify token**, and subscribe **`messages`**.
6. **Instagram account ID**: Graph API Explorer → `GET https://graph.instagram.com/me?fields=user_id,username`
   with that token → use `user_id`.
7. VendSlip: **Instagram account ID** and **Instagram username** → **Save**.

### Test

From an Instagram account that has a role on the app (**App roles → Roles → Instagram testers**;
accept the invite in Instagram → **Settings → Website permissions → Tester invites**), send a DM `hi`
to your business account. Expect the welcome text, then `/link CODE`.

---

## Part 5. Tokens that don't expire

The test WhatsApp token dies in about 24 hours, and Page tokens from the dashboard can expire. For
anything beyond a quick test, use a **System User** token.

1. **[business.facebook.com](https://business.facebook.com) → Settings → Users → System users → Add** →
   name it *vendslip-bot*, role **Admin**.
2. **Assign assets**:
   - **Apps** → your app → **Full control** (Manage app);
   - **WhatsApp accounts** → your WhatsApp Business account → **Full control**;
   - **Pages** → your Page → **Full control** (for Messenger and Instagram option A).
3. **Generate token** → choose your app → **Token expiration: Never** → tick the permissions:
   - `whatsapp_business_messaging`, `whatsapp_business_management`, `business_management`;
   - `pages_messaging`, `pages_manage_metadata`, `pages_show_list`;
   - `instagram_basic`, `instagram_manage_messages` (for Instagram option A).
4. **Generate token** → copy it now (it's shown once).
5. VendSlip: paste it as the **WhatsApp access token**. For Messenger, use a **Page** token derived from it:
   Graph API Explorer with the system-user token → `GET /me/accounts` → copy that Page's
   `access_token` → **Page access token**. A Page token obtained this way doesn't expire.

---

## Part 6. Going live for real merchants

While the app is in **development** mode, only you and your testers get replies. Before real merchants
can use it:

| Step | Where | Notes |
|---|---|---|
| **Business verification** | business.facebook.com → **Security centre** | Company documents (e.g. CAC certificate), address, phone or domain |
| **Your own WhatsApp number** | WhatsApp → **API Setup → Add phone number** | A number **not** already on the WhatsApp app (or delete it there first). Verify by SMS or call. Set a **display name** (e.g. *VendSlip*); Meta approves it |
| Update VendSlip | Gateways & Bots | New **Phone number ID** and **WhatsApp display number** |
| **App Review** | App dashboard → **App Review → Requests** | Ask for advanced access to `whatsapp_business_messaging`, `pages_messaging`, `instagram_manage_messages` (whichever you use). Each needs a short screen recording showing the bot in use and the privacy policy URL |
| **Publish** | App dashboard → **Publish** (switch to *Live*) | Only after the reviews pass |

**Costs:** replying to messages people send you (*service conversations*) is free on WhatsApp. VendSlip only
ever replies, and it saves even those replies by staying silent while a draft is incomplete.

---

## Troubleshooting

| Problem | Fix |
|---|---|
| **Verify and save** fails | Verify token differs between Meta and VendSlip; or `APP_URL`/tunnel isn't public. Open `https://YOUR-DOMAIN/api/v1/public/legal` in a browser. If that fails, Meta can't reach you either |
| Verified, but messages get no reply | `messages` field not subscribed; wrong **Phone number ID** (it's the ID, not the number); Route not set to **Meta Cloud API** |
| Worked yesterday, silent today | The 24-hour WhatsApp token expired → System User token (Part 5) |
| Your **own** number gets no webhooks | Your app isn't subscribed to that WhatsApp account. Run in Graph API Explorer: `POST /<WABA_ID>/subscribed_apps` with the system-user token |
| Security Logs shows `webhook … bad_signature` | **App secret** is wrong (or, for Instagram option B, **Instagram app secret** is missing) |
| Only you get replies | App still in development mode: add testers under **App roles**, or finish Part 6 |
| Instagram DMs don't arrive | **Allow access to messages** is off in Instagram; or the account isn't professional / isn't linked to the Page (option A) |

Sources: Meta's [WhatsApp Cloud API Get Started](https://developers.facebook.com/documentation/business-messaging/whatsapp/get-started),
[Messenger use case](https://developers.facebook.com/documentation/development/create-an-app/messenger-use-case.md),
[Instagram API with Instagram Login](https://developers.secure.facebook.com/documentation/instagram-platform/instagram-api-with-instagram-login/get-started),
[Graph API versions](https://developers.facebook.com/docs/graph-api/changelog/versions).
Meta renames menus from time to time; if a label differs, look for the nearest match.
