<?php

namespace App\Modules\Tables\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Tables\Models\Area;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tables\Services\TableQr;
use App\Modules\Tables\Services\TableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TableController extends Controller
{
    public function __construct(private readonly TableService $tables, private readonly TableQr $qr) {}

    public function index(Request $request, LimitGuard $limits): View
    {
        $restaurant = $request->user()->restaurant;
        $areas = Area::orderBy('sort')->orderBy('id')->get();
        $areaId = $request->query('area');
        $tables = DiningTable::with('area')
            ->when($areaId === 'none', fn ($q) => $q->whereNull('area_id'))
            ->when(ctype_digit((string) $areaId), fn ($q) => $q->where('area_id', (int) $areaId))
            ->orderBy('sort')->orderBy('id')->get();

        return view('tables::tables.index', [
            'restaurant' => $restaurant,
            'tables' => $tables,
            'areas' => $areas,
            'areaFilter' => $areaId,
            'total' => DiningTable::count(),
            'limit' => $limits->limit($restaurant, 'tables'),
            'remaining' => $this->tables->remaining($restaurant),
            'qr' => $this->qr,
            'style' => $this->qr->style($restaurant),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;

        if ($this->tables->remaining($restaurant) === 0) {
            return back()->withInput()->withErrors(['limit' => __('tables.limit_reached')]);
        }

        DiningTable::create($this->validated($request) + ['sort' => (int) DiningTable::max('sort') + 1]);

        return back()->with('status', __('tables.created'));
    }

    public function bulk(Request $request): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;
        $data = $request->validate([
            'prefix' => ['nullable', 'string', 'max:30'],
            'from' => ['required', 'integer', 'min:1', 'max:9999'],
            'to' => ['required', 'integer', 'gte:from', 'max:9999'],
            'area_id' => ['nullable', $this->areaRule()],
            'seats' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        if ($data['to'] - $data['from'] + 1 > TableService::BULK_MAX) {
            return back()->withInput()->withErrors(['to' => __('tables.bulk_too_many', ['max' => TableService::BULK_MAX])]);
        }

        $remaining = $this->tables->remaining($restaurant);
        $prefix = trim($data['prefix'] ?? '') ?: __('tables.default_prefix');
        $wanted = collect(range($data['from'], $data['to']))->map(fn ($n) => "{$prefix} {$n}")->diff(DiningTable::pluck('name'))->count();

        if ($remaining !== null && $wanted > $remaining) {
            return back()->withInput()->withErrors(['limit' => __('tables.limit_bulk', ['count' => $remaining])]);
        }

        $result = $this->tables->bulkCreate($prefix, (int) $data['from'], (int) $data['to'], $data['area_id'] ?? null, $data['seats'] ?? null);

        return back()->with('status', __('tables.bulk_done', $result));
    }

    public function edit(DiningTable $table): View
    {
        return view('tables::tables.edit', ['table' => $table, 'areas' => Area::orderBy('sort')->get()]);
    }

    public function update(Request $request, DiningTable $table): RedirectResponse
    {
        $table->update($this->validated($request, $table) + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('tables.index')->with('status', __('admin.saved'));
    }

    public function destroy(DiningTable $table): RedirectResponse
    {
        $table->delete();

        return redirect()->route('tables.index')->with('status', __('tables.deleted'));
    }

    /** Issue a new token: printed QR codes of this table stop working. */
    public function regenerate(DiningTable $table): RedirectResponse
    {
        $table->regenerateToken();

        return back()->with('status', __('tables.regenerated'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?DiningTable $table = null): array
    {
        $tenant = app(TenantContext::class)->id();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('dining_tables', 'name')->where('restaurant_id', $tenant)->ignore($table?->id)],
            'area_id' => ['nullable', $this->areaRule()],
            'seats' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        return ['name' => trim($data['name']), 'area_id' => $data['area_id'] ?? null, 'seats' => $data['seats'] ?? null];
    }

    private function areaRule()
    {
        return Rule::exists('areas', 'id')->where('restaurant_id', app(TenantContext::class)->id());
    }
}
