<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

/*
| A platform user (super admin) belongs to no restaurant. Every page and action that is meant for restaurant staff must
| turn that user away politely (redirect, 403 or 404) and never answer with a server error.
*/
it('never answers a platform user with a server error on restaurant-only pages', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $admin->assignRole(Permissions::SUPER_ADMIN);
    $this->actingAs($admin);

    $failures = [];

    foreach (app('router')->getRoutes()->getRoutes() as $route) {
        $uri = $route->uri();
        $middleware = implode(' ', $route->gatherMiddleware());

        if (! str_contains($middleware, 'tenant.user') || str_contains($uri, '{') || str_starts_with($uri, 'admin') || in_array($uri, ['logout', 'impersonation/stop'], true)) {
            continue;
        }

        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $status = $this->call($method, '/'.$uri)->getStatusCode();

            if ($status >= 500) {
                $failures[] = "$method /$uri → $status";
            }
        }
    }

    expect($failures)->toBe([]);
});
