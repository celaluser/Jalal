<?php

use App\Modules\Core\Tenancy\BelongsToRestaurant;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Minimal tenant-owned model, defined for the test only. */
class IsolationProbe extends Model
{
    use BelongsToRestaurant;

    protected $table = 'isolation_probes';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('isolation_probes', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('restaurant_id')->index();
        $table->string('label');
        $table->timestamps();
    });

    $this->a = Restaurant::create(['name' => 'A', 'slug' => 'a']);
    $this->b = Restaurant::create(['name' => 'B', 'slug' => 'b']);
    $this->ctx = app(TenantContext::class);

    $this->ctx->runAs($this->a, fn () => IsolationProbe::create(['label' => 'a-row']));
    $this->ctx->runAs($this->b, fn () => IsolationProbe::create(['label' => 'b-row']));
});

it('only returns rows of the current tenant', function () {
    $labels = $this->ctx->runAs($this->a, fn () => IsolationProbe::pluck('label')->all());

    expect($labels)->toBe(['a-row']);
});

it('cannot load another tenant\'s row by id', function () {
    $bRowId = $this->ctx->runAs($this->b, fn () => IsolationProbe::first()->id);

    $found = $this->ctx->runAs($this->a, fn () => IsolationProbe::find($bRowId));

    expect($found)->toBeNull();
});

it('cannot update or delete another tenant\'s rows', function () {
    $this->ctx->runAs($this->a, function () {
        expect(IsolationProbe::query()->update(['label' => 'hacked']))->toBe(1);
        expect(IsolationProbe::query()->delete())->toBe(1);
    });

    $this->ctx->bypass(function () {
        $row = IsolationProbe::where('restaurant_id', $this->b->id)->first();
        expect($row)->not->toBeNull()->and($row->label)->toBe('b-row');
    });
});

it('fails closed when no tenant is set', function () {
    expect(IsolationProbe::count())->toBe(0);
});

it('sees everything only inside an explicit bypass', function () {
    expect($this->ctx->bypass(fn () => IsolationProbe::count()))->toBe(2);
    expect(IsolationProbe::count())->toBe(0);
});

it('stamps restaurant_id from the context on create', function () {
    $row = $this->ctx->runAs($this->a, fn () => IsolationProbe::create(['label' => 'new']));

    expect($row->restaurant_id)->toBe($this->a->id);
});

it('refuses to create a row without any tenant', function () {
    IsolationProbe::create(['label' => 'orphan']);
})->throws(LogicException::class);

it('restores the previous tenant after runAs', function () {
    $this->ctx->set($this->a);
    $this->ctx->runAs($this->b, fn () => null);

    expect($this->ctx->id())->toBe($this->a->id);
});
