<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Support\Permissions;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Custom roles created by a restaurant owner. They belong to one restaurant (spatie "team")
 * and can only carry restaurant-level permissions, never platform ones.
 */
class RestaurantRoleService
{
    /**
     * @param  list<string>  $permissions
     */
    public function create(Restaurant $restaurant, string $name, array $permissions): Role
    {
        $name = trim($name);

        if ($name === '' || in_array($name, [Permissions::SUPER_ADMIN, ...Permissions::RESTAURANT_ROLES], true)) {
            throw ValidationException::withMessages(['name' => __('roles.name_reserved')]);
        }

        $allowed = array_values(array_intersect($permissions, Permissions::restaurant()));

        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($restaurant->id);

        try {
            if (Role::where('name', $name)->where('restaurant_id', $restaurant->id)->exists()) {
                throw ValidationException::withMessages(['name' => __('roles.name_taken')]);
            }

            $role = Role::create(['name' => $name, 'guard_name' => 'web', 'restaurant_id' => $restaurant->id]);
            $role->syncPermissions($allowed);

            return $role;
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }
}
