<?php

use Illuminate\Support\Arr;

/** The staff panel is translated, not only the guest menu: every English text has a counterpart. */
it('translates every panel text into :locale with the same placeholders', function (string $locale) {
    foreach (glob(base_path('lang/en/*.php')) as $path) {
        $file = basename($path);
        $en = Arr::dot(require $path);
        $other = Arr::dot(file_exists(base_path("lang/{$locale}/{$file}")) ? require base_path("lang/{$locale}/{$file}") : []);

        expect(array_keys(array_diff_key($en, $other)))->toBe([], "{$locale}/{$file} misses texts");

        foreach ($en as $key => $text) {
            if (! is_string($text) || ! is_string($other[$key] ?? null)) {
                continue;
            }

            preg_match_all('/(?<![A-Za-z0-9]):[a-z_]+/', $text, $a);
            preg_match_all('/(?<![A-Za-z0-9]):[a-z_]+/', $other[$key], $b);
            expect(array_values(array_unique($b[0])))->toEqualCanonicalizing(array_values(array_unique($a[0])), "placeholders differ in {$locale}/{$file} {$key}");
        }
    }
})->with(['tr', 'az', 'ru', 'ar']);
