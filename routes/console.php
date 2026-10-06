<?php

use Illuminate\Support\Facades\Schedule;

// Public demo sites restore pristine data every night. demo:reset itself refuses to run outside DEMO_MODE.
Schedule::command('demo:reset')->dailyAt('04:00')->when(fn () => config('demo.enabled'));

Schedule::command('billing:expire')->dailyAt('02:30');
