<?php

namespace App\Modules\Tables\Services;

use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tenancy\Models\Restaurant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/** Bundles QR codes for printing: a ZIP of image files, or a PDF sheet of table cards. */
class QrPackager
{
    public function __construct(private readonly TableQr $qr) {}

    /**
     * @param  Collection<int, DiningTable>  $tables
     * @param  'png'|'svg'  $format
     * @return string path of a temporary ZIP; the caller deletes it after sending
     */
    public function zip(Restaurant $restaurant, Collection $tables, string $format): string
    {
        $path = tempnam(sys_get_temp_dir(), 'qr');
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create the ZIP file.');
        }

        $style = $this->qr->style($restaurant);
        $used = [];

        foreach ($tables as $table) {
            // File names come from table names, so strip everything but safe characters and keep them unique.
            $base = Str::slug($table->name) ?: 'table';
            $name = $base;
            for ($i = 2; isset($used[$name]); $i++) {
                $name = "{$base}-{$i}";
            }
            $used[$name] = true;

            $zip->addFromString("{$name}.{$format}", $format === 'svg' ? $this->qr->svg($restaurant, $table, $style) : $this->qr->png($restaurant, $table, 1024, $style));
        }

        $zip->close();

        return $path;
    }

    /**
     * A4 sheet of cards, two per row, each with the restaurant, the table and its QR code.
     *
     * @param  Collection<int, DiningTable>  $tables
     */
    public function pdf(Restaurant $restaurant, Collection $tables): \Barryvdh\DomPDF\PDF
    {
        $style = $this->qr->style($restaurant);

        $cards = $tables->map(fn (DiningTable $table) => [
            'name' => $table->name,
            'area' => $table->area?->name,
            'png' => base64_encode($this->qr->png($restaurant, $table, 600, $style)),
        ]);

        return Pdf::loadView('tables::qr.sheet', [
            'restaurant' => $restaurant,
            'cards' => $cards->chunk(2)->chunk(3),
            'caption' => $this->qr->caption($restaurant),
            'brand' => $restaurant->brandColor(),
        ])->setPaper('a4');
    }
}
