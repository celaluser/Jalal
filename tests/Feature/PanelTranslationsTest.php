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
})->with(['tr', 'az', 'ru', 'ar', 'es', 'pt', 'fr', 'de', 'it', 'hi', 'id', 'zh']);

/** A screen must never show a raw key such as "admin.actions": every literal key used in the code exists in English. */
it('has an English text for every translation key the code uses', function () {
    $keys = [];
    foreach (glob(base_path('lang/en/*.php')) as $path) {
        foreach (Arr::dot(require $path) as $key => $text) {
            $keys[basename($path, '.php').'.'.$key] = true;
        }
    }

    $missing = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

    foreach ($files as $file) {
        if (! str_ends_with((string) $file, '.php')) {
            continue;
        }

        preg_match_all("/(?<![A-Za-z_>])(?:__|trans|trans_choice)\(\s*'([a-z_]+(?:\.[A-Za-z0-9_\-]+)+)'/", (string) file_get_contents((string) $file), $m);

        foreach ($m[1] as $key) {
            // A key ending in "_" or "." is a prefix completed at run time (for example 'orders.status_'.$status).
            if (str_ends_with($key, '_') || isset($keys[$key]) || array_filter(array_keys($keys), fn ($k) => str_starts_with($k, $key.'.'))) {
                continue;
            }

            $missing[$key] = true;
        }
    }

    expect(array_keys($missing))->toBe([]);
});
