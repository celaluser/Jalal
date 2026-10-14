<?php

use App\Models\User;
use App\Modules\Admin\Services\SystemInfo;
use App\Modules\Admin\Services\WebCron;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Models\Setting;
use App\Modules\Core\Services\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    Cache::flush();
});

function ntAdmin(): User
{
    $u = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('tenancy.platform_team_id'));
    $u->assignRole(Permissions::SUPER_ADMIN);

    return $u;
}

describe('web cron address', function () {
    it('runs the scheduler and the queue for the right secret only', function () {
        $token = app(WebCron::class)->token();
        expect($token)->toHaveLength(40)->and(app(WebCron::class)->token())->toBe($token); // stable once created

        $this->get('/cron/wrong')->assertNotFound();
        $this->get('/cron/'.$token)->assertOk()->assertJsonPath('ran', true)->assertHeader('Cache-Control');
        expect(Cache::get(SystemInfo::SCHEDULER_KEY))->not->toBeNull(); // system:heartbeat ran through schedule:run
    });

    it('does not run twice at the same moment', function () {
        $lock = Cache::lock(WebCron::LOCK, 30);
        $lock->get();
        $this->get('/cron/'.app(WebCron::class)->token())->assertOk()->assertJsonPath('ran', false);
        $lock->release();
    });

    it('processes waiting jobs', function () {
        config(['queue.default' => 'database']);
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => json_encode(['displayName' => 'x', 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'maxTries' => 1, 'data' => ['commandName' => 'NoSuchJob', 'command' => 'x']]), 'attempts' => 0, 'available_at' => time(), 'created_at' => time()]);
        $this->get('/cron/'.app(WebCron::class)->token())->assertOk();
        expect(DB::table('jobs')->count())->toBe(0); // picked up (this one fails and moves to failed_jobs, which is fine)
    });
});

describe('visitor-driven fallback', function () {
    it('runs after a page when no cron has been seen, once a minute', function () {
        $cron = app(WebCron::class);
        expect(Cache::get(SystemInfo::SCHEDULER_KEY))->toBeNull();
        $cron->maybeRunAfterResponse();
        expect(Cache::get(SystemInfo::SCHEDULER_KEY))->not->toBeNull();

        Cache::forget(SystemInfo::SCHEDULER_KEY);
        $cron->maybeRunAfterResponse(); // gate is closed for a minute
        expect(Cache::get(SystemInfo::SCHEDULER_KEY))->toBeNull();
    });

    it('stays quiet when a real cron is running or when switched off', function () {
        $cron = app(WebCron::class);
        SystemInfo::beat(SystemInfo::SCHEDULER_KEY);
        $before = Cache::get(SystemInfo::SCHEDULER_KEY);
        $cron->maybeRunAfterResponse();
        expect(Cache::has(WebCron::GATE))->toBeFalse();

        Cache::forget(SystemInfo::SCHEDULER_KEY);
        Setting::updateOrCreate(['key' => 'system.web_cron'], ['value' => 'off']);
        app(SettingsService::class)->set('system.web_cron', 'off');
        $cron->maybeRunAfterResponse();
        expect(Cache::get(SystemInfo::SCHEDULER_KEY))->toBeNull();
    });
});

describe('admin tools', function () {
    it('shows the cron address and tools to the super admin only', function () {
        $this->actingAs(User::factory()->create())->get(route('admin.system.index'))->assertForbidden();
        $this->actingAs(ntAdmin())->get(route('admin.system.index'))->assertOk()->assertSee('/cron/'.app(WebCron::class)->token())->assertSee('Update database')->assertSee('Process waiting jobs now');
    });

    it('runs allowed tools and refuses anything else', function () {
        $admin = ntAdmin();
        $this->actingAs($admin)->post(route('admin.system.tool', 'migrate'))->assertRedirect()->assertSessionHas('status');
        $this->actingAs($admin)->post(route('admin.system.tool', 'scheduler'))->assertRedirect()->assertSessionHas('status');
        $this->actingAs($admin)->post(route('admin.system.tool', 'queue'))->assertRedirect()->assertSessionHas('status');
        $this->actingAs($admin)->post(route('admin.system.tool', 'retry_failed'))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.system.tool', 'db:wipe'))->assertNotFound();
        $this->actingAs($admin)->post(route('admin.system.tool', 'migrate:fresh'))->assertNotFound();
        $this->actingAs(User::factory()->create())->post(route('admin.system.tool', 'migrate'))->assertForbidden();
    });

    it('changes the fallback mode and creates a new secret address', function () {
        $admin = ntAdmin();
        $old = app(WebCron::class)->token();
        $this->actingAs($admin)->put(route('admin.system.cron'), ['mode' => 'always'])->assertRedirect();
        expect(app(WebCron::class)->mode())->toBe('always');
        $this->actingAs($admin)->put(route('admin.system.cron'), ['mode' => 'weird'])->assertSessionHasErrors('mode');
        $this->actingAs($admin)->put(route('admin.system.cron'), ['regenerate' => 1])->assertRedirect();
        $new = app(WebCron::class)->token();
        expect($new)->not->toBe($old);
        $this->get('/cron/'.$old)->assertNotFound();
        $this->get('/cron/'.$new)->assertOk();
    });
});

describe('uploads without a storage link', function () {
    it('serves public files itself, only known types and only inside the public disk', function () {
        Storage::disk('public')->put('t/ok.webp', 'webp-bytes');
        Storage::disk('public')->put('t/evil.php', '<?php echo 1;');
        $this->get('/storage/t/ok.webp')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/storage/t/evil.php')->assertNotFound();
        $this->get('/storage/t/missing.webp')->assertNotFound();
        $this->get('/storage/../../.env')->assertNotFound();
        $this->get('/storage/t/%2e%2e/%2e%2e/.env.png')->assertNotFound();
        Storage::disk('public')->deleteDirectory('t');
    });
});
