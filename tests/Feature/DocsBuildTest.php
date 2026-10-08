<?php

use Illuminate\Support\Facades\File;

it('builds the HTML manual from the Markdown guides, stripping raw HTML', function () {
    $out = sys_get_temp_dir().'/docs-'.uniqid();
    $this->artisan('docs:build', ['--out' => $out])->assertSuccessful();

    expect(is_file($out.'/index.html'))->toBeTrue()->and(is_file($out.'/installation.html'))->toBeTrue()->and(is_file($out.'/api.html'))->toBeTrue();
    $html = file_get_contents($out.'/installation.html');
    expect($html)->toContain('<h1>Installation</h1>')->toContain('<nav>')->toContain('href="api.html"')->not->toContain('<script');
    File::deleteDirectory($out);
});

it('lists every bundled PHP package with its licence', function () {
    $this->artisan('docs:licenses')->assertSuccessful();
    $md = file_get_contents(base_path('docs/THIRD_PARTY_LICENSES.md'));
    expect($md)->toContain('laravel/framework')->toContain('LGPL packages')->not->toContain('| unknown |');
});

it('keeps the guides that the manual links to', function () {
    foreach (array_keys(\App\Console\Commands\BuildDocs::PAGES) as $file) {
        expect(is_file($file === 'CHANGELOG.md' ? base_path($file) : base_path('docs/'.$file)))->toBeTrue($file);
    }
});
