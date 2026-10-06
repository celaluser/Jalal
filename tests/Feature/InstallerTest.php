<?php

use App\Modules\Installer\Services\DatabaseConnector;
use App\Modules\Installer\Services\EnvWriter;
use App\Modules\Installer\Services\InstallerService;
use App\Modules\Installer\Services\RequirementsChecker;
use App\Modules\Licensing\Services\EnvatoLicenseVerifier;
use App\Modules\Licensing\Services\FormatLicenseVerifier;
use App\Modules\Licensing\Services\LicenseManager;
use App\Modules\Licensing\Services\ServerLicenseVerifier;
use App\Modules\Licensing\Support\LicenseResult;
use Illuminate\Support\Facades\Http;

const VALID_CODE = '86731e51-1234-4abc-9def-0123456789ab';

function tempEnv(string $contents = "APP_NAME=Old\nAPP_KEY=\n# comment\n"): string
{
    $path = tempnam(sys_get_temp_dir(), 'env');
    file_put_contents($path, $contents);

    return $path;
}

it('updates existing .env keys, appends new ones and keeps comments', function () {
    $path = tempEnv();
    (new EnvWriter($path))->set(['APP_NAME' => 'My Menu', 'DB_HOST' => '127.0.0.1']);

    $env = file_get_contents($path);
    expect($env)->toContain('APP_NAME="My Menu"')
        ->toContain('# comment')
        ->toContain('DB_HOST=127.0.0.1')
        ->and((new EnvWriter($path))->get('APP_NAME'))->toBe('My Menu');
});

it('cannot be tricked into injecting extra variables through a value', function () {
    $path = tempEnv();
    (new EnvWriter($path))->set(['APP_NAME' => "x\nAPP_ENV=local", 'DB_PASSWORD' => 'pa$$"word']);

    $env = file_get_contents($path);
    expect($env)->not->toContain("\nAPP_ENV=")
        ->and((new EnvWriter($path))->get('DB_PASSWORD'))->toContain('pa');
    // The newline is stripped, so no line can start a new variable.
    expect(preg_match('/^APP_ENV=/m', $env))->toBe(0);
});

it('reports requirements', function () {
    $checks = (new RequirementsChecker)->checks();

    expect($checks)->not->toBeEmpty()
        ->and(collect($checks)->firstWhere('label', 'like', 'PHP >=*')['ok'] ?? true)->toBeTrue();
});

it('tests database connections and reports failures', function () {
    $connector = new DatabaseConnector;
    $file = tempnam(sys_get_temp_dir(), 'db').'.sqlite';
    touch($file);

    expect($connector->test(['driver' => 'sqlite', 'database' => $file]))->toBeNull();
    expect($connector->test(['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'x', 'username' => 'x', 'password' => 'x']))
        ->toBeString();
});

it('verifies purchase code formats', function () {
    $verifier = new FormatLicenseVerifier;

    expect($verifier->verify(VALID_CODE, 'localhost')->valid)->toBeTrue()
        ->and($verifier->verify('nope', 'localhost')->valid)->toBeFalse();
});

it('uses the licence server and distinguishes rejection from outage', function () {
    config(['licensing.server_url' => 'https://license.test/verify']);
    $verifier = new ServerLicenseVerifier;

    Http::fake(['license.test/*' => Http::sequence()
        ->push(['valid' => true, 'buyer' => 'Ada'])
        ->push(['valid' => false, 'message' => 'Already used'], 403)
        ->push('boom', 500)]);

    expect($verifier->verify(VALID_CODE, 'shop.test'))->valid->toBeTrue()->buyer->toBe('Ada');

    $rejected = $verifier->verify(VALID_CODE, 'shop.test');
    expect($rejected->valid)->toBeFalse()->and($rejected->reachable)->toBeTrue()->and($rejected->message)->toBe('Already used');

    $down = $verifier->verify(VALID_CODE, 'shop.test');
    expect($down->valid)->toBeFalse()->and($down->reachable)->toBeFalse();
});

it('checks the item id with the envato verifier', function () {
    config(['licensing.envato_token' => 'tok', 'licensing.envato_item_id' => '111']);

    Http::fake(['api.envato.com/*' => Http::sequence()
        ->push(['item' => ['id' => 222], 'buyer' => 'Bob'])
        ->push(['item' => ['id' => 111], 'buyer' => 'Bob'])
        ->push([], 404)]);

    expect((new EnvatoLicenseVerifier)->verify(VALID_CODE, 'x')->valid)->toBeFalse();
    expect((new EnvatoLicenseVerifier)->verify(VALID_CODE, 'x'))->valid->toBeTrue();
    expect((new EnvatoLicenseVerifier)->verify(VALID_CODE, 'x'))->valid->toBeFalse()->reachable->toBeTrue();
});

it('stores the licence encrypted and marks unreachable checks as unverified', function () {
    $manager = app(LicenseManager::class);

    $manager->record(VALID_CODE, LicenseResult::unreachable('down'));

    expect($manager->status())->toBe('unverified')
        ->and(DB::table('settings')->where('key', 'license.core.code')->value('value'))->not->toContain(VALID_CODE);
});

it('hides the wizard once installed', function () {
    $this->get('/install')->assertNotFound();
});

describe('before installation', function () {
    beforeEach(function () {
        config(['installer.force_installed' => false, 'installer.lock_file' => storage_path('framework/testing/never-installed')]);
    });

    it('redirects every page to the wizard', function () {
        $this->get('/login')->assertRedirect(route('install.welcome'));
        $this->get('/dashboard')->assertRedirect(route('install.welcome'));
    });

    it('shows the requirements step', function () {
        $this->get('/install')->assertOk()->assertSee(__('installer.step_requirements'));
    });

    it('rejects a malformed purchase code and accepts a valid one', function () {
        $this->post('/install/license', ['purchase_code' => 'nope'])->assertSessionHasErrors('purchase_code');
        $this->post('/install/license', ['purchase_code' => VALID_CODE])->assertRedirect(route('install.database'));
        $this->assertSame(VALID_CODE, session('installer.license.code'));
    });

    it('does not let you skip steps', function () {
        $this->get('/install/database')->assertRedirect(route('install.license'));
        $this->get('/install/admin')->assertRedirect(route('install.database'));
        $this->post('/install/run', [
            'site_name' => 'x', 'site_url' => 'http://x.test', 'name' => 'a', 'email' => 'a@x.test',
            'password' => 'long-enough-1', 'password_confirmation' => 'long-enough-1',
        ])->assertRedirect(route('install.welcome'));
    });

    it('rejects unreachable databases at the database step', function () {
        $this->withSession(['installer.license' => ['code' => VALID_CODE, 'valid' => true]])
            ->post('/install/database', ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'x', 'username' => 'x'])
            ->assertSessionHasErrors('database');
    });
});

it('installs end to end on a fresh sqlite database', function () {
    $dir = sys_get_temp_dir().'/install-'.uniqid();
    mkdir($dir);
    $dbFile = $dir.'/fresh.sqlite';
    touch($dbFile);
    $lock = $dir.'/installed';
    $envPath = $dir.'/.env';
    file_put_contents($envPath, "APP_NAME=Old\n");

    config(['installer.force_installed' => false, 'installer.lock_file' => $lock]);
    $this->app->singleton(EnvWriter::class, fn () => new EnvWriter($envPath));
    $this->app->forgetInstance(InstallerService::class);

    $originalDefault = config('database.default');
    app(InstallerService::class)->install([
        'license' => ['code' => VALID_CODE, 'valid' => true],
        'database' => ['driver' => 'sqlite', 'database' => $dbFile],
        'site' => ['name' => 'Menu SaaS', 'url' => 'https://menu.test'],
        'admin' => ['name' => 'Root', 'email' => 'root@menu.test', 'password' => 'long-enough-1'],
    ]);

    expect(file_exists($lock))->toBeTrue()
        ->and(DB::table('users')->where('email', 'root@menu.test')->exists())->toBeTrue()
        ->and(DB::table('roles')->where('name', 'super_admin')->exists())->toBeTrue()
        ->and((new EnvWriter($envPath))->get('APP_URL'))->toBe('https://menu.test')
        ->and(DB::table('settings')->where('key', 'license.core.status')->value('value'))->toBe('verified');

    // A second run against a populated database is refused.
    expect(fn () => app(InstallerService::class)->install([
        'license' => ['code' => VALID_CODE, 'valid' => true],
        'database' => ['driver' => 'sqlite', 'database' => $dbFile],
        'site' => ['name' => 'x', 'url' => 'https://x.test'],
        'admin' => ['name' => 'x', 'email' => 'x@x.test', 'password' => 'long-enough-1'],
    ]))->toThrow(RuntimeException::class);

    config(['database.default' => $originalDefault]);
    DB::purge('installer_target');
});
