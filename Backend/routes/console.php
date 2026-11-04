<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('vendslip:mark-overdue')->dailyAt('00:05')->timezone(config('vendslip.timezone'));
Schedule::command('vendslip:expire-drafts')->hourly();
Schedule::command('vendslip:digests')->dailyAt('20:00')->timezone(config('vendslip.timezone'))->withoutOverlapping();
