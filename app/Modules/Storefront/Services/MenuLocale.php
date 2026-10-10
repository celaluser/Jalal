<?php

namespace App\Modules\Storefront\Services;

use App\Modules\Core\Models\Language;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Http\Request;

/**
 * Language of the customer menu: ?lang=, then the cookie from an earlier visit, then the browser's
 * preference, then the restaurant's default. Only languages the restaurant offers are accepted.
 */
class MenuLocale
{
    public const COOKIE = 'menu_lang';

    public function resolve(Request $request, Restaurant $restaurant): string
    {
        $offered = $restaurant->menuLocales();

        foreach ([$request->query('lang'), $request->cookie(self::COOKIE), $request->getPreferredLanguage($offered)] as $candidate) {
            if (is_string($candidate) && in_array($candidate, $offered, true)) {
                return $candidate;
            }
        }

        return $offered[0];
    }

    public function isRtl(string $locale): bool
    {
        return (bool) Language::where('code', $locale)->value('is_rtl');
    }

    /** @return array<string, string> code => native name, for the language switcher */
    public function names(Restaurant $restaurant): array
    {
        $languages = Language::whereIn('code', $restaurant->menuLocales())->get()->keyBy('code');

        return collect($restaurant->menuLocales())->mapWithKeys(fn ($code) => [$code => $languages[$code]->native_name ?? $languages[$code]->name ?? strtoupper($code)])->all();
    }
}
