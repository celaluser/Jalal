<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Wipes the database and reloads demo data. Refuses to run unless DEMO_MODE is on, so it
 * can never erase a real installation.
 */
class DemoReset extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Reset the database to fresh demo data (DEMO_MODE only)';

    public function handle(): int
    {
        if (! config('demo.enabled')) {
            $this->error('demo:reset only runs when DEMO_MODE=true.');

            return self::FAILURE;
        }

        $this->call('migrate:fresh', ['--force' => true]);
        $this->call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
        $this->call('optimize:clear');

        return self::SUCCESS;
    }
}
