<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Updater\Exceptions\UpdateException;
use App\Modules\Updater\Models\SystemUpdate;
use App\Modules\Updater\Services\UpdateApplier;
use App\Modules\Updater\Services\UpdatePackage;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->keypair = sodium_crypto_sign_keypair();
    $this->secret = sodium_crypto_sign_secretkey($this->keypair);
    config([
        'updater.public_key' => base64_encode(sodium_crypto_sign_publickey($this->keypair)),
        'updater.allow_unsigned' => false,
        'version.current' => '1.0.0',
    ]);

    $this->base = sys_get_temp_dir().'/upd-'.uniqid();
    mkdir($this->base.'/app', 0777, true);
    file_put_contents($this->base.'/app/a.php', 'old-a');
    file_put_contents($this->base.'/.env', 'SECRET=1');
});

/**
 * Build a signed update zip. $files is path => contents; $tamper changes content after hashing.
 */
function makePackage(array $files, array $manifest = [], ?string $secret = null, array $tamper = [], bool $sign = true, array $symlinks = []): string
{
    $manifest = array_merge([
        'version' => '1.1.0',
        'notes' => 'Test release',
        'files' => array_map(fn ($c) => hash('sha256', $c), $files),
    ], $manifest);

    $raw = json_encode($manifest);
    $path = tempnam(sys_get_temp_dir(), 'pkg').'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('manifest.json', $raw);

    if ($sign && $secret) {
        $zip->addFromString('manifest.sig', base64_encode(sodium_crypto_sign_detached($raw, $secret)));
    }

    foreach ($files as $name => $content) {
        $zip->addFromString('files/'.$name, $tamper[$name] ?? $content);
    }

    foreach ($symlinks as $name) {
        $zip->addFromString('files/'.$name, 'target');
        $zip->setExternalAttributesName('files/'.$name, ZipArchive::OPSYS_UNIX, 0120777 << 16);
    }

    $zip->close();

    return $path;
}

it('applies a signed package, keeps protected files and records the update', function () {
    $zip = makePackage(['app/a.php' => 'new-a', 'app/sub/b.php' => 'new-b', 'config/version.php' => '<?php return [];'], secret: $this->secret);

    $record = (new UpdateApplier($this->base))->apply(UpdatePackage::open($zip), runMigrations: false);

    expect(file_get_contents($this->base.'/app/a.php'))->toBe('new-a')
        ->and(file_get_contents($this->base.'/app/sub/b.php'))->toBe('new-b')
        ->and(file_get_contents($this->base.'/.env'))->toBe('SECRET=1')
        ->and($record->status)->toBe('applied')
        ->and($record->version)->toBe('1.1.0')
        ->and(is_file($record->backup_path))->toBeTrue();
});

it('rejects a package with a bad or missing signature', function () {
    $other = sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());

    expect(fn () => UpdatePackage::open(makePackage(['app/a.php' => 'x'], secret: $other)))->toThrow(UpdateException::class);
    expect(fn () => UpdatePackage::open(makePackage(['app/a.php' => 'x'], sign: false)))->toThrow(UpdateException::class);
});

it('refuses unsigned packages when no key is configured unless explicitly allowed', function () {
    config(['updater.public_key' => null]);
    $zip = makePackage(['app/a.php' => 'x'], sign: false);

    expect(fn () => UpdatePackage::open($zip))->toThrow(UpdateException::class);

    config(['updater.allow_unsigned' => true]);
    expect(UpdatePackage::open($zip)->version())->toBe('1.1.0');
});

it('rejects files whose content does not match the signed hash', function () {
    $zip = makePackage(['app/a.php' => 'good'], secret: $this->secret, tamper: ['app/a.php' => 'evil']);

    expect(fn () => UpdatePackage::open($zip))->toThrow(UpdateException::class);
});

it('rejects unsafe, protected and out-of-scope paths', function (string $path) {
    expect(fn () => UpdatePackage::open(makePackage([$path => 'x'], secret: $this->secret)))
        ->toThrow(UpdateException::class);
})->with([
    'traversal' => '../outside.php',
    'nested traversal' => 'app/../../outside.php',
    'absolute' => '/etc/cron.d/evil',
    'windows drive' => 'C:/evil.php',
    'backslash' => 'app\\evil.php',
    'env file' => '.env',
    'storage' => 'storage/logs/x.php',
    'unlisted root' => 'tests/Evil.php',
    'public storage' => 'public/storage/shell.php',
]);

it('rejects symlinks inside the package', function () {
    $zip = makePackage(['app/a.php' => 'x'], secret: $this->secret, symlinks: ['app/link']);

    expect(fn () => UpdatePackage::open($zip))->toThrow(UpdateException::class);
});

it('only accepts newer versions and honours min_version', function () {
    $applier = new UpdateApplier($this->base);

    $same = UpdatePackage::open(makePackage(['app/a.php' => 'x'], ['version' => '1.0.0'], $this->secret));
    expect(fn () => $applier->apply($same, runMigrations: false))->toThrow(UpdateException::class);

    $tooNew = UpdatePackage::open(makePackage(['app/a.php' => 'x'], ['version' => '3.0.0', 'min_version' => '2.0.0'], $this->secret));
    expect(fn () => $applier->apply($tooNew, runMigrations: false))->toThrow(UpdateException::class);
});

it('restores the previous files when applying fails midway', function () {
    // app/blocker.php is a directory, so writing it fails after app/a.php was already replaced.
    mkdir($this->base.'/app/blocker.php');
    $zip = makePackage(['app/a.php' => 'new-a', 'app/blocker.php' => 'x'], secret: $this->secret);

    expect(fn () => (new UpdateApplier($this->base))->apply(UpdatePackage::open($zip), runMigrations: false))
        ->toThrow(UpdateException::class);

    expect(file_get_contents($this->base.'/app/a.php'))->toBe('old-a')
        ->and(SystemUpdate::first()->status)->toBe('rolled_back');
});

describe('admin screen', function () {
    beforeEach(function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->app->bind(UpdateApplier::class, fn () => new UpdateApplier($this->base));
    });

    function superAdmin(): User
    {
        $user = User::factory()->create(['password' => 'long-enough-1']);
        app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
        $user->assignRole(Permissions::SUPER_ADMIN);

        return $user;
    }

    it('is closed to everyone but super admins', function () {
        $this->get('/admin/updates')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/admin/updates')->assertForbidden();
    });

    it('uploads, reviews and applies with password confirmation', function () {
        $zip = makePackage(['app/a.php' => 'new-a'], secret: $this->secret);
        $this->actingAs(superAdmin());

        $this->post('/admin/updates', ['package' => new UploadedFile($zip, 'update.zip', 'application/zip', null, true)])
            ->assertRedirect(route('admin.updates.review'));
        $this->get('/admin/updates/review')->assertOk()->assertSee('1.1.0');

        $this->post('/admin/updates/apply', ['password' => 'wrong'])->assertSessionHasErrors('password');
        expect(file_get_contents($this->base.'/app/a.php'))->toBe('old-a');

        $this->post('/admin/updates/apply', ['password' => 'long-enough-1'])->assertRedirect(route('admin.updates.index'));
        expect(file_get_contents($this->base.'/app/a.php'))->toBe('new-a');
    });

    it('shows validation errors for a tampered upload and applies nothing', function () {
        $zip = makePackage(['app/a.php' => 'good'], secret: $this->secret, tamper: ['app/a.php' => 'evil']);

        $this->actingAs(superAdmin())
            ->post('/admin/updates', ['package' => new UploadedFile($zip, 'update.zip', 'application/zip', null, true)])
            ->assertSessionHasErrors('package');
        expect(file_get_contents($this->base.'/app/a.php'))->toBe('old-a');
    });
});
