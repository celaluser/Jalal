<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Standart SMM panel API'si (Perfect Panel ve çoğu sağlayıcı): POST key=…&action=services.
 * Cevap: [{"service":1,"name":"…","type":"Default","category":"…","rate":"0.90","min":"50","max":"10000","refill":true,"cancel":true}, …]
 * DENENMEDİ: gerçek panellere istek bu ortamdan yapılamadı.
 */
final class SmmApi
{
    /**
     * @return list<array{service_id:int,name:string,category:string,rate:?float,min:?int,max:?int,refill:?int,cancel:?int,type:string}>
     */
    public static function services(string $url, string $key, int $timeout = 60): array
    {
        if (!preg_match('~^https://~i', $url)) {
            throw new \InvalidArgumentException('API adresi https:// ile başlamalı (ör. https://panel.com/api/v2)');
        }
        $r = Http::request('POST', $url, ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
            http_build_query(['key' => $key, 'action' => 'services']), $timeout);
        if ($r['error'] !== '') {
            throw new \RuntimeException('Bağlantı hatası: ' . $r['error']);
        }
        return self::parse($r['body'], $r['status']);
    }

    /** API cevabını okur (ağdan bağımsız, test edilebilir). */
    public static function parse(string $body, int $status = 200): array
    {
        $r = ['body' => $body, 'status' => $status];
        $d = json_decode($r['body'], true);
        if (is_array($d) && isset($d['error'])) {
            throw new \RuntimeException('Panel hatası: ' . (is_string($d['error']) ? $d['error'] : json_encode($d['error'])));
        }
        if ($r['status'] !== 200 || !is_array($d)) {
            throw new \RuntimeException('Beklenmeyen cevap (HTTP ' . $r['status'] . '): ' . mb_substr(trim(strip_tags($r['body'])), 0, 160));
        }
        if (isset($d['services']) && is_array($d['services'])) {
            $d = $d['services']; // bazı paneller listeyi sarmalar
        }
        $out = [];
        foreach ($d as $row) {
            if (!is_array($row) || !isset($row['service'], $row['name'])) {
                continue;
            }
            $out[] = [
                'service_id' => (int)$row['service'],
                'name' => trim((string)$row['name']),
                'category' => trim((string)($row['category'] ?? '')),
                'rate' => isset($row['rate']) && is_numeric($row['rate']) ? (float)$row['rate'] : null,
                'min' => isset($row['min']) && is_numeric($row['min']) ? (int)$row['min'] : null,
                'max' => isset($row['max']) && is_numeric($row['max']) ? (int)$row['max'] : null,
                'refill' => self::flag($row['refill'] ?? null),
                'cancel' => self::flag($row['cancel'] ?? null),
                'type' => (string)($row['type'] ?? ''),
            ];
        }
        if (!$out) {
            throw new \RuntimeException('Cevapta servis listesi bulunamadı. API adresini ve anahtarı kontrol edin.');
        }
        return $out;
    }

    private static function flag($v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_bool($v)) {
            return $v ? 1 : 0;
        }
        return in_array(strtolower((string)$v), ['1', 'true', 'yes'], true) ? 1 : 0;
    }

    /** Kaynağı senkronize eder: eski listeyi siler, yenisini yazar. */
    public static function sync(Db $db, int $sourceId): int
    {
        $src = $db->one('SELECT * FROM sources WHERE id = ?', [$sourceId]);
        if ($src === null) {
            throw new \InvalidArgumentException('Kaynak bulunamadı');
        }
        try {
            $list = self::services($src['api_url'], $src['api_key']);
        } catch (\Throwable $e) {
            $db->run('UPDATE sources SET last_error = ? WHERE id = ?', [$e->getMessage(), $sourceId]);
            throw $e;
        }
        $db->tx(static function () use ($db, $sourceId, $list): void {
            $db->run('DELETE FROM remote_services WHERE source_id = ?', [$sourceId]);
            $st = $db->pdo->prepare('INSERT OR REPLACE INTO remote_services(source_id, service_id, name, category, rate, min, max, refill, cancel, type) VALUES(?,?,?,?,?,?,?,?,?,?)');
            foreach ($list as $s) {
                $st->execute([$sourceId, $s['service_id'], $s['name'], $s['category'], $s['rate'], $s['min'], $s['max'], $s['refill'], $s['cancel'], $s['type']]);
            }
        });
        $db->run('UPDATE sources SET services = ?, last_sync_at = ?, last_error = NULL WHERE id = ?', [count($list), time(), $sourceId]);
        $db->log('info', 'bank', "{$src['name']}: " . count($list) . ' servis alındı');
        return count($list);
    }
}
