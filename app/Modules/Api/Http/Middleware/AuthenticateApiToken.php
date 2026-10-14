<?php

namespace App\Modules\Api\Http\Middleware;

use App\Modules\Api\Services\ApiTokens;
use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Tenancy\Models\Restaurant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Authorization: Bearer qrm_...` → the token's restaurant becomes the tenant for this request. Usage: `api.token:orders:read`
 * (the ability the route needs). A missing, unknown or expired token, a suspended restaurant, or a plan without the API all answer
 * the same 401/403 JSON, so nothing is revealed about which tokens exist.
 */
class AuthenticateApiToken
{
    public function __construct(private readonly ApiTokens $tokens, private readonly TenantContext $tenant, private readonly LimitGuard $limits) {}

    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $token = $this->tokens->find($request->bearerToken());
        $restaurant = $token ? Restaurant::find($token->restaurant_id) : null;

        if (! $token || ! $restaurant || $restaurant->isSuspended()) {
            return response()->json(['message' => 'Unauthenticated.'], 401, ['WWW-Authenticate' => 'Bearer']);
        }

        if (! $this->limits->hasFeature($restaurant, 'api')) {
            return response()->json(['message' => 'Your plan does not include API access.'], 403);
        }

        if ($ability && ! $token->can($ability)) {
            return response()->json(['message' => 'This token cannot do that.', 'required' => $ability], 403);
        }

        $this->tenant->set($restaurant);
        $request->attributes->set('api_token', $token);
        $request->attributes->set('api_restaurant', $restaurant);

        // Remember when it was last used, at most once a minute.
        if (! $token->last_used_at || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
