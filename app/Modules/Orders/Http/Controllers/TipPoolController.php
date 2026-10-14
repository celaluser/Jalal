<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Services\ReportService;
use App\Modules\Orders\Services\TipPool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** The tip pool: what was collected in a period and how it divides between the team. */
class TipPoolController extends Controller
{
    public function __construct(private readonly TipPool $pool, private readonly ReportService $reports) {}

    public function index(Request $request): View
    {
        return view('orders::tips.index', $this->data($request));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['weights' => ['nullable', 'array', 'max:500'], 'weights.*' => ['nullable', 'numeric', 'min:0', 'max:100'], 'house_percent' => ['nullable', 'numeric', 'min:0', 'max:100']]);
        $this->pool->save($request->user()->restaurant, $data['weights'] ?? [], (float) ($data['house_percent'] ?? 0));

        return redirect()->route('tips.index', $request->only(['range', 'from', 'to']))->with('status', __('admin.saved'));
    }

    public function export(Request $request): Response
    {
        $d = $this->data($request);
        $money = fn (int $c) => number_format($c / 100, 2, '.', '');
        $rows = [[__('tips.staff'), __('tips.weight'), __('tips.share')]];

        foreach ($d['rows'] as $r) {
            $rows[] = [$r['name'], $r['weight'], $money($r['share'])];
        }

        $rows[] = [__('tips.house'), '', $money($d['split']['house'])];
        $rows[] = [__('tips.collected'), '', $money($d['split']['total'])];

        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($out, array_map(fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'".$v : $v, $row), ',', '"', '');
        }

        rewind($out);

        return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="tip-pool-'.$d['period']['from']->toDateString().'_'.$d['period']['to']->toDateString().'.csv"']);
    }

    /** @return array<string, mixed> */
    private function data(Request $request): array
    {
        $restaurant = $request->user()->restaurant;
        $period = $this->reports->period($restaurant, $request->query('range', '30'), $request->query('from'), $request->query('to'));
        $weights = $this->pool->weights($restaurant);
        $house = $this->pool->housePercent($restaurant);
        $collected = $this->pool->collected($period['from']->startOfDay()->setTimezone('UTC'), $period['to']->endOfDay()->setTimezone('UTC'));
        $split = $this->pool->split($collected, $house, $weights);
        $rows = $this->pool->staff($restaurant)->map(fn ($u) => ['id' => $u->id, 'name' => $u->name ?: $u->email, 'weight' => $weights[$u->id] ?? 1.0, 'share' => $split['shares'][$u->id] ?? 0])->all();

        return ['restaurant' => $restaurant, 'period' => $period, 'rows' => $rows, 'split' => $split, 'house' => $house];
    }
}
