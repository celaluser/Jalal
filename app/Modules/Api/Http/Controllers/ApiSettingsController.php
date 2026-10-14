<?php

namespace App\Modules\Api\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\Models\ApiToken;
use App\Modules\Api\Models\WebhookEndpoint;
use App\Modules\Api\Services\ApiTokens;
use App\Modules\Api\Services\UrlGuard;
use App\Modules\Api\Services\WebhookDispatcher;
use App\Modules\Billing\Services\LimitGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Panel page: API tokens and webhook endpoints. */
class ApiSettingsController extends Controller
{
    public function __construct(private readonly LimitGuard $limits) {}

    public function index(Request $request): View
    {
        $restaurant = $request->user()->restaurant;

        return view('api::settings', [
            'restaurant' => $restaurant, 'enabled' => $this->limits->hasFeature($restaurant, 'api'),
            'tokens' => ApiToken::orderByDesc('id')->get(), 'endpoints' => WebhookEndpoint::with(['deliveries' => fn ($q) => $q->limit(5)])->orderByDesc('id')->get(),
            'abilities' => ApiToken::ABILITIES, 'events' => WebhookEndpoint::EVENTS,
        ]);
    }

    public function createToken(Request $request, ApiTokens $tokens): RedirectResponse
    {
        $this->gate($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'abilities' => ['required', 'array', 'min:1'], 'abilities.*' => ['in:'.implode(',', ApiToken::ABILITIES)], 'days' => ['nullable', 'integer', 'between:1,3650']]);
        abort_if(ApiToken::count() >= 20, 422, __('api.too_many_tokens'));
        $issued = $tokens->issue($data['name'], $data['abilities'], isset($data['days']) ? (int) $data['days'] : null);

        // The token is shown once, on the next page load only.
        return back()->with('new_token', $issued['plain']);
    }

    public function revokeToken(int $token): RedirectResponse
    {
        ApiToken::findOrFail($token)->delete();

        return back()->with('status', __('api.token_revoked'));
    }

    public function createEndpoint(Request $request, UrlGuard $guard): RedirectResponse
    {
        $this->gate($request);
        $data = $request->validate(['url' => ['required', 'url:https', 'max:500'], 'events' => ['required', 'array', 'min:1'], 'events.*' => ['in:'.implode(',', [...WebhookEndpoint::EVENTS, '*'])]]);

        if (! $guard->allowed($data['url'])) {
            throw ValidationException::withMessages(['url' => __('api.url_not_allowed')]);
        }

        abort_if(WebhookEndpoint::count() >= 10, 422, __('api.too_many_endpoints'));
        $endpoint = new WebhookEndpoint(['url' => $data['url'], 'events' => array_values($data['events'])]);
        $endpoint->secret = 'whsec_'.Str::random(32);
        $endpoint->save();

        return back()->with('new_secret', ['url' => $endpoint->url, 'secret' => $endpoint->secret]);
    }

    public function toggleEndpoint(int $endpoint): RedirectResponse
    {
        $model = WebhookEndpoint::findOrFail($endpoint);
        $model->forceFill(['is_active' => ! $model->is_active, 'failures' => 0])->save();

        return back();
    }

    public function deleteEndpoint(int $endpoint): RedirectResponse
    {
        WebhookEndpoint::findOrFail($endpoint)->delete();

        return back()->with('status', __('api.endpoint_deleted'));
    }

    public function testEndpoint(Request $request, WebhookDispatcher $dispatcher, int $endpoint): RedirectResponse
    {
        $dispatcher->queue(WebhookEndpoint::findOrFail($endpoint), 'ping', ['message' => 'Hello from '.$request->user()->restaurant->name]);

        return back()->with('status', __('api.test_sent'));
    }

    private function gate(Request $request): void
    {
        abort_unless($this->limits->hasFeature($request->user()->restaurant, 'api'), 403);
    }
}
