<?php

namespace App\Modules\Cms\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Models\Plan;
use App\Modules\Cms\Models\BlogPost;
use App\Modules\Cms\Models\Page;
use App\Modules\Cms\Services\LandingContent;
use App\Modules\Core\Services\SettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The public marketing site: landing page, blog, static pages, sitemap. Content is shown in the
 * visitor's language and falls back to the platform's default language when it is not translated.
 */
class SiteController extends Controller
{
    public function landing(LandingContent $landing, SettingsService $settings): View
    {
        return view('cms::site.landing', [
            'c' => $landing->for(app()->getLocale()),
            'plans' => Plan::active()->get(),
            'registration' => (bool) $settings->get('auth.registration_enabled', true),
        ]);
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

    public function sitemap(): Response
    {
        $default = config('app.default_locale');
        $url = fn (string $path, string $locale) => url($path).($locale === $default ? '' : '?lang='.$locale);

        $entries = [['loc' => url('/'), 'lastmod' => null], ['loc' => url('/blog'), 'lastmod' => null]];

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
