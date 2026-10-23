<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Çeviri sözlüğü. Varsayılanlar burada; web panelden düzenlenen sürüm veritabanında (settings.dictionary) saklanır.
 *
 * - rules:   [desen, yerine] regex kuralları; sözlükten ÖNCE uygulanır (sayılı kalıplar için).
 * - phrases: İngilizce => Azerice (büyük/küçük harf duyarsız, tam kelime, en uzun ifade önce).
 * - keep:    çevrilmeyen ve uyarı verilmeyen kelimeler (marka adları vb.).
 * - guesses: kullanıcının henüz onaylamadığı tahmini çeviriler (panelde işaretlenir).
 * - options: ad kalıbı seçenekleri.
 */
final class Dictionary
{
    public static function defaults(): array
    {
        return [
            'rules' => [
                ['(\d+)\s*Days?\s*(?:Refill|Guarantee[d]?)', '$1 Gün Qarantili'],
                ['(?:Lifetime|Life\s*Time)\s*(?:Refill|Guarantee[d]?)', 'Ömürlük Qarantili'],
                ['No\s*Refill', 'Qarantisiz'],
                ['(?:No|Non|Zero)[\s-]*Drop', 'Azalma olmaz'],
                ['Low\s*Drop', 'Az azalma'],
                ['Max(?:imum)?\.?\s*:?\s*(\d[\d.,]*\s*[KkMm]?)', 'Maks $1'],
                ['Speed\s*:?\s*(\d[\d.,]*\s*[KkMm]?)\s*(?:/|Per)\s*(?:Day|D)\b', 'Gündə $1'],
                ['(\d[\d.,]*\s*[KkMm]?)\s*(?:/|Per|P/)\s*(?:Day|D)\b', 'Gündə $1'],
                ['(\d[\d.,]*\s*[KkMm]?)\s*(?:/|Per)\s*(?:Hour|Hr|H)\b', 'Saatda $1'],
                ['Instant\s*Start', 'Dərhal Başlayır'],
            ],
            'phrases' => [
                // Kullanıcının kendi terimleri
                'Likes' => 'Bəyəni', 'Like' => 'Bəyəni',
                'Comments' => 'Rəy', 'Comment' => 'Rəy',
                'Refill' => 'Qarantili', 'Guarantee' => 'Qarantili', 'Guaranteed' => 'Qarantili',
                'Fast' => 'Sürətli',
                // Tahminler (kullanıcı onayı bekliyor)
                'Followers' => 'İzləyici', 'Follower' => 'İzləyici',
                'Views' => 'Baxış', 'View' => 'Baxış',
                'Saves' => 'Yadda Saxla', 'Save' => 'Yadda Saxla',
                'Shares' => 'Paylaşım', 'Share' => 'Paylaşım',
                'Random Comments' => 'Təsadüfi Rəy', 'Random Comment' => 'Təsadüfi Rəy',
                'Custom Comments' => 'Özəl Rəy', 'Custom Comment' => 'Özəl Rəy',
                'Subscribers' => 'Abunəçi', 'Subscriber' => 'Abunəçi',
                'Members' => 'Üzv', 'Member' => 'Üzv',
                'Reactions' => 'Reaksiya', 'Reaction' => 'Reaksiya',
                'Story Views' => 'Hekayə Baxışı', 'Story' => 'Hekayə',
                'Live Stream' => 'Canlı Yayım', 'Live' => 'Canlı',
                'Votes' => 'Səs', 'Vote' => 'Səs',
                'Impressions' => 'Göstərim', 'Reach' => 'Əhatə',
                'Plays' => 'Dinləmə', 'Watch Time' => 'İzləmə Vaxtı', 'Hours' => 'Saat',
                'Instant' => 'Dərhal', 'Super Fast' => 'Çox Sürətli', 'Speed' => 'Sürət',
                'Day' => 'Gün', 'Days' => 'Gün',
                'High Quality' => 'Yüksək Keyfiyyət', 'Worldwide' => 'Dünya üzrə',
                'Mixed' => 'Qarışıq', 'Cheap' => 'Ucuz', 'Cheapest' => 'Ən Ucuz', 'Stable' => 'Stabil',
                'Old Accounts' => 'Köhnə Hesablar', 'Accounts' => 'Hesablar', 'New' => 'Yeni',
                'Female' => 'Qadın', 'Male' => 'Kişi', 'Profile' => 'Profil',
                'Channel' => 'Kanal', 'Group' => 'Qrup', 'Poll' => 'Sorğu',
                'Cancel Enabled' => 'Ləğv aktivdir',
            ],
            'keep' => [
                'Instagram', 'Threads', 'TikTok', 'YouTube', 'Telegram', 'Facebook', 'Twitter', 'X',
                'Spotify', 'SoundCloud', 'Twitch', 'Kick', 'LinkedIn', 'Snapchat', 'Pinterest', 'Discord',
                'Reddit', 'Quora', 'Google', 'WhatsApp', 'Reels', 'Reel', 'IGTV', 'Shorts', 'Post', 'Posts',
                'Video', 'Videos', 'Real', 'Premium', 'Bot', 'Emoji', 'VIP', 'HQ', 'USA', 'UK', 'Tweet', 'Tweets',
            ],
            'guesses' => [
                'Followers', 'Follower', 'Views', 'View', 'Saves', 'Save', 'Shares', 'Share',
                'Random Comments', 'Random Comment', 'Custom Comments', 'Custom Comment',
            ],
            'options' => [
                'keep_max' => true,   // "Maks 50K" kısmını ada koy
                'keep_speed' => true, // "Gündə 5K" kısmını ada koy
            ],
        ];
    }

    public static function load(Db $db): array
    {
        $d = $db->getJson('dictionary');
        $def = self::defaults();
        if (!is_array($d)) {
            return $def;
        }
        foreach ($def as $k => $v) {
            if (!isset($d[$k]) || !is_array($d[$k])) {
                $d[$k] = $v;
            }
        }
        $d['options'] = array_merge($def['options'], $d['options']);
        return $d;
    }

    public static function save(Db $db, array $dict): void
    {
        $db->setJson('dictionary', $dict);
    }

    // --- web paneldeki metin kutuları için ---

    public static function phrasesToText(array $phrases): string
    {
        $out = [];
        foreach ($phrases as $en => $az) {
            $out[] = "$en = $az";
        }
        return implode("\n", $out);
    }

    public static function phrasesFromText(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $text) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$en, $az] = array_map('trim', explode('=', $line, 2));
            if ($en !== '') {
                $out[$en] = $az;
            }
        }
        return $out;
    }

    public static function rulesToText(array $rules): string
    {
        return implode("\n", array_map(static fn($r) => $r[0] . ' => ' . $r[1], $rules));
    }

    /** @return array{0: list<array{0:string,1:string}>, 1: list<string>} kurallar ve hatalar */
    public static function rulesFromText(string $text): array
    {
        $rules = [];
        $errors = [];
        foreach (preg_split('/\R/u', $text) as $n => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $pos = strrpos($line, ' => ');
            if ($pos === false) {
                $errors[] = 'Satır ' . ($n + 1) . ': " => " ayracı yok';
                continue;
            }
            $pat = trim(substr($line, 0, $pos));
            $rep = trim(substr($line, $pos + 4));
            if (@preg_match(Translator::ruleRegex($pat), '') === false) {
                $errors[] = 'Satır ' . ($n + 1) . ': geçersiz regex';
                continue;
            }
            $rules[] = [$pat, $rep];
        }
        return [$rules, $errors];
    }

    public static function listFromText(string $text): array
    {
        $parts = preg_split('/[\s,]+/u', $text) ?: [];
        return array_values(array_unique(array_filter(array_map('trim', $parts), static fn($w) => $w !== '')));
    }
}
