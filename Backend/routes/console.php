<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('vendslip:mark-overdue')->dailyAt('00:05')->timezone(config('vendslip.timezone'));
Schedule::command('vendslip:invoice-reminders')->dailyAt('09:10')->timezone(config('vendslip.timezone'))->withoutOverlapping();
Schedule::command('vendslip:expire-drafts')->hourly();
Schedule::command('vendslip:fx-refresh')->everySixHours()->withoutOverlapping();
Schedule::command('vendslip:digests')->dailyAt('20:00')->timezone(config('vendslip.timezone'))->withoutOverlapping();

// Background jobs (emails, alerts) are processed by the scheduler, so a single cron line is all a
// host needs:  * * * * * cd /path/to/Backend && php artisan schedule:run >> /dev/null 2>&1
// The worker drains the queue and exits before the next minute, so cPanel never runs two at once.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3 --backoff=30')
    ->everyMinute()
    ->withoutOverlapping(2);
