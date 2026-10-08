<?php

use App\Modules\Addons\Services\AddonInstaller;
use App\Modules\Updater\Exceptions\UpdateException;
use App\Modules\Updater\Services\PackageBuilder;
use App\Modules\Updater\Services\UpdatePackage;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->tmp = sys_get_temp_dir().'/pkgtool-'.uniqid();
    File::ensureDirectoryExists($this->tmp.'/src/app/Sub');
    File::ensureDirectoryExists($this->tmp.'/src/config');
    file_put_contents($this->tmp.'/src/app/Sub/A.php', '<?php // a');
    file_put_contents($this->tmp.'/src/config/x.txt', 'x');
    $this->keys = (new PackageBuilder)->keys();
    config(['updater.public_key' => $this->keys['public'], 'updater.allow_unsigned' => false]);
});

afterEach(fn () => File::deleteDirectory($this->tmp));

it('builds an update package the updater accepts, with correct hashes', function () {
    $n = (new PackageBuilder)->build($this->tmp.'/src', $this->tmp.'/u.zip', '1.2.0', $this->keys['secret'], 'Fixes');
    $pkg = UpdatePackage::open($this->tmp.'/u.zip');
    expect($n)->toBe(2)->and($pkg->version())->toBe('1.2.0')->and($pkg->notes())->toBe('Fixes')->and($pkg->paths())->toBe(['app/Sub/A.php', 'config/x.txt'])->and($pkg->contents('config/x.txt'))->toBe('x');
});

it('rejects a package signed with another key', function () {
    $other = (new PackageBuilder)->keys();
    (new PackageBuilder)->build($this->tmp.'/src', $this->tmp.'/u.zip', '1.0.1', $other['secret']);
    expect(fn () => UpdatePackage::open($this->tmp.'/u.zip'))->toThrow(UpdateException::class);
});

it('validates version, key and source', function () {
    $b = new PackageBuilder;
    expect(fn () => $b->build($this->tmp.'/src', $this->tmp.'/u.zip', 'v1', $this->keys['secret']))->toThrow(UpdateException::class)
        ->and(fn () => $b->build($this->tmp.'/src', $this->tmp.'/u.zip', '1.0.0', 'bad'))->toThrow(UpdateException::class)
        ->and(fn () => $b->build($this->tmp.'/nope', $this->tmp.'/u.zip', '1.0.0', $this->keys['secret']))->toThrow(UpdateException::class);
});

it('packs an add-on folder under addons/<slug>/ so the add-on installer takes it', function () {
    File::ensureDirectoryExists($this->tmp.'/hello/src');
    file_put_contents($this->tmp.'/hello/addon.json', json_encode(['slug' => 'hello-tool', 'name' => 'H', 'version' => '1.0.0', 'namespace' => 'Addons\\HelloTool\\', 'provider' => 'Addons\\HelloTool\\P']));
    file_put_contents($this->tmp.'/hello/src/P.php', '<?php namespace Addons\\HelloTool; class P extends \\Illuminate\\Support\\ServiceProvider {}');
    config(['addons.path' => $this->tmp.'/installed', 'addons.state_file' => $this->tmp.'/state.json']);
    File::ensureDirectoryExists($this->tmp.'/installed');

    (new PackageBuilder)->build($this->tmp.'/hello', $this->tmp.'/a.zip', '1.0.0', $this->keys['secret'], addonSlug: 'hello-tool');
    $manifest = app(AddonInstaller::class)->install($this->tmp.'/a.zip');
    expect($manifest['slug'])->toBe('hello-tool')->and(is_file($this->tmp.'/installed/hello-tool/src/P.php'))->toBeTrue();
});

it('generates keys through artisan and refuses to overwrite one', function () {
    $file = $this->tmp.'/k.key';
    $this->artisan('package:keys', ['--out' => $file])->expectsOutputToContain('UPDATER_PUBLIC_KEY=')->assertSuccessful();
    expect(trim(file_get_contents($file)))->not->toBe('')->and(substr(sprintf('%o', fileperms($file)), -4))->toBe('0600');
    $this->artisan('package:keys', ['--out' => $file])->assertFailed();
});

it('builds a package through artisan', function () {
    file_put_contents($this->tmp.'/k.key', $this->keys['secret']);
    $this->artisan('package:build', ['source' => $this->tmp.'/src', 'output' => $this->tmp.'/c.zip', '--ver' => '2.0.0', '--key' => $this->tmp.'/k.key'])->assertSuccessful();
    expect(UpdatePackage::open($this->tmp.'/c.zip')->version())->toBe('2.0.0');
    $this->artisan('package:build', ['source' => $this->tmp.'/src', 'output' => $this->tmp.'/d.zip', '--ver' => '2.0.0', '--key' => $this->tmp.'/missing.key'])->assertFailed();
});
