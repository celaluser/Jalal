<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Servis AÇIKLAMASI çevirisi (eklentiden gelir): tgwatch bankası + Google Translate + DeepSeek karması.
 *
 * Sıra (mod "mix"):
 *   1. Banka: aynı açıklama daha önce çevrildiyse bankadaki karşılık (kind = desc).
 *   2. Sözlük: açıklamada geçen sözlük terimleri (phrases) ve çevrilmeyecek markalar (keep) toplanır.
 *   3. Google Translate (Cloud Translation v2, API anahtarı): satır satır ham çeviri.
 *   4. DeepSeek (chat/completions): kaynak + Google taslağı + sözlük → son metin (satır sayısı, sayılar, bağlantılar korunur).
 *      Denetimden geçmezse ya da hata verirse Google taslağı kullanılır; o da yoksa çeviri yapılmaz.
 * Modlar: mix | google | deepseek | bank (yalnız banka). Ad kurallarındaki kelime kelime çeviri açıklamalara UYGULANMAZ
 * (uzun cümlelerde yarım çeviri üretir); makine çevirisi yoksa açıklama olduğu gibi kalır.
 * DENENMEDİ: gerçek Google / DeepSeek anahtarıyla istek yapılmadı (bu ortamdan erişim yok); istek ve cevap biçimleri
 * belgelerine göre yazıldı, testler sahte HTTP ile.
 */
final class DescTranslator
{
    public const MODES = [
        'mix' => 'Karma: banka + Google + DeepSeek (önerilen)',
        'google' => 'Yalnız Google Translate (+ banka)',
        'deepseek' => 'Yalnız DeepSeek (+ banka)',
        'bank' => 'Yalnız banka (makine çevirisi yok)',
    ];
    public const DEEPSEEK_MODELS = ['deepseek-chat' => 'DeepSeek-V3 (deepseek-chat)', 'deepseek-reasoner' => 'DeepSeek-R1 (deepseek-reasoner, yavaş)'];
    public const MAX_LEN = 5000;
    private const GOOGLE_URL = 'https://translation.googleapis.com/language/translate/v2';
    private const DEEPSEEK_URL = 'https://api.deepseek.com/chat/completions';

    public function __construct(private Db $db, private array $config = [])
    {
    }

    // ------------------------------------------------------------ ayarlar

    public function mode(): string
    {
        $m = (string)$this->db->get('desc_mode', 'mix');
        return isset(self::MODES[$m]) ? $m : 'mix';
    }
    public function googleKey(): string
    {
        $k = trim((string)$this->db->get('google_api_key', ''));
        return $k !== '' ? $k : trim((string)($this->config['google_api_key'] ?? ''));
    }
    public function deepseekKey(): string
    {
        $k = trim((string)$this->db->get('deepseek_api_key', ''));
        return $k !== '' ? $k : trim((string)($this->config['deepseek_api_key'] ?? ''));
    }
    public function deepseekModel(): string
    {
        $m = (string)$this->db->get('deepseek_model', 'deepseek-chat');
        return isset(self::DEEPSEEK_MODELS[$m]) ? $m : 'deepseek-chat';
    }
    public function monthlyLimit(): int
    {
        return max(0, (int)$this->db->get('desc_monthly_limit', '1000'));
    }
    public function usage(?string $month = null): array
    {
        $all = $this->db->getJson('desc_usage', []);
        return $all[$month ?? date('Y-m')] ?? ['google' => 0, 'deepseek' => 0, 'chars' => 0, 'bank' => 0];
    }
    private function addUsage(string $what, int $chars = 0): void
    {
        $all = $this->db->getJson('desc_usage', []);
        $k = date('Y-m');
        $u = $all[$k] ?? ['google' => 0, 'deepseek' => 0, 'chars' => 0, 'bank' => 0];
        $u[$what] = (int)($u[$what] ?? 0) + 1;
        $u['chars'] += $chars;
        $all[$k] = $u;
        $this->db->setJson('desc_usage', $all);
    }

    /** İstenen mod (eklentiden) ile ayarlı anahtarlara göre fiilen kullanılacak kaynaklar. */
    public function plan(?string $want = null): array
    {
        $m = $want !== null && isset(self::MODES[$want]) ? $want : $this->mode();
        return [
            'mode' => $m,
            'google' => in_array($m, ['mix', 'google'], true) && $this->googleKey() !== '',
            'deepseek' => in_array($m, ['mix', 'deepseek'], true) && $this->deepseekKey() !== '',
        ];
    }

    // ------------------------------------------------------------ çeviri

    /**
     * @return array{az:string, via:string, note:string}
     *   via: bank | mix | deepseek | google | already | none
     */
    public function translate(string $text, ?string $mode = null): array
    {
        $src = str_replace(["\r\n", "\r"], "\n", $text);
        if (trim($src) === '') {
            return ['az' => '', 'via' => 'none', 'note' => 'boş açıklama'];
        }
        if (mb_strlen($src) > self::MAX_LEN) {
            return ['az' => '', 'via' => 'none', 'note' => 'açıklama çok uzun (' . mb_strlen($src) . ' karakter, en çok ' . self::MAX_LEN . ')'];
        }
        if (preg_match('/[əƏ]/u', $src)) {
            return ['az' => '', 'via' => 'already', 'note' => 'açıklama zaten Azerice'];
        }
        $mem = new Memory($this->db);
        $hit = $mem->lookup('desc', $src);
        if ($hit !== null) {
            $this->addUsage('bank');
            return ['az' => $hit, 'via' => 'bank', 'note' => ''];
        }
        $plan = $this->plan($mode);
        if (!$plan['google'] && !$plan['deepseek']) {
            $why = $plan['mode'] === 'bank' ? 'mod "yalnız banka" ve bankada yok' : 'Google / DeepSeek anahtarı girilmemiş (Çeviri ayarları → Açıklama çevirisi)';
            return ['az' => '', 'via' => 'none', 'note' => $why];
        }
        $limit = $this->monthlyLimit();
        $u = $this->usage();
        if ($limit > 0 && ($u['google'] + $u['deepseek']) >= $limit) {
            return ['az' => '', 'via' => 'none', 'note' => "bu ayın açıklama çeviri sınırına ($limit) ulaşıldı"];
        }

        $lines = explode("\n", $src);
        $idx = []; // çevrilecek satırlar (harf içerenler)
        foreach ($lines as $i => $l) {
            if (preg_match('/\p{L}/u', $l)) {
                $idx[] = $i;
            }
        }
        if (!$idx) {
            return ['az' => '', 'via' => 'none', 'note' => 'çevrilecek yazı yok'];
        }
        $todo = array_map(static fn($i) => $lines[$i], $idx);
        $gloss = $this->glossary($src);
        $notes = [];

        $google = null;
        if ($plan['google']) {
            try {
                $google = $this->google($todo);
                $this->addUsage('google', mb_strlen(implode("\n", $todo)));
            } catch (\Throwable $e) {
                $notes[] = 'Google: ' . $e->getMessage();
                $this->db->log('warn', 'desc', 'Açıklama: Google Translate kullanılamadı: ' . $e->getMessage());
            }
        }
        $final = null; $via = '';
        if ($plan['deepseek']) {
            try {
                $ds = $this->deepseek($todo, $google, $gloss);
                $this->addUsage('deepseek');
                $bad = self::check($todo, $ds);
                if ($bad === '') {
                    $final = $ds;
                    $via = $google !== null ? 'mix' : 'deepseek';
                } else {
                    $notes[] = 'DeepSeek denetimi: ' . $bad;
                    $this->db->log('warn', 'desc', 'Açıklama: DeepSeek cevabı denetimden geçmedi (' . $bad . '), Google taslağı kullanıldı');
                }
            } catch (\Throwable $e) {
                $notes[] = 'DeepSeek: ' . $e->getMessage();
                $this->db->log('warn', 'desc', 'Açıklama: DeepSeek kullanılamadı: ' . $e->getMessage());
            }
        }
        if ($final === null && $google !== null && self::check($todo, $google) === '') {
            $final = $google;
            $via = 'google';
        }
        if ($final === null) {
            return ['az' => '', 'via' => 'none', 'note' => $notes ? implode('; ', $notes) : 'çevrilemedi'];
        }
        foreach ($idx as $k => $i) {
            $lines[$i] = $final[$k];
        }
        return ['az' => implode("\n", $lines), 'via' => $via, 'note' => implode('; ', $notes)];
    }

    /** Eklentinin doldurduğu (ya da kullanıcının düzelttiği) açıklamayı bankaya öğretir. */
    public function learn(string $en, string $az, string $origin = 'import'): bool
    {
        $en = str_replace(["\r\n", "\r"], "\n", $en);
        if (trim($en) === '' || trim($az) === '' || mb_strlen($en) > self::MAX_LEN || Learner::same($en, $az)) {
            return false;
        }
        (new Memory($this->db))->learn('desc', $en, $az, $origin);
        return true;
    }

    /**
     * Açıklamada geçen sözlük terimleri (en → az) ve çevrilmeyecek markalar.
     * @return array{terms: array<string,string>, keep: list<string>}
     */
    public function glossary(string $text): array
    {
        $dict = Dictionary::load($this->db);
        $low = mb_strtolower($text);
        $terms = [];
        $phrases = $dict['phrases'] ?? [];
        uksort($phrases, static fn($a, $b) => mb_strlen((string)$b) <=> mb_strlen((string)$a)); // uzun ifade önce
        foreach ($phrases as $en => $az) {
            $en = (string)$en;
            if ($en === '' || count($terms) >= 60) {
                continue;
            }
            if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote(mb_strtolower($en), '/') . '(?![\p{L}\p{N}])/u', $low)) {
                $terms[$en] = (string)$az;
            }
        }
        $keep = [];
        foreach ($dict['keep'] ?? [] as $w) {
            $w = (string)$w;
            if (mb_strlen($w) > 1 && preg_match('/(?<![\p{L}\p{N}])' . preg_quote(mb_strtolower($w), '/') . '(?![\p{L}\p{N}])/u', $low)) {
                $keep[] = $w;
            }
        }
        return ['terms' => $terms, 'keep' => array_slice($keep, 0, 40)];
    }

    /**
     * Denetim: satır sayısı aynı, her satırda sayılar ve bağlantılar korunmuş, boş satır yok.
     * @param list<string> $src
     * @param list<string> $out
     */
    public static function check(array $src, array $out): string
    {
        if (count($src) !== count($out)) {
            return 'satır sayısı ' . count($out) . ' ≠ ' . count($src);
        }
        foreach ($src as $i => $s) {
            $o = (string)$out[$i];
            if (trim($o) === '') {
                return ($i + 1) . '. satır boş';
            }
            $ns = self::nums($s); $no = self::nums($o);
            if ($ns !== $no) {
                return ($i + 1) . '. satırda sayılar değişmiş (' . implode(',', $ns) . ' → ' . implode(',', $no) . ')';
            }
            preg_match_all('~https?://\S+~u', $s, $us);
            foreach ($us[0] as $url) {
                if (!str_contains($o, rtrim($url, '.,;)'))) {
                    return ($i + 1) . '. satırda bağlantı değişmiş';
                }
            }
        }
        return '';
    }
    /** @return list<string> */
    private static function nums(string $s): array
    {
        preg_match_all('/\d+(?:[.,]\d+)*/u', $s, $m);
        $n = array_map(static fn($x) => str_replace(',', '.', $x), $m[0]);
        sort($n);
        return $n;
    }

    // ------------------------------------------------------------ dış servisler

    /** @param list<string> $lines @return list<string> */
    private function google(array $lines): array
    {
        $url = self::GOOGLE_URL . '?key=' . rawurlencode($this->googleKey());
        $r = Http::postJson($url, ['q' => $lines, 'target' => 'az', 'format' => 'text'], [], 40);
        if ($r['error'] !== '') {
            throw new \RuntimeException('bağlantı hatası: ' . $r['error']);
        }
        $d = json_decode($r['body'], true);
        if ($r['status'] !== 200 || !is_array($d)) {
            $msg = is_array($d) ? (string)($d['error']['message'] ?? '') : mb_substr($r['body'], 0, 160);
            throw new \RuntimeException('HTTP ' . $r['status'] . ($msg !== '' ? ': ' . $msg : ''));
        }
        $tr = $d['data']['translations'] ?? null;
        if (!is_array($tr) || count($tr) !== count($lines)) {
            throw new \RuntimeException('beklenmeyen cevap');
        }
        return array_map(static fn($t) => html_entity_decode((string)($t['translatedText'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $tr);
    }

    /**
     * @param list<string> $lines
     * @param list<string>|null $google
     * @param array{terms: array<string,string>, keep: list<string>} $gloss
     * @return list<string>
     */
    private function deepseek(array $lines, ?array $google, array $gloss): array
    {
        $system = "Sən SMM paneli üçün peşəkar tərcüməçisən. Xidmət AÇIQLAMASINI Azərbaycan dilinə tərcümə et.\n"
            . "Qaydalar:\n"
            . "- Hər giriş sətri üçün tam bir çıxış sətri qaytar (sətir sayı eyni olmalıdır), sətirləri birləşdirmə və bölmə.\n"
            . "- Bütün rəqəmləri, ölçüləri (K, M), faizləri, linkləri, emojiləri və işarələri olduğu kimi saxla.\n"
            . "- \"glossary\" terminlərini MƏCBURİ olaraq verilən kimi işlət; \"keep\" sözlərini (marka adları) tərcümə etmə.\n"
            . "- \"google\" sahəsi Google Translate qaralamasıdır: istinad kimi istifadə et, səhvlərini düzəlt, təbii və aydın yaz.\n"
            . "- Yalnız JSON qaytar: {\"lines\": [\"...\", ...]}";
        $user = json_encode(['lines' => array_values($lines), 'google' => $google, 'glossary' => (object)$gloss['terms'], 'keep' => $gloss['keep']], JSON_UNESCAPED_UNICODE);
        $body = [
            'model' => $this->deepseekModel(),
            'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
            'temperature' => 0.3,
            'response_format' => ['type' => 'json_object'],
            'stream' => false,
        ];
        $r = null;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $r = Http::postJson(self::DEEPSEEK_URL, $body, ['Authorization: Bearer ' . $this->deepseekKey()], 90);
            if ($r['error'] === '' && !in_array($r['status'], [429, 500, 502, 503], true)) {
                break;
            }
            if (Http::$fake === null) {
                sleep(3);
            }
        }
        if ($r['error'] !== '') {
            throw new \RuntimeException('bağlantı hatası: ' . $r['error']);
        }
        $d = json_decode($r['body'], true);
        if ($r['status'] !== 200 || !is_array($d)) {
            $msg = is_array($d) ? (string)($d['error']['message'] ?? '') : mb_substr($r['body'], 0, 160);
            throw new \RuntimeException('HTTP ' . $r['status'] . ($msg !== '' ? ': ' . $msg : ''));
        }
        $text = (string)($d['choices'][0]['message']['content'] ?? '');
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/u', '', trim($text)) ?? $text;
        $j = json_decode($text, true);
        if (!is_array($j) || !isset($j['lines']) || !is_array($j['lines'])) {
            throw new \RuntimeException('cevap beklenen JSON biçiminde değil');
        }
        return array_map(static fn($x) => trim((string)$x), array_values($j['lines']));
    }
}
