<?php

namespace App\Console\Commands;

use App\Modules\Admin\Services\SystemInfo;
use Illuminate\Console\Command;

/** Scheduled every minute: proves to the System page that the cron entry is really running. */
class SystemHeartbeat extends Command
{
    protected $signature = 'system:heartbeat';

    protected $description = 'Record that the scheduler is running';

    public function handle(): int
    {
        SystemInfo::beat(SystemInfo::SCHEDULER_KEY);

        return self::SUCCESS;
    }
}
