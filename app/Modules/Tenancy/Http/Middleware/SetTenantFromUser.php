<?php

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Core\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the authenticated user's restaurant as the tenant for panel requests and scopes
 * spatie permissions to it. Platform users (no restaurant) get the platform team id.
 */
class SetTenantFromUser
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null, 401);

        $restaurant = $user->restaurant;

        if ($restaurant !== null) {
            abort_if($restaurant->isSuspended(), 403, __('auth.restaurant_suspended'));
            $this->context->set($restaurant);
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId(
            $restaurant?->getKey() ?? config('tenancy.platform_team_id')
        );

        return $next($request);
    }
}
