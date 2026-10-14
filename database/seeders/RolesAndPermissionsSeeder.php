<?php

namespace Database\Seeders;

use App\Modules\Auth\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the built-in roles as global (team-less) roles. Restaurant owners may create their
 * own custom roles per restaurant on top of these.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([...Permissions::platform(), ...Permissions::restaurant()] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $superAdmin = Role::findOrCreate(Permissions::SUPER_ADMIN, 'web');
        $superAdmin->syncPermissions(Permissions::platform());

        foreach (Permissions::defaults() as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions(
                $permissions === ['*'] ? Permissions::restaurant() : $permissions
            );
        }
    }
}
