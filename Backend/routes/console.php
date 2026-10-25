<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('netslip:mark-overdue')->dailyAt('00:05')->timezone(config('netslip.timezone'));
Schedule::command('netslip:expire-drafts')->hourly();
Schedule::command('netslip:digests')->dailyAt('20:00')->timezone(config('netslip.timezone'))->withoutOverlapping();
