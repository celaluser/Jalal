<?php

use App\Modules\Orders\Services\OrderSettings;
use App\Modules\Tenancy\Models\Restaurant;

function soundShop(array $settings): Restaurant
{
    return Restaurant::create(['name' => 'Loud Cafe', 'slug' => 'ls'.uniqid(), 'locale' => 'en', 'currency_code' => 'USD', 'timezone' => 'UTC', 'order_settings' => $settings]);
}

it('defaults to a calm chime at medium volume with the late alarm on', function () {
    expect(app(OrderSettings::class)->sound(soundShop([])))->toBe(['tone' => 'chime', 'volume' => 6, 'lateAlarm' => true]);
});

it('keeps tone and volume inside safe bounds', function () {
    $s = app(OrderSettings::class);

    expect($s->sound(soundShop(['sound_tone' => 'evil', 'sound_volume' => 99])))->toMatchArray(['tone' => 'chime', 'volume' => 10])
        ->and($s->sound(soundShop(['sound_tone' => 'siren', 'sound_volume' => 0, 'late_alarm' => false])))->toBe(['tone' => 'siren', 'volume' => 1, 'lateAlarm' => false]);
});

it('validates the sound settings', function () {
    $rules = app(OrderSettings::class)->rules();

    expect(validator(['sound_tone' => 'laser'], ['sound_tone' => $rules['sound_tone']])->fails())->toBeTrue()
        ->and(validator(['sound_volume' => 11], ['sound_volume' => $rules['sound_volume']])->fails())->toBeTrue()
        ->and(validator(['sound_tone' => 'bell', 'sound_volume' => 5], ['sound_tone' => $rules['sound_tone'], 'sound_volume' => $rules['sound_volume']])->passes())->toBeTrue();
});

it('hands the sound configuration to the board and keeps the alarm code in the bundle source', function () {
    expect(file_get_contents(base_path('resources/js/orders.js')))->toContain("beep('late')")->toContain('snooze()');
});
