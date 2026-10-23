<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduled tasks. On Forge, enable the scheduler for the site (it runs
| `php artisan schedule:run` every minute).
*/

Schedule::command('tiqs:automatic-payouts')
    ->dailyAt('06:00')
    ->timezone(config('tiqs.business_timezone'))
    ->withoutOverlapping()
    ->onOneServer();
