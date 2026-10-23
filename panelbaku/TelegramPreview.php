<?php

/**
 * Açıq Telegram kanalının (https://t.me/s/<kanal>) son postlarını çəkib
 * təhlükəsiz (sanitize olunmuş) JSON strukturuna çevirir.
 * QEYD: yalnız açıq (@username) kanallar üçün işləyir.
 */
class TelegramPreview
{
    private const ALLOWED_TAGS = ['b', 'strong', 'i', 'em', 'u', 's', 'code', 'pre', 'a', 'br'];

    public static function usernameFrom(string $channelId): ?string
    {
        $channelId = trim($channelId);
        if (preg_match('~^@([A-Za-z0-9_]{4,})$~', $channelId, $m)) {
            return $m[1];
        }
        if (preg_match('~^(?:https?://)?t\.me/(?:s/)?([A-Za-z0-9_]{4,})/?$~', $channelId, $m)) {
            return $m[1];
        }
        return null;
    }

    public static function fetch(string $username, int $limit = 15): array
    {
        $ch = curl_init('https://t.me/s/' . rawurlencode($username));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; PanelBakuPreview/1.0)',
        ]);
        $html = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($html === false || $code !== 200) {
            throw new RuntimeException('Telegram kanalı oxunmadı' . ($err ? ": {$err}" : " (HTTP {$code})"));
        }

        return self::parse((string)$html, $username, $limit);
    }

    public static function parse(string $html, string $username, int $limit = 15): array
    {
        $prev = libxml_use_internal_errors(true);
        $doc  = new DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $xp = new DOMXPath($doc);

        $has = static fn(string $cls): string => "contains(concat(' ', normalize-space(@class), ' '), ' {$cls} ')";

        $title = $username;
        $t = $xp->query("//*[{$has('tgme_channel_info_header_title')}]")->item(0);
        if ($t) {
            $title = trim($t->textContent);
        }

        $posts = [];
        foreach ($xp->query("//*[{$has('tgme_widget_message')}][@data-post]") as $msg) {
            $dataPost = $msg->getAttribute('data-post'); // kanal/123
            $id = (int)substr((string)strrchr($dataPost, '/'), 1);

            $textNode = $xp->query(".//*[{$has('tgme_widget_message_text')}]", $msg)->item(0);
            $textHtml = $textNode ? self::sanitizeChildren($textNode) : '';

            $photo = null;
            $photoNode = $xp->query(".//*[{$has('tgme_widget_message_photo_wrap')}]", $msg)->item(0);
            if ($photoNode && preg_match("~url\\('(https://[^']+)'\\)~", $photoNode->getAttribute('style'), $m)) {
                $photo = $m[1];
            }

            if ($textHtml === '' && $photo === null) {
                continue;
            }

            $views = $xp->query(".//*[{$has('tgme_widget_message_views')}]", $msg)->item(0);
            $time  = $xp->query(".//time[@datetime]", $msg)->item(0);

            $posts[] = [
                'id'       => $id,
                'html'     => $textHtml,
                'photo'    => $photo,
                'views'    => $views ? trim($views->textContent) : '',
                'time'     => $time ? trim($time->textContent) : '',
                'datetime' => $time ? $time->getAttribute('datetime') : '',
            ];
        }

        return [
            'username' => $username,
            'title'    => $title,
            'posts'    => array_slice($posts, -$limit),
        ];
    }

    private static function sanitizeChildren(DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= self::sanitizeNode($child);
        }
        return trim($out);
    }

    private static function sanitizeNode(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return htmlspecialchars($node->textContent, ENT_QUOTES, 'UTF-8');
        }
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return '';
        }

        $tag   = strtolower($node->nodeName);
        $inner = self::sanitizeChildren($node);

        if ($tag === 'br') {
            return '<br>';
        }
        if ($tag === 'a') {
            $href = $node->getAttribute('href');
            if (!preg_match('~^(https?://|tg://)~i', $href)) {
                return $inner;
            }
            return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8')
                . '" target="_blank" rel="noopener noreferrer">' . $inner . '</a>';
        }
        if (in_array($tag, self::ALLOWED_TAGS, true)) {
            return "<{$tag}>{$inner}</{$tag}>";
        }
        // Naməlum teqlər (məs. emoji <i class="emoji"><b>😀</b></i> → mətn) yalnız məzmunu saxlayır
        return $inner;
    }
}
