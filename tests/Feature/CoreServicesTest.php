<?php

use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Models\Translation;
use App\Modules\Core\Services\FileUploader;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\LanguagesAndCurrenciesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('stores settings per scope and encrypts secrets at rest', function () {
    $settings = app(SettingsService::class);

    $settings->set('mail.host', 'smtp.example.com');
    $settings->set('ai.key', 'sk-secret', encrypt: true);
    $settings->set('mail.host', 'resto.example.com', restaurantId: 7);

    expect($settings->get('mail.host'))->toBe('smtp.example.com')
        ->and($settings->get('mail.host', restaurantId: 7))->toBe('resto.example.com')
        ->and($settings->get('ai.key'))->toBe('sk-secret')
        ->and($settings->get('missing', 'fallback'))->toBe('fallback')
        ->and(DB::table('settings')->where('key', 'ai.key')->value('value'))->not->toContain('sk-secret');
});

it('formats money by currency symbol, position and separators', function () {
    $this->seed(LanguagesAndCurrenciesSeeder::class);

    expect(Currency::where('code', 'USD')->first()->format(1234.5))->toBe('$1,234.50')
        ->and(Currency::where('code', 'TRY')->first()->format(1234.5))->toBe('1.234,50 ₺');
});

it('seeds active languages including an RTL one', function () {
    $this->seed(LanguagesAndCurrenciesSeeder::class);

    expect(Language::active()->pluck('is_rtl', 'code')->all())->toMatchArray(['en' => false, 'ar' => true]);
});

it('lets panel edits override file translations', function () {
    expect(__('auth.login'))->toBe('Sign in');

    Translation::create(['locale' => 'en', 'group' => 'auth', 'key' => 'login', 'value' => 'Log in now']);
    app('translator')->setLoaded([]);

    expect(__('auth.login'))->toBe('Log in now');
});

it('serves the page in the browser language and sets rtl direction', function () {
    $this->seed(LanguagesAndCurrenciesSeeder::class);
    Cache::flush();

    $this->withHeaders(['Accept-Language' => 'ar'])->get('/login')->assertSee('dir="rtl"', false);
    $this->get('/login?lang=tr')->assertSee('Giriş yap');
    // The chosen language sticks in the session; an unknown code is ignored, not applied.
    $this->get('/login?lang=xx')->assertSee('Giriş yap');
    $this->flushSession();
    $this->get('/login?lang=xx')->assertSee('Sign in');
});

it('converts uploaded images to webp, scoped to the tenant', function () {
    Storage::fake('public');
    $restaurant = Restaurant::create(['name' => 'Img', 'slug' => 'img']);

    $media = app(TenantContext::class)->runAs($restaurant, fn () => app(FileUploader::class)
        ->store(UploadedFile::fake()->image('photo.jpg', 3000, 2000), 'products'));

    expect($media->mime)->toBe('image/webp')
        ->and($media->path)->toEndWith('.webp')
        ->and($media->path)->toContain("r{$restaurant->id}")
        ->and($media->width)->toBeLessThanOrEqual(1920);
    Storage::disk('public')->assertExists($media->path);
});

it('rejects files that are not images', function () {
    Storage::fake('public');

    app(FileUploader::class)->store(UploadedFile::fake()->create('evil.php', 10, 'text/x-php'));
})->throws(InvalidArgumentException::class);
