<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** Languages the platform offers: which are on, which is the default, which read right-to-left. */
class LanguageController extends Controller
{
    public function index(): View
    {
        return view('admin::localization.languages', ['languages' => Language::orderBy('sort')->orderBy('code')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', 'unique:languages,code'],
            'name' => ['required', 'string', 'max:80'], 'native_name' => ['nullable', 'string', 'max:80'], 'is_rtl' => ['nullable', 'boolean'],
        ]);
        Language::create(['code' => $data['code'], 'name' => $data['name'], 'native_name' => $data['native_name'] ?? $data['name'], 'is_rtl' => $request->boolean('is_rtl'), 'is_active' => true, 'sort' => (int) Language::max('sort') + 1]);
        Cache::forget('languages.active');

        return back()->with('status', __('admin.localization.language_added'));
    }

    public function update(Request $request, Language $language): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'native_name' => ['nullable', 'string', 'max:80'], 'is_rtl' => ['nullable', 'boolean'], 'is_active' => ['nullable', 'boolean']]);

        // The default language can never be switched off: the site would have nothing to show.
        $language->update(['name' => $data['name'], 'native_name' => $data['native_name'] ?? $data['name'], 'is_rtl' => $request->boolean('is_rtl'), 'is_active' => $language->is_default ? true : $request->boolean('is_active')]);
        Cache::forget('languages.active');

        return back()->with('status', __('admin.saved'));
    }

    public function makeDefault(Language $language): RedirectResponse
    {
        Language::query()->update(['is_default' => false]);
        $language->update(['is_default' => true, 'is_active' => true]);
        Cache::forget('languages.active');

        return back()->with('status', __('admin.saved'));
    }
}
