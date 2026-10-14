<?php

use App\Modules\Core\Models\Setting;
use App\Modules\Core\Services\SettingsService;
use Illuminate\Support\Facades\Cache;

it('keeps encrypted settings encrypted in the cache and still reads them back', function () {
    $settings = app(SettingsService::class);
    $settings->set('payments.stripe.secret_key', 'sk_live_super_secret', null, true);
    Cache::flush();
    app()->forgetInstance(SettingsService::class);

    expect(app(SettingsService::class)->get('payments.stripe.secret_key'))->toBe('sk_live_super_secret');

    $cached = json_encode(Cache::get('settings.v2.platform'));
    expect($cached)->not->toContain('sk_live_super_secret')
        ->and(Setting::where('key', 'payments.stripe.secret_key')->value('value'))->not->toBe('sk_live_super_secret');
});
