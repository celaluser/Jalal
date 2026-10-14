<?php

use App\Models\User;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Core\Models\Language;
use App\Modules\Tenancy\Models\Restaurant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

it('shows a calm page instead of a server error when the database has not caught up with the files', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_active' => true, 'is_default' => true]);
    $r = Restaurant::create(['name' => 'Late Cafe', 'slug' => 'late', 'locale' => 'en', 'onboarded_at' => now()]);
    $owner = User::factory()->create(['restaurant_id' => $r->id]);
    $reg = app(PermissionRegistrar::class);
    $reg->setPermissionsTeamId($r->id);
    $owner->assignRole(Permissions::OWNER);
    $reg->setPermissionsTeamId(config('tenancy.platform_team_id'));

    $this->actingAs($owner)->get(route('billing.index'))->assertOk();

    // The files are newer than the database: a table the new code reads does not exist yet.
    Schema::drop('saved_cards');

    $this->actingAs($owner)->get(route('billing.index'))->assertStatus(503)->assertSee(__('update_pending.title'))->assertDontSee(__('update_pending.admin_hint'))->assertHeader('Retry-After', '60');
    $this->actingAs($owner)->getJson(route('billing.index'))->assertStatus(503)->assertJsonPath('message', 'The site is finishing an update. Please try again in a minute.');

    // Real errors keep being errors.
    $this->withoutExceptionHandling();
    expect(fn () => DB::select('select nonsense from'))->toThrow(QueryException::class);
});
