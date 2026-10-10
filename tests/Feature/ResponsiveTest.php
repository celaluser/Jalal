<?php

// The phone layout is checked in a real browser (see docs/DEVELOPER.md); these guard the rules that keep it working.

it('keeps grids from widening the page and gives touch screens bigger targets', function () {
    $css = file_get_contents(base_path('resources/css/app.css'));

    expect($css)->toContain('.grid { grid-template-columns: minmax(0, 1fr); }')
        ->toContain('@media (pointer: coarse)')
        ->toContain(':where(.btn-sm) { min-height: 2.5rem;');

    // The built stylesheet that ships must contain them too (the zip has no node).
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $built = file_get_contents(public_path('build/'.$manifest['resources/css/app.css']['file']));
    expect($built)->toContain('minmax(0,1fr)')->toContain('pointer:coarse');
});

it('puts the language buttons in the user menu on phones and keeps every layout viewport-friendly', function () {
    // Every layout is built on base.blade.php, which sets the viewport.
    expect(file_get_contents(resource_path('views/components/layouts/base.blade.php')))->toContain('width=device-width');

    $shell = file_get_contents(resource_path('views/components/layouts/shell.blade.php'));
    expect($shell)->toContain('hidden sm:block')->toContain('sm:hidden');
});

it('ships a root .htaccess and index.php so a package uploaded into public_html still works and stays closed', function () {
    $htaccess = file_get_contents(base_path('.htaccess'));
    expect($htaccess)->toContain('public/$1')->toContain('Options -Indexes')->toContain('vendor')->toContain('Require all denied');
    expect(file_get_contents(base_path('index.php')))->toContain('public/index.php');

    foreach (['app', 'bootstrap', 'config', 'database', 'lang', 'resources', 'routes', 'storage'] as $dir) {
        expect(file_exists(base_path($dir.'/.htaccess')))->toBeTrue("{$dir}/.htaccess");
    }
});
