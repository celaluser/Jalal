<?php
declare(strict_types=1);

namespace TgWatch;

/** Eski kayıtları temizler (Ayarlar > Veri ve bakım). Günde bir cron'dan, istenirse elle çalışır. */
final class Cleaner
{
    public function __construct(private Db $db, private array $config)
    {
    }

    /** @return array{logs:int,jobs:int,notices:int,attempts:int} silinen satır sayıları */
    public function run(): array
    {
        $logDays = max(1, (int)($this->config['retention_logs_days'] ?? 30));
        $jobDays = max(0, (int)($this->config['retention_jobs_days'] ?? 0));
        $out = ['logs' => 0, 'jobs' => 0, 'notices' => 0, 'attempts' => 0];
        $out['logs'] = $this->db->run('DELETE FROM logs WHERE ts < ?', [time() - $logDays * 86400]);
        $out['attempts'] = $this->db->run('DELETE FROM login_attempts WHERE locked_until < ? AND last_at < ?', [time(), time() - 7 * 86400]);
        if ($jobDays > 0) {
            $cut = time() - $jobDays * 86400;
            $out['jobs'] = $this->db->run("DELETE FROM jobs WHERE status IN ('approved','published','rejected') AND updated_at < ?", [$cut]);
            $out['notices'] = $this->db->run("DELETE FROM notices WHERE status != 'open' AND updated_at < ?", [$cut]);
        }
        $this->db->run('DELETE FROM cron_runs WHERE id NOT IN (SELECT id FROM cron_runs ORDER BY id DESC LIMIT 500)');
        foreach (glob(rtrim((string)($this->config['data_dir'] ?? ''), '/') . '/sessions/sess_*') ?: [] as $f) { // süresi geçen oturum dosyaları
            if (@filemtime($f) < time() - 3 * 86400) {
                @unlink($f);
            }
        }
        $this->db->set('cleaned_at', (string)time());
        $n = array_sum($out);
        if ($n > 0) {
            $this->db->log('info', 'clean', "Temizlik: {$out['logs']} kayıt, {$out['jobs']} iş, {$out['notices']} bildirim silindi");
        }
        return $out;
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
