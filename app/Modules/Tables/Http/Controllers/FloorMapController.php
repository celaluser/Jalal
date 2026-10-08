<?php

namespace App\Modules\Tables\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Branches\Services\BranchContext;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Tables\Models\Area;
use App\Modules\Tables\Models\DiningTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** Floor map: tables as shapes on a canvas. Managers arrange them; waiters see which are free, busy, ready or unpaid. */
class FloorMapController extends Controller
{
    public const SHAPES = ['square', 'round', 'wide'];

    public function show(Request $request): View
    {
        $user = $request->user();
        $areaId = $request->query('area');
        $tables = $this->tables($areaId);
        $canArrange = $user->can('tables.manage');
        $canOrder = $user->can('orders.create');

        return view('tables::map.index', [
            'areas' => Area::orderBy('sort')->orderBy('id')->get(),
            'areaId' => ctype_digit((string) $areaId) ? (int) $areaId : null,
            'config' => [
                'tables' => $tables->map(fn (DiningTable $t, int $i) => $this->present($t, $i))->all(),
                'statusUrl' => route('tables.map.status'),
                'saveUrl' => $canArrange ? route('tables.map.save') : null,
                'posUrl' => $canOrder ? route('orders.pos.index') : null,
                'orderUrl' => $user->can('orders.view') ? url('/orders') : null,
                'csrf' => csrf_token(),
                'area' => ctype_digit((string) $areaId) ? (int) $areaId : null,
            ],
            'canArrange' => $canArrange,
        ]);
    }

    /** Live state of every table, polled by the map. free | busy (open order) | ready (food ready to serve) | unpaid (served, not paid). */
    public function status(Request $request): JsonResponse
    {
        $orders = Order::where('type', 'dine_in')->whereNotNull('table_id')
            ->where(fn ($q) => $q->whereIn('status', OrderStatus::OPEN)->orWhere(fn ($q) => $q->where('status', OrderStatus::COMPLETED)->whereNull('paid_at')->where('completed_at', '>=', now()->subHours(6))))
            ->get(['id', 'table_id', 'status', 'paid_at', 'total_cents', 'created_at']);

        $states = [];

        foreach ($orders->groupBy('table_id') as $tableId => $group) {
            $state = $group->contains('status', OrderStatus::READY) ? 'ready'
                : ($group->contains(fn ($o) => in_array($o->status, OrderStatus::OPEN, true)) ? 'busy' : 'unpaid');
            $states[$tableId] = ['state' => $state, 'orders' => $group->count(), 'since' => $group->min('created_at')?->toIso8601String(), 'total' => $group->whereNull('paid_at')->sum('total_cents')];
        }

        return response()->json(['states' => (object) $states, 'now' => now()->toIso8601String()])->header('Cache-Control', 'no-store');
    }

    /** Positions and shapes from the arrange mode. Ids of other restaurants are ignored by the tenant scope. */
    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tables' => ['required', 'array', 'max:500'],
            'tables.*.id' => ['required', 'integer'],
            'tables.*.x' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'tables.*.y' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'tables.*.shape' => ['nullable', 'in:'.implode(',', self::SHAPES)],
        ]);

        foreach ($data['tables'] as $row) {
            DiningTable::where('id', $row['id'])->update(['map_x' => $row['x'] ?? null, 'map_y' => $row['y'] ?? null, 'shape' => $row['shape'] ?? 'square']);
        }

        return response()->json(['saved' => count($data['tables'])]);
    }

    /** @return Collection<int, DiningTable> */
    private function tables(mixed $areaId)
    {
        $branchId = app(BranchContext::class)->currentId();

        return DiningTable::where('is_active', true)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when(ctype_digit((string) $areaId), fn ($q) => $q->where('area_id', (int) $areaId))
            ->orderBy('sort')->orderBy('id')->get();
    }

    /** Tables never placed get a tidy grid position so they show up and can be dragged into place. */
    private function present(DiningTable $t, int $i): array
    {
        $placed = $t->map_x !== null && $t->map_y !== null;

        return [
            'id' => $t->id, 'name' => table_label($t->name), 'seats' => $t->seats, 'shape' => in_array($t->shape, self::SHAPES, true) ? $t->shape : 'square',
            'x' => $placed ? (int) $t->map_x : 60 + ($i % 6) * 150, 'y' => $placed ? (int) $t->map_y : 60 + intdiv($i, 6) * 170, 'placed' => $placed,
        ];
    }
}
