<?php
declare(strict_types=1);

namespace TgWatch;

/** Bir cron turu: bot güncellemeleri (polling), gönderilemeyenleri yeniden dene, kanalları oku. CLI ve web cron ortak kullanır. */
final class CronRunner
{
    /** @var callable(string):void */
    private $say;

    public function __construct(private array $config, ?callable $say = null)
    {
        $this->say = $say ?? static function (string $s): void {
        };
    }

    /**
     * @return int|null hata sayısı; başka bir cron çalışıyorsa null
     */
    public function run(bool $test = false, int $backfill = 0, ?string $only = null, int $timeLimit = 50): ?int
    {
        $say = $this->say;
        $db = App::db();
        $lock = fopen($this->config['data_dir'] . '/cron.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            $say('Başka bir cron çalışıyor, çıkılıyor.');
            return null;
        }
        $deadline = time() + $timeLimit;
        $started = microtime(true);
        $errors = 0;
        $flow = App::flow();
        $watcher = new Watcher($db, $flow, new ChannelReader(min((int)$this->config['fetch_timeout'], max(5, $timeLimit - 5))));

        try {
            if (!$test && $this->config['bot_mode'] === 'polling') {
                try {
                    $offset = (int)$db->get('last_update_id', '0') + 1;
                    $updates = App::telegram()->call('getUpdates', ['offset' => $offset, 'timeout' => 0, 'allowed_updates' => ['message', 'callback_query']]);
                    foreach ($updates as $u) {
                        $flow->handleUpdate($u);
                    }
                    $say('Bot güncellemesi: ' . count($updates));
                } catch (\Throwable $e) {
                    $errors++;
                    $hint = str_contains($e->getMessage(), 'webhook') ? ' (webhook kurulu: polling için panelde Sağlık → Webhook\'u kaldır)' : '';
                    $db->log('error', 'bot', 'getUpdates: ' . $e->getMessage() . $hint);
                    $say('getUpdates hatası: ' . $e->getMessage() . $hint);
                }
            }

            if (!$test) {
                $flow->retryUnsent();
            }

            if (!$test && $this->config['bot_mode'] === 'webhook') {
                $this->checkWebhook($db);
            }

            $reports = $watcher->runAll($backfill, $test, $only, $deadline);
            if (!$reports) {
                $say('Etkin kanal yok. Web panelden kanal ekleyin.');
            }
            foreach ($reports as $u => $r) {
                if (!$r['ok']) {
                    $errors++;
                    $say("@$u  ✗ {$r['error']}");
                    continue;
                }
                $say("@$u  ✓ {$r['posts']} gönderi okundu, {$r['new']} yeni, " . count($r['found']) . ' servis duyurusu, ' . count($r['notices']) . ' değişiklik bildirimi' . ($test ? '' : ', iş: ' . (implode(',', $r['jobs']) ?: '-')));
                foreach ($r['notices'] as $nt) {
                    $say("   gönderi {$nt['post']}: " . (Notices::KINDS[$nt['kind']] ?? $nt['kind']) . ', servisler: ' . implode(', ', $nt['ids']));
                }
                if ($test || $backfill > 0) {
                    foreach ($r['found'] as $f) {
                        $say("   gönderi {$f['id']}:");
                        foreach ($f['items'] as $it) {
                            $az = $flow->engine()->translate($it['name']);
                            $say("     {$it['id']}  {$it['name']}" . ($it['price'] !== '' ? "  (\${$it['price']})" : ''));
                            $say("       → {$az['text']}" . ($az['unknown'] ? '   ⚠ sözlükte yok: ' . implode(', ', $az['unknown']) : ''));
                        }
                    }
                }
            }
            if (!$test) {
                $aiJob = $flow->processAiQueue($deadline);
                if ($aiJob !== null) {
                    $say("Claude: iş #$aiJob işlendi");
                }
            }
        } catch (\Throwable $e) {
            $errors++;
            $db->log('error', 'cron', get_class($e) . ': ' . $e->getMessage());
            $say('Hata: ' . $e->getMessage());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        if (!$test) {
            $db->set('cron_last_run', (string)time());
            $db->setJson('cron_last_result', ['errors' => $errors, 'seconds' => round(microtime(true) - $started, 2), 'at' => time()]);
            (new Alerts($db))->check(true);
        }
        $say(sprintf('Bitti: %.1f sn, %d hata.', microtime(true) - $started, $errors));
        return $errors;
    }

    /** 10 dakikada bir Telegram'a webhook durumunu sorar; yeni bir hata varsa loglar (butonlar çalışmıyorsa sebebi burada görünür). */
    private function checkWebhook(Db $db): void
    {
        if (time() - (int)$db->get('webhook_checked_at', '0') < 600) {
            return;
        }
        $db->set('webhook_checked_at', (string)time());
        try {
            $i = App::telegram()->call('getWebhookInfo', [], 10);
        } catch (\Throwable $e) {
            $db->log('error', 'webhook', 'getWebhookInfo: ' . $e->getMessage());
            return;
        }
        $url = (string)($i['url'] ?? '');
        if ($url === '') {
            $db->log('error', 'webhook', "Webhook kurulu değil: butonlar çalışmaz. Panel → Sağlık → Webhook'u kur (ya da bot_mode => polling).");
        } elseif ($url !== (string)$this->config['webhook_url']) {
            $db->log('error', 'webhook', "Telegram'daki webhook adresi config.php'dekinden farklı: $url — Panel → Sağlık → Webhook'u kur.");
        }
        $errAt = (int)($i['last_error_date'] ?? 0);
        if ($errAt > (int)$db->get('webhook_last_error_at', '0')) {
            $db->set('webhook_last_error_at', (string)$errAt);
            $db->log('error', 'webhook', 'Telegram webhook\'a ulaşamadı (' . date('H:i', $errAt) . '): ' . ($i['last_error_message'] ?? '?')
                . ' · bekleyen güncelleme: ' . ($i['pending_update_count'] ?? 0));
            (new Alerts($db))->send('webhook', 'webhook', "⚠️ <b>Telegram bot adresine ulaşamıyor</b>\n" . Telegram::esc((string)($i['last_error_message'] ?? '?')) . "\nButonlar çalışmayabilir. Panel → Sağlık → Webhook'u kur.", 21600);
        } elseif ($errAt > 0 && time() - $errAt > 1800) {
            (new Alerts($db))->resolve('webhook', 'webhook', '✅ Telegram webhook yeniden çalışıyor.');
        }
    }
}
