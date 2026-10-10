<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Menu\Services\ThemeLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The super admin's theme studio: edit, switch off, reset and create the themes restaurants choose for their menu. */
class MenuThemeController extends Controller
{
    public function __construct(private readonly ThemeLibrary $themes) {}

    public function index(): View
    {
        return view('admin::themes.index', ['themes' => $this->themes->all(), 'default' => $this->themes->defaultKey()]);
    }

    public function create(Request $request): View
    {
        // A copy of an existing theme is the easiest start.
        $from = $this->themes->all()[$request->query('from')] ?? $this->themes->all()[$this->themes->defaultKey()];

        return view('admin::themes.form', ['theme' => ['key' => null, 'builtin' => false] + array_merge($from, ['name' => $from['name'].' 2'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if(count($this->themes->all()) >= 60, 422);
        $key = $this->themes->create($request->validate($this->themes->rules()));

        return redirect()->route('admin.themes.index')->with('status', __('admin.themes.saved', ['name' => $this->themes->all()[$key]['name']]));
    }

    public function edit(string $theme): View
    {
        return view('admin::themes.form', ['theme' => $this->find($theme)]);
    }

    public function update(Request $request, string $theme): RedirectResponse
    {
        $this->find($theme);
        $this->themes->save($theme, $request->validate($this->themes->rules()));

        return redirect()->route('admin.themes.index')->with('status', __('admin.themes.saved', ['name' => $this->themes->all()[$theme]['name']]));
    }

    public function toggle(string $theme): RedirectResponse
    {
        $this->find($theme);
        $this->themes->toggle($theme);

        return back()->with('status', __('admin.saved'));
    }

    public function makeDefault(string $theme): RedirectResponse
    {
        $this->find($theme);
        $this->themes->setDefault($theme);

        return back()->with('status', __('admin.saved'));
    }

    /** Built-in themes go back to how they shipped; custom ones are deleted. */
    public function destroy(string $theme): RedirectResponse
    {
        $this->find($theme);
        $this->themes->reset($theme);

        return redirect()->route('admin.themes.index')->with('status', __('admin.saved'));
    }

    /** @return array<string, mixed> */
    private function find(string $key): array
    {
        return $this->themes->all()[$key] ?? abort(404);
    }
}
