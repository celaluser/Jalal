<?php

namespace App\Modules\Updater\Console;

use App\Modules\Updater\Services\PackageBuilder;
use Illuminate\Console\Command;

class PackageKeys extends Command
{
    protected $signature = 'package:keys {--out= : File to write the secret key to (mode 0600)}';

    protected $description = 'Generate the Ed25519 key pair that signs updates and add-ons (run on YOUR machine)';

    public function handle(PackageBuilder $builder): int
    {
        $keys = $builder->keys();
        $out = $this->option('out') ?: 'package-signing.key';

        if (file_exists($out)) {
            $this->error("{$out} already exists; refusing to overwrite a key.");

            return self::FAILURE;
        }

        file_put_contents($out, $keys['secret']."\n");
        chmod($out, 0600);
        $this->info("Secret key written to {$out}. Keep it offline and out of git.");
        $this->line('Public key for the .env of every installation:');
        $this->line('UPDATER_PUBLIC_KEY='.$keys['public']);

        return self::SUCCESS;
    }
}
