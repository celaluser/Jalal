<?php

use App\Models\User;
use App\Modules\Admin\Services\DatabaseBackup;
use App\Modules\Admin\Services\LogReader;
use App\Modules\Admin\Services\SystemInfo;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $this->admin->assignRole(Permissions::SUPER_ADMIN);

    $this->logFile = storage_path('logs/systemtest.log');
    File::delete($this->logFile);
    File::deleteDirectory(storage_path('app/backups'));
});

afterEach(function () {
    File::delete($this->logFile);
    File::deleteDirectory(storage_path('app/backups'));
});

function writeLog(string $path): void
{
    file_put_contents($path, implode("\n", [
        '[2026-01-01 10:00:00] testing.INFO: Hello info',
        '[2026-01-01 10:01:00] testing.ERROR: Payment webhook exploded',
        '#0 /app/Stripe.php(10): fail()',
        '#1 {main}',
        '[2026-01-01 10:02:00] testing.WARNING: Disk almost full',
    ])."\n");
}

it('keeps the system tools away from non super admins', function () {
    $owner = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId(Restaurant::create(['name' => 'Shop', 'slug' => 'shop'.uniqid()])->id);

    $this->post(route('admin.system.backups.store'))->assertRedirect(route('login'));

    foreach (['admin.system.index', 'admin.system.logs', 'admin.system.backups'] as $route) {
        $this->actingAs($owner)->get(route($route))->assertForbidden();
    }
    $this->actingAs($owner)->post(route('admin.system.clear', 'cache'))->assertForbidden();
});

it('renders the system overview', function () {
    $this->actingAs($this->admin)->get(route('admin.system.index'))
        ->assertOk()->assertSee(__('admin.system.scheduler'))->assertSee('Laravel');
});

it('reports scheduler and queue heartbeat states', function () {
    Cache::forget(SystemInfo::SCHEDULER_KEY);
    Cache::forget(SystemInfo::QUEUE_KEY);
    $info = app(SystemInfo::class);

    expect($info->workers()['scheduler']['state'])->toBe('never');

    SystemInfo::beat(SystemInfo::SCHEDULER_KEY);
    expect($info->workers()['scheduler']['state'])->toBe('ok');

    Cache::forever(SystemInfo::SCHEDULER_KEY, now()->subMinutes(10)->timestamp);
    expect($info->workers()['scheduler']['state'])->toBe('stale');

    Cache::forever(SystemInfo::SCHEDULER_KEY, now()->subDay()->timestamp);
    expect($info->workers()['scheduler']['state'])->toBe('never');
});

it('writes heartbeats from the command and from processed jobs', function () {
    Cache::forget(SystemInfo::SCHEDULER_KEY);
    Cache::forget(SystemInfo::QUEUE_KEY);

    $this->artisan('system:heartbeat')->assertSuccessful();
    expect(Cache::get(SystemInfo::SCHEDULER_KEY))->not->toBeNull();

    event(new JobProcessed('database', Mockery::mock(Job::class)));
    expect(Cache::get(SystemInfo::QUEUE_KEY))->not->toBeNull();
});

it('clears caches only through the allowlist', function () {
    $this->actingAs($this->admin)->post(route('admin.system.clear', 'views'))->assertRedirect()->assertSessionHas('status');
    $this->actingAs($this->admin)->post(route('admin.system.clear', 'migrate:fresh'))->assertNotFound();
});

it('lists, filters and searches log entries', function () {
    writeLog($this->logFile);
    $reader = app(LogReader::class);

    expect($reader->entries('systemtest.log'))->toHaveCount(3);
    expect($reader->entries('systemtest.log')[0]['level'])->toBe('warning'); // newest first
    expect($reader->entries('systemtest.log', 'error'))->toHaveCount(1);
    expect($reader->entries('systemtest.log', 'error')[0]['trace'])->toContain('Stripe.php');
    expect($reader->entries('systemtest.log', null, 'disk'))->toHaveCount(1);

    $this->actingAs($this->admin)->get(route('admin.system.logs', ['file' => 'systemtest.log', 'level' => 'error']))
        ->assertOk()->assertSee('Payment webhook exploded')->assertDontSee('Hello info');
});

it('reads only the tail of very large log files', function () {
    $line = '[2026-01-01 10:00:00] testing.INFO: '.str_repeat('x', 200)."\n";
    file_put_contents($this->logFile, str_repeat($line, 9000)); // ~1.8 MB
    file_put_contents($this->logFile, "[2026-01-02 10:00:00] testing.ERROR: latest\n", FILE_APPEND);

    $entries = app(LogReader::class)->entries('systemtest.log', null, null, 10000);

    expect(count($entries))->toBeLessThan(9001)->and($entries[0]['message'])->toBe('latest');
});

it('refuses log names outside storage/logs', function () {
    $reader = app(LogReader::class);

    foreach (['../../.env', '..%2F.env', '/etc/passwd', 'laravel.log/../../.env', '.env'] as $name) {
        expect($reader->path($name))->toBeNull()->and($reader->entries($name))->toBe([]);
    }

    $this->actingAs($this->admin)->delete(route('admin.system.logs.clear'), ['file' => '../../.env'])->assertNotFound();
});

it('empties a log file', function () {
    writeLog($this->logFile);

    $this->actingAs($this->admin)->delete(route('admin.system.logs.clear'), ['file' => 'systemtest.log'])->assertRedirect();

    expect(filesize($this->logFile))->toBe(0);
});

it('creates a backup that restores into a fresh database with the same rows', function () {
    foreach (range(1, 3) as $i) {
        Restaurant::create(['name' => "Shop {$i}", 'slug' => 'shop'.$i.uniqid()]);
    }
    $usersBefore = User::count();
    $restaurantsBefore = Restaurant::count();
    $quote = User::factory()->create(['name' => "O'Brien \"quoted\" ünï"]);

    $name = app(DatabaseBackup::class)->create();
    $path = app(DatabaseBackup::class)->path($name);
    expect($path)->not->toBeNull();

    $target = tempnam(sys_get_temp_dir(), 'restore');
    $pdo = new PDO('sqlite:'.$target);
    $pdo->exec(file_get_contents($path));

    expect((int) $pdo->query('select count(*) from users')->fetchColumn())->toBe($usersBefore + 1)
        ->and((int) $pdo->query('select count(*) from restaurants')->fetchColumn())->toBe($restaurantsBefore)
        ->and($pdo->query('select name from users where id = '.$quote->id)->fetchColumn())->toBe("O'Brien \"quoted\" ünï");

    unset($pdo);
    @unlink($target);
})->skip(fn () => DB::getDriverName() !== 'sqlite', 'The restore check loads the dump into SQLite, so it only makes sense on a SQLite run.');

it('creates, downloads and deletes backups from the panel', function () {
    $this->actingAs($this->admin)->post(route('admin.system.backups.store'))->assertRedirect()->assertSessionHas('status');

    $name = app(DatabaseBackup::class)->all()[0]['name'];

    $this->actingAs($this->admin)->get(route('admin.system.backups'))->assertOk()->assertSee($name);
    $this->actingAs($this->admin)->get(route('admin.system.backups.download', $name))->assertOk()->assertDownload($name);
    $this->actingAs($this->admin)->delete(route('admin.system.backups.destroy', $name))->assertRedirect();

    expect(app(DatabaseBackup::class)->all())->toBe([]);
});

it('validates backup names against path traversal', function () {
    file_put_contents(base_path('storage/app/secret.sql'), 'x');
    $backups = app(DatabaseBackup::class);

    foreach (['../secret.sql', 'backup-1.sql', 'backup-20260101-000000.sql/../../secret.sql', '..\\secret.sql'] as $name) {
        expect($backups->path($name))->toBeNull();
    }

    $this->actingAs($this->admin)->get(route('admin.system.backups.download', 'nope.sql'))->assertNotFound();
    $this->actingAs($this->admin)->delete(route('admin.system.backups.destroy', 'nope.sql'))->assertNotFound();
    @unlink(base_path('storage/app/secret.sql'));
});

it('prunes old backups and keeps the newest', function () {
    File::ensureDirectoryExists(storage_path('app/backups'));
    foreach (['20260101-000001', '20260101-000002', '20260101-000003'] as $i => $stamp) {
        $f = storage_path("app/backups/backup-{$stamp}.sql");
        file_put_contents($f, '--');
        touch($f, time() - (10 - $i));
    }

    $this->artisan('system:backup --keep=2')->assertSuccessful();

    expect(app(DatabaseBackup::class)->all())->toHaveCount(2);
});
