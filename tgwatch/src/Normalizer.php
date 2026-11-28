<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * 𝐓𝐡𝐫𝐞𝐚𝐝𝐬, 𝘛𝘩𝘳𝘦𝘢𝘥𝘴, 𝕋𝕙𝕣𝕖𝕒𝕕𝕤, Ｔｈｒｅａｄｓ gibi süslü Unicode harfleri normal ASCII'ye çevirir.
 * Emojiler ve diğer karakterler olduğu gibi kalır.
 */
final class Normalizer
{
    /** U+1D400..U+1D6A3 aralığında her biri 52 harflik (A-Z, a-z) bloklar. */
    private const LETTER_BLOCKS = [
        0x1D400, 0x1D434, 0x1D468, 0x1D49C, 0x1D4D0, 0x1D504, 0x1D538,
        0x1D56C, 0x1D5A0, 0x1D5D4, 0x1D608, 0x1D63C, 0x1D670,
    ];

    /** Matematiksel alfabedeki "delikler" Letterlike Symbols bloğundadır. */
    private const HOLES = [
        'ℎ' => 'h', 'ℬ' => 'B', 'ℰ' => 'E', 'ℱ' => 'F', 'ℋ' => 'H', 'ℐ' => 'I', 'ℒ' => 'L',
        'ℳ' => 'M', 'ℛ' => 'R', 'ℯ' => 'e', 'ℊ' => 'g', 'ℴ' => 'o', 'ℭ' => 'C', 'ℌ' => 'H',
        'ℑ' => 'I', 'ℜ' => 'R', 'ℨ' => 'Z', 'ℂ' => 'C', 'ℍ' => 'H', 'ℕ' => 'N', 'ℙ' => 'P',
        'ℚ' => 'Q', 'ℝ' => 'R', 'ℤ' => 'Z',
    ];

    public static function text(string $s): string
    {
        $s = strtr($s, self::HOLES);
        $s = preg_replace_callback('/[\x{1D400}-\x{1D7FF}\x{FF01}-\x{FF5E}\x{24B6}-\x{24E9}\x{1F130}-\x{1F189}]/u', static function (array $m): string {
            return self::char(mb_ord($m[0], 'UTF-8'));
        }, $s) ?? $s;
        // Sıfır genişlikli boşluklar, NBSP vb.
        $s = preg_replace('/[\x{200B}\x{200C}\x{2060}\x{FEFF}]/u', '', $s) ?? $s;
        $s = preg_replace('/[\x{00A0}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]/u', ' ', $s) ?? $s;
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        return $s;
    }

    private static function char(int $cp): string
    {
        if ($cp >= 0x1D400 && $cp <= 0x1D6A3) {
            foreach (self::LETTER_BLOCKS as $base) {
                if ($cp >= $base && $cp < $base + 52) {
                    $i = $cp - $base;
                    return chr($i < 26 ? 65 + $i : 97 + $i - 26);
                }
            }
        }
        if ($cp >= 0x1D7CE && $cp <= 0x1D7FF) {
            return chr(48 + ($cp - 0x1D7CE) % 10);
        }
        if ($cp >= 0xFF01 && $cp <= 0xFF5E) {
            return chr($cp - 0xFF01 + 0x21);
        }
        if ($cp >= 0x24B6 && $cp <= 0x24CF) { // Ⓐ-Ⓩ
            return chr(65 + $cp - 0x24B6);
        }
        if ($cp >= 0x24D0 && $cp <= 0x24E9) { // ⓐ-ⓩ
            return chr(97 + $cp - 0x24D0);
        }
        foreach ([0x1F130, 0x1F150, 0x1F170] as $base) { // kare/daire içinde büyük harfler
            if ($cp >= $base && $cp < $base + 26) {
                return chr(65 + $cp - $base);
            }
        }
        return mb_chr($cp, 'UTF-8');
    }
}
