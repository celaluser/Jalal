<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Sağlayıcı API izleme: sağlayıcı yeni servisi kanalda duyurmasa bile panelinde göründüğü an haber verir.
 * Cron her çalıştığında, izleme açık sağlayıcıların servis listesi (ayarlardaki aralıkla) çekilir ve daha önce görülmemiş
 * servisler için normal iş açılır: çeviri + fiyat önerisi ile bota ✅ Onayla / ✏️ Düzenle butonlu mesaj gider.
 * İlk çalışmada mevcut servisler sessizce "görüldü" sayılır; yalnızca sonradan gelenler bildirilir.
 */
final class ApiWatch
{
    public const PER_JOB = 20;
    public const PER_RUN = 60;

    public function __construct(private Db $db, private Flow $flow, private array $config)
    {
    }

    public function minutes(): int
    {
        return max(5, min(1440, (int)($this->config['api_watch_minutes'] ?? 30)));
    }

    /** @return list<array> izleme açık sağlayıcılar */
    public function sources(): array
    {
        return $this->db->all("SELECT * FROM sources WHERE kind = 'provider' AND watch = 1 ORDER BY id");
    }

    /**
     * @return array<int,array{name:string,ok:bool,new:int,jobs:list<int>,error:string,baseline:bool}> kaynak no => rapor
     */
    public function run(int $deadline = 0, bool $force = false): array
    {
        $out = [];
        foreach ($this->sources() as $s) {
            if ($deadline && time() > $deadline - 8) {
                break;
            }
            if (!$force && $s['watch_at'] && (int)$s['watch_at'] > time() - $this->minutes() * 60 + 20) {
                continue;
            }
            $out[(int)$s['id']] = $this->check($s);
        }
        return $out;
    }

    public function check(array $s): array
    {
        $rep = ['name' => (string)$s['name'], 'ok' => true, 'new' => 0, 'jobs' => [], 'error' => '', 'baseline' => false];
        $sid = (int)$s['id'];
        try {
            $d = SmmApi::syncDiff($this->db, $sid, false);
        } catch (\Throwable $e) {
            $rep['ok'] = false;
            $rep['error'] = $e->getMessage();
            $this->db->run('UPDATE sources SET watch_at = ?, watch_note = ? WHERE id = ?', [time(), 'Hata: ' . mb_substr($e->getMessage(), 0, 160), $sid]);
            $this->db->log('error', 'apiwatch', "{$s['name']}: " . $e->getMessage());
            return $rep;
        }
        if ($d['baseline']) {
            $rep['baseline'] = true;
            $this->db->run('UPDATE sources SET watch_at = ?, watch_note = ? WHERE id = ?', [time(), count($d['all']) . ' mevcut servis kaydedildi; bundan sonra eklenenler bildirilir.', $sid]);
            $this->db->log('info', 'apiwatch', "{$s['name']}: izleme başladı, " . count($d['all']) . ' mevcut servis kaydedildi');
            return $rep;
        }
        $batch = array_slice($d['new'], 0, self::PER_RUN);
        $rep['new'] = count($batch);
        $mark = $this->db->pdo->prepare('INSERT OR IGNORE INTO api_seen(source_id, service_id, first_seen) VALUES(?,?,?)');
        foreach (array_chunk($batch, self::PER_JOB) as $k => $chunk) {
            $id = $this->flow->createJob('api-' . $sid, time() + $k, self::text($chunk));
            if ($id !== null) {
                $rep['jobs'][] = $id;
            }
            foreach ($chunk as $x) {
                $mark->execute([$sid, $x['service_id'], time()]);
            }
        }
        $left = count($d['new']) - count($batch);
        $note = $batch ? count($batch) . ' yeni servis bildirildi' . ($left > 0 ? ", $left tanesi sıradaki kontrolde" : '') : 'Yeni servis yok';
        $this->db->run('UPDATE sources SET watch_at = ?, watch_note = ? WHERE id = ?', [time(), $note, $sid]);
        if ($batch) {
            $this->db->log('info', 'apiwatch', "{$s['name']}: $note, iş: " . (implode(',', $rep['jobs']) ?: '-'));
        }
        return $rep;
    }

    /**
     * Servisleri normal duyuru metnine çevirir; böylece kanal duyurusuyla aynı hattan geçer.
     * @param list<array{service_id:int,name:string,category:string,rate:?float}> $services
     */
    public static function text(array $services): string
    {
        $lines = [];
        $cat = null;
        foreach ($services as $s) {
            $c = trim((string)$s['category']);
            if ($c !== $cat && $c !== '') {
                $lines[] = '';
                $lines[] = mb_substr(preg_replace('/\s+/u', ' ', $c) ?? $c, 0, 80);
                $cat = $c;
            }
            $lines[] = '🆔 ' . (int)$s['service_id'] . ' ' . preg_replace('/\s+/u', ' ', (string)$s['name']);
            if ($s['rate'] !== null) {
                $lines[] = '$' . rtrim(rtrim(number_format((float)$s['rate'], 4, '.', ''), '0'), '.');
            }
        }
        return trim(implode("\n", $lines));
    }
}
