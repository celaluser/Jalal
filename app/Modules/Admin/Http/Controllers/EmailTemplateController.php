<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Mail\EmailTemplateRegistry;
use App\Modules\Core\Mail\EmailTemplateRenderer;
use App\Modules\Core\Mail\SafeMail;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Core\Models\EmailTemplate;
use App\Modules\Core\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class EmailTemplateController extends Controller
{
    public function index(): View
    {
        return view('admin::email-templates.index', [
            'templates' => EmailTemplateRegistry::all(),
            'customised' => EmailTemplate::all()->groupBy('key')->map(fn ($rows) => $rows->pluck('locale')->all()),
        ]);
    }

    public function edit(Request $request, string $key): View
    {
        $definition = EmailTemplateRegistry::find($key) ?? abort(404);
        $locales = Language::active()->pluck('name', 'code')->all() ?: ['en' => 'English'];
        $locale = array_key_exists((string) $request->query('locale'), $locales) ? $request->query('locale') : array_key_first($locales);
        $row = EmailTemplate::where(['key' => $key, 'locale' => $locale])->first();

        return view('admin::email-templates.edit', [
            'key' => $key,
            'definition' => $definition,
            'locales' => $locales,
            'locale' => $locale,
            'subject' => old('subject', $row?->subject ?? $definition['subject']),
            'body' => old('body', $row?->body ?? $definition['body']),
            'active' => $row?->is_active ?? true,
            'customised' => $row !== null,
        ]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        $definition = EmailTemplateRegistry::find($key) ?? abort(404);
        $data = $this->validated($request);

        EmailTemplate::updateOrCreate(
            ['key' => $key, 'locale' => $data['locale']],
            ['subject' => $data['subject'], 'body' => $data['body'], 'is_active' => $definition['required'] ? true : $request->boolean('is_active')]
        );

        return redirect()->route('admin.email-templates.edit', [$key, 'locale' => $data['locale']])->with('status', __('admin.saved'));
    }

    /** Back to the built-in text for one language. */
    public function reset(Request $request, string $key): RedirectResponse
    {
        EmailTemplateRegistry::find($key) ?? abort(404);

        EmailTemplate::where(['key' => $key, 'locale' => (string) $request->input('locale')])->delete();

        return redirect()->route('admin.email-templates.edit', [$key, 'locale' => $request->input('locale')])->with('status', __('admin.email_templates.reset_done'));
    }

    /** Renders the form's current (unsaved) text with sample data, in a new tab. */
    public function preview(Request $request, string $key, EmailTemplateRenderer $renderer): Response
    {
        $definition = EmailTemplateRegistry::find($key) ?? abort(404);
        $data = $this->validated($request);

        $rendered = $renderer->render($key, $definition['sample'], $data['locale'], ['subject' => $data['subject'], 'body' => $data['body'], 'is_active' => true]);

        return response(view('core::mail.layout', ['body' => '<p style="color:#888;font-size:12px">'.e(__('admin.email_templates.subject')).': <strong>'.e($rendered['subject']).'</strong></p>'.$rendered['html']]))
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src https: data:");
    }

    public function test(Request $request, string $key): RedirectResponse
    {
        $definition = EmailTemplateRegistry::find($key) ?? abort(404);
        $locale = (string) $request->input('locale');

        $sent = SafeMail::send($request->user(), new TemplatedMail($key, $definition['sample'], $locale));

        return back()->with('status', $sent ? __('admin.settings.mail.test_sent', ['email' => $request->user()->email]) : __('admin.email_templates.test_failed'));
    }

    /**
     * @return array{locale: string, subject: string, body: string}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'locale' => ['required', 'string', 'max:12', 'exists:languages,code'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
        ]);
    }
}
