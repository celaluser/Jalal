<?php

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

/*
| Guest "crawl": every route that has no URL parameter is requested without signing in. Anything that answers 200
| must be on the list of pages that are meant to be public; everything else has to redirect to login or refuse.
| A new route that forgets its auth middleware shows up here.
*/

function guestRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $r) => ! str_contains($r->uri(), '{') && ! str_starts_with($r->uri(), '_') && ! str_starts_with($r->uri(), 'storage/') && $r->uri() !== 'up')
        ->flatMap(fn (LaravelRoute $r) => collect($r->methods())->reject(fn ($m) => $m === 'HEAD')->map(fn ($m) => [$m, '/'.ltrim($r->uri(), '/')]))
        ->unique(fn ($x) => $x[0].$x[1])
        ->values()
        ->all();
}

it('does not serve panel pages or accept panel actions from a guest', function () {
    // Pages and actions that are meant to work without signing in (guests of a restaurant, the website, the installer).
    $public = '#^/($|login|register|forgot-password|reset-password|two-factor-challenge|auth/|blog|about|restaurants$|up$|install|sw\.js|manifest|staff\.webmanifest|robots\.txt|sitemap\.xml|widget\.js|cart|order|reserve|offers|request|tab|reviews|links|tablet-mode|account|email/verify|logout|api/)#';
    $exposed = [];
    $checked = 0;

    foreach (guestRoutes() as [$method, $uri]) {
        if (preg_match($public, $uri) === 1) {
            continue;
        }

        $checked++;
        $status = $this->call($method, $uri)->getStatusCode();

        if ($status < 300 || $status >= 500) {
            $exposed[] = "$method $uri → $status";
        }
    }

    expect($checked)->toBeGreaterThan(150)->and($exposed)->toBe([]);
});

it('keeps the storage folder closed but lets public uploads through when the whole package sits in public_html', function () {
    $htaccess = file_get_contents(base_path('storage/.htaccess'));

    // Closed by default, with the real private folders named so no rule can ever open them.
    expect($htaccess)->toContain('Require all denied')->toContain('(app|framework|logs|private)')
        // The address /storage/... of the public disk is the only thing let through (Apache checks this file before the root rewrite).
        ->toContain('Require all granted')->toContain('#/storage/#i');
});

it('does not ship uploaded or test files in the release package', function () {
    $script = file_get_contents(base_path('docs/tools/build-release.sh'));

    expect($script)->toContain("--exclude='./storage/app/public/*'")->toContain("--exclude='./storage/framework/testing'")->toContain('test_files');
});
