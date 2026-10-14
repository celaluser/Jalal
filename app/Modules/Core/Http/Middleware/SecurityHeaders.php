<?php

namespace App\Modules\Core\Http\Middleware;

use App\Modules\Core\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-side hardening on every web response: no MIME sniffing, a stricter referrer, no camera/microphone, HSTS on https,
 * and no framing of the panel and sign-in pages (clickjacking). The guest menu may be framed on purpose: it powers the website
 * button and iframe embed.
 *
 * A Content-Security-Policy that limits where scripts, frames and forms may come from can be switched on in the admin settings
 * (off, report only, enforce). Pages rely on inline scripts (Alpine, Livewire), so inline code stays allowed; what it blocks is
 * code, frames and form posts from hosts nobody listed. Extra hosts (analytics, pixels, a chat widget) are added in the settings.
 */
class SecurityHeaders
{
    public function __construct(private readonly SettingsService $settings) {}

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

        $mode = (string) $this->settings->get('security.csp', 'off');

        if (in_array($mode, ['report', 'enforce'], true)) {
            $headers->set($mode === 'enforce' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only', $this->policy(), false);
        }

        return $response;
    }

    /** The policy text. Hosts from the settings are checked, so a stray character cannot add a directive. */
    public function policy(): string
    {
        $extra = collect(preg_split('/[\s,]+/', (string) $this->settings->get('security.csp_hosts', ''), -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn ($h) => preg_match('#^(https://)?(\*\.)?[a-z0-9-]+(\.[a-z0-9-]+)+(:\d+)?$#i', $h))
            ->map(fn ($h) => str_starts_with($h, 'https://') ? $h : 'https://'.$h)->unique()->implode(' ');

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.googletagmanager.com https://www.google.com https://www.gstatic.com https://challenges.cloudflare.com https://connect.facebook.net".($extra ? ' '.$extra : ''),
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: blob: https:",
            "media-src 'self' https:",
            "connect-src 'self' https://www.google-analytics.com https://*.google-analytics.com https://www.facebook.com".($extra ? ' '.$extra : ''),
            "frame-src 'self' https:",
            "worker-src 'self'",
            "manifest-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self' https:",
        ]);
    }
}
