<?php

namespace App\Modules\Menu\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Menu\Services\CsvMenuImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Upload a spreadsheet, check the preview, then apply it. */
class MenuImportController extends Controller
{
    public function __construct(private readonly CsvMenuImport $import) {}

    public function show(Request $request): View
    {
        return view('menu::import.index', ['preview' => $request->session()->get('menu_import_preview')]);
    }

    public function preview(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:1024']]);

        try {
            $result = $this->import->parse($request->file('file')->getRealPath(), $request->user()->restaurant);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['file' => __('menu.import_error_'.$e->getMessage())]);
        }

        $request->session()->put('menu_import_rows', $result['rows']);
        $request->session()->put('menu_import_preview', array_diff_key($result, ['rows' => 1]) + ['sample' => array_slice($result['rows'], 0, 40), 'total' => count($result['rows'])]);

        return redirect()->route('menu.import');
    }

    public function apply(Request $request): RedirectResponse
    {
        $rows = $request->session()->get('menu_import_rows');

        if (! $rows) {
            return redirect()->route('menu.import');
        }

        try {
            $done = $this->import->apply($request->user()->restaurant, $rows);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('menu.import')->withErrors(['file' => __('menu.'.str_replace('limit_', 'limit_', $e->getMessage()))]);
        }

        $request->session()->forget(['menu_import_rows', 'menu_import_preview']);

        return redirect()->route('menu.index')->with('status', __('menu.import_done', $done));
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(['menu_import_rows', 'menu_import_preview']);

        return redirect()->route('menu.import');
    }

    public function sample(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Category', 'Dish', 'Description', 'Price', 'Stock', 'Available', 'Calories'], ',', '"', '');
            fputcsv($out, ['Starters', 'Bruschetta', 'Grilled bread, tomato, basil', '7.50', '', 'yes', '210'], ',', '"', '');
            fputcsv($out, ['Drinks', 'Lemonade', 'Fresh and cold', '3.00', '40', 'yes', ''], ',', '"', '');
            fclose($out);
        }, 'menu-sample.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
