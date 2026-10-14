<?php

namespace App\Modules\Tables\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tables\Qr\QrStyle;
use App\Modules\Tables\Services\QrPackager;
use App\Modules\Tables\Services\TableQr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** QR design, live preview and downloads (single code, ZIP, printable PDF sheet). */
class QrController extends Controller
{
    public function __construct(private readonly TableQr $qr, private readonly QrPackager $packager) {}

    public function show(Request $request): View
    {
        $restaurant = $request->user()->restaurant;

        return view('tables::qr.show', [
            'restaurant' => $restaurant,
            'settings' => $this->qr->settings($restaurant),
            'hasLogo' => $restaurant->logo_media_id !== null,
            'caption' => $this->qr->caption($restaurant),
            'tableCount' => DiningTable::count(),
            'previewUrl' => $this->qr->url($restaurant),
            'svg' => $this->qr->svg($restaurant),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;
        $data = $this->validatedStyle($request);

        $branding = $restaurant->branding ?? [];
        $branding['qr'] = [
            'fg' => strtolower($data['fg']), 'bg' => strtolower($data['bg']), 'shape' => $data['shape'],
            'logo' => $request->boolean('logo') && $restaurant->logo_media_id !== null,
            'caption' => trim((string) ($data['caption'] ?? '')) ?: null,
            'frame' => $data['frame'] ?? 'none', 'template' => $data['template'] ?? 'cards',
        ];
        $restaurant->update(['branding' => $branding]);

        return back()->with('status', __('admin.saved'));
    }

    /** SVG of the restaurant code with unsaved form values, for the live preview. */
    public function preview(Request $request): Response
    {
        $restaurant = $request->user()->restaurant;
        $data = $this->validatedStyle($request);
        $style = $this->qr->style($restaurant, ['fg' => $data['fg'], 'bg' => $data['bg'], 'shape' => $data['shape'], 'logo' => $request->boolean('logo')]);

        return response($this->qr->svg($restaurant, null, $style), 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'no-store']);
    }

    /** A single code. {table} is a table id, or "menu" for the restaurant-wide code. */
    public function download(Request $request, string $target, string $format): Response
    {
        $restaurant = $request->user()->restaurant;
        $table = $target === 'menu' ? null : DiningTable::findOrFail($target);
        $name = $table ? Str::slug($table->name) ?: 'table' : 'menu';

        return $format === 'svg'
            ? response($this->qr->svg($restaurant, $table), 200, ['Content-Type' => 'image/svg+xml', 'Content-Disposition' => "attachment; filename=\"qr-{$name}.svg\""])
            : response($this->qr->png($restaurant, $table), 200, ['Content-Type' => 'image/png', 'Content-Disposition' => "attachment; filename=\"qr-{$name}.png\""]);
    }

    public function zip(Request $request, string $format): BinaryFileResponse
    {
        $restaurant = $request->user()->restaurant;
        $tables = DiningTable::orderBy('sort')->orderBy('id')->get();
        abort_if($tables->isEmpty(), 404);

        return response()->download($this->packager->zip($restaurant, $tables, $format), "qr-codes-{$restaurant->slug}.zip")->deleteFileAfterSend();
    }

    public function pdf(Request $request)
    {
        $restaurant = $request->user()->restaurant;
        $ids = array_filter(array_map('intval', (array) $request->query('tables', [])));
        $tables = DiningTable::with('area')->when($ids, fn ($q) => $q->whereIn('id', $ids))->orderBy('sort')->orderBy('id')->get();
        abort_if($tables->isEmpty(), 404);

        return $this->packager->pdf($restaurant, $tables, $request->query('template'), $request->query('frame'))->download("qr-sheet-{$restaurant->slug}.pdf");
    }

    /** NFC tags: one link per table to write onto a tag, as a list and as a CSV for bulk encoders. */
    public function nfc(Request $request): View
    {
        $restaurant = $request->user()->restaurant;

        return view('tables::qr.nfc', ['restaurant' => $restaurant, 'links' => DiningTable::orderBy('sort')->orderBy('id')->get()->map(fn ($t) => ['name' => $t->name, 'url' => $this->qr->url($restaurant, $t)])->all(), 'menuUrl' => $this->qr->url($restaurant)]);
    }

    public function nfcCsv(Request $request): Response
    {
        $restaurant = $request->user()->restaurant;
        $rows = [['name', 'url']];

        foreach (DiningTable::orderBy('sort')->orderBy('id')->get() as $t) {
            // Names starting with = + - @ would run as formulas in a spreadsheet.
            $name = preg_match('/^[=+\-@\t\r]/', $t->name) ? "'".$t->name : $t->name;
            $rows[] = [$name, $this->qr->url($restaurant, $t)];
        }

        $csv = implode("\n", array_map(fn ($r) => implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', $v).'"', $r)), $rows));

        return response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="nfc-links-'.$restaurant->slug.'.csv"']);
    }

    /** @return array<string, mixed> */
    private function validatedStyle(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'fg' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'bg' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'shape' => ['required', Rule::in(QrStyle::SHAPES)],
            'caption' => ['nullable', 'string', 'max:60'],
            'frame' => ['nullable', Rule::in(TableQr::FRAMES)],
            'template' => ['nullable', Rule::in(array_keys(TableQr::TEMPLATES))],
        ]);

        // Dark-on-light with enough contrast, or phone cameras struggle with the printed code.
        $validator->after(function ($v) {
            $d = $v->getData();

            if (! $v->errors()->hasAny(['fg', 'bg']) && ! QrStyle::isScannable($d['fg'], $d['bg'])) {
                $v->errors()->add('fg', __('tables.low_contrast'));
            }
        });

        $data = $validator->validate();

        return $data;
    }
}
