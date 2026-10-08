<?php

namespace App\Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-side hardening on every web response: no MIME sniffing, a stricter referrer, no camera/microphone, HSTS on https,
 * and no framing of the panel and sign-in pages (clickjacking). The guest menu may be framed on purpose: it powers the website
 * button and iframe embed. A full Content-Security-Policy is not set because the pages rely on inline scripts (Alpine, Livewire).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff', false);
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), payment=(self), geolocation=(self)', false);

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000', false);
        }

        $name = (string) $request->route()?->getName();

        if (! str_starts_with($name, 'storefront.')) {
            $headers->set('X-Frame-Options', 'SAMEORIGIN', false);
            $headers->set('Content-Security-Policy', "frame-ancestors 'self'", false);
        }

        return $response;
    }
}
