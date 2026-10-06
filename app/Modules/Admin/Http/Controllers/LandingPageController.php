<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cms\Models\LandingPage;
use App\Modules\Cms\Services\LandingContent;
use App\Modules\Core\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandingPageController extends Controller
{
    public function edit(Request $request, LandingContent $landing): View
    {
        $locales = Language::active()->pluck('name', 'code')->all() ?: ['en' => 'English'];
        $locale = array_key_exists((string) $request->query('locale'), $locales) ? $request->query('locale') : array_key_first($locales);

        return view('admin::cms.landing', [
            'locales' => $locales,
            'locale' => $locale,
            'c' => $landing->editable($locale),
            'translated' => LandingPage::where('locale', $locale)->exists(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', 'max:12', 'exists:languages,code'],
            'hero.title' => ['required', 'string', 'max:150'],
            'hero.subtitle' => ['nullable', 'string', 'max:400'],
            'hero.cta_label' => ['required', 'string', 'max:50'],
            'hero.secondary_label' => ['nullable', 'string', 'max:50'],
            'features' => ['nullable', 'array', 'max:12'],
            'features.*.title' => ['nullable', 'string', 'max:100'],
            'features.*.text' => ['nullable', 'string', 'max:300'],
            'pricing.title' => ['required', 'string', 'max:120'],
            'pricing.subtitle' => ['nullable', 'string', 'max:300'],
            'faq' => ['nullable', 'array', 'max:20'],
            'faq.*.question' => ['nullable', 'string', 'max:200'],
            'faq.*.answer' => ['nullable', 'string', 'max:1000'],
            'testimonials' => ['nullable', 'array', 'max:12'],
            'testimonials.*.name' => ['nullable', 'string', 'max:80'],
            'testimonials.*.role' => ['nullable', 'string', 'max:80'],
            'testimonials.*.quote' => ['nullable', 'string', 'max:400'],
            'contact.title' => ['nullable', 'string', 'max:120'],
            'contact.text' => ['nullable', 'string', 'max:300'],
            'contact.email' => ['nullable', 'email', 'max:190'],
            'contact.phone' => ['nullable', 'string', 'max:40'],
            'contact.address' => ['nullable', 'string', 'max:300'],
        ]);

        $content = [
            'hero' => $data['hero'] + ['subtitle' => '', 'secondary_label' => ''],
            'pricing' => $data['pricing'] + ['subtitle' => ''],
            'contact' => ($data['contact'] ?? []) + ['title' => '', 'text' => '', 'email' => '', 'phone' => '', 'address' => ''],
            // Rows are added in the browser; half-filled and empty ones are dropped here.
            'features' => $this->rows($data['features'] ?? [], ['title', 'text'], ['title', 'text']),
            'faq' => $this->rows($data['faq'] ?? [], ['question', 'answer'], ['question', 'answer']),
            'testimonials' => $this->rows($data['testimonials'] ?? [], ['name', 'role', 'quote'], ['name', 'quote']),
        ];

        LandingPage::updateOrCreate(['locale' => $data['locale']], ['content' => $content]);

        return redirect()->route('admin.landing.edit', ['locale' => $data['locale']])->with('status', __('admin.saved'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  list<string>  $keys  fields to keep
     * @param  list<string>  $required  fields that must be filled for the row to count
     * @return list<array<string, string>>
     */
    private function rows(array $rows, array $keys, array $required): array
    {
        $clean = [];

        foreach ($rows as $row) {
            $item = [];
            foreach ($keys as $key) {
                $item[$key] = trim((string) ($row[$key] ?? ''));
            }

            if (collect($required)->every(fn ($key) => $item[$key] !== '')) {
                $clean[] = $item;
            }
        }

        return $clean;
    }
}
