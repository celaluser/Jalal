<?php

namespace App\Modules\Installer\Http\Middleware;

use App\Modules\Installer\Services\InstallerService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Before installation every page redirects to the wizard; afterwards the wizard disappears (404).
 */
class EnsureInstalled
{
    public function __construct(private readonly InstallerService $installer) {}

    public function handle(Request $request, Closure $next): Response
    {
        $isInstallerRoute = $request->is('install', 'install/*');

        if ($this->installer->isInstalled()) {
            // The last wizard page is still shown for a short while after the install (it holds the cron address);
            // anyone arriving later, for example from an old bookmark, is sent to the sign-in page.
            if ($request->is('install/finished') && $this->installer->justInstalled()) {
                return $next($request);
            }

            if ($isInstallerRoute) {
                return $request->is('install', 'install/finished') ? redirect()->route('login') : abort(404);
            }

            return $next($request);
        }

        if ($isInstallerRoute || $request->is('build/*', 'up')) {
            return $next($request);
        }

        return redirect()->route('install.welcome');
    }
}
