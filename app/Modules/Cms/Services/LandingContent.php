<?php

namespace App\Modules\Cms\Services;

use App\Modules\Cms\Models\LandingPage;

/**
 * Landing page copy per language. Resolution: stored content for the locale, else for the app default
 * locale, else the built-in English text; each section falls back independently, so a partly
 * translated page never shows empty blocks.
 */
class LandingContent
{
    /**
     * The built-in text in the given language (see the landing_defaults keys in each language site.php); missing languages show English.
     *
     * @return array<string, mixed>
     */
    public function defaults(?string $locale = null): array
    {
        $t = fn (string $key) => __('site.landing_defaults.'.$key, [], $locale ?? app()->getLocale());

        return [
            'hero' => ['title' => $t('hero_title'), 'subtitle' => $t('hero_subtitle'), 'cta_label' => $t('hero_cta'), 'secondary_label' => $t('hero_secondary')],
            'features' => [
                ['title' => $t('feature_1_title'), 'text' => $t('feature_1_text')],
                ['title' => $t('feature_2_title'), 'text' => $t('feature_2_text')],
                ['title' => $t('feature_3_title'), 'text' => $t('feature_3_text')],
                ['title' => $t('feature_4_title'), 'text' => $t('feature_4_text')],
            ],
            'pricing' => ['title' => $t('pricing_title'), 'subtitle' => $t('pricing_subtitle')],
            'faq' => [
                ['question' => $t('faq_1_q'), 'answer' => $t('faq_1_a')],
                ['question' => $t('faq_2_q'), 'answer' => $t('faq_2_a')],
            ],
            'testimonials' => [],
            'contact' => ['title' => $t('contact_title'), 'text' => $t('contact_text'), 'email' => '', 'phone' => '', 'address' => ''],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function for(string $locale): array
    {
        $default = config('app.default_locale');
        $rows = LandingPage::whereIn('locale', array_unique([$locale, $default]))->get()->keyBy('locale');
        $content = $this->defaults($locale);

        foreach (array_unique([$default, $locale]) as $source) { // later sources win, section by section
            foreach ($rows[$source]->content ?? [] as $section => $value) {
                if ($value !== [] && $value !== null) {
                    $content[$section] = $value;
                }
            }
        }

        return $content;
    }

    /**
     * Content stored for exactly one locale (what the editor shows), falling back to the defaults.
     *
     * @return array<string, mixed>
     */
    public function editable(string $locale): array
    {
        return array_merge($this->defaults($locale), LandingPage::where('locale', $locale)->first()?->content ?? []);
    }
}
