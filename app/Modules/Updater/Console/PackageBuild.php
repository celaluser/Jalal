<?php

namespace App\Modules\Updater\Console;

use App\Modules\Updater\Exceptions\UpdateException;
use App\Modules\Updater\Services\PackageBuilder;
use Illuminate\Console\Command;

class PackageBuild extends Command
{
    protected $signature = 'package:build {source : Folder to package} {output : Zip file to create} {--ver= : Version, e.g. 1.2.0} {--key= : File with the secret key} {--notes= : Release notes} {--addon= : Add-on slug (packs the folder as addons/<slug>/)}';

    protected $description = 'Build a signed update or add-on package (run on YOUR machine)';

    public function handle(PackageBuilder $builder): int
    {
        $key = is_file((string) $this->option('key')) ? trim((string) file_get_contents((string) $this->option('key'))) : '';

        try {
            $count = $builder->build($this->argument('source'), $this->argument('output'), (string) $this->option('ver'), $key, $this->option('notes'), $this->option('addon'));
        } catch (UpdateException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Packed {$count} file(s) into ".$this->argument('output'));

        return self::SUCCESS;
    }
}
