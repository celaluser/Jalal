<?php

use App\Models\User;
use App\Modules\Auth\Services\AppleSignIn;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Services\SettingsService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\Two\FacebookProvider;
use Laravel\Socialite\Two\User as SocialUser;

beforeEach(function () {
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
});

function slEnable(string $provider, array $extra = []): void
{
    $s = app(SettingsService::class);
    $s->set("auth.{$provider}_enabled", true);
    foreach ($extra as $k => $v) {
        $s->set("auth.{$provider}_{$k}", $v);
    }
}

function slKey(): string
{
    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    openssl_pkey_export($key, $pem);

    return $pem;
}

function slAppleIdToken(array $over = []): string
{
    $b = fn ($v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');

    return $b('{"alg":"none"}').'.'.$b(json_encode(array_merge(['iss' => 'https://appleid.apple.com', 'aud' => 'svc.id', 'exp' => time() + 600, 'sub' => 'apple-123', 'email' => 'ana@example.com'], $over))).'.sig';
}

it('keeps both buttons hidden and the routes closed until enabled', function () {
    $this->get('/login')->assertDontSee('Continue with Facebook')->assertDontSee('Continue with Apple');
    $this->get('/auth/social/facebook')->assertNotFound();
    $this->get('/auth/social/apple')->assertNotFound();
    $this->get('/auth/social/twitter')->assertNotFound();
});

it('shows the buttons once enabled', function () {
    slEnable('facebook');
    slEnable('apple');
    $this->get('/login')->assertSee('Continue with Facebook')->assertSee('Continue with Apple');
});

it('signs in an existing user through Facebook, linking by e-mail', function () {
    slEnable('facebook', ['client_id' => 'x', 'client_secret' => 'y']);
    $user = User::factory()->create(['email' => 'ana@example.com']);
    $fb = (new SocialUser)->map(['id' => 'fb-1', 'email' => 'ana@example.com', 'name' => 'Ana']);
    $driver = Mockery::mock(FacebookProvider::class);
    $driver->shouldReceive('scopes')->andReturnSelf();
    $driver->shouldReceive('user')->andReturn($fb);
    $factory = Mockery::mock(Factory::class);
    $factory->shouldReceive('driver')->with('facebook')->andReturn($driver);
    $this->app->instance(Factory::class, $factory);

    $this->get('/auth/social/facebook/callback')->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->facebook_id)->toBe('fb-1');
});

it('refuses a Facebook identity that has no account, or a switched-off one', function () {
    slEnable('facebook');
    $fb = (new SocialUser)->map(['id' => 'fb-9', 'email' => 'nobody@example.com', 'name' => 'N']);
    $driver = Mockery::mock(FacebookProvider::class);
    $driver->shouldReceive('scopes')->andReturnSelf();
    $driver->shouldReceive('user')->andReturn($fb);
    $factory = Mockery::mock(Factory::class);
    $factory->shouldReceive('driver')->andReturn($driver);
    $this->app->instance(Factory::class, $factory);

    $this->get('/auth/social/facebook/callback')->assertRedirect(route('login'))->assertSessionHasErrors('email');
    $this->assertGuest();

    User::factory()->create(['email' => 'nobody@example.com', 'disabled_at' => now()]);
    $this->get('/auth/social/facebook/callback')->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('builds an Apple link with a signed, expiring state', function () {
    slEnable('apple', ['client_id' => 'svc.id']);
    $location = $this->get('/auth/social/apple')->assertRedirect()->headers->get('Location');
    parse_str(parse_url($location, PHP_URL_QUERY), $q);

    expect($location)->toStartWith('https://appleid.apple.com/auth/authorize')->and($q['response_mode'])->toBe('form_post')->and($q['client_id'])->toBe('svc.id');
    $apple = app(AppleSignIn::class);
    expect($apple->validState($q['state']))->toBeTrue()->and($apple->validState('forged'))->toBeFalse()->and($apple->validState(Crypt::encryptString(json_encode(['exp' => time() - 5]))))->toBeFalse();
});

it('signs a valid ES256 client secret', function () {
    slEnable('apple', ['client_id' => 'svc.id', 'team_id' => 'TEAM123456', 'key_id' => 'KEY1234567', 'private_key' => slKey()]);
    $jwt = app(AppleSignIn::class)->clientSecret();
    [$h, $c, $sig] = explode('.', $jwt);
    $d = fn ($v) => base64_decode(strtr($v, '-_', '+/'));

    expect(json_decode($d($h), true))->toMatchArray(['alg' => 'ES256', 'kid' => 'KEY1234567'])->and(json_decode($d($c), true))->toMatchArray(['iss' => 'TEAM123456', 'sub' => 'svc.id', 'aud' => 'https://appleid.apple.com'])->and(strlen($d($sig)))->toBe(64);
});

it('signs in through Apple and rejects bad tokens', function () {
    slEnable('apple', ['client_id' => 'svc.id', 'team_id' => 'T', 'key_id' => 'K', 'private_key' => slKey()]);
    $user = User::factory()->create(['email' => 'ana@example.com']);
    $state = app(AppleSignIn::class)->makeState();

    Http::fake(['appleid.apple.com/auth/token' => Http::sequence()->push(['id_token' => slAppleIdToken()])->push(['id_token' => slAppleIdToken(['aud' => 'someone-else'])])]);
    $this->post('/auth/social/apple/callback', ['code' => 'c', 'state' => $state])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->apple_id)->toBe('apple-123');

    auth()->logout();
    $this->post('/auth/social/apple/callback', ['code' => 'c', 'state' => $state])->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->post('/auth/social/apple/callback', ['code' => 'c', 'state' => 'forged'])->assertStatus(400);
});
