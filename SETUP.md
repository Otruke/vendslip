# NetSlip — local setup

*Close the sale. Send the slip.*

> On Windows, the fastest path is **`START-HERE.md`**: run `setup-windows.bat` once, then `start-netslip.bat`.

```
netSlip/
├── Backend/         Laravel 13 API (/api/v1) + Blade Admin & Super-Admin portal (/admin)
├── Frontend/        React 19 + Vite public site and merchant dashboard
├── whatsapp_bot/    Twilio WhatsApp handler (interactive confirm buttons)
├── telegram_bot/    Telegram Bot API 10.3 handler (rich tables, ephemeral drafts)
├── instagram_bot/   Twilio Instagram DM handler
└── facebook_bot/    Twilio Facebook Messenger handler
```

The four bot folders are PSR-4 autoloaded by the backend (`NetSlip\Bots\…`), so they share one
codebase, one database and one draft/issuance engine.

## Requirements

- PHP 8.4+ with `pdo_pgsql`, `mbstring`, `gd`, `intl`, `zip`, `fileinfo`, `openssl`
- Composer 2, Node 20+ (npm), PostgreSQL 16+

## 1. Backend

```bash
cd Backend
composer install
composer dump-autoload            # registers the four bot folders
cp .env.example .env              # first time only, then fill in DB_* and keys
php artisan key:generate          # first time only
createdb netslip_db               # or create it in pgAdmin
php artisan migrate --seed        # roles, templates, plans, credit pack, super-admin
php artisan storage:link
php artisan serve                 # http://127.0.0.1:8000
```

Set `NETSLIP_SUPER_ADMIN_EMAIL` / `NETSLIP_SUPER_ADMIN_PASSWORD` **before** seeding.
If the password is empty the seeder prints a random one once.

In `APP_ENV=local` the seeder also creates a demo shop:
`owner@aminaluxury.test` / `password` (Owner) and `sales@aminaluxury.test` / `password` (Sales agent).

Admin portal: <http://127.0.0.1:8000/admin>. Admin assets are prebuilt in `public/build`;
run `npm install && npm run build` in `Backend/` only if you change Blade CSS/JS.

Scheduler (overdue invoices, draft expiry, nightly digests):

```bash
php artisan schedule:work         # dev; in production run schedule:run every minute via cron
```

## 2. Frontend

```bash
cd Frontend
npm install
npm run dev                       # http://localhost:5173
```

Vite proxies `/api`, `/sanctum` and `/storage` to `VITE_BACKEND_URL` (default `http://127.0.0.1:8000`),
so cookies stay same-origin. Keep `FRONTEND_URL=http://localhost:5173` and
`SANCTUM_STATEFUL_DOMAINS` (see `.env.example`) in the backend `.env`.

Production build: `npm run build` → `Frontend/dist` (serve behind the same domain as `/api`).

## 3. Bots

All webhooks live under `https://<public-host>/api/v1/webhooks/…`. Locally, expose the backend
with a tunnel (e.g. ngrok) and set `APP_URL` to the tunnel URL and `TRUSTED_PROXIES` to the tunnel IPs.

| Channel   | Webhook                  | Notes |
|-----------|--------------------------|-------|
| Telegram  | `/webhooks/telegram`     | Set `TELEGRAM_BOT_TOKEN`, `TELEGRAM_WEBHOOK_SECRET`, then `php artisan netslip:telegram-webhook` |
| WhatsApp  | `/webhooks/whatsapp`     | Twilio console → Messaging → sender webhook. Optional quick-reply Content template SID in `TWILIO_WHATSAPP_CONFIRM_CONTENT_SID` |
| Instagram | `/webhooks/instagram`    | Twilio Instagram sender (`TWILIO_INSTAGRAM_FROM`) |
| Messenger | `/webhooks/facebook`     | Twilio Messenger sender (`TWILIO_FACEBOOK_FROM`) |

Twilio signatures are always validated (`TWILIO_VALIDATE_SIGNATURE=true`). Merchants link a chat
from **Dashboard → Bot Channels** by sending the one-time `/link CODE` to the bot.

## 4. Payments

- Platform billing (plans & credit packs): Flutterwave v3 and NOWPayments keys in `.env`
  or in **/admin/gateways** (Super-Admin; stored encrypted).
  - Flutterwave webhook: `/api/v1/webhooks/flutterwave` (secret hash = `FLUTTERWAVE_SECRET_HASH`)
  - NOWPayments IPN: `/api/v1/webhooks/nowpayments` (`NOWPAYMENTS_IPN_SECRET`)
- Merchant invoice collection: each merchant adds their own keys in **Settings → Payment links**;
  the dashboard shows their personal webhook URLs.

## 5. Quality checks

```bash
cd Backend  && php artisan test && vendor/bin/phpstan analyse && vendor/bin/pint --test
cd Frontend && npx tsc -b && npx eslint . && npm run build
```

Tests run against a PostgreSQL database named `netslip_test` (see `phpunit.xml`).

TypeScript types for enums/DTOs are generated from PHP:
`php artisan typescript:transform` → `Frontend/src/types/generated.ts`.

## 6. Production deploy (single domain)

Serve the SPA and Laravel from **one domain** — Sanctum's cookie login depends on it.
A tested nginx config is in `deploy/nginx.conf.example` (SPA at `/`, Laravel at `/api`, `/sanctum`,
`/admin`, `/build`, `/storage`, `/up`).

```bash
# Backend
cd Backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force          # FIRST: `optimize` writes to the database cache table
php artisan db:seed --force          # first deploy only (needs NETSLIP_SUPER_ADMIN_PASSWORD)
php artisan storage:link
php artisan optimize                 # config, routes, views, events, laravel-data caches
# Frontend
cd ../Frontend && npm ci && npm run build   # -> Frontend/dist
```

`.env` essentials: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` and `FRONTEND_URL` = your
domain (e.g. `https://netslip.ng`), `SESSION_SECURE_COOKIE=true`, real `MAIL_*`, PostgreSQL `DB_*`.
The hosts in `APP_URL` / `FRONTEND_URL` are trusted for cookie login automatically.

Cron (as the web user): `* * * * * cd /var/www/netslip/Backend && php artisan schedule:run >> /dev/null 2>&1`.
No queue worker is needed — nothing is queued.

After every deploy: `php artisan migrate --force && php artisan optimize`, and run `composer audit`.

## Notes before going live

- **Fonts:** the bundled Lupio files are the free *demo* cut, subset to letters only. Buy a
  commercial Lupio licence and replace `Backend/resources/fonts/lupio-*.ttf` and
  `Frontend/public/assets/fonts/lupio-*.woff2`. Brush Script MT is also a licensed font.
- **Verification key:** set `NETSLIP_VERIFICATION_KEY` once and never rotate it casually —
  every issued QR code is signed with it.
- Use HTTPS, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, and a real mail driver
  (password resets and staff invitations are emailed).
