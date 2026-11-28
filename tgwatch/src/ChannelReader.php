<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Herkese açık kanalın https://t.me/s/<kanal> web önizlemesini okur.
 * Not: Telegram bu HTML yapısını belgelemiyor ve değiştirebilir. Seçiciler
 * (tgme_widget_message, data-post, tgme_widget_message_text) gerçek sayfada
 * ilk kurulumda doğrulanmalı.
 */
class ChannelReader
{
    public function __construct(private int $timeout = 20)
    {
    }

    public static function cleanUsername(string $input): string
    {
        $u = trim($input);
        $u = preg_replace('~^(https?://)?(www\.)?(t\.me|telegram\.me)/(s/)?~i', '', $u) ?? $u;
        $u = ltrim($u, '@');
        $u = explode('/', $u)[0];
        $u = explode('?', $u)[0];
        return preg_match('/^[A-Za-z][A-Za-z0-9_]{3,31}$/', $u) === 1 ? $u : '';
    }

    /**
     * @return array{ok:bool, error:string, title:string, posts:list<array{id:int,text:string,date:string}>}
     */
    public function fetch(string $username, ?int $before = null): array
    {
        $url = 'https://t.me/s/' . rawurlencode($username) . ($before ? '?before=' . $before : '');
        $r = Http::get($url, $this->timeout);
        if ($r['error'] !== '') {
            return ['ok' => false, 'error' => 'Bağlantı hatası: ' . $r['error'], 'title' => '', 'posts' => []];
        }
        if ($r['status'] !== 200) {
            return ['ok' => false, 'error' => 'HTTP ' . $r['status'], 'title' => '', 'posts' => []];
        }
        return self::parse($r['body'], $username);
    }

    /** Son $count gönderiyi getirir (gerekirse ?before= ile geriye sayfalar). */
    public function fetchLast(string $username, int $count): array
    {
        $res = $this->fetch($username);
        if (!$res['ok']) {
            return $res;
        }
        $posts = $res['posts'];
        $guard = 0;
        while (count($posts) < $count && $posts && $guard++ < 5) {
            $older = $this->fetch($username, $posts[0]['id']);
            if (!$older['ok'] || !$older['posts']) {
                break;
            }
            $posts = array_merge($older['posts'], $posts);
        }
        $res['posts'] = array_slice($posts, -$count);
        return $res;
    }

    public static function parse(string $html, string $username = ''): array
    {
        $out = ['ok' => false, 'error' => '', 'title' => '', 'posts' => []];
        if (trim($html) === '') {
            $out['error'] = 'Boş sayfa';
            return $out;
        }
        $prev = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $xp = new \DOMXPath($doc);

        $titleNode = $xp->query('//div[contains(concat(" ", normalize-space(@class), " "), " tgme_channel_info_header_title ")]')->item(0);
        $out['title'] = $titleNode ? trim($titleNode->textContent) : '';

        $nodes = $xp->query('//div[contains(concat(" ", normalize-space(@class), " "), " tgme_widget_message ") and @data-post]');
        $seen = [];
        foreach ($nodes as $node) {
            /** @var \DOMElement $node */
            $dataPost = $node->getAttribute('data-post');
            if (!preg_match('~/(\d+)$~', $dataPost, $m)) {
                continue;
            }
            $id = (int)$m[1];
            // Albümlerde aynı id birden çok kez geçebilir.
            if (isset($seen[$id])) {
                continue;
            }
            $textNode = $xp->query('.//div[contains(concat(" ", normalize-space(@class), " "), " tgme_widget_message_text ")]', $node)->item(0);
            $text = $textNode ? self::nodeText($textNode) : '';
            $timeNode = $xp->query('.//a[contains(@class, "tgme_widget_message_date")]//time', $node)->item(0);
            $date = $timeNode instanceof \DOMElement ? $timeNode->getAttribute('datetime') : '';
            $seen[$id] = true;
            $out['posts'][] = ['id' => $id, 'text' => $text, 'date' => $date];
        }
        usort($out['posts'], static fn($a, $b) => $a['id'] <=> $b['id']);

        if ($out['posts']) {
            $out['ok'] = true;
        } elseif ($xp->query('//*[contains(@class, "tgme_channel_history")]')->length > 0) {
            $out['ok'] = true; // kanal var ama gönderi yok
        } elseif (str_contains($html, 'tgme_page_title') || str_contains($html, 'tgme_page_extra')) {
            $out['error'] = 'Kanal web önizlemesi kapalı ya da kanal herkese açık değil';
        } else {
            $out['error'] = 'Sayfa yapısı tanınmadı (Telegram HTML yapısını değiştirmiş olabilir)';
        }
        return $out;
    }

    /** <br> satır sonlarını koruyarak düz metin çıkarır. */
    private static function nodeText(\DOMNode $node): string
    {
        $s = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $s .= $child->nodeValue;
            } elseif ($child instanceof \DOMElement) {
                $tag = strtolower($child->tagName);
                if ($tag === 'br') {
                    $s .= "\n";
                } elseif ($tag === 'img' && $child->hasAttribute('alt')) {
                    $s .= $child->getAttribute('alt'); // özel emojiler
                } else {
                    $s .= self::nodeText($child);
                    if (in_array($tag, ['p', 'div', 'blockquote'], true)) {
                        $s .= "\n";
                    }
                }
            }
        }
        return trim($s);
    }
}
