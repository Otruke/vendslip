# NetSlip: start here

*Close the sale. Send the slip.*

## 1. Folder layout

Unzip so these sit **directly** inside `C:\Users\rufus.otolorin\Documents\Ampps\netSlip\`:

```
netSlip\
├── START-HERE.md            ← this file
├── setup-windows.bat        ← run once (installs packages, builds the database)
├── start-netslip.bat        ← run every time you want to use NetSlip
├── SETUP.md                 ← full developer + production guide
├── ARCHITECTURE_AND_PLAN.md
├── GOOGLE_STITCH_UI_UX_PROMPT.md
├── Backend\                 ← Laravel 13 API + Admin / Super-Admin portal
├── Frontend\                ← React 19 public site + merchant dashboard
├── whatsapp_bot\  telegram_bot\  instagram_bot\  facebook_bot\
├── deploy\                  ← production nginx config
├── brand_identity\
└── google_stitch_ui_ux\
```

If Windows unzipped into `netSlip\netSlip\…`, move the inner contents up one level.
Your existing `Backend\.env`, `Backend\vendor` and `Frontend\node_modules` are not in the zip,
so unzipping over the folder keeps them.

## 2. You need

- **PHP 8.4.1+** on PATH, with `pdo_pgsql, mbstring, openssl, fileinfo, gd, dom, zip, curl` enabled
- **Composer**, **Node.js 20+**, **PostgreSQL** with a database called `netslip_db`

## 3. Run it

1. Double-click **`setup-windows.bat`**. It checks the tools, installs packages, resets `netslip_db`
   with demo data, and links storage. On a brand-new folder it opens `Backend\.env` in
   Notepad first: set `DB_PASSWORD` and `NETSLIP_SUPER_ADMIN_PASSWORD`, then save and close.
2. Double-click **`start-netslip.bat`**. Three windows start (API, scheduler, web app) and
   your browser opens. Close those windows to stop NetSlip.

| What | Address |
|---|---|
| Public site + merchant dashboard | <http://localhost:5173> |
| Admin / Super-Admin portal | <http://127.0.0.1:8000/admin> |

## 4. Logins (demo data)

| Role | Email | Password |
|---|---|---|
| Shop owner (Business Plus) | `owner@aminaluxury.test` | `password` |
| Sales agent | `sales@aminaluxury.test` | `password` |
| Super-Admin | `NETSLIP_SUPER_ADMIN_EMAIL` in `.env` (default `superadmin@netslip.test`) | `NETSLIP_SUPER_ADMIN_PASSWORD`, or the one printed during setup |

## 5. What to test

**Merchant dashboard** (log in as owner):
- **/Slash Composer:** type `/name = Ada`, `/item = 2x Ankara @ 7500`, `/discount = 10%`,
  `/shipping = 1500`. Switch to Form and back. Confirm, then share or download the PDF.
- **Receipts & Invoices:** open a slip, record a payment, edit it with a reason, view its
  revision history, issue a credit note, void a slip, export CSV.
- **Verification:** open a slip's public check page (the QR on the PDF points there). Phone
  scanning only works once `FRONTEND_URL` is a public address, since `localhost` is your PC.
- Customers & Debtors, Inventory (stock moves on issue/void), Templates, Team (invite,
  roles), Billing & Credits, Settings (branding/logo, payment links, 2FA).
- Register a new shop at <http://localhost:5173/register>: free plan, 15 slips, watermark.

**Super-Admin portal:** merchants, per-client mutability (Immutable / Audited / Unrestricted),
plans and credit pack prices, templates, gateway keys, payments, security log, admin users.

**Needs external accounts (not testable offline):**
- Real card/crypto checkout needs Flutterwave or NOWPayments keys in `.env` or `/admin/gateways`.
- The chat bots need a public HTTPS URL (e.g. `ngrok http 8000`) plus Telegram or Twilio
  credentials. See `SETUP.md` §3.
- Emails (password reset, staff invites) are written to `Backend\storage\logs\laravel.log`
  while `MAIL_MAILER=log`.

## 6. If something goes wrong

| Symptom | Fix |
|---|---|
| Login works but the dashboard bounces you back | Open the app at `http://localhost:5173`, not `127.0.0.1:5173` |
| `SQLSTATE … password authentication failed` | Fix `DB_*` in `Backend\.env`, then rerun setup |
| `could not find driver` | Enable `extension=pdo_pgsql` in php.ini |
| Changed `.env` but nothing happens | `cd Backend` then `php artisan optimize:clear` |
| Port 8000 or 5173 busy | Close the old NetSlip windows, then start again |
| Red `401 /api/v1/auth/me` in browser DevTools | Normal before you log in; the app is checking for a session |

Quality checks: `cd Backend && php artisan test` (63 tests, needs a `netslip_test` database).
