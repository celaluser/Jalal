<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cms\Models\Page;
use App\Modules\Core\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PageController extends Controller
{
    public function index(): View
    {
        return view('admin::cms.pages-index', ['pages' => Page::orderBy('locale')->orderBy('sort')->orderBy('title')->get()]);
    }

    public function create(): View
    {
        return view('admin::cms.page-form', ['page' => new Page(['locale' => config('app.default_locale'), 'is_published' => true]), 'locales' => $this->locales()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Page::create($this->validated($request));

        return redirect()->route('admin.pages.index')->with('status', __('admin.saved'));
    }

    public function edit(Page $page): View
    {
        return view('admin::cms.page-form', ['page' => $page, 'locales' => $this->locales()]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $page->update($this->validated($request, $page));

        return redirect()->route('admin.pages.index')->with('status', __('admin.saved'));
    }

    public function destroy(Page $page): RedirectResponse
    {
        $page->delete();

        return back()->with('status', __('admin.cms.deleted'));
    }

    /** @return array<string, string> */
    private function locales(): array
    {
        return Language::active()->pluck('name', 'code')->all() ?: ['en' => 'English'];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Page $page = null): array
    {
        $locale = (string) $request->input('locale');

        $data = $request->validate([
            'locale' => ['required', 'string', 'max:12', 'exists:languages,code'],
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('pages', 'slug')->where('locale', $locale)->ignore($page?->id)],
            'body' => ['required', 'string', 'max:100000'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ]);

        return $data + ['sort' => 0, 'is_published' => $request->boolean('is_published'), 'in_footer' => $request->boolean('in_footer')];
    }
}
