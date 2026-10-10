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
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'hero' => [
                'title' => 'Your menu, one scan away',
                'subtitle' => 'Create a beautiful QR menu, take orders from every table and grow your restaurant — in minutes, without an app.',
                'cta_label' => 'Start free trial',
                'secondary_label' => 'See pricing',
            ],
            'features' => [
                ['title' => 'QR menus that update instantly', 'text' => 'Change prices and dishes once; every table sees it immediately.'],
                ['title' => 'Orders from the table', 'text' => 'Guests order and pay from their phone, the kitchen gets tickets in real time.'],
                ['title' => 'Multi-language & multi-currency', 'text' => 'Serve tourists in their own language, with right-to-left support.'],
                ['title' => 'AI menu import', 'text' => 'Photograph your paper menu and let AI build the digital one.'],
            ],
            'pricing' => ['title' => 'Simple, honest pricing', 'subtitle' => 'Start with a free trial. Upgrade when you grow.'],
            'faq' => [
                ['question' => 'Do my guests need to install an app?', 'answer' => 'No. They scan the QR code and the menu opens in their browser.'],
                ['question' => 'Can I cancel at any time?', 'answer' => 'Yes. Your plan stays active until the end of the period you paid for.'],
            ],
            'testimonials' => [],
            'contact' => ['title' => 'Questions? Talk to us', 'text' => 'We usually reply within one business day.', 'email' => '', 'phone' => '', 'address' => ''],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function for(string $locale): array
    {
        $default = config('app.default_locale');
        $rows = LandingPage::whereIn('locale', array_unique([$locale, $default]))->get()->keyBy('locale');
        $content = $this->defaults();

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
        return array_merge($this->defaults(), LandingPage::where('locale', $locale)->first()?->content ?? []);
    }
}
