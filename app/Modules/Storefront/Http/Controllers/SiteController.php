<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Marketing\Services\MarketingSettings;
use App\Modules\Marketing\Services\ReviewService;
use App\Modules\Storefront\Services\MenuLocale;
use App\Modules\Storefront\Services\SiteSettings;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** The restaurant's public mini website (About page) and its sitemap. */
class SiteController extends Controller
{
    public function __construct(private readonly TenantContext $tenant, private readonly SiteSettings $site, private readonly MenuLocale $locales) {}

    public function about(Request $request): View
    {
        $restaurant = $this->tenant->get();
        abort_unless($this->site->for($restaurant)['site_enabled'], 404);
        $locale = $this->locales->resolve($request, $restaurant);
        app()->setLocale($locale);
        $s = $this->site->for($restaurant);
        $marketing = app(MarketingSettings::class)->for($restaurant);
        $rating = $marketing['show_rating'] ? app(ReviewService::class)->summary(public: true) : null;
        $rating = $rating && $rating['count'] >= 3 ? ['average' => $rating['average'], 'count' => $rating['count']] : null;

        return view('storefront::about', [
            'restaurant' => $restaurant, 'locale' => $locale, 'dir' => $this->locales->isRtl($locale) ? 'rtl' : 'ltr', 's' => $s, 'marketing' => $marketing, 'rating' => $rating,
            'about' => $this->site->text($restaurant, 'about', $locale), 'logo' => $restaurant->logo?->url(),
            'title' => $this->site->text($restaurant, 'seo_title', $locale) ?: $restaurant->name,
            'description' => $this->site->text($restaurant, 'seo_description', $locale) ?: __('customer.meta_description', ['name' => $restaurant->name]),
            'jsonLd' => $this->site->jsonLd($restaurant, $locale, $restaurant->logo?->url(), $rating),
        ]);
    }

    /** sitemap.xml with the menu (in every language) and the About page. Empty of private pages: tables, orders and accounts never appear. */
    public function sitemap(): Response
    {
        $restaurant = $this->tenant->get();
        $s = $this->site->for($restaurant);
        $urls = $s['indexable'] ? [$restaurant->publicUrl()] : [];

        if ($s['indexable'] && $s['site_enabled']) {
            $urls[] = $restaurant->publicUrl('about');
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";

        foreach ($urls as $url) {
            $xml .= '  <url><loc>'.e($url).'</loc>';

            foreach ($restaurant->menuLocales() as $code) {
                $xml .= '<xhtml:link rel="alternate" hreflang="'.e($code).'" href="'.e($url.'?lang='.$code).'"/>';
            }

            $xml .= '</url>'."\n";
        }

        return response($xml.'</urlset>', 200, ['Content-Type' => 'application/xml; charset=utf-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
