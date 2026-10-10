<?php

namespace App\Modules\Auth\Http\Middleware;

use App\Modules\Core\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Google reCAPTCHA v2 on public forms, when enabled in the panel. Fails closed: if Google cannot
 * be reached the form is refused rather than left open to bots.
 */
class VerifyRecaptcha
{
    public function __construct(private readonly SettingsService $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->settings->get('security.turnstile_enabled') === '1') {
            return $this->turnstile($request, $next);
        }

        if ($this->settings->get('security.recaptcha_enabled') !== '1') {
            return $next($request);
        }

        $token = (string) $request->input('g-recaptcha-response');
        $secret = (string) $this->settings->get('security.recaptcha_secret');

        $passed = false;

        if ($token !== '' && $secret !== '') {
            try {
                $passed = Http::asForm()->timeout(8)->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => $secret, 'response' => $token, 'remoteip' => $request->ip(),
                ])->json('success') === true;
            } catch (Throwable) {
                $passed = false;
            }
        }

        if (! $passed) {
            throw ValidationException::withMessages(['g-recaptcha-response' => __('auth.recaptcha_failed')]);
        }

        return $next($request);
    }

    /** Cloudflare Turnstile: same idea, different endpoint and field. Fails closed too. */
    private function turnstile(Request $request, Closure $next): Response
    {
        $token = (string) $request->input('cf-turnstile-response');
        $secret = (string) $this->settings->get('security.turnstile_secret');
        $passed = false;

        if ($token !== '' && $secret !== '') {
            try {
                $passed = Http::asForm()->timeout(8)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secret, 'response' => $token, 'remoteip' => $request->ip(),
                ])->json('success') === true;
            } catch (Throwable) {
                $passed = false;
            }
        }

        if (! $passed) {
            throw ValidationException::withMessages(['g-recaptcha-response' => __('auth.recaptcha_failed')]);
        }

        return $next($request);
    }
}
