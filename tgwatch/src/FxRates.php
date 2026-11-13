<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Canlı kur: open.er-api.com'dan (anahtar gerekmez) USD bazlı kurlar çekilir.
 * DENENMEDİ: bu ortamdan dış servise erişilemedi.
 */
final class FxRates
{
    public const CURRENCIES = ['RUB', 'TRY', 'AZN', 'EUR'];
    private const URL = 'https://open.er-api.com/v6/latest/USD';

    /** Testler için: ağ yerine bu fonksiyon çağrılır () → ham cevap gövdesi. */
    public static ?\Closure $fake = null;

    /** @return array<string,float> 1 birim yabancı para = kaç USD */
    public static function fetch(int $timeout = 15): array
    {
        $body = self::$fake !== null ? (string)(self::$fake)() : null;
        if ($body === null) {
            $r = Http::request('GET', self::URL, ['Accept: application/json'], null, $timeout);
            if ($r['error'] !== '') {
                throw new \RuntimeException('Bağlantı hatası: ' . $r['error']);
            }
            if ($r['status'] !== 200) {
                throw new \RuntimeException('Beklenmeyen cevap (HTTP ' . $r['status'] . ')');
            }
            $body = $r['body'];
        }
        $d = json_decode($body, true);
        if (!is_array($d) || !isset($d['rates']) || !is_array($d['rates'])) {
            throw new \RuntimeException('Kur servisinden geçerli cevap alınamadı.');
        }
        $out = [];
        foreach (self::CURRENCIES as $c) {
            $v = $d['rates'][$c] ?? null;
            if (is_numeric($v) && (float)$v > 0) {
                $out[$c] = round(1 / (float)$v, 6);
            }
        }
        if (!$out) {
            throw new \RuntimeException('Kur servisi beklenen para birimlerini döndürmedi.');
        }
        return $out;
    }

    /** @param array<string,float> $rates */
    public static function text(array $rates): string
    {
        $parts = [];
        foreach ($rates as $c => $v) {
            $parts[] = $c . '=' . rtrim(rtrim(number_format((float)$v, 6, '.', ''), '0'), '.');
        }
        return implode(', ', $parts);
    }

    /** Panelde gösterilecek durum: son çekilen kurlar, zamanı, hatası. */
    public static function status(Db $db): array
    {
        $live = $db->getJson('fx_live');
        return [
            'rates' => is_array($live) ? $live : [],
            'at' => (int)$db->get('fx_live_at', '0'),
            'error' => (string)$db->get('fx_live_error', ''),
        ];
    }

    /**
     * Kullanılacak kurlar: `fx_auto` açıksa ve son çekilen kurlar 48 saatten taze ise onlar; değilse
     * (otomatik kapalı, hiç çekilmemiş ya da bayatladıysa) Ayarlar → Kanal okuma'daki manuel `fx_rates`.
     * @return array<string,float>
     */
    public static function effective(Db $db, array $config): array
    {
        $manual = Flow::fxRates($config);
        if (empty($config['fx_auto'])) {
            return $manual;
        }
        $st = self::status($db);
        if ($st['rates'] && time() - $st['at'] < 48 * 3600) {
            return $st['rates'];
        }
        return $manual;
    }

    /** cron: `fx_auto` açıksa günde bir kez kurları çeker ve kaydeder. */
    public static function refresh(Db $db, array $config, bool $force = false): ?array
    {
        if (empty($config['fx_auto']) || (!$force && time() - (int)$db->get('fx_live_at', '0') < 24 * 3600)) {
            return null;
        }
        try {
            $rates = self::fetch();
            $db->setJson('fx_live', $rates);
            $db->set('fx_live_at', (string)time());
            $db->set('fx_live_error', '');
            $db->log('info', 'fx', 'Kurlar güncellendi: ' . self::text($rates));
            return $rates;
        } catch (\Throwable $e) {
            $db->set('fx_live_error', $e->getMessage());
            $db->log('error', 'fx', 'Kur çekilemedi: ' . $e->getMessage());
            return null;
        }
    }
}
