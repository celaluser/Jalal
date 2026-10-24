<?php
declare(strict_types=1);

namespace TgWatch;

/** Telegram Bot API istemcisi. Mesajlar HTML parse_mode ile gönderilir. */
class Telegram
{
    public const MAX_TEXT = 4096;

    private string $token;
    private string $base;

    /** @param string|null $base API adresi (varsayılan https://api.telegram.org). Telegram'a doğrudan erişilemeyen hosting'lerde ara sunucu adresi verilebilir. */
    public function __construct(string $token, ?string $base = null)
    {
        $this->token = self::cleanToken($token);
        $this->base = rtrim($base !== null && $base !== '' ? $base : 'https://api.telegram.org', '/');
    }

    /** Kopyala-yapıştırda karışan boşlukları, satır sonlarını ve görünmez karakterleri temizler. */
    public static function cleanToken(string $token): string
    {
        return preg_replace('/[\s\x{200B}-\x{200D}\x{2060}\x{FEFF}]+/u', '', $token) ?? trim($token);
    }

    public function configured(): bool
    {
        return preg_match('/^\d+:[A-Za-z0-9_-]{30,}$/', $this->token) === 1;
    }

    /** Token'ı göstermeden ne olduğunu açıklar. */
    public static function describeToken(string $token): string
    {
        $t = self::cleanToken($token);
        if ($t === '') {
            return 'bot_token boş (config.php içinde doldurun)';
        }
        if (preg_match('/^\d+:[A-Za-z0-9_-]{30,}$/', $t)) {
            return 'biçim uygun (' . strlen($t) . ' karakter)';
        }
        if (!str_contains($t, ':')) {
            return 'biçim yanlış: ":" yok (' . strlen($t) . ' karakter) — BotFather token\'ı "1234567890:AA..." biçimindedir';
        }
        [$id, $rest] = explode(':', $t, 2);
        if (!ctype_digit($id)) {
            return 'biçim yanlış: ":" öncesi sadece rakam olmalı';
        }
        if (preg_match('/[^A-Za-z0-9_-]/', $rest)) {
            return 'biçim yanlış: ":" sonrası geçersiz karakter içeriyor (tırnak, nokta vb. karışmış olabilir)';
        }
        return 'biçim yanlış: ":" sonrası ' . strlen($rest) . ' karakter (normalde 35) — eksik kopyalanmış olabilir';
    }

    /** @return mixed Telegram'ın "result" alanı */
    public function call(string $method, array $params = [], int $timeout = 20)
    {
        if (!$this->configured()) {
            throw new \RuntimeException('bot_token: ' . self::describeToken($this->token));
        }
        $url = $this->base . '/bot' . $this->token . '/' . $method;
        $r = Http::postJson($url, $params, [], $timeout);
        if ($r['error'] !== '') {
            throw new \RuntimeException("Telegram'a bağlanılamadı ($method): " . $r['error']);
        }
        $d = json_decode($r['body'], true);
        if (!is_array($d) || empty($d['ok'])) {
            $desc = is_array($d) ? (string)($d['description'] ?? '') : substr($r['body'], 0, 200);
            throw new \RuntimeException("Telegram hatası ($method, HTTP {$r['status']}): $desc");
        }
        return $d['result'];
    }

    public function sendMessage(int|string $chatId, string $html, ?array $keyboard = null): array
    {
        $p = [
            'chat_id' => $chatId,
            'text' => self::cut($html),
            'parse_mode' => 'HTML',
            'link_preview_options' => ['is_disabled' => true],
        ];
        if ($keyboard !== null) {
            $p['reply_markup'] = ['inline_keyboard' => $keyboard];
        }
        return $this->call('sendMessage', $p);
    }

    public function editMessage(int|string $chatId, int $messageId, string $html, ?array $keyboard = null): void
    {
        $p = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => self::cut($html),
            'parse_mode' => 'HTML',
            'link_preview_options' => ['is_disabled' => true],
            'reply_markup' => ['inline_keyboard' => $keyboard ?? []],
        ];
        try {
            $this->call('editMessageText', $p);
        } catch (\RuntimeException $e) {
            // Aynı metinle düzenleme hata değildir.
            if (!str_contains($e->getMessage(), 'message is not modified')) {
                throw $e;
            }
        }
    }

    public function answerCallback(string $callbackId, string $text = ''): void
    {
        $this->call('answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => $text]);
    }

    /** Metin dosyası gönderir (multipart). */
    public function sendDocument(int|string $chatId, string $filename, string $content, string $caption = ''): void
    {
        if (!$this->configured()) {
            throw new \RuntimeException('bot_token: ' . self::describeToken($this->token));
        }
        $tmp = tempnam(sys_get_temp_dir(), 'tgw');
        file_put_contents($tmp, $content);
        try {
            $r = Http::request('POST', $this->base . '/bot' . $this->token . '/sendDocument', [], [
                'chat_id' => (string)$chatId,
                'caption' => $caption,
                'document' => new \CURLFile($tmp, 'text/plain', $filename),
            ], 30);
        } finally {
            @unlink($tmp);
        }
        $d = json_decode($r['body'], true);
        if ($r['error'] !== '' || !is_array($d) || empty($d['ok'])) {
            throw new \RuntimeException('sendDocument başarısız: ' . ($r['error'] ?: (is_array($d) ? ($d['description'] ?? '') : 'HTTP ' . $r['status'])));
        }
    }

    public static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** HTML etiketlerini bozmadan 4096 sınırına sığdırmak için düz kesme (çağıran taraf kısa tutmalı). */
    private static function cut(string $html): string
    {
        if (mb_strlen($html) <= self::MAX_TEXT) {
            return $html;
        }
        return mb_substr(strip_tags($html), 0, self::MAX_TEXT - 20) . "\n…(kəsildi)";
    }
}
