<?php

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Tenancy\Services\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the restaurant for public (storefront) requests.
 *
 * Order: verified custom domain, then subdomain of the base domain, then the
 * `{restaurant}` route parameter (/r/{slug}).
 */
class ResolveTenant
{
    public function __construct(private readonly TenantContext $context, private readonly TenantResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $restaurant = $this->resolver->fromRequest($request);

        if ($restaurant === null || $restaurant->isSuspended()) {
            abort(404);
        }

        $this->context->set($restaurant);
        $request->attributes->set('restaurant', $restaurant);

        return $next($request);
    }
}
