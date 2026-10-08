<?php

namespace App\Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Services\MenuEngineering;
use App\Modules\Analytics\Services\ReportService;
use App\Modules\Billing\Services\LimitGuard;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Sales reports. The full set needs the plan's analytics feature; every plan gets the headline numbers for the last week. */
class ReportController extends Controller
{
    /** Days shown to plans without the analytics feature. */
    private const FREE_DAYS = 7;

    public function __construct(private readonly ReportService $reports, private readonly LimitGuard $limits) {}

    public function index(Request $request): View
    {
        $restaurant = $request->user()->restaurant;
        $full = $this->limits->hasFeature($restaurant, 'analytics');
        $period = $this->reports->period($restaurant, $request->query('range'), $request->query('from'), $request->query('to'), $full ? ReportService::MAX_DAYS : self::FREE_DAYS);

        return view('analytics::index', [
            'restaurant' => $restaurant, 'full' => $full, 'period' => $period, 'report' => $this->reports->build($restaurant, $period, $full),
        ]);
    }

    /** Which dishes to push, reprice or drop (needs the analytics feature and cost prices on the dishes). */
    public function menu(Request $request, MenuEngineering $engineering): View
    {
        $restaurant = $request->user()->restaurant;
        abort_unless($this->limits->hasFeature($restaurant, 'analytics'), 403);
        $period = $this->reports->period($restaurant, in_array($request->query('range'), ['7', '30', '90'], true) ? $request->query('range') : '30');

        return view('analytics::menu', ['restaurant' => $restaurant, 'period' => $period, 'data' => $engineering->build($restaurant, $period)]);
    }

    public function menuExport(Request $request, MenuEngineering $engineering): StreamedResponse
    {
        $restaurant = $request->user()->restaurant;
        abort_unless($this->limits->hasFeature($restaurant, 'analytics'), 403);
        $period = $this->reports->period($restaurant, in_array($request->query('range'), ['7', '30', '90'], true) ? $request->query('range') : '30');
        $data = $engineering->build($restaurant, $period);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Dish', 'Category', 'Sold', 'Revenue', 'Cost', 'Margin', 'Margin per portion', 'Class']);
            $safe = fn ($v) => is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) ? "'".$v : $v;
            $money = fn (int $c) => number_format($c / 100, 2, '.', '');

            foreach ($data['items'] as $i) {
                fputcsv($out, [$safe($i['name']), $safe($i['category']), $i['qty'], $money($i['revenue']), $money($i['cost']), $money($i['margin']), $money($i['unit_margin']), $i['class']]);
            }

            foreach ($data['unclassified'] as $i) {
                fputcsv($out, [$safe($i['name']), $safe($i['category']), $i['qty'], $money($i['revenue']), '', '', '', 'no_cost']);
            }

            fclose($out);
        }, 'menu-engineering.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function export(Request $request): StreamedResponse
    {
        $restaurant = $request->user()->restaurant;
        abort_unless($this->limits->hasFeature($restaurant, 'analytics'), 403);
        $period = $this->reports->period($restaurant, $request->query('range'), $request->query('from'), $request->query('to'));

        return response()->streamDownload(function () use ($restaurant, $period) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Order', 'Placed', 'Status', 'Type', 'Source', 'Payment', 'Paid', 'Items', 'Discount', 'Service', 'Delivery', 'Tax', 'Total', 'Promo code']);

            foreach ($this->reports->orderRows($restaurant, $period) as $row) {
                fputcsv($out, array_map(fn ($v) => is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) ? "'".$v : $v, $row));
            }

            fclose($out);
        }, 'orders-'.$period['from']->toDateString().'_'.$period['to']->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
