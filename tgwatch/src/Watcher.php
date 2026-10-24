<?php
declare(strict_types=1);

namespace TgWatch;

/** Kanalları okuyup yeni duyurulardan iş oluşturur (cron tarafından çağrılır). */
final class Watcher
{
    public function __construct(private Db $db, private Flow $flow, private ChannelReader $reader)
    {
    }

    public function addChannel(string $input, string $title = ''): string
    {
        $u = ChannelReader::cleanUsername($input);
        if ($u === '') {
            throw new \InvalidArgumentException('Geçersiz kanal adı. Örnek: @kanaladi ya da https://t.me/kanaladi');
        }
        $this->db->run('INSERT OR IGNORE INTO channels(username, title, created_at) VALUES(?, ?, ?)', [$u, $title, time()]);
        return $u;
    }

    /**
     * @param int  $backfill >0 ise son N gönderiyi (servis içerenleri) işler
     * @param bool $dry      true: hiçbir şey yazmaz/göndermez, sadece rapor verir
     * @return array{ok:bool, error:string, title:string, posts:int, new:int, jobs:list<int>, found:list<array>}
     */
    public function checkChannel(array $ch, int $backfill = 0, bool $dry = false): array
    {
        $u = $ch['username'];
        $res = $backfill > 0 ? $this->reader->fetchLast($u, $backfill) : $this->reader->fetch($u);
        $report = ['ok' => $res['ok'], 'error' => $res['error'], 'title' => $res['title'], 'posts' => count($res['posts']), 'new' => 0, 'jobs' => [], 'found' => [], 'notices' => []];

        if (!$res['ok']) {
            if (!$dry) {
                $this->db->run('UPDATE channels SET last_checked_at = ?, last_error = ? WHERE id = ?', [time(), $res['error'], $ch['id']]);
                $this->db->log('error', 'channel', "@$u okunamadı: {$res['error']}");
            }
            return $report;
        }

        $maxId = $res['posts'] ? max(array_column($res['posts'], 'id')) : (int)$ch['last_post_id'];
        $last = $ch['last_post_id'] === null ? null : (int)$ch['last_post_id'];

        foreach ($res['posts'] as $p) {
            $isNew = $backfill > 0 || ($last !== null && $p['id'] > $last);
            if (!$isNew) {
                continue;
            }
            $report['new']++;
            if ($this->skipped($ch, $p['text'])) { // kanal için tanımlı "yoksay" kelimelerinden biri geçiyor
                $report['skipped'] = ($report['skipped'] ?? 0) + 1;
                continue;
            }
            $kind = Notices::classify($p['text']);
            if ($kind !== null) { // fiyat değişikliği ya da kapatılan servis: yeni servis değildir
                $nid = $dry ? null : $this->flow->createNotice($u, $p['id'], $p['text'], $kind);
                $report['notices'][] = ['post' => $p['id'], 'kind' => $kind, 'ids' => Notices::extractIds($p['text']), 'notice' => $nid];
                continue;
            }
            $items = Parser::parse($p['text']);
            if (!$items) {
                continue;
            }
            $report['found'][] = ['id' => $p['id'], 'items' => $items];
            if (!$dry) {
                $jobId = $this->flow->createJob($u, $p['id'], $p['text']);
                if ($jobId !== null) {
                    $report['jobs'][] = $jobId;
                }
            }
        }

        if (!$dry) {
            // İlk okumada geçmişi işleme: sadece başlangıç noktasını kaydet.
            $newLast = $last === null ? $maxId : max($last, $maxId);
            $this->db->run(
                "UPDATE channels SET last_post_id = ?, last_checked_at = ?, last_ok_at = ?, last_error = NULL, title = CASE WHEN title = '' THEN ? ELSE title END WHERE id = ?",
                [$newLast, time(), time(), $res['title'], $ch['id']]
            );
            if ($last === null && $backfill === 0) {
                $this->db->log('info', 'channel', "@$u ilk kez okundu, başlangıç gönderisi $newLast (eski gönderiler işlenmedi)");
            }
        }
        return $report;
    }

    /** Kanalın "yoksay" kelimelerinden (virgül ya da satırla ayrılmış) biri gönderide geçiyorsa true. */
    private function skipped(array $ch, string $text): bool
    {
        foreach (preg_split('/[,\n]+/u', (string)($ch['skip_words'] ?? '')) as $w) {
            $w = trim($w);
            if ($w !== '' && mb_stripos($text, $w) !== false) {
                return true;
            }
        }
        return false;
    }

    /** @return list<array> kanal başına rapor */
    public function runAll(int $backfill = 0, bool $dry = false, ?string $only = null, int $deadline = 0): array
    {
        $out = [];
        $rows = $this->db->all('SELECT * FROM channels WHERE enabled = 1 ORDER BY COALESCE(last_checked_at, 0) ASC');
        foreach ($rows as $i => $ch) {
            if ($only !== null && strcasecmp($only, $ch['username']) !== 0) {
                continue;
            }
            if ($deadline > 0 && time() >= $deadline) {
                $this->db->log('warn', 'cron', 'Süre doldu, kalan kanallar sonraki çalışmada okunacak');
                break;
            }
            if ($i > 0) {
                usleep(700000); // Telegram'ı yormamak için kanallar arasında bekle
            }
            $out[$ch['username']] = $this->checkChannel($ch, $backfill, $dry);
        }
        return $out;
    }
}
