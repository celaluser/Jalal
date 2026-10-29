<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Bot komutlarının listesi (panelden düzenlenir, veritabanında "bot_commands" olarak saklanır).
 * Her komut: ad (yazılan /ad), eylem (ne yapacağı), açık/kapalı, açıklama (Telegram menüsünde görünür),
 * ve "metin" eyleminde sabit cevap. Aynı eyleme birden çok ad verilebilir (takma ad).
 * /start, /id ve /iptal sabittir (kimlik öğrenme ve düzenlemeyi bırakma için gerekli).
 */
final class BotRegistry
{
    /** eylem => [etiket, kullanım ipucu, ne yapar] */
    public const ACTIONS = [
        'pending' => ['Bekleyen işler', '', 'Karar bekleyen işleri listeler, altında "Hepsini onayla" düğmesi çıkar'],
        'job_show' => ['İşi göster', 'NO', 'İş numarasını onay butonlarıyla yeniden gösterir'],
        'approve' => ['İşi onayla', 'NO', 'İş numarasını onaylar'],
        'reject' => ['İşi reddet', 'NO', 'İş numarasını reddeder'],
        'search' => ['İşlerde ara', 'ID/kelime', 'Servis ID ya da kelimeyle işlerde arar'],
        'price' => ['Fiyat önerisi', '0.44', 'Sağlayıcı fiyatından önerilen satış fiyatını hesaplar'],
        'summary' => ['Özet', '', 'Bugünün ve genel durumun özeti, sorun varsa listeler'],
        'status' => ['İş sayıları', '', 'Duruma göre iş sayıları ve son cron zamanı'],
        'channels' => ['Kanal durumu', '', 'Kanalların okunma durumu'],
        'notices' => ['Değişiklik bildirimleri', '', 'Bekleyen fiyat/kapanma bildirimlerini butonlarıyla gösterir'],
        'backup' => ['Yedek gönder', '', 'Yedeği şimdi dosya olarak gönderir'],
        'read_now' => ['Kanalları şimdi oku', '', 'Kanalları hemen okur (en çok ~20 sn), yeni iş sayısını söyler'],
        'help' => ['Komut listesi', '', 'Açık komutları ve ne yaptıklarını listeler'],
        'text' => ['Sabit cevap', '', 'Yazdığın metni cevap olarak gönderir (not, hatırlatma, bağlantı)'],
    ];

    /** Telegram komut kuralı + sabit komutlarla çakışmayan adlar. */
    public const RESERVED = ['start', 'id', 'iptal'];

    /** @return list<array{name:string,action:string,enabled:bool,desc:string,text:string}> */
    public static function defaults(): array
    {
        $d = static fn(string $n, string $a, string $desc) => ['name' => $n, 'action' => $a, 'enabled' => true, 'desc' => $desc, 'text' => ''];
        return [
            $d('bekleyen', 'pending', 'Karar bekleyen işler, hepsini onayla'),
            $d('ozet', 'summary', 'Bugünün ve genel durumun özeti'),
            $d('ara', 'search', 'Servis ID ya da kelimeyle işlerde ara'),
            $d('is', 'job_show', 'İş numarasını onay butonlarıyla göster'),
            $d('onayla', 'approve', 'İşi onayla (/onayla 5)'),
            $d('reddet', 'reject', 'İşi reddet (/reddet 5)'),
            $d('fiyat', 'price', 'Önerilen satış fiyatı (/fiyat 0.44)'),
            $d('kanallar', 'channels', 'Kanalların durumu'),
            $d('degisiklik', 'notices', 'Fiyat/kapanma bildirimleri'),
            $d('oku', 'read_now', 'Kanalları şimdi oku'),
            $d('yedek', 'backup', 'Yedeği şimdi gönder'),
            $d('durum', 'status', 'İş sayıları ve son cron'),
            $d('yardim', 'help', 'Komut listesi'),
        ];
    }

    /** @return list<array{name:string,action:string,enabled:bool,desc:string,text:string}> */
    public static function load(Db $db): array
    {
        $v = $db->getJson('bot_commands');
        if (!is_array($v) || !isset($v['list']) || !is_array($v['list'])) {
            return self::defaults();
        }
        $out = [];
        foreach ($v['list'] as $c) {
            if (isset($c['name'], $c['action']) && isset(self::ACTIONS[$c['action']])) {
                $out[] = ['name' => (string)$c['name'], 'action' => (string)$c['action'], 'enabled' => !empty($c['enabled']),
                    'desc' => (string)($c['desc'] ?? ''), 'text' => (string)($c['text'] ?? '')];
            }
        }
        return $out;
    }

    public static function masterOn(Db $db): bool
    {
        $v = $db->getJson('bot_commands');
        return !is_array($v) || ($v['on'] ?? true) !== false;
    }

    /** Yazılan komut adını (Türkçe harfler dahil) kayıt adına çevirir: /özet → ozet. */
    public static function normalize(string $s): string
    {
        return strtr(mb_strtolower($s), ['ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'i̇' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u']);
    }

    /** @return array<string,mixed>|null açık komut */
    public static function find(Db $db, string $typed): ?array
    {
        if (!self::masterOn($db)) {
            return null;
        }
        $n = self::normalize($typed);
        foreach (self::load($db) as $c) {
            if ($c['enabled'] && $c['name'] === $n) {
                return $c;
            }
        }
        return null;
    }

    /**
     * Panelden gelen listeyi doğrular ve kaydeder.
     * @param list<array<string,mixed>> $rows
     * @return list<string> hatalar (varsa kaydedilmez)
     */
    public static function save(Db $db, array $rows, bool $masterOn): array
    {
        $list = [];
        $seen = [];
        $errors = [];
        foreach ($rows as $r) {
            $name = ltrim(self::normalize(trim((string)($r['name'] ?? ''))), '/');
            if ($name === '' && trim((string)($r['text'] ?? '')) === '') {
                continue; // boş satır
            }
            $action = (string)($r['action'] ?? '');
            if (!preg_match('/^[a-z0-9_]{1,32}$/', $name)) {
                $errors[] = "“/{$name}”: yalnızca küçük harf (a-z), rakam ve _ olabilir, en çok 32 karakter";
                continue;
            }
            if (in_array($name, self::RESERVED, true)) {
                $errors[] = "“/{$name}” sabit bir komut, kullanılamaz";
                continue;
            }
            if (isset($seen[$name])) {
                $errors[] = "“/{$name}” iki kez yazılmış";
                continue;
            }
            if (!isset(self::ACTIONS[$action])) {
                $errors[] = "“/{$name}”: geçersiz eylem";
                continue;
            }
            $text = mb_substr(trim((string)($r['text'] ?? '')), 0, 1500);
            if ($action === 'text' && $text === '') {
                $errors[] = "“/{$name}”: “Sabit cevap” için cevap metni yazın";
                continue;
            }
            $desc = trim((string)($r['desc'] ?? ''));
            $desc = $desc === '' ? self::ACTIONS[$action][0] : mb_substr($desc, 0, 120);
            $seen[$name] = true;
            $list[] = ['name' => $name, 'action' => $action, 'enabled' => !empty($r['enabled']), 'desc' => $desc, 'text' => $action === 'text' ? $text : ''];
        }
        if (count($list) > 40) {
            $errors[] = 'En çok 40 komut tanımlanabilir';
        }
        if ($errors) {
            return $errors;
        }
        $db->setJson('bot_commands', ['on' => $masterOn, 'list' => $list]);
        return [];
    }

    public static function reset(Db $db): void
    {
        $db->setJson('bot_commands', ['on' => true, 'list' => self::defaults()]);
    }

    /** Telegram "/" menüsü için liste. @return list<array{command:string,description:string}> */
    public static function menu(Db $db): array
    {
        if (!self::masterOn($db)) {
            return [];
        }
        $out = [];
        foreach (self::load($db) as $c) {
            if ($c['enabled']) {
                $out[] = ['command' => $c['name'], 'description' => mb_substr($c['desc'] !== '' ? $c['desc'] : self::ACTIONS[$c['action']][0], 0, 256)];
            }
        }
        return array_slice($out, 0, 100);
    }
}
