<?php
declare(strict_types=1);

namespace TgWatch;

/** Dış izleme için sağlık özeti: boş liste = sorun yok. */
final class Monitor
{
    /** @return list<string> */
    public static function problems(Db $db, ?int $now = null): array
    {
        $now ??= time();
        $p = [];
        $last = (int)$db->get('cron_last_run', '0');
        if ($last === 0) {
            $p[] = 'Cron hiç çalışmadı';
        } elseif ($now - $last > 900) {
            $p[] = 'Cron ' . intdiv($now - $last, 60) . ' dakikadır çalışmıyor';
        }
        foreach ($db->all('SELECT username, last_error, last_ok_at, last_checked_at FROM channels WHERE enabled = 1') as $c) {
            if ($c['last_error'] && $now - (int)($c['last_ok_at'] ?: $c['last_checked_at'] ?: $now) > 10800) {
                $p[] = '@' . $c['username'] . ' 3 saattir okunamıyor: ' . $c['last_error'];
            }
        }
        $res = $db->getJson('cron_last_result');
        if (is_array($res) && (int)($res['errors'] ?? 0) > 0 && $now - (int)($res['at'] ?? 0) < 900) {
            $p[] = 'Son cron turunda ' . (int)$res['errors'] . ' hata var';
        }
        return $p;
    }
}
