<?php

namespace App\Modules\Menu\Services;

use InvalidArgumentException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Reads the first sheet of an .xlsx file as CSV text, so the menu import can treat Excel and CSV the same way.
 * Only ext-zip and SimpleXML are needed. A spreadsheet is a zip file, so its unpacked size is capped before
 * anything is read, which stops a zip bomb.
 */
class XlsxReader
{
    private const MAX_UNPACKED = 8_388_608;

    /**
     * @return string CSV text, comma separated
     *
     * @throws InvalidArgumentException code: unreadable | too_big
     */
    public function toCsv(string $path): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new InvalidArgumentException('unreadable');
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('unreadable');
        }

        try {
            $sheet = $this->firstSheetName($zip);
            $stat = $zip->statName($sheet);
            $strings = $zip->statName('xl/sharedStrings.xml');

            if ($stat === false || $stat['size'] > self::MAX_UNPACKED || ($strings && $strings['size'] > self::MAX_UNPACKED)) {
                throw new InvalidArgumentException($stat === false ? 'unreadable' : 'too_big');
            }

            $shared = $strings ? $this->sharedStrings((string) $zip->getFromName('xl/sharedStrings.xml')) : [];
            $xml = $this->xml((string) $zip->getFromName($sheet));
        } finally {
            $zip->close();
        }

        $out = fopen('php://temp', 'r+');

        foreach ($xml->sheetData->row ?? [] as $row) {
            $cells = [];

            foreach ($row->c as $c) {
                $col = $this->column((string) $c['r']);
                $type = (string) $c['t'];
                $value = match ($type) {
                    's' => $shared[(int) $c->v] ?? '',
                    'inlineStr' => $this->text($c->is),
                    'b' => (string) $c->v === '1' ? '1' : '0',
                    default => (string) $c->v,
                };

                $cells[$col] = $value;
            }

            if ($cells === []) {
                continue;
            }

            $line = [];

            for ($i = 0; $i <= max(array_keys($cells)); $i++) {
                $line[] = $cells[$i] ?? '';
            }

            fputcsv($out, $line, ',', '"', '');
        }

        rewind($out);

        return (string) stream_get_contents($out);
    }

    private function firstSheetName(ZipArchive $zip): string
    {
        // Normally xl/worksheets/sheet1.xml, but the first sheet can be named differently.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                $names[] = $name;
            }
        }

        if (empty($names)) {
            throw new InvalidArgumentException('unreadable');
        }

        sort($names, SORT_NATURAL);

        return in_array('xl/worksheets/sheet1.xml', $names, true) ? 'xl/worksheets/sheet1.xml' : $names[0];
    }

    /** @return list<string> */
    private function sharedStrings(string $raw): array
    {
        $xml = $this->xml($raw);
        $strings = [];

        foreach ($xml->si ?? [] as $si) {
            $strings[] = $this->text($si);
        }

        return $strings;
    }

    /** Plain and rich-text strings both: the text of every run, joined. */
    private function text(?SimpleXMLElement $node): string
    {
        if ($node === null) {
            return '';
        }

        if (isset($node->t)) {
            return (string) $node->t;
        }

        $text = '';

        foreach ($node->r ?? [] as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    private function xml(string $raw): SimpleXMLElement
    {
        // Spreadsheet XML never needs entities or network access.
        $xml = @simplexml_load_string($raw, SimpleXMLElement::class, LIBXML_NONET);

        if ($xml === false) {
            throw new InvalidArgumentException('unreadable');
        }

        return $xml;
    }

    /** "C7" => 2 (zero based column index). */
    private function column(string $ref): int
    {
        preg_match('/^[A-Z]+/', strtoupper($ref), $m);
        $n = 0;

        foreach (str_split($m[0] ?? 'A') as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return max(0, $n - 1);
    }
}
