<?php

namespace App\Modules\Cms\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Models\Plan;
use App\Modules\Cms\Models\BlogPost;
use App\Modules\Cms\Models\Page;
use App\Modules\Cms\Services\LandingContent;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Storefront\Http\Controllers\PublicMenuController;
use App\Modules\Tenancy\Models\Restaurant;
use App\Modules\Tenancy\Services\TenantResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The public marketing site: landing page, blog, static pages, sitemap. Content is shown in the
 * visitor's language and falls back to the platform's default language when it is not translated.
 */
class SiteController extends Controller
{
    public function landing(Request $request, LandingContent $landing, SettingsService $settings, TenantResolver $tenants): View|Response
    {
        // A restaurant's own domain shows its menu instead of the SaaS landing page.
        if ($restaurant = $tenants->fromHost($request)) {
            return app(PublicMenuController::class)->host($request, $restaurant);
        }

        return view('cms::site.landing', [
            'c' => $landing->for(app()->getLocale()),
            'plans' => Plan::active()->get(),
            'featured' => $this->directoryEnabled($settings) ? $this->featured(6) : collect(),
            'registration' => (bool) $settings->get('auth.registration_enabled', true),
        ]);
    }

    /** The restaurants the platform owner chose to show: premium ones first, then by the order set by hand. */
    public function directory(SettingsService $settings): View
    {
        abort_unless($this->directoryEnabled($settings), 404);

        return view('cms::site.directory', ['restaurants' => $this->featured(60)]);
    }

    private function directoryEnabled(SettingsService $settings): bool
    {
        return $settings->get('general.directory_enabled', '1') === '1';
    }

    /** @return Collection<int, Restaurant> */
    private function featured(int $limit)
    {
        return Restaurant::query()->with('logo')->where('is_featured', true)->where('status', Restaurant::STATUS_ACTIVE)->whereNotNull('onboarded_at')
            ->orderByDesc('is_premium')->orderBy('featured_order')->orderBy('id')->limit($limit)->get();
    }

    public function blog(): View
    {
        $locale = $this->contentLocale(fn ($l) => BlogPost::visible()->where('locale', $l)->exists());

        return view('cms::site.blog-index', [
            'posts' => BlogPost::visible()->where('locale', $locale)->latest('published_at')->latest('id')->paginate(9),
        ]);
    }

    public function post(string $slug): View
    {
        return view('cms::site.blog-post', ['post' => $this->findBySlug(BlogPost::visible(), $slug)]);
    }

    public function page(string $slug): View
    {
        return view('cms::site.page', ['page' => $this->findBySlug(Page::published(), $slug)]);
    }

    /** robots.txt: the admin can close the site to search engines and add lines of their own. A restaurant's own domain points at its own sitemap. */
    public function robots(Request $request, SettingsService $settings, TenantResolver $tenants): Response
    {
        $sitemap = 'Sitemap: '.url('/sitemap.xml');

        if ($tenants->fromHost($request)) {
            $body = "User-agent: *\nDisallow: /t/\n{$sitemap}\n";
        } elseif ($settings->get('seo.indexing', '1') !== '1') {
            $body = "User-agent: *\nDisallow: /\n";
        } else {
            // Staff and sign-in screens have nothing for a search engine to list.
            $private = ['admin', 'dashboard', 'orders', 'menu', 'tables', 'reports', 'customers', 'team', 'billing', 'settings', 'integrations', 'channels', 'inventory', 'delivery', 'courier', 'store', 'support', 'login', 'register', 'forgot-password', 'reset-password', 'install', 'api', 'hub', 'cron'];
            $extra = trim((string) $settings->get('seo.robots_extra', ''));
            $body = "User-agent: *\n".collect($private)->map(fn ($p) => "Disallow: /{$p}")->implode("\n")."\n".($extra !== '' ? $extra."\n" : '')."\n{$sitemap}\n";
        }

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function sitemap(Request $request, TenantResolver $tenants): Response
    {
        // A restaurant's own domain has its own sitemap.
        if ($restaurant = $tenants->fromHost($request)) {
            app(TenantContext::class)->set($restaurant);

            return app(\App\Modules\Storefront\Http\Controllers\SiteController::class)->sitemap();
        }

        $default = config('app.default_locale');
        $url = fn (string $path, string $locale) => url($path).($locale === $default ? '' : '?lang='.$locale);

        $entries = [['loc' => url('/'), 'lastmod' => null], ['loc' => url('/blog'), 'lastmod' => null]];

        if ($this->directoryEnabled(app(SettingsService::class)) && Restaurant::where('is_featured', true)->exists()) {
            $entries[] = ['loc' => url('/restaurants'), 'lastmod' => null];
        }

        foreach (BlogPost::visible()->get() as $post) {
            $entries[] = ['loc' => $url('/blog/'.$post->slug, $post->locale), 'lastmod' => $post->updated_at];
        }

        foreach (Page::published()->get() as $page) {
            $entries[] = ['loc' => $url('/p/'.$page->slug, $page->locale), 'lastmod' => $page->updated_at];
        }

        return response(view('cms::site.sitemap', ['entries' => $entries]), 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * The visitor's language if it has any content, else the default language.
     */
    private function contentLocale(callable $hasContent): string
    {
        $locale = app()->getLocale();

        return $hasContent($locale) ? $locale : config('app.default_locale');
    }

    /**
     * Find by slug in the visitor's language, then in the default language.
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<T>  $query
     * @return T
     */
    private function findBySlug($query, string $slug)
    {
        $locales = array_unique([app()->getLocale(), config('app.default_locale')]);

        return $query->clone()->where('slug', $slug)->whereIn('locale', $locales)->get()
            ->sortBy(fn ($m) => array_search($m->locale, $locales, true))->first() ?? abort(404);
    }
}
