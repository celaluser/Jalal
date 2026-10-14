<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\PermissionRegistrar;

class CreateSuperAdmin extends Command
{
    protected $signature = 'app:create-super-admin {email} {--name=Super Admin} {--password= : Prompted when omitted}';

    protected $description = 'Create a platform super admin account';

    public function handle(): int
    {
        $password = $this->option('password') ?: $this->secret('Password');

        $validator = Validator::make(
            ['email' => $this->argument('email'), 'password' => $password],
            ['email' => ['required', 'email', 'unique:users,email'], 'password' => ['required', Password::min(8)]]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $this->option('name'),
            'email' => $this->argument('email'),
            'password' => $password,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
        $user->assignRole(Permissions::SUPER_ADMIN);

        $this->info("Super admin {$user->email} created.");

        return self::SUCCESS;
    }
}
