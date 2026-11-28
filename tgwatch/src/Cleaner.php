<?php
declare(strict_types=1);

namespace TgWatch;

/** Eski kayıtları temizler (Ayarlar > Veri ve bakım). Günde bir cron'dan, istenirse elle çalışır. */
final class Cleaner
{
    public function __construct(private Db $db, private array $config)
    {
    }

    /** @return array{logs:int,jobs:int,notices:int,attempts:int,stale:int} silinen/temizlenen satır sayıları */
    public function run(): array
    {
        $logDays = max(1, (int)($this->config['retention_logs_days'] ?? 30));
        $jobDays = max(0, (int)($this->config['retention_jobs_days'] ?? 0));
        $out = ['logs' => 0, 'jobs' => 0, 'notices' => 0, 'attempts' => 0, 'stale' => 0];
        $out['logs'] = $this->db->run('DELETE FROM logs WHERE ts < ?', [time() - $logDays * 86400]);
        $out['attempts'] = $this->db->run('DELETE FROM login_attempts WHERE locked_until < ? AND last_at < ?', [time(), time() - 7 * 86400]);
        if ($jobDays > 0) {
            $cut = time() - $jobDays * 86400;
            $out['jobs'] = $this->db->run("DELETE FROM jobs WHERE status IN ('approved','published','rejected') AND updated_at < ?", [$cut]);
            $out['notices'] = $this->db->run("DELETE FROM notices WHERE status != 'open' AND updated_at < ?", [$cut]);
        }
        $out['stale'] = $this->staleRefs();
        $this->db->run('DELETE FROM cron_runs WHERE id NOT IN (SELECT id FROM cron_runs ORDER BY id DESC LIMIT 500)');
        foreach (glob(rtrim((string)($this->config['data_dir'] ?? ''), '/') . '/sessions/sess_*') ?: [] as $f) { // süresi geçen oturum dosyaları
            if (@filemtime($f) < time() - 3 * 86400) {
                @unlink($f);
            }
        }
        $this->db->set('cleaned_at', (string)time());
        $n = array_sum($out);
        if ($n > 0) {
            $this->db->log('info', 'clean', "Temizlik: {$out['logs']} kayıt, {$out['jobs']} iş, {$out['notices']} bildirim, {$out['stale']} eski servis referansı silindi/temizlendi");
        }
        return $out;
    }

    /**
     * Kendi panelinden ya da bir sağlayıcı/rakipten silinmiş servislere değinen kayıtları temizler
     * (çeviri bankasındaki eşleşme bağlantısı, rakip kıyası, "görülen servis" kaydı). Servisin kendisi
     * remote_services'ten her senkronda zaten güncellenir; burada ona bağlı artık anlamsız kalan satırlar silinir.
     * Çeviri bankasındaki çevirinin kendisi (en/az) silinmez, yalnızca servis bağlantısı boşaltılır.
     * @return int etkilenen satır sayısı
     */
    public function staleRefs(): int
    {
        $n = 0;
        $n += $this->db->run('UPDATE tm SET own_service = NULL WHERE own_service IS NOT NULL AND NOT EXISTS (
            SELECT 1 FROM remote_services r JOIN sources s ON s.id = r.source_id AND s.kind = \'own\' WHERE r.service_id = tm.own_service)');
        $n += $this->db->run('UPDATE tm SET provider_source = NULL, provider_service = NULL WHERE provider_source IS NOT NULL AND NOT EXISTS (
            SELECT 1 FROM remote_services r WHERE r.source_id = tm.provider_source AND r.service_id = tm.provider_service)');
        $n += $this->db->run('DELETE FROM rival_map WHERE NOT EXISTS (
            SELECT 1 FROM remote_services r JOIN sources s ON s.id = r.source_id AND s.kind = \'own\' WHERE r.service_id = rival_map.own_service)
            OR NOT EXISTS (SELECT 1 FROM remote_services r WHERE r.source_id = rival_map.rival_source AND r.service_id = rival_map.rival_service)');
        $n += $this->db->run('DELETE FROM rival_state WHERE NOT EXISTS (
            SELECT 1 FROM remote_services r JOIN sources s ON s.id = r.source_id AND s.kind = \'own\' WHERE r.service_id = rival_state.own_service)
            OR NOT EXISTS (SELECT 1 FROM sources s WHERE s.id = rival_state.rival_source AND s.kind = \'rival\')');
        $n += $this->db->run('DELETE FROM order_provider WHERE NOT EXISTS (
            SELECT 1 FROM remote_services r JOIN sources s ON s.id = r.source_id AND s.kind = \'own\' WHERE r.service_id = order_provider.own_service)');
        $n += $this->db->run('DELETE FROM api_seen WHERE source_id NOT IN (SELECT id FROM sources)');
        $n += $this->db->run('DELETE FROM remote_services WHERE source_id NOT IN (SELECT id FROM sources)');
        return $n;
    }

    /** Günde bir kez (cron'dan). */
    public function daily(): void
    {
        if (time() - (int)$this->db->get('cleaned_at', '0') >= 86400) {
            $this->run();
        }
    }

    public function vacuum(): void
    {
        $this->db->pdo->exec('VACUUM');
    }
}
