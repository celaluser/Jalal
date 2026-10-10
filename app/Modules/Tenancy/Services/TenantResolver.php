<?php

namespace App\Modules\Tenancy\Services;

use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Http\Request;

/**
 * Finds the restaurant a public request is for: verified custom domain, then subdomain of the base
 * domain, then (when allowed) the {restaurant} route parameter of /r/{slug}.
 */
class TenantResolver
{
    public function fromHost(Request $request): ?Restaurant
    {
        $host = strtolower($request->getHost());

        if (in_array($host, config('tenancy.central_domains'), true)) {
            return null;
        }

        if (config('tenancy.custom_domains_enabled')) {
            $byDomain = Restaurant::query()->where('custom_domain', $host)->whereNotNull('domain_verified_at')->first();

            if ($byDomain) {
                return $byDomain;
            }
        }

        $base = config('tenancy.base_domain');

        if (config('tenancy.subdomains_enabled') && $base && str_ends_with($host, '.'.$base)) {
            return Restaurant::query()->where('subdomain', substr($host, 0, -strlen('.'.$base)))->first();
        }

        return null;
    }

    public function fromRequest(Request $request): ?Restaurant
    {
        if ($byHost = $this->fromHost($request)) {
            return $byHost;
        }

        $slug = $request->route('restaurant');

        return is_string($slug) ? Restaurant::query()->where('slug', $slug)->first() : null;
    }
}
