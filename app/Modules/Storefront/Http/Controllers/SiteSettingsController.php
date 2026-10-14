<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Services\FileUploader;
use App\Modules\Storefront\Services\SiteBuilder;
use App\Modules\Storefront\Services\SiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/** Panel page for the mini website and search-engine options. */
class SiteSettingsController extends Controller
{
    public function __construct(private readonly SiteSettings $site, private readonly SiteBuilder $builder) {}

    public function edit(Request $request): View
    {
        $restaurant = $request->user()->restaurant;

        $s = $this->site->for($restaurant);

        return view('storefront::admin.site', ['restaurant' => $restaurant, 's' => $s, 'locales' => $restaurant->menuLocales(), 'urls' => $this->builder->urls($s)]);
    }

    public function update(Request $request): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;
        $this->site->save($restaurant, $request->validate($this->site->rules()));

        return back()->with('status', __('admin.saved'));
    }

    /** A picture for a section or the gallery. Answers with its id and address for the editor. */
    public function media(Request $request, FileUploader $uploader): JsonResponse
    {
        $request->validate(['image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192']]);

        try {
            $media = $uploader->store($request->file('image'), 'site');
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['id' => $media->id, 'url' => $media->url()], 201);
    }
}
