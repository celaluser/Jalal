<?php

namespace App\Modules\Core\Mail;

use App\Modules\Core\Models\EmailTemplate;
use Illuminate\Support\Str;

/**
 * Resolves a template (admin override for the locale, else the English override, else the built-in
 * default) and renders it. Safety: the admin's text goes through Markdown with raw HTML stripped,
 * and variable values are inserted afterwards, HTML-escaped, so user-supplied names cannot inject
 * markup or links into the e-mail.
 */
class EmailTemplateRenderer
{
    /**
     * @param  array<string, scalar|null>  $vars
     * @return array{subject: string, html: string}|null null when the template is switched off
     */
    public function render(string $key, array $vars, ?string $locale = null, ?array $override = null): ?array
    {
        $definition = EmailTemplateRegistry::find($key) ?? throw new \InvalidArgumentException("Unknown e-mail template [{$key}].");

        $row = $override ?? $this->row($key, $locale ?? config('app.default_locale'));

        if ($row !== null && ! $definition['required'] && ! ($row['is_active'] ?? true)) {
            return null;
        }

        $subject = $row['subject'] ?? $definition['subject'];
        $body = $row['body'] ?? $definition['body'];
        $vars = ['app_name' => config('app.name')] + $vars;

        $html = Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        return [
            'subject' => str_replace(["\r", "\n"], ' ', $this->replace($subject, $vars, escape: false)),
            'html' => $this->replace($html, $vars, escape: true),
        ];
    }

    /**
     * @return array{subject: string, body: string, is_active: bool}|null
     */
    private function row(string $key, string $locale): ?array
    {
        $row = EmailTemplate::where('key', $key)->whereIn('locale', array_unique([$locale, 'en']))->get()
            ->sortBy(fn ($r) => $r->locale === $locale ? 0 : 1)->first();

        return $row ? ['subject' => $row->subject, 'body' => $row->body, 'is_active' => $row->is_active] : null;
    }

    /**
     * Replaces {{name}} tokens. CommonMark percent-encodes braces inside link destinations, so the
     * encoded form is handled too.
     *
     * @param  array<string, scalar|null>  $vars
     */
    private function replace(string $text, array $vars, bool $escape): string
    {
        $value = fn (string $name) => $escape ? e((string) ($vars[$name] ?? '')) : (string) ($vars[$name] ?? '');

        $text = preg_replace_callback('/%7B%7B([a-z_]+)%7D%7D/i', fn ($m) => $value($m[1]), $text);

        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/i', fn ($m) => $value($m[1]), $text);
    }
}
