<?php

namespace App\Modules\Orders\Support;

/**
 * Builds ESC/POS bytes for thermal receipt printers (Epson and the many compatibles). Text is converted to the printer's
 * code page; characters it cannot show are transliterated or dropped, so a ticket always prints.
 */
final class EscPos
{
    /** code page name => [iconv charset, ESC t number] */
    public const PAGES = ['cp437' => ['CP437', 0], 'cp850' => ['CP850', 2], 'cp857' => ['CP857', 13], 'cp1252' => ['CP1252', 16], 'ascii' => ['ASCII', 0]];

    private string $buffer = "\x1B@"; // initialise

    private string $charset;

    public function __construct(private readonly int $width = 48, string $codepage = 'cp437')
    {
        [$this->charset, $number] = self::PAGES[$codepage] ?? self::PAGES['cp437'];
        $this->buffer .= "\x1Bt".chr($number);
    }

    public function width(): int
    {
        return $this->width;
    }

    public function align(string $where): self
    {
        $this->buffer .= "\x1Ba".chr(['left' => 0, 'center' => 1, 'right' => 2][$where] ?? 0);

        return $this;
    }

    public function bold(bool $on = true): self
    {
        $this->buffer .= "\x1BE".chr($on ? 1 : 0);

        return $this;
    }

    /** Character size multiplier 1-4 (width and height). */
    public function size(int $multiplier = 1): self
    {
        $n = max(1, min(4, $multiplier)) - 1;
        $this->buffer .= "\x1D!".chr($n << 4 | $n);

        return $this;
    }

    public function text(string $line = ''): self
    {
        $this->buffer .= $this->encode($line)."\n";

        return $this;
    }

    /** Left text and right text on one line, padded to the paper width. The left part is cut if there is no room. */
    public function columns(string $left, string $right): self
    {
        $right = $this->encode($right);
        $room = max(1, $this->width - strlen($right) - 1);
        $left = $this->encode($left);
        $left = strlen($left) > $room ? substr($left, 0, $room) : $left;
        $this->buffer .= $left.str_repeat(' ', max(1, $this->width - strlen($left) - strlen($right))).$right."\n";

        return $this;
    }

    /** Wraps long text to the paper width. */
    public function wrapped(string $text, int $indent = 0): self
    {
        foreach (explode("\n", wordwrap($text, max(8, $this->width - $indent), "\n", true)) as $line) {
            $this->text(str_repeat(' ', $indent).$line);
        }

        return $this;
    }

    public function rule(string $char = '-'): self
    {
        $this->buffer .= str_repeat($char, $this->width)."\n";

        return $this;
    }

    public function feed(int $lines = 1): self
    {
        $this->buffer .= str_repeat("\n", $lines);

        return $this;
    }

    public function qr(string $data, int $module = 6): self
    {
        $data = substr($data, 0, 300);
        $this->buffer .= "\x1D(k\x04\x001A2\x00"                         // model 2
            ."\x1D(k\x03\x001C".chr(max(1, min(12, $module)))              // module size
            ."\x1D(k\x03\x001E1"                                            // error correction L
            ."\x1D(k".pack('v', strlen($data) + 3).'1P0'.$data              // store
            ."\x1D(k\x03\x001Q0";                                           // print

        return $this;
    }

    /** Feed and cut the paper. */
    public function cut(): self
    {
        $this->buffer .= "\n\n\n\x1DV\x42\x00";

        return $this;
    }

    public function bytes(): string
    {
        return $this->buffer;
    }

    private function encode(string $text): string
    {
        $out = @iconv('UTF-8', $this->charset.'//TRANSLIT//IGNORE', $text);

        return $out === false ? preg_replace('/[^\x20-\x7E]/', '?', $text) : $out;
    }
}
