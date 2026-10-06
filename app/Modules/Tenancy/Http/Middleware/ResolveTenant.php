<?php

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Tenancy\Models\Restaurant;
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
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $restaurant = $this->resolve($request);

        if ($restaurant === null || $restaurant->isSuspended()) {
            abort(404);
        }

        $this->context->set($restaurant);
        $request->attributes->set('restaurant', $restaurant);

        return $next($request);
    }

    private function resolve(Request $request): ?Restaurant
    {
        $host = strtolower($request->getHost());

        if (! in_array($host, config('tenancy.central_domains'), true)) {
            if (config('tenancy.custom_domains_enabled')) {
                $byDomain = Restaurant::query()
                    ->where('custom_domain', $host)
                    ->whereNotNull('domain_verified_at')
                    ->first();

                if ($byDomain) {
                    return $byDomain;
                }
            }

            $base = config('tenancy.base_domain');

            if (config('tenancy.subdomains_enabled') && $base && str_ends_with($host, '.'.$base)) {
                $sub = substr($host, 0, -strlen('.'.$base));

                return Restaurant::query()->where('subdomain', $sub)->first();
            }
        }

        $slug = $request->route('restaurant');

        return is_string($slug) ? Restaurant::query()->where('slug', $slug)->first() : null;
    }
}
