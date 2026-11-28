<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Eklenti API'si (public/api.php) istatistikleri: günlük işlem başına istek/hata/süre ve son istekler.
 * Panelde API sekmesinde gösterilir. Kayıt hatası API cevabını asla bozmaz.
 */
final class ApiStats
{
    public const KEEP_CALLS = 300; // son istek kaydı sayısı
    public const KEEP_DAYS = 400;

    private static function ensure(Db $db): void
    {
        static $done = [];
        $k = spl_object_id($db);
        if (isset($done[$k])) {
            return;
        }
        $db->run('CREATE TABLE IF NOT EXISTS api_stats (day TEXT NOT NULL, action TEXT NOT NULL, ok INTEGER NOT NULL DEFAULT 0, err INTEGER NOT NULL DEFAULT 0, ms REAL NOT NULL DEFAULT 0, PRIMARY KEY(day, action))');
        $db->run('CREATE TABLE IF NOT EXISTS api_calls (id INTEGER PRIMARY KEY, at INTEGER NOT NULL, action TEXT NOT NULL, code INTEGER NOT NULL, ms REAL NOT NULL, ip TEXT NOT NULL DEFAULT \'\', ext TEXT NOT NULL DEFAULT \'\', error TEXT NOT NULL DEFAULT \'\')');
        $done[$k] = true;
    }

    /** Bir isteği kaydet. $action: işlem adı ("—" anahtar/JSON hatası gibi işleme varmayan istekler). */
    public static function record(Db $db, string $action, int $code, float $ms, string $ip = '', string $ext = '', string $error = ''): void
    {
        try {
            self::ensure($db);
            $action = mb_substr(preg_replace('/[^a-z0-9_—-]/u', '', mb_strtolower($action)) ?: '—', 0, 40);
            $ok = $code < 400 ? 1 : 0;
            $db->run('INSERT INTO api_stats(day, action, ok, err, ms) VALUES(?,?,?,?,?) ON CONFLICT(day, action) DO UPDATE SET ok = ok + excluded.ok, err = err + excluded.err, ms = ms + excluded.ms',
                [date('Y-m-d'), $action, $ok, 1 - $ok, round($ms, 1)]);
            $db->run('INSERT INTO api_calls(at, action, code, ms, ip, ext, error) VALUES(?,?,?,?,?,?,?)',
                [time(), $action, $code, round($ms, 1), mb_substr($ip, 0, 45), mb_substr(preg_replace('/[^0-9.]/', '', $ext) ?? '', 0, 20), mb_substr($error, 0, 200)]);
            if (random_int(1, 50) === 1) {
                $db->run('DELETE FROM api_calls WHERE id <= (SELECT MAX(id) FROM api_calls) - ?', [self::KEEP_CALLS]);
                $db->run('DELETE FROM api_stats WHERE day < ?', [date('Y-m-d', time() - self::KEEP_DAYS * 86400)]);
            }
        } catch (\Throwable $e) {
            // istatistik yazılamadı: API'yi etkilemesin
        }
    }

    /**
     * Özet: son $days gün toplamları, günlük seri, işlem başına tablo, son istekler, son görülen eklenti sürümü.
     * @return array{total:array{req:int,err:int,ms:float},today:array{req:int,err:int},days:list<array{ts:int,values:list<int>}>,actions:list<array>,calls:list<array>,last:?array,ext:string}
     */
    public static function summary(Db $db, int $days = 14): array
    {
        self::ensure($db);
        $from = date('Y-m-d', time() - ($days - 1) * 86400);
        $rows = $db->all('SELECT * FROM api_stats WHERE day >= ?', [$from]);
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $ts = strtotime('today') - $i * 86400;
            $series[date('Y-m-d', $ts)] = ['ts' => $ts, 'values' => [0, 0]];
        }
        $acts = [];
        $tot = ['req' => 0, 'err' => 0, 'ms' => 0.0];
        $today = ['req' => 0, 'err' => 0];
        foreach ($rows as $r) {
            $n = (int)$r['ok'] + (int)$r['err'];
            if (isset($series[$r['day']])) {
                $series[$r['day']]['values'][0] += (int)$r['ok'];
                $series[$r['day']]['values'][1] += (int)$r['err'];
            }
            $acts[$r['action']] ??= ['action' => $r['action'], 'req' => 0, 'err' => 0, 'ms' => 0.0, 'last' => 0];
            $acts[$r['action']]['req'] += $n;
            $acts[$r['action']]['err'] += (int)$r['err'];
            $acts[$r['action']]['ms'] += (float)$r['ms'];
            $tot['req'] += $n;
            $tot['err'] += (int)$r['err'];
            $tot['ms'] += (float)$r['ms'];
            if ($r['day'] === date('Y-m-d')) {
                $today['req'] += $n;
                $today['err'] += (int)$r['err'];
            }
        }
        foreach ($db->all('SELECT action, MAX(at) at FROM api_calls GROUP BY action') as $r) {
            if (isset($acts[$r['action']])) {
                $acts[$r['action']]['last'] = (int)$r['at'];
            }
        }
        usort($acts, static fn($a, $b) => $b['req'] <=> $a['req']);
        $calls = $db->all('SELECT * FROM api_calls ORDER BY id DESC LIMIT 40');
        $ext = $db->one("SELECT ext FROM api_calls WHERE ext != '' ORDER BY id DESC LIMIT 1");
        return ['total' => $tot, 'today' => $today, 'days' => array_values($series), 'actions' => array_values($acts), 'calls' => $calls, 'last' => $calls[0] ?? null, 'ext' => (string)($ext['ext'] ?? '')];
    }

    public static function reset(Db $db): void
    {
        self::ensure($db);
        $db->run('DELETE FROM api_stats');
        $db->run('DELETE FROM api_calls');
    }
}
