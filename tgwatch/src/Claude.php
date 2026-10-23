<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * İsteğe bağlı: "Yeniden oluştur" için Claude API (Messages API) ile çeviri.
 * DENENMEDİ: gerçek API çağrısı geliştirme ortamında yapılmadı.
 */
final class Claude
{
    public function __construct(private string $apiKey, private string $model)
    {
    }

    public function configured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @param list<array{id:int,name:string}> $items
     * @return array<int,string> id => Azerice ad
     */
    public function translate(array $items, array $dict): array
    {
        $glossary = [];
        foreach ($dict['phrases'] ?? [] as $en => $az) {
            $glossary[] = "$en => $az";
        }
        $examples = [];
        foreach ($dict['rules'] ?? [] as [$p, $r]) {
            $examples[] = "/$p/ => $r";
        }
        $system = "You translate SMM panel service names from English to Azerbaijani for a store.\n"
            . "Rules:\n- Keep brand/platform names unchanged (" . implode(', ', array_slice($dict['keep'] ?? [], 0, 40)) . ").\n"
            . "- Keep the ' | ' separators, emojis, numbers and units (50K, 1M) exactly.\n"
            . "- Use this glossary strictly:\n" . implode("\n", $glossary) . "\n"
            . "- Patterns (regex => output):\n" . implode("\n", $examples) . "\n"
            . "- Example: \"Threads Followers | No Drop | Max 50K | 5K/Day | 30 Days Refill ♻️\" => \"Threads İzləyici | Azalma olmaz | Maks 50K | Gündə 5K | 30 Gün Qarantili ♻️\"\n"
            . "Answer ONLY with a JSON object mapping each id (string) to the translated name. No other text.";
        $input = [];
        foreach ($items as $it) {
            $input[(string)$it['id']] = Normalizer::text($it['name']);
        }
        $r = Http::postJson('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => 4096,
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => json_encode($input, JSON_UNESCAPED_UNICODE)]],
        ], [
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01',
        ], 60);
        if ($r['error'] !== '') {
            throw new \RuntimeException('Claude API bağlantı hatası: ' . $r['error']);
        }
        $d = json_decode($r['body'], true);
        if ($r['status'] !== 200 || !is_array($d)) {
            $msg = is_array($d) ? ($d['error']['message'] ?? '') : '';
            throw new \RuntimeException("Claude API hatası (HTTP {$r['status']}): $msg");
        }
        $text = '';
        foreach ($d['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
        if (!preg_match('/\{.*\}/su', $text, $m)) {
            throw new \RuntimeException('Claude cevabında JSON bulunamadı');
        }
        $map = json_decode($m[0], true);
        if (!is_array($map)) {
            throw new \RuntimeException('Claude cevabı geçersiz JSON');
        }
        $out = [];
        foreach ($map as $id => $name) {
            if (is_string($name) && trim($name) !== '') {
                $out[(int)$id] = trim($name);
            }
        }
        return $out;
    }
}
