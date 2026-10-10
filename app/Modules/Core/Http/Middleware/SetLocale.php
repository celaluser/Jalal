<?php

namespace App\Modules\Core\Http\Middleware;

use App\Modules\Core\Models\Language;
use App\Modules\Core\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chooses the UI locale: ?lang= / session, then user preference, then restaurant default,
 * then browser Accept-Language, then app default. Only active languages are accepted.
 * Shares the text direction with views as $dir.
 */
class SetLocale
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $active = $this->activeLanguages();
        $codes = array_keys($active);

        $locale = $this->pick($request, $codes) ?? config('app.default_locale');

        if ($request->hasSession() && $request->query('lang') === $locale) {
            $request->session()->put('locale', $locale);
        }

        app()->setLocale($locale);
        view()->share('dir', ($active[$locale] ?? false) ? 'rtl' : 'ltr');
        view()->share('activeLanguages', $active);

        return $next($request);
    }

    /**
     * @return array<string, bool> locale code => is RTL
     */
    private function activeLanguages(): array
    {
        try {
            if (! Schema::hasTable('languages')) {
                return [config('app.default_locale') => false];
            }

            return Cache::remember('languages.active', 3600, fn () => Language::active()->pluck('is_rtl', 'code')->all())
                ?: [config('app.default_locale') => false];
        } catch (\Throwable) {
            return [config('app.default_locale') => false];
        }
    }

    /**
     * @param  list<string>  $codes
     */
    private function pick(Request $request, array $codes): ?string
    {
        $candidates = [
            $request->query('lang'),
            $request->hasSession() ? $request->session()->get('locale') : null,
            $request->user()?->locale,
            $this->tenant->get()?->locale,
            $request->getPreferredLanguage($codes),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && in_array($candidate, $codes, true)) {
                return $candidate;
            }
        }

        return null;
    }
}
