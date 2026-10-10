<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Branches\Services\BranchContext;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Support\OrderStatus;
use App\Modules\Orders\Support\OrderType;
use App\Modules\Tables\Models\DiningTable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Every order ever taken, with filters: date, status, type, payment, where it came from, a table, or a name/number/phone. */
class OrderHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $restaurant = $request->user()->restaurant;
        $query = $this->filtered($request);

        return view('orders::board.history', [
            'orders' => (clone $query)->latest('id')->paginate(25)->withQueryString(),
            'totals' => ['count' => (clone $query)->count(), 'sum' => (int) (clone $query)->where('status', '!=', OrderStatus::CANCELLED)->sum('total_cents'), 'tips' => (int) (clone $query)->sum('tip_cents')],
            'filters' => $request->only(['q', 'status', 'type', 'paid', 'source', 'from', 'to', 'table']),
            'restaurant' => $restaurant, 'tables' => DiningTable::orderBy('sort')->orderBy('id')->get(['id', 'name']),
        ]);
    }

    /** The same filters as a spreadsheet. Cells that could run as formulas are neutralised. */
    public function export(Request $request): Response
    {
        $restaurant = $request->user()->restaurant;
        $tz = in_array($restaurant->timezone, timezone_identifiers_list(), true) ? $restaurant->timezone : 'UTC';
        $safe = fn (?string $v) => $v !== null && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : (string) $v;
        $rows = [['number', 'date', 'status', 'type', 'source', 'table', 'customer', 'phone', 'subtotal', 'discount', 'total', 'tip', 'paid', 'method']];

        $this->filtered($request)->latest('id')->limit(5000)->get()->each(function (Order $o) use (&$rows, $tz, $safe) {
            $rows[] = [$o->number, $o->created_at->setTimezone($tz)->format('Y-m-d H:i'), $o->status, $o->type, $o->source, $safe($o->table_name), $safe($o->customer_name), $safe($o->customer_phone),
                number_format($o->subtotal_cents / 100, 2, '.', ''), number_format(($o->discount_cents + $o->manual_discount_cents) / 100, 2, '.', ''), number_format($o->total_cents / 100, 2, '.', ''), number_format($o->tip_cents / 100, 2, '.', ''), $o->isPaid() ? 'yes' : 'no', $o->payment_method];
        });

        $csv = "\xEF\xBB\xBF".implode("\n", array_map(fn ($r) => implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', $r)), $rows));

        return response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="orders-'.$restaurant->slug.'.csv"']);
    }

    /** @return Builder<Order> */
    private function filtered(Request $request): Builder
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:80'], 'status' => ['nullable', 'in:'.implode(',', [...OrderStatus::FLOW, OrderStatus::CANCELLED])], 'type' => ['nullable', 'in:'.implode(',', OrderType::ALL)],
            'paid' => ['nullable', 'in:yes,no'], 'source' => ['nullable', 'in:qr,staff,kiosk'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'table' => ['nullable', 'integer'],
        ]);
        $tz = in_array($request->user()->restaurant->timezone, timezone_identifiers_list(), true) ? $request->user()->restaurant->timezone : 'UTC';
        $branch = app(BranchContext::class)->currentId($request->user());

        return Order::query()
            ->when($branch, fn ($q) => $q->where('branch_id', $branch))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($f['source'] ?? null, fn ($q, $v) => $q->where('source', $v))
            ->when($f['table'] ?? null, fn ($q, $v) => $q->where('table_id', $v))
            ->when(($f['paid'] ?? null) === 'yes', fn ($q) => $q->whereNotNull('paid_at'))
            ->when(($f['paid'] ?? null) === 'no', fn ($q) => $q->whereNull('paid_at'))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', CarbonImmutable::parse($v, $tz)->startOfDay()->utc()))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', CarbonImmutable::parse($v, $tz)->endOfDay()->utc()))
            ->when($f['q'] ?? null, function ($q, $v) {
                $term = ltrim(trim($v), '#');
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
                $q->where(function ($w) use ($term, $like) {
                    // A number could be an order number, a table or part of a phone number.
                    if (ctype_digit($term)) {
                        $w->where('number', (int) $term)->orWhere('table_name', $term);
                    }

                    $w->orWhere('customer_name', 'like', $like)->orWhere('customer_phone', 'like', $like)->orWhere('table_name', 'like', $like);
                });
            });
    }
}
