<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Models\Banner;
use App\Modules\Menu\Services\MenuImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Banners and pop-ups on the guest menu: a new dish, a happy hour, a holiday notice. */
class BannerController extends Controller
{
    public function __construct(private readonly MenuImage $images) {}

    public function index(): View
    {
        return view('marketing::banners.index', ['banners' => Banner::with('image')->orderBy('sort')->orderByDesc('id')->get()]);
    }

    public function create(Request $request): View
    {
        return view('marketing::banners.form', ['banner' => new Banner(['is_active' => true]), 'locales' => $request->user()->restaurant->menuLocales()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $fields = $this->validated($request);
        Banner::create($fields + ['sort' => (int) Banner::max('sort') + 1, 'image_media_id' => $this->images->sync(null, $request->file('image'), false) ?: null]);

        return redirect()->route('banners.index')->with('status', __('marketing.banner_saved'));
    }

    public function edit(Request $request, Banner $banner): View
    {
        return view('marketing::banners.form', ['banner' => $banner, 'locales' => $request->user()->restaurant->menuLocales()]);
    }

    public function update(Request $request, Banner $banner): RedirectResponse
    {
        $fields = $this->validated($request);
        $image = $this->images->sync($banner->image_media_id, $request->file('image'), $request->boolean('remove_image'));

        if ($image !== false) {
            $fields['image_media_id'] = $image;
        }

        $banner->update($fields);

        return redirect()->route('banners.index')->with('status', __('marketing.banner_saved'));
    }

    public function destroy(Banner $banner): RedirectResponse
    {
        $this->images->sync($banner->image_media_id, null, true);
        $banner->delete();

        return redirect()->route('banners.index')->with('status', __('marketing.banner_deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $locales = $request->user()->restaurant->menuLocales();
        $request->validate([
            'title' => ['required', 'array'], "title.{$locales[0]}" => ['required', 'string', 'max:100'], 'title.*' => ['nullable', 'string', 'max:100'],
            'text' => ['nullable', 'array'], 'text.*' => ['nullable', 'string', 'max:300'],
            'button' => ['nullable', 'array'], 'button.*' => ['nullable', 'string', 'max:30'],
            // https links, or a path on this site (for example the menu section to open).
            'link_url' => ['nullable', 'string', 'max:500', 'regex:/^(https:\/\/[^\s]+|\/[^\s\/][^\s]*|#[A-Za-z0-9_-]+)$/'],
            'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);

        return [
            'title' => Banner::cleanTranslations($request->input('title', []), $locales),
            'text' => Banner::cleanTranslations($request->input('text', []), $locales) ?: null,
            'button' => Banner::cleanTranslations($request->input('button', []), $locales) ?: null,
            'link_url' => $request->filled('link_url') ? trim($request->input('link_url')) : null,
            'starts_on' => $request->input('starts_on') ?: null, 'ends_on' => $request->input('ends_on') ?: null,
            'is_popup' => $request->boolean('is_popup'), 'is_active' => $request->boolean('is_active'),
        ];
    }
}
