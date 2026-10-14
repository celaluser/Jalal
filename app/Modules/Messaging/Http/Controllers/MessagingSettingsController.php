<?php

namespace App\Modules\Messaging\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Messaging\Services\MessagingManager;
use App\Modules\Messaging\Services\Messenger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Super admin: which SMS and WhatsApp services the platform uses, and a test message. */
class MessagingSettingsController extends Controller
{
    public function __construct(private readonly MessagingManager $manager) {}

    public function edit(): View
    {
        return view('messaging::settings', ['manager' => $this->manager]);
    }

    public function update(Request $request, string $provider): RedirectResponse
    {
        $driver = $this->manager->find($provider);
        abort_if($driver === null, 404);

        $rules = [];

        foreach ($driver->fields() as $field) {
            $rules[$field['key']] = $field['type'] === 'select' ? ['nullable', 'in:'.implode(',', array_keys($field['options']))] : ['nullable', 'string', 'max:500'];
        }

        $this->manager->save($driver, $request->validate($rules));

        return back()->with('status', __('admin.saved'));
    }

    public function choose(Request $request): RedirectResponse
    {
        $data = $request->validate(['sms_provider' => ['nullable', 'string'], 'whatsapp_provider' => ['nullable', 'string']]);

        foreach (['sms', 'whatsapp'] as $channel) {
            $code = $data["{$channel}_provider"] ?? '';
            $provider = $code !== '' ? $this->manager->find($code) : null;
            abort_if($code !== '' && (! $provider || ! in_array($channel, $provider->channels(), true)), 422);
            $this->manager->choose($channel, $code);
        }

        return back()->with('status', __('admin.saved'));
    }

    public function test(Request $request, Messenger $messenger): RedirectResponse
    {
        $data = $request->validate(['channel' => ['required', 'in:sms,whatsapp'], 'to' => ['required', 'string', 'max:30']]);

        if (! $messenger->available($data['channel'])) {
            return back()->withErrors(['to' => __('messaging.test_not_ready')]);
        }

        $ok = $messenger->send(null, $data['channel'], $data['to'], __('messaging.test_text', ['app' => config('app.name')]), 'test');

        return $ok ? back()->with('status', __('messaging.test_sent')) : back()->withErrors(['to' => __('messaging.test_failed')]);
    }
}
