<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Models\Translation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

/**
 * Edit any text of the script without touching files: pick a language and a file, change a line, save.
 * Changes are stored as overrides on top of the files (so updates never overwrite them) and can be reset.
 */
class TranslationController extends Controller
{
    private const PER_PAGE = 40;

    public function index(Request $request): View
    {
        $locale = $this->locale($request->query('locale'));
        $group = $this->group($request->query('group'));
        $q = trim((string) $request->query('q'));

        $base = $this->lines('en', $group);
        $own = $this->lines($locale, $group);
        $overrides = Translation::where('locale', $locale)->where('group', $group)->pluck('value', 'key')->all();

        $rows = collect($base)->map(fn ($english, $key) => ['key' => $key, 'english' => $english, 'file' => $own[$key] ?? '', 'value' => $overrides[$key] ?? ($own[$key] ?? ''), 'overridden' => array_key_exists($key, $overrides)])
            ->when($q !== '', fn ($c) => $c->filter(fn ($r) => stripos($r['key'], $q) !== false || stripos($r['english'], $q) !== false || stripos($r['value'], $q) !== false))
            ->values();

        $page = max(1, (int) $request->query('page', 1));

        return view('admin::localization.translations', [
            'languages' => Language::orderBy('code')->get(), 'groups' => $this->groups(), 'locale' => $locale, 'group' => $group, 'q' => $q,
            'rows' => $rows->forPage($page, self::PER_PAGE), 'total' => $rows->count(), 'page' => $page, 'pages' => (int) ceil($rows->count() / self::PER_PAGE),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $locale = $this->locale($request->input('locale'));
        $group = $this->group($request->input('group'));
        $data = $request->validate(['rows' => ['required', 'array', 'max:200'], 'rows.*' => ['nullable', 'string', 'max:5000']]);
        $known = $this->lines('en', $group);
        $file = $this->lines($locale, $group);

        foreach ($data['rows'] as $key => $value) {
            if (! array_key_exists($key, $known)) {
                continue; // only real keys of this file can be written
            }

            $value = (string) $value;

            // Empty, or the same as the file: no override is needed (and an old one is removed).
            if ($value === '' || $value === ($file[$key] ?? null)) {
                Translation::where(['locale' => $locale, 'group' => $group, 'key' => $key])->delete();

                continue;
            }

            Translation::updateOrCreate(['locale' => $locale, 'group' => $group, 'key' => $key], ['value' => $value]);
        }

        return back()->with('status', __('admin.saved'));
    }

    public function reset(Request $request): RedirectResponse
    {
        Translation::where('locale', $this->locale($request->input('locale')))->where('group', $this->group($request->input('group')))->delete();

        return back()->with('status', __('admin.localization.reset_done'));
    }

    /** @return array<string, string> flat dotted keys of a language file (strings only) */
    private function lines(string $locale, string $group): array
    {
        $path = lang_path("{$locale}/{$group}.php");

        return is_file($path) ? array_filter(Arr::dot(require $path), 'is_string') : [];
    }

    /** @return list<string> */
    private function groups(): array
    {
        return collect(glob(lang_path('en/*.php')))->map(fn ($f) => basename($f, '.php'))->reject(fn ($g) => in_array($g, ['validation', 'pagination', 'passwords'], true))->sort()->values()->all();
    }

    private function group(?string $group): string
    {
        return in_array($group, $this->groups(), true) ? $group : 'customer';
    }

    private function locale(?string $locale): string
    {
        return Language::where('code', $locale)->exists() ? $locale : (string) (Language::where('is_default', true)->value('code') ?? 'en');
    }
}
