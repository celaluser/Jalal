<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Settings\SettingsSchema;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Services\FileUploader;
use App\Modules\Core\Services\RuntimeSettings;
use App\Modules\Core\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

/**
 * One controller for every schema-driven settings screen (see SettingsSchema).
 */
class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly RuntimeSettings $runtime,
    ) {}

    public function edit(string $section): View
    {
        $schema = SettingsSchema::section($section) ?? abort(404);

        $values = [];
        $configured = [];

        foreach ($schema['fields'] as $field) {
            $stored = $this->settings->get($field['key']);
            $values[$field['name']] = $stored ?? ($field['default'] ?? null);
            $configured[$field['name']] = $stored !== null && $stored !== '';
        }

        return view('admin::settings.section', [
            'section' => $section,
            'schema' => $schema,
            'sections' => SettingsSchema::sections(),
            'values' => $values,
            'configured' => $configured,
            'options' => fn (array $field) => $this->options($field),
        ]);
    }

    public function update(Request $request, string $section): RedirectResponse
    {
        $schema = SettingsSchema::section($section) ?? abort(404);

        $rules = [];

        foreach ($schema['fields'] as $field) {
            $rules[$field['name']] = match ($field['type']) {
                'toggle' => ['nullable', 'boolean'],
                'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:2048'],
                'select' => array_merge($field['rules'] ?? [], is_string($field['options'] ?? null) ? [Rule::in(array_keys($this->options($field)))] : []),
                default => $field['rules'] ?? ['nullable', 'string', 'max:500'],
            };
        }

        $data = $request->validate($rules);

        foreach ($schema['fields'] as $field) {
            $name = $field['name'];

            switch ($field['type']) {
                case 'toggle':
                    $this->settings->set($field['key'], $request->boolean($name) ? '1' : '0');
                    break;

                case 'secret':
                    // Blank keeps the stored secret so re-saving a form never wipes API keys.
                    if (($data[$name] ?? '') !== '') {
                        $this->settings->set($field['key'], $data[$name], encrypt: true);
                    }
                    break;

                case 'image':
                    $this->saveImage($request, $field);
                    break;

                default:
                    $this->settings->set($field['key'], ($data[$name] ?? '') === '' ? null : $data[$name]);
            }
        }

        Cache::forget('languages.active');
        $this->runtime->apply();

        return back()->with('status', __('admin.saved'));
    }

    /** Sends a message through the configured SMTP settings so the admin can verify them. */
    public function testMail(Request $request): RedirectResponse
    {
        try {
            Mail::raw(__('admin.settings.mail.test_body'), fn ($m) => $m->to($request->user()->email)->subject(__('admin.settings.mail.test_subject')));
        } catch (Throwable $e) {
            return back()->withErrors(['mail' => __('admin.settings.mail.test_failed', ['error' => $e->getMessage()])]);
        }

        return back()->with('status', __('admin.settings.mail.test_sent', ['email' => $request->user()->email]));
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function saveImage(Request $request, array $field): void
    {
        $name = $field['name'];

        if ($request->boolean('remove_'.$name)) {
            $this->settings->set($field['key'], null);
        }

        if (! $request->hasFile($name)) {
            return;
        }

        try {
            $media = app(FileUploader::class)->store($request->file($name), 'branding');
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        $this->settings->set($field['key'], Storage::disk($media->disk)->url($media->path));
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, string>
     */
    private function options(array $field): array
    {
        return match ($field['options'] ?? null) {
            'languages' => Language::active()->pluck('name', 'code')->all(),
            'currencies' => Currency::where('is_active', true)->pluck('code', 'code')->all(),
            'timezones' => SettingsSchema::timezones(),
            default => is_array($field['options'] ?? null) ? $field['options'] : [],
        };
    }
}
