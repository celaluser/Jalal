<?php

use Illuminate\Support\Facades\Schedule;

// Public demo sites restore pristine data every night. demo:reset itself refuses to run outside DEMO_MODE.
Schedule::command('demo:reset')->dailyAt('04:00')->when(fn () => config('demo.enabled'));

Schedule::command('billing:expire')->dailyAt('02:30');
Schedule::command('billing:remind')->dailyAt('09:00');

// Heartbeat for the System page, and a daily database backup (newest 7 are kept).
Schedule::command('system:heartbeat')->everyMinute();
Schedule::command('system:backup --keep=7')->dailyAt('03:30');

// Owners hear about new orders nobody accepted in time (only for restaurants that asked for it).
Schedule::command('orders:escalate')->everyMinute();

Schedule::command('reservations:remind')->everyTenMinutes();

Schedule::command('marketing:autopilot')->dailyAt('10:15');
Schedule::command('reports:digest')->weeklyOn(1, '07:30');
