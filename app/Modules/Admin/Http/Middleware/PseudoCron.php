<?php

namespace App\Modules\Admin\Http\Middleware;

use App\Modules\Admin\Services\WebCron;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Lets visitors drive the scheduler when no cron job is set up (see WebCron). Runs after the page has been sent. */
class PseudoCron
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $installed = config('installer.force_installed') || is_file((string) config('installer.lock_file'));

        if ($installed && $request->isMethod('GET') && ! $request->is('install*', 'cron/*', 'livewire/*', 'up')) {
            app(WebCron::class)->maybeRunAfterResponse();
        }
    }
}
