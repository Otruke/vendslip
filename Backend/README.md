# NetSlip Backend

Laravel 13 REST API (`/api/v1`) and the Blade Admin / Super-Admin portal (`/admin`).

- Windows quick start: `../START-HERE.md` (`setup-windows.bat`, then `start-netslip.bat`)
- Full setup, bots, payments and production deploy: `../SETUP.md`

```bash
composer install
php artisan migrate:fresh --seed   # demo shop in APP_ENV=local
php artisan serve                  # http://127.0.0.1:8000
php artisan test                   # needs a PostgreSQL database named netslip_test
```

The four bot modules (`../whatsapp_bot`, `../telegram_bot`, `../instagram_bot`, `../facebook_bot`)
are autoloaded from `composer.json` under the `NetSlip\Bots\…` namespaces.
