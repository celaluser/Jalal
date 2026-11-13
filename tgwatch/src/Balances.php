<?php
declare(strict_types=1);

namespace TgWatch;

/** Sağlayıcı bakiyeleri: her sağlayıcının API'sine action=balance (bot /balans ve Chrome eklentisi kullanır). */
final class Balances
{
    /**
     * @return array{items:list<array{id:int,name:string,balance:?float,currency:string,error:string}>,totals:array<string,float>,at:int}
     */
    public static function check(Db $db): array
    {
        $items = [];
        $totals = [];
        foreach ($db->all("SELECT * FROM sources WHERE kind = 'provider' ORDER BY name COLLATE NOCASE") as $p) {
            $it = ['id' => (int)$p['id'], 'name' => (string)$p['name'], 'balance' => null, 'currency' => '', 'error' => ''];
            try {
                $b = SmmApi::balance((string)$p['api_url'], (string)$p['api_key']);
                $it['balance'] = $b['balance'];
                $it['currency'] = $b['currency'];
                $totals[$b['currency']] = ($totals[$b['currency']] ?? 0) + $b['balance'];
            } catch (\Throwable $e) {
                $it['error'] = mb_substr($e->getMessage(), 0, 120);
                $db->log('warn', 'bot', 'Bakiye (' . $p['name'] . '): ' . $e->getMessage());
            }
            $items[] = $it;
        }
        ksort($totals);
        return ['items' => $items, 'totals' => $totals, 'at' => time()];
    }

    /** Son kaydedilen sonuç (cron ya da eklenti/bot sorgusu yazar). */
    public static function cached(Db $db): ?array
    {
        $r = $db->getJson('balances_last');
        return is_array($r) && isset($r['items']) ? $r : null;
    }

    /** Sorgula ve kaydet; düşük bakiye eşiklerini kontrol et. */
    public static function checkAndSave(Db $db, array $config = [], ?Telegram $tg = null): array
    {
        $r = self::check($db);
        $db->setJson('balances_last', $r);
        self::alerts($db, $r, $config, $tg);
        return $r;
    }

    /** Cron: "balance_minutes" (varsayılan 1) dolduysa sorgular. Dönüş: sorgulandıysa sonuç, değilse null. */
    public static function cron(Db $db, array $config, ?Telegram $tg = null): ?array
    {
        $min = max(1, min(1440, (int)($config['balance_minutes'] ?? 1)));
        $last = self::cached($db);
        if ($last && time() - (int)$last['at'] < $min * 60 - 5) {
            return null;
        }
        if (!$db->one("SELECT id FROM sources WHERE kind = 'provider' LIMIT 1")) {
            return null;
        }
        return self::checkAndSave($db, $config, $tg);
    }

    /** "USD=5, TRY=200" → ['USD' => 5.0, 'TRY' => 200.0] (para birimi büyük harf; "*" tüm para birimleri). */
    public static function thresholds(string $s): array
    {
        $out = [];
        foreach (preg_split('/[,;\n]+/', $s) ?: [] as $part) {
            if (preg_match('/^\s*([A-Za-z*₼$€₺₽]{1,5})\s*[=:]\s*([0-9]+(?:[.,][0-9]+)?)\s*$/u', $part, $m)) {
                $out[mb_strtoupper($m[1])] = (float)str_replace(',', '.', $m[2]);
            }
        }
        return $out;
    }

    /** Eşiğin altına inen sağlayıcı için bir kez uyarı; eşiğin üstüne çıkınca "düzeldi". Sağlayıcı cevap vermezse de uyarı. */
    private static function alerts(Db $db, array $r, array $config, ?Telegram $tg = null): void
    {
        $th = self::thresholds((string)($config['balance_low'] ?? ''));
        try {
            $al = new Alerts($db, $tg, $config ?: []);
        } catch (\Throwable $e) {
            return;
        }
        foreach ($r['items'] as $it) {
            $key = 'balance:' . $it['id'];
            $name = Telegram::esc($it['name']);
            if ($it['error'] !== '') {
                $al->send($key . ':err', 'balance', "⚠️ <b>Bakiye okunamadı:</b> $name\n<code>" . Telegram::esc($it['error']) . '</code>', 6 * 3600);
                continue;
            }
            $al->resolve($key . ':err', 'balance', "✅ <b>Bakiye yeniden okunuyor:</b> $name");
            $cur = mb_strtoupper((string)$it['currency']);
            $lim = $th[$cur] ?? ($th['*'] ?? null);
            if ($lim === null) {
                continue;
            }
            $txt = self::money((float)$it['balance']) . ($it['currency'] !== '' ? ' ' . Telegram::esc($it['currency']) : '');
            if ((float)$it['balance'] < $lim) {
                $al->send($key . ':low', 'balance', "🔴 <b>Düşük bakiye:</b> $name — <code>$txt</code> (eşik " . self::money($lim) . ($cur !== '' ? ' ' . Telegram::esc($cur) : '') . ')', 6 * 3600);
            } else {
                $al->resolve($key . ':low', 'balance', "🟢 <b>Bakiye yeniden eşiğin üstünde:</b> $name — <code>$txt</code>");
            }
        }
    }

    public static function money(float $v): string
    {
        return number_format($v, $v != 0 && abs($v) < 1 ? 4 : 2, '.', ' ');
    }
}
