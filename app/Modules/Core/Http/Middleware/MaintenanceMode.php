<?php

namespace App\Modules\Core\Http\Middleware;

use App\Modules\Core\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel-controlled maintenance switch. Visitors get a 503 page; platform users (super admins) and the
 * login screens keep working so the switch can always be turned off. Payment webhooks are not in the
 * web group, so they are never blocked.
 */
class MaintenanceMode
{
    public function __construct(private readonly SettingsService $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->settings->get('general.maintenance') !== '1') {
            return $next($request);
        }

        $user = $request->user();

        if (($user && $user->isPlatformUser()) || $request->is('login', 'logout', 'two-factor-challenge', 'auth/google*', 'forgot-password', 'reset-password/*', 'build/*')) {
            return $next($request);
        }

        return response()->view('maintenance', [
            'message' => $this->settings->get('general.maintenance_message') ?: __('maintenance.default_message'),
        ], 503, ['Retry-After' => 3600]);
    }
}
