<?php

namespace App\Modules\Demo\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * In demo mode every state-changing request (anything but GET/HEAD/OPTIONS) is refused, except
 * a short allowlist of route names (login, logout...). Keeps a public demo from being vandalised.
 */
class BlockDemoActions
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('demo.enabled') || $request->isMethodSafe()) {
            return $next($request);
        }

        $route = $request->route()?->getName();

        if ($route !== null && in_array($route, config('demo.allowed_routes'), true)) {
            return $next($request);
        }

        $message = __('demo.blocked');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return back()->withErrors(['demo' => $message]);
    }
}
