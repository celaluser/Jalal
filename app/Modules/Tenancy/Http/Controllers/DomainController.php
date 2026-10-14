<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Tenancy\Services\DomainService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/** Where customers find the menu: subdomain and own domain. */
class DomainController extends Controller
{
    /** Resolved per call (not injected): the router keeps controller instances across requests of one process. */
    private function domains(): DomainService
    {
        return app(DomainService::class);
    }

    public function index(Request $request): View
    {
        $restaurant = $request->user()->restaurant;

        return view('tenancy::settings.domains', [
            'restaurant' => $restaurant, 'domains' => $this->domains(),
            'dnsInstructions' => (string) app(SettingsService::class)->get('domains.dns_instructions', ''),
        ]);
    }

    public function subdomain(Request $request): RedirectResponse
    {
        $data = $request->validate(['subdomain' => ['nullable', 'string', 'max:40']]);

        return $this->attempt(fn () => $this->domains()->setSubdomain($request->user()->restaurant, $data['subdomain'] ?? null), __('admin.saved'), 'subdomain');
    }

    public function customDomain(Request $request): RedirectResponse
    {
        $data = $request->validate(['custom_domain' => ['nullable', 'string', 'max:255']]);

        return $this->attempt(fn () => $this->domains()->setCustomDomain($request->user()->restaurant, $data['custom_domain'] ?? null), __('admin.saved'), 'custom_domain');
    }

    public function verify(Request $request): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;
        abort_unless($this->domains()->customDomainsAvailable($restaurant), 403);

        return $this->domains()->verify($restaurant)
            ? back()->with('status', __('domains.verified_now'))
            : back()->withErrors(['custom_domain' => __('domains.not_found_yet')]);
    }

    private function attempt(callable $action, string $success, string $field): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors([$field => __('domains.error_'.$e->getMessage())]);
        }

        return back()->with('status', $success);
    }
}
