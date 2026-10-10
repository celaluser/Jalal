<?php

namespace App\Modules\Core\Models\Concerns;

/**
 * Per-language text stored as JSON ({"en": "Soup", "tr": "Çorba"}) in the attributes listed in
 * $translatable (each must be cast to "array"). Read with tr('name'); it falls back to the
 * restaurant's default language and then to any language that has text, so a half-translated
 * menu never shows blanks.
 */
trait HasTranslations
{
    public function tr(string $attribute, ?string $locale = null, ?string $default = null): string
    {
        $values = array_filter((array) ($this->{$attribute} ?? []), fn ($v) => is_string($v) && trim($v) !== '');

        // The restaurant's language is only looked up when nothing else matched and the caller gave no default:
        // loading it for every dish of a menu would cost one query per dish.
        foreach ([$locale ?? app()->getLocale(), $default] as $candidate) {
            if ($candidate !== null && isset($values[$candidate])) {
                return $values[$candidate];
            }
        }

        if ($default === null && $values !== [] && ($own = $this->restaurant?->locale) !== null && isset($values[$own])) {
            return $values[$own];
        }

        return (string) (reset($values) ?: '');
    }

    /**
     * Clean submitted translations: trim, drop empty and unknown languages.
     *
     * @param  array<string, mixed>  $input
     * @param  list<string>  $locales
     * @return array<string, string>
     */
    public static function cleanTranslations(array $input, array $locales): array
    {
        $clean = [];

        foreach ($locales as $locale) {
            $text = isset($input[$locale]) && is_string($input[$locale]) ? trim($input[$locale]) : '';

            if ($text !== '') {
                $clean[$locale] = $text;
            }
        }

        return $clean;
    }
}
