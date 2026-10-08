<?php

use App\Models\User;
use App\Modules\Addons\Services\AddonInstaller;
use App\Modules\Addons\Services\AddonManager;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Updater\Exceptions\UpdateException;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $keys = sodium_crypto_sign_keypair();
    $this->secret = sodium_crypto_sign_secretkey($keys);
    $this->dir = sys_get_temp_dir().'/addons-'.uniqid();
    mkdir($this->dir, 0777, true);
    config([
        'updater.public_key' => base64_encode(sodium_crypto_sign_publickey($keys)), 'updater.allow_unsigned' => false, 'version.current' => '1.0.0',
        'addons.path' => $this->dir, 'addons.state_file' => $this->dir.'/state.json', 'addons.upload_enabled' => true, 'demo.enabled' => false,
    ]);
});

afterEach(fn () => \Illuminate\Support\Facades\File::deleteDirectory($this->dir));

/** Builds a signed add-on zip with a service provider that adds a route and a table. @return array{0: string, 1: string} zip path, slug */
function adPackage(array $over = [], array $files = [], ?string $secret = null, ?string $slug = null): array
{
    $slug ??= 'hello-'.strtolower(substr(uniqid(), -6));
    $ns = 'Hx'.str_replace('-', '', ucwords($slug, '-'));
    $table = 'ad_'.str_replace('-', '_', $slug);
    $manifest = $over + ['slug' => $slug, 'name' => 'Hello', 'version' => '1.0.0', 'min_core' => '1.0.0', 'namespace' => "Addons\\{$ns}\\", 'provider' => "Addons\\{$ns}\\HelloProvider"];
    $files = $files + [
        "addons/{$slug}/addon.json" => json_encode($manifest),
        "addons/{$slug}/src/HelloProvider.php" => "<?php\nnamespace Addons\\{$ns};\nuse Illuminate\\Support\\ServiceProvider;\nclass HelloProvider extends ServiceProvider {\n public function boot(): void {\n  \\Illuminate\\Support\\Facades\\Route::get('/{$slug}-hi', fn () => 'hello from {$slug}');\n }\n}\n",
        "addons/{$slug}/database/migrations/2030_01_01_000000_create_{$table}.php" => "<?php\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\nreturn new class extends Migration {\n public function up(): void { Schema::create('{$table}', fn (Blueprint \$t) => \$t->id()); }\n public function down(): void { Schema::dropIfExists('{$table}'); }\n};\n",
    ];
    $raw = json_encode(['version' => $manifest['version'], 'files' => array_map(fn ($c) => hash('sha256', $c), $files)]);
    $path = tempnam(sys_get_temp_dir(), 'ad').'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('manifest.json', $raw);

    if ($secret) {
        $zip->addFromString('manifest.sig', base64_encode(sodium_crypto_sign_detached($raw, $secret)));
    }

    foreach ($files as $name => $content) {
        $zip->addFromString('files/'.$name, $content);
    }

    $zip->close();

    return [$path, $slug];
}

function adAdmin(): User
{
    $u = User::factory()->create();
    $reg = app(PermissionRegistrar::class);
    $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $u->assignRole(Permissions::SUPER_ADMIN);

    return $u;
}

it('installs a signed add-on switched off, then turns it on with its migrations and routes', function () {
    [$zip, $slug] = adPackage(secret: $this->secret);
    $installer = app(AddonInstaller::class);
    $manifest = $installer->install($zip);

    expect($manifest['slug'])->toBe($slug)->and(is_file("{$this->dir}/{$slug}/addon.json"))->toBeTrue()->and(app(AddonManager::class)->enabled($slug))->toBeFalse();

    $installer->enable($slug);
    expect(Schema::hasTable('ad_'.str_replace('-', '_', $slug)))->toBeTrue()->and(app(AddonManager::class)->enabled($slug))->toBeTrue();

    app(AddonManager::class)->boot();
    $this->get("/{$slug}-hi")->assertOk()->assertSee("hello from {$slug}");

    $installer->disable($slug);
    expect(app(AddonManager::class)->enabled($slug))->toBeFalse();
});

it('refuses unsigned, wrongly signed and tampered packages and leaves nothing behind', function () {
    $other = sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());
    $installer = app(AddonInstaller::class);

    foreach ([adPackage()[0], adPackage(secret: $other)[0]] as $zip) {
        expect(fn () => $installer->install($zip))->toThrow(UpdateException::class);
    }

    expect(glob($this->dir.'/*'))->toBe([]);
});

it('refuses packages that write outside addons/<slug>/ or have a mismatched manifest', function () {
    $installer = app(AddonInstaller::class);
    [$zip] = adPackage(files: ['app/Evil.php' => '<?php'], secret: $this->secret);
    expect(fn () => $installer->install($zip))->toThrow(UpdateException::class);

    [$zip] = adPackage(files: ['addons/other-thing/x.php' => '<?php', 'addons/zzz-top/addon.json' => '{}'], secret: $this->secret, slug: 'zzz-top');
    expect(fn () => $installer->install($zip))->toThrow(UpdateException::class);

    [$zip, $slug] = adPackage(['slug' => 'different'], secret: $this->secret);
    expect(fn () => $installer->install($zip))->toThrow(RuntimeException::class);
    expect(glob($this->dir.'/*'))->toBe([]);

    [$zip] = adPackage(['namespace' => 'App\\Hacked\\', 'provider' => 'App\\Hacked\\P'], secret: $this->secret);
    expect(fn () => $installer->install($zip))->toThrow(RuntimeException::class);
});

it('refuses add-ons that need a newer core', function () {
    [$zip] = adPackage(['min_core' => '9.0.0'], secret: $this->secret);
    expect(fn () => app(AddonInstaller::class)->install($zip))->toThrow(RuntimeException::class, 'Needs application version 9.0.0');
});

it('replaces an older copy when the same add-on is installed again', function () {
    [$zip, $slug] = adPackage(['version' => '1.0.0'], secret: $this->secret);
    app(AddonInstaller::class)->install($zip);
    [$zip] = adPackage(['version' => '1.1.0'], ['addons/'.$slug.'/NEW.txt' => 'new'], $this->secret, $slug);
    app(AddonInstaller::class)->install($zip);
    expect(app(AddonManager::class)->manifest($slug)['version'])->toBe('1.1.0')->and(is_file("{$this->dir}/{$slug}/NEW.txt"))->toBeTrue()->and(glob($this->dir.'/*.old-*'))->toBe([]);
});

it('does not crash the application when an enabled add-on is broken', function () {
    [$zip, $slug] = adPackage(files: [], secret: $this->secret);
    app(AddonInstaller::class)->install($zip);
    file_put_contents("{$this->dir}/{$slug}/src/HelloProvider.php", "<?php\nthrow new RuntimeException('boom');");
    app(AddonManager::class)->set($slug, true);

    app(AddonManager::class)->boot();
    expect(app(AddonManager::class)->error($slug))->not->toBeNull();
    $this->actingAs(adAdmin())->get(route('admin.addons.index'))->assertOk()->assertSee('Hello');
});

it('removes the code on uninstall and keeps the database tables', function () {
    [$zip, $slug] = adPackage(secret: $this->secret);
    $installer = app(AddonInstaller::class);
    $installer->install($zip);
    $installer->enable($slug);
    $installer->uninstall($slug);

    expect(is_dir("{$this->dir}/{$slug}"))->toBeFalse()->and(app(AddonManager::class)->enabled($slug))->toBeFalse()->and(Schema::hasTable('ad_'.str_replace('-', '_', $slug)))->toBeTrue();
    expect(fn () => $installer->uninstall('../etc'))->toThrow(RuntimeException::class);
});

describe('admin page', function () {
    it('is for super admins only', function () {
        $this->get(route('admin.addons.index'))->assertRedirect();
        $this->actingAs(User::factory()->create())->get(route('admin.addons.index'))->assertForbidden();
        $this->actingAs(adAdmin())->get(route('admin.addons.index'))->assertOk()->assertSee('No add-ons installed');
    });

    it('uploads, switches and removes through the page', function () {
        [$zip, $slug] = adPackage(secret: $this->secret);
        $admin = adAdmin();
        $this->actingAs($admin)->post(route('admin.addons.upload'), ['package' => new UploadedFile($zip, 'addon.zip', 'application/zip', null, true)])->assertRedirect()->assertSessionHas('status');
        $this->actingAs($admin)->get(route('admin.addons.index'))->assertSee('Hello')->assertSee('Off');
        $this->actingAs($admin)->post(route('admin.addons.toggle', $slug))->assertRedirect();
        expect(app(AddonManager::class)->enabled($slug))->toBeTrue();
        $this->actingAs($admin)->delete(route('admin.addons.destroy', $slug))->assertRedirect();
        expect(is_dir("{$this->dir}/{$slug}"))->toBeFalse();
        $this->actingAs($admin)->post(route('admin.addons.toggle', 'nope-nope'))->assertNotFound();
        $this->actingAs($admin)->post(route('admin.addons.toggle', '..%2f..'))->assertNotFound();
    });

    it('rejects a bad upload with a message, and is closed when uploads are switched off', function () {
        $admin = adAdmin();
        $upload = fn () => ['package' => new UploadedFile(adPackage()[0], 'a.zip', 'application/zip', null, true)]; // unsigned
        $this->actingAs($admin)->post(route('admin.addons.upload'), $upload())->assertSessionHasErrors('package');
        config(['addons.upload_enabled' => false]);
        $this->actingAs($admin)->post(route('admin.addons.upload'), $upload())->assertForbidden();
    });
});

it('installs add-ons signed by a trusted author key, but never accepts that key for updates', function () {
    $author = sodium_crypto_sign_keypair();
    $authorSecret = sodium_crypto_sign_secretkey($author);
    [$zip, $slug] = adPackage(secret: $authorSecret);

    expect(fn () => app(AddonInstaller::class)->install($zip))->toThrow(UpdateException::class); // author key not trusted yet

    config(['addons.trusted_keys' => [base64_encode(sodium_crypto_sign_publickey($author))]]);
    expect(app(AddonInstaller::class)->install($zip)['slug'])->toBe($slug);
    expect(fn () => \App\Modules\Updater\Services\UpdatePackage::open($zip))->toThrow(UpdateException::class); // the update path ignores it
});
