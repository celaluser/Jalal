<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Claude ile çeviri (doğrudan HTTP, Messages API, anthropic-version 2026-10-21).
 *
 * Modlar:
 *   bank   — yalnızca kurallar + çeviri bankası (ücretsiz, Claude çağrılmaz)
 *   hybrid — bankadan tam çevrilemeyen ya da sözlükte olmayan kelime içeren adlar Claude'a gider
 *   claude — tüm adlar Claude'a gider (bankadan benzer örneklerle)
 * Elle yapıştırma (claude.ai): aynı istem metin olarak hazırlanır, cevap "ID | ad" satırları olarak geri yapıştırılır.
 *
 * Claude'un her çevirisi kontrol edilir (sayılar, emojiler, parça sayısı, zorunlu sözlük terimleri);
 * kontrolden geçmeyen ad kullanılmaz, motorun (banka/kural) çevirisi kalır.
 * DENENMEDİ: gerçek API çağrısı bu ortamdan yapılamadı.
 */
final class Ai
{
    public const API_VERSION = '2026-10-21';
    /** model => [ad, giriş $/1M, çıkış $/1M, sunucu tarafı fallback desteği] (fiyatlar 2026-10 itibarıyla, değişebilir) */
    public const MODELS = [
        'claude-opus-5-5' => ['Claude Opus 5.5 (en kaliteli)', 4.00, 20.00, true],
        'claude-sonnet-5-5' => ['Claude Sonnet 5.5 (dengeli)', 2.00, 10.00, true],
        'claude-haiku-5-5' => ['Claude Haiku 5.5 (en ucuz, en hızlı)', 0.10, 0.50, false],
    ];
    public const MODES = [
        'bank' => 'Kurallar + çeviri bankası (ücretsiz, Claude kullanılmaz)',
        'hybrid' => 'Karma: bankada olmayan ya da eksik kelimeli adlar Claude ile',
        'claude' => 'Tüm adlar Claude ile (bankadan örneklerle)',
    ];
    public const EFFORTS = ['' => 'Model varsayılanı', 'low' => 'Düşük (hızlı, ucuz)', 'medium' => 'Orta', 'high' => 'Yüksek'];

    public const DEFAULT_RULES = <<<'TXT'
1. Ad biçimi: "Platform Servis türü | özellik | özellik". Kaynaktaki parça sırası ve " | " ayracı korunur.
2. Platform ve marka adları değişmez: Instagram, TikTok, YouTube, Threads, Telegram, Facebook, X, Spotify…
3. Sayılar, birimler (K, M) ve emojiler aynen ve aynı yerde kalır (♻️ gibi).
4. Yazım tamamen Azerice olur (ə, ı, ö, ü, ş, ç, ğ). Türkçe kelime kullanılmaz: Takipçi → İzləyici, Beğeni → Bəyəni.
5. Sözlükteki karşılıklar aynen ve aynı büyük/küçük harfle kullanılır.
6. "30 Days Refill" → "30 Gün Qarantili", "Lifetime Refill" → "Ömürlük Qarantili", "No Refill" → "Qarantisiz".
7. "Max 50K" → "Maks 50K", "5K/Day" → "Gündə 5K".
8. Hız: sayı varsa "Gündə 5K"; yalnızca kelime varsa Fast → Sürətli, Super Fast → Çox Sürətli, Instant → Dərhal.
9. High Quality → Yüksək Keyfiyyətli.
10. Kaynakta olmayan bilgi eklenmez, kaynaktaki bilgi atlanmaz.
TXT;

    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'items' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => ['id' => ['type' => 'integer'], 'az' => ['type' => 'string']],
                    'required' => ['id', 'az'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['items'],
        'additionalProperties' => false,
    ];

    public function __construct(private Db $db, private array $config)
    {
    }

    // ------------------------------------------------------------ ayarlar

    public function mode(): string
    {
        $m = (string)$this->db->get('translate_mode', 'bank');
        return isset(self::MODES[$m]) ? $m : 'bank';
    }

    public function apiKey(): string
    {
        $k = trim((string)$this->db->get('claude_api_key', ''));
        return $k !== '' ? $k : trim((string)($this->config['claude_api_key'] ?? ''));
    }

    public function model(): string
    {
        $m = (string)$this->db->get('claude_model', '');
        return isset(self::MODELS[$m]) ? $m : 'claude-opus-5-5';
    }

    public function effort(): string
    {
        $e = (string)$this->db->get('claude_effort', '');
        return isset(self::EFFORTS[$e]) ? $e : '';
    }

    public function monthlyLimit(): int
    {
        return max(0, (int)$this->db->get('claude_monthly_limit', '300'));
    }

    public function styleRules(): string
    {
        $r = (string)$this->db->get('style_rules', '');
        return trim($r) !== '' ? $r : self::DEFAULT_RULES;
    }

    public function configured(): bool
    {
        return $this->apiKey() !== '';
    }

    /** Otomatik (API) çeviri bu modda ve ayarlarla çalışır mı? */
    public function active(): bool
    {
        return $this->mode() !== 'bank' && $this->configured();
    }

    public function usage(?string $month = null): array
    {
        $all = $this->db->getJson('ai_usage', []);
        return $all[$month ?? date('Y-m')] ?? ['requests' => 0, 'input' => 0, 'output' => 0, 'cost' => 0.0];
    }

    private function addUsage(string $model, int $in, int $out): void
    {
        $all = $this->db->getJson('ai_usage', []);
        $k = date('Y-m');
        $u = $all[$k] ?? ['requests' => 0, 'input' => 0, 'output' => 0, 'cost' => 0.0];
        $p = self::MODELS[$model] ?? [null, 0, 0];
        $u['requests']++;
        $u['input'] += $in;
        $u['output'] += $out;
        $u['cost'] = round($u['cost'] + $in / 1e6 * $p[1] + $out / 1e6 * $p[2], 4);
        $all[$k] = $u;
        $this->db->setJson('ai_usage', $all);
    }

    /** Bu ad Claude'a gönderilmeli mi (moda göre)? Elle düzenlenen adlar hiç gönderilmez. */
    public function needs(array $item): bool
    {
        if (!empty($item['edited'])) {
            return false;
        }
        return match ($this->mode()) {
            'claude' => true,
            'hybrid' => ($item['via'] ?? 'rules') !== 'bank' || !empty($item['unknown']),
            default => false,
        };
    }

    // ------------------------------------------------------------ istem

    public function system(array $dict): string
    {
        $guess = array_flip($dict['guesses'] ?? []);
        $gl = [];
        foreach ($dict['phrases'] ?? [] as $en => $az) {
            $gl[] = "- $en → $az" . (isset($guess[$en]) ? ' (tahmin; daha iyi bir karşılık varsa kullanabilirsin)' : '');
        }
        $pat = [];
        foreach ($dict['rules'] ?? [] as [$p, $r]) {
            $pat[] = "- /$p/ → $r";
        }
        return "Sen bir Azerbaycan SMM panelinin servis adı çevirmenisin. Sağlayıcıların İngilizce (bazen Türkçe ya da karışık) servis adlarını, "
            . "panel sahibinin kurallarına ve önceki çevirilerine birebir uyarak Azericeye çevirirsin.\n\n"
            . "## Panel sahibinin kuralları\n" . trim($this->styleRules()) . "\n\n"
            . "## Zorunlu sözlük\n" . implode("\n", $gl) . "\n\n"
            . "## Kalıplar (regex → çıktı, \$1 yakalanan grup)\n" . implode("\n", $pat) . "\n\n"
            . "## Çevrilmeyecek kelimeler\n" . implode(', ', $dict['keep'] ?? []) . "\n\n"
            . "Her servis için önceki çevirilerden benzer örnekler ve kural tabanlı bir taslak verilir. Örnekler panel sahibinin tarzıdır, ona uy. "
            . "Taslak doğruysa aynen bırak, yanlış ya da eksik çevrilmiş kısımları düzelt.";
    }

    /** Çevrilecek adlar + her biri için banka örnekleri + tarz örnekleri. */
    public function userMessage(array $items, Engine $engine): string
    {
        $mem = $engine->memory();
        $blocks = [];
        foreach ($items as $it) {
            $src = $engine->strip($it['name']);
            $b = "### ID {$it['id']}\nKaynak: $src\nTaslak: {$it['az']}";
            $sim = $mem->similar($src, 6);
            if ($sim) {
                $b .= "\nÖnceki çeviriler:\n" . implode("\n", array_map(static fn($e) => "- {$e['en']} → {$e['az']}", $sim));
            }
            $style = $mem->styleExamples($src, 4);
            if ($style) {
                $b .= "\nPaneldeki benzer adlar: " . implode(' / ', $style);
            }
            $blocks[] = $b;
        }
        return "Aşağıdaki servis adlarını çevir.\n\n" . implode("\n\n", $blocks);
    }

    /** claude.ai'ye yapıştırılacak tek parça metin. Cevap "ID | ad" satırları olarak istenir. */
    public function manualPrompt(array $items, Engine $engine, array $dict): string
    {
        return $this->system($dict) . "\n\n" . $this->userMessage($items, $engine)
            . "\n\nCevap olarak YALNIZCA her servis için bir satır yaz, başka hiçbir şey yazma. Biçim:\nID | Azerice ad\n"
            . "Örnek: 13125 | Threads İzləyici | Azalma olmaz | Maks 50K | Gündə 5K | 30 Gün Qarantili ♻️";
    }

    // ------------------------------------------------------------ API

    /**
     * @return array{map:array<int,string>, usage:array{input:int,output:int}, model:string}
     */
    public function call(string $system, string $user): array
    {
        $key = $this->apiKey();
        if ($key === '') {
            throw new \RuntimeException('Claude API anahtarı girilmemiş (Çeviri ayarları).');
        }
        $limit = $this->monthlyLimit();
        if ($limit > 0 && $this->usage()['requests'] >= $limit) {
            throw new \RuntimeException("Bu ayın Claude istek sınırına ($limit) ulaşıldı. Çeviri ayarlarından artırabilirsiniz.");
        }
        $model = $this->model();
        $body = [
            'model' => $model,
            'max_tokens' => 16000,
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $user]],
            'output_config' => ['format' => ['type' => 'json_schema', 'schema' => self::SCHEMA]],
        ];
        if ($this->effort() !== '') {
            $body['output_config']['effort'] = $this->effort();
        }
        $headers = ['x-api-key: ' . $key, 'anthropic-version: ' . self::API_VERSION];
        if (self::MODELS[$model][3]) {
            // Güvenlik sınıflandırıcısı isteği reddederse sunucu tarafında Anthropic'in önerdiği modelle yeniden dener.
            $body['fallbacks'] = 'default';
            $headers[] = 'anthropic-beta: server-side-fallback-2026-07-01';
        }

        $r = null;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $r = Http::postJson('https://api.anthropic.com/v1/messages', $body, $headers, 90);
            if ($r['error'] === '' && !in_array($r['status'], [429, 500, 502, 503, 529], true)) {
                break;
            }
            sleep(3);
        }
        if ($r['error'] !== '') {
            throw new \RuntimeException('Claude API bağlantı hatası: ' . $r['error']);
        }
        $d = json_decode($r['body'], true);
        if ($r['status'] !== 200 || !is_array($d)) {
            $msg = is_array($d) ? (string)($d['error']['message'] ?? '') : mb_substr($r['body'], 0, 200);
            throw new \RuntimeException("Claude API hatası (HTTP {$r['status']}): $msg");
        }
        $in = (int)($d['usage']['input_tokens'] ?? 0);
        $out = (int)($d['usage']['output_tokens'] ?? 0);
        $this->addUsage($model, $in, $out);

        $stop = (string)($d['stop_reason'] ?? '');
        if ($stop === 'refusal') {
            throw new \RuntimeException('Claude isteği reddetti' . (isset($d['stop_details']['category']) ? ' (' . $d['stop_details']['category'] . ')' : '') . '.');
        }
        if ($stop === 'max_tokens') {
            throw new \RuntimeException('Claude cevabı yarıda kesildi (çok uzun liste). Daha az servisle tekrar deneyin.');
        }
        $text = '';
        foreach ($d['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
        $json = json_decode($text, true);
        if (!is_array($json) || !isset($json['items']) || !is_array($json['items'])) {
            throw new \RuntimeException('Claude cevabı beklenen JSON biçiminde değil.');
        }
        $map = [];
        foreach ($json['items'] as $row) {
            if (isset($row['id'], $row['az']) && is_string($row['az']) && trim($row['az']) !== '') {
                $map[(int)$row['id']] = trim($row['az']);
            }
        }
        return ['map' => $map, 'usage' => ['input' => $in, 'output' => $out], 'model' => (string)($d['model'] ?? $model)];
    }

    /** "ID | ad" satırlarını okur (claude.ai'den yapıştırılan cevap). */
    public static function parseLines(string $text): array
    {
        $map = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim(trim($line), "`*- \t");
            if (preg_match('/^(\d{2,7})\s*(?:\||:|=|->|→|–)\s*(.+)$/u', $line, $m)) {
                $map[(int)$m[1]] = trim($m[2]);
            }
        }
        return $map;
    }

    // ------------------------------------------------------------ kontrol

    private static function nums(string $s): array
    {
        preg_match_all('/\d+(?:[.,]\d+)?\s?[KkMm]?(?![\p{L}])/u', $s, $m);
        $n = array_map(static fn($x) => strtoupper(str_replace([' ', ','], ['', '.'], $x)), $m[0]);
        sort($n);
        return $n;
    }

    private static function emojis(string $s): array
    {
        preg_match_all('/\p{Extended_Pictographic}/u', $s, $m);
        $e = array_values(array_unique($m[0]));
        sort($e);
        return $e;
    }

    /**
     * Claude'un çevirisini taslağa (banka/kural) göre denetler.
     * @return list<string> sorunlar (boşsa geçti)
     */
    public static function validate(string $source, string $draft, string $out, array $dict): array
    {
        $p = [];
        if (trim($out) === '') {
            return ['boş çeviri'];
        }
        if (self::nums($draft) !== self::nums($out)) {
            $p[] = 'sayılar değişmiş (' . implode(' ', self::nums($draft)) . ' → ' . implode(' ', self::nums($out)) . ')';
        }
        if (self::emojis($draft) !== self::emojis($out)) {
            $p[] = 'emojiler değişmiş';
        }
        if (substr_count($draft, '|') !== substr_count($out, '|')) {
            $p[] = 'parça sayısı değişmiş';
        }
        $guess = array_flip($dict['guesses'] ?? []);
        $low = mb_strtolower($out);
        $draftLow = mb_strtolower($draft);
        foreach ($dict['phrases'] ?? [] as $en => $az) {
            // Yalnızca onaylı terimler ve taslakta (banka/kural sonucu) da kullanılmış olanlar zorunlu:
            // bankadaki kendi yazımınız sözlükten farklıysa ona dokunulmaz.
            if (isset($guess[$en]) || $az === '' || !str_contains($draftLow, mb_strtolower((string)$az))) {
                continue;
            }
            if (preg_match('/(?<![\p{L}\p{N}])' . str_replace(' ', '\s+', preg_quote((string)$en, '/')) . '(?![\p{L}\p{N}])/iu', $source)
                && !str_contains($low, mb_strtolower((string)$az))) {
                // Daha uzun bir ifade bu terimi kapsıyorsa (ör. "Random Comments") ve onun karşılığı varsa sorun sayma.
                $covered = false;
                foreach ($dict['phrases'] as $en2 => $az2) {
                    if ($en2 !== $en && mb_stripos((string)$en2, (string)$en) !== false && mb_stripos($source, (string)$en2) !== false && str_contains($low, mb_strtolower((string)$az2))) {
                        $covered = true;
                        break;
                    }
                }
                if (!$covered) {
                    $p[] = "\"$en\" için sözlükteki \"$az\" kullanılmamış";
                }
            }
        }
        return $p;
    }

    /**
     * Claude cevabını işe uygular: kontrolden geçenleri yazar, geçmeyenlerde taslak kalır ve not düşülür.
     * @param array<int,string> $map
     * @return array{items:array, applied:int, rejected:list<string>}
     */
    public static function apply(array $items, array $map, array $dict, string $via = 'claude'): array
    {
        $applied = 0;
        $rejected = [];
        foreach ($items as &$it) {
            $id = (int)$it['id'];
            if (!isset($map[$id]) || !empty($it['edited'])) {
                continue;
            }
            $problems = self::validate($it['name'], $it['az'], $map[$id], $dict);
            if ($problems) {
                $it['ai_note'] = 'Claude önerisi kullanılmadı: ' . implode('; ', $problems) . ' — öneri: ' . $map[$id];
                $rejected[] = "$id: " . implode('; ', $problems);
                continue;
            }
            if ($map[$id] !== $it['az']) {
                $it['az'] = $map[$id];
                $it['via'] = $via;
            }
            $it['unknown'] = [];
            unset($it['ai_note']);
            $applied++;
        }
        unset($it);
        return ['items' => $items, 'applied' => $applied, 'rejected' => $rejected];
    }

    /** Moda göre gereken adları Claude ile çevirir. */
    public function improve(array $items, Engine $engine, array $dict): array
    {
        $todo = array_values(array_filter($items, fn($i) => $this->needs($i)));
        if (!$todo) {
            return ['items' => $items, 'applied' => 0, 'rejected' => [], 'sent' => 0];
        }
        $res = $this->call($this->system($dict), $this->userMessage($todo, $engine));
        $r = self::apply($items, $res['map'], $dict);
        $r['sent'] = count($todo);
        return $r;
    }
}
