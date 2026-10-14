<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Turns the Markdown guides in docs/ into a small offline HTML manual in docs/html/ (open index.html in a browser).
 * The pages are self-contained: one stylesheet, no scripts, light and dark, readable on a phone.
 */
class BuildDocs extends Command
{
    protected $signature = 'docs:build {--out= : Output folder (default docs/html)}';

    protected $description = 'Build the HTML manual from docs/*.md';

    /** @var array<string, string> file => menu title, in reading order */
    public const PAGES = [
        'INSTALLATION.md' => 'Installation', 'USER_GUIDE.md' => 'Restaurant owner guide', 'ADMIN_GUIDE.md' => 'Platform admin guide', 'UPDATING.md' => 'Updating',
        'API.md' => 'REST API & webhooks', 'ADDONS.md' => 'Add-ons', 'DEVELOPER.md' => 'Developer guide', 'TROUBLESHOOTING.md' => 'Troubleshooting',
        'FEATURE_STATUS.md' => 'Feature status', 'THIRD_PARTY_LICENSES.md' => 'Third-party licences', 'CHANGELOG.md' => 'Changelog',
    ];

    public function handle(): int
    {
        $out = $this->option('out') ?: base_path('docs/html');
        File::ensureDirectoryExists($out);
        $built = [];

        foreach (self::PAGES as $file => $title) {
            $path = $file === 'CHANGELOG.md' ? base_path($file) : base_path('docs/'.$file);

            if (is_file($path)) {
                $built[Str::slug(pathinfo($file, PATHINFO_FILENAME)).'.html'] = [$title, Str::markdown((string) file_get_contents($path), ['html_input' => 'strip', 'allow_unsafe_links' => false])];
            }
        }

        $menu = '<nav><strong>'.e(config('app.name')).'</strong>'.collect($built)->map(fn ($p, $f) => '<a href="'.$f.'">'.e($p[0]).'</a>')->implode('').'</nav>';

        foreach ($built as $file => [$title, $html]) {
            File::put($out.'/'.$file, $this->page($title, $menu, $html));
        }

        File::put($out.'/index.html', $this->page(config('app.name').' documentation', $menu, '<h1>'.e(config('app.name')).' documentation</h1><p>Choose a guide from the menu.</p><ul>'
            .collect($built)->map(fn ($p, $f) => '<li><a href="'.$f.'">'.e($p[0]).'</a></li>')->implode('').'</ul>'));

        $this->info('Built '.(count($built) + 1).' page(s) in '.$out);

        return self::SUCCESS;
    }

    private function page(string $title, string $menu, string $body): string
    {
        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'.e($title).'</title><style>'
            .':root{color-scheme:light dark;--bg:#fff;--fg:#1c1917;--muted:#57534e;--line:#e7e5e4;--code:#f5f5f4;--link:#0f766e}@media(prefers-color-scheme:dark){:root{--bg:#151413;--fg:#f3f1ee;--muted:#a8a29e;--line:#2b2927;--code:#211f1d;--link:#5eead4}}'
            .'body{margin:0;font:16px/1.65 system-ui,sans-serif;background:var(--bg);color:var(--fg);display:flex;flex-wrap:wrap}nav{flex:0 0 15rem;padding:1.25rem;border-inline-end:1px solid var(--line);box-sizing:border-box}'
            .'nav a{display:block;padding:.3rem 0;color:var(--link);text-decoration:none}nav strong{display:block;margin-bottom:.75rem}main{flex:1 1 30rem;min-width:0;max-width:52rem;padding:1.5rem 1.5rem 4rem;box-sizing:border-box}'
            .'a{color:var(--link)}code,pre{background:var(--code);border-radius:.4rem;font-size:.9em}code{padding:.1rem .3rem}pre{padding:.85rem;overflow-x:auto}pre code{padding:0}'
            .'table{border-collapse:collapse;display:block;overflow-x:auto}td,th{border:1px solid var(--line);padding:.4rem .6rem;text-align:start}h1,h2,h3{line-height:1.25}img{max-width:100%}'
            .'@media(max-width:700px){nav{flex-basis:100%;border:0;border-bottom:1px solid var(--line)}}</style></head><body>'.$menu.'<main>'.$body.'</main></body></html>';
    }
}
