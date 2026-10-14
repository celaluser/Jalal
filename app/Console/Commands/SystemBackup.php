<?php

namespace App\Console\Commands;

use App\Modules\Admin\Services\DatabaseBackup;
use Illuminate\Console\Command;

class SystemBackup extends Command
{
    protected $signature = 'system:backup {--keep=7 : Number of newest backups to keep}';

    protected $description = 'Create a database backup and remove old ones';

    public function handle(DatabaseBackup $backups): int
    {
        $name = $backups->create();
        $removed = $backups->prune((int) $this->option('keep'));

        $this->info("Created {$name}; removed {$removed} old backup(s).");

        return self::SUCCESS;
    }
}
