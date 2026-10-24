<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Yedek: çeviri bankası, sözlük, ayarlar, kanal listesi ve kaynak adresleri.
 * API anahtarları, panel şifresi ve işler yedeğe girmez. Sağlayıcı anahtarları geri yüklemeden sonra panelden yeniden girilir.
 */
final class Backup
{
    private const SETTINGS = ['dictionary', 'style_rules', 'translate_mode', 'claude_model', 'claude_effort', 'claude_monthly_limit',
        'markup_a', 'markup_b', 'markup_round', 'alerts_enabled', 'alerts_daily', 'alerts_daily_hour', 'login_notify'];

    public function __construct(private Db $db)
    {
    }

    public function export(): array
    {
        $settings = [];
        foreach (self::SETTINGS as $k) {
            $v = $this->db->get($k);
            if ($v !== null) {
                $settings[$k] = $v;
            }
        }
        return [
            'tgwatch' => 1, 'created' => date('c'),
            'settings' => $settings,
            'sources' => array_map(static fn($s) => ['kind' => $s['kind'], 'name' => $s['name'], 'api_url' => $s['api_url']], $this->db->all('SELECT kind, name, api_url FROM sources ORDER BY id')),
            'channels' => array_map(static fn($c) => ['username' => $c['username'], 'title' => $c['title'], 'enabled' => (int)$c['enabled'], 'source' => $c['source_name']],
                $this->db->all('SELECT c.username, c.title, c.enabled, s.name source_name FROM channels c LEFT JOIN sources s ON s.id = c.source_id ORDER BY c.username')),
            'tm' => array_map(static fn($r) => ['kind' => $r['kind'], 'en' => $r['en'], 'az' => $r['az'], 'origin' => $r['origin']],
                $this->db->all("SELECT kind, en, az, origin FROM tm WHERE status = 'ok' AND origin != 'import'")),
        ];
    }

    /** @return array{tm:int, channels:int, settings:int, sources:int} */
    public function import(string $json): array
    {
        $d = json_decode($json, true);
        if (!is_array($d) || ($d['tgwatch'] ?? 0) !== 1) {
            throw new \InvalidArgumentException('Bu bir tgwatch yedek dosyası değil.');
        }
        $n = ['tm' => 0, 'channels' => 0, 'settings' => 0, 'sources' => 0];
        $mem = new Memory($this->db);
        $this->db->tx(function () use ($d, &$n, $mem): void {
            foreach ($d['settings'] ?? [] as $k => $v) {
                if (in_array($k, self::SETTINGS, true) && is_string($v)) {
                    $this->db->set($k, $v);
                    $n['settings']++;
                }
            }
            foreach ($d['sources'] ?? [] as $s) {
                if (!isset($s['kind'], $s['name'], $s['api_url']) || !in_array($s['kind'], ['own', 'provider'], true) || !preg_match('~^https://~i', (string)$s['api_url'])) {
                    continue;
                }
                if (!$this->db->one('SELECT id FROM sources WHERE name = ?', [$s['name']])) {
                    $this->db->run('INSERT INTO sources(kind, name, api_url, api_key, created_at) VALUES(?,?,?,?,?)', [$s['kind'], $s['name'], $s['api_url'], '', time()]);
                    $n['sources']++;
                }
            }
            foreach ($d['channels'] ?? [] as $c) {
                $u = ChannelReader::cleanUsername((string)($c['username'] ?? ''));
                if ($u === '') {
                    continue;
                }
                $src = isset($c['source']) ? $this->db->one('SELECT id FROM sources WHERE name = ?', [(string)$c['source']]) : null;
                $this->db->run('INSERT OR IGNORE INTO channels(username, title, enabled, source_id, created_at) VALUES(?,?,?,?,?)', [$u, (string)($c['title'] ?? ''), (int)($c['enabled'] ?? 1), $src['id'] ?? null, time()]);
                $n['channels'] += $this->db->pdo->query('SELECT changes()')->fetchColumn() > 0 ? 1 : 0;
            }
            foreach ($d['tm'] ?? [] as $r) {
                if (isset($r['kind'], $r['en'], $r['az']) && in_array($r['kind'], ['service', 'category'], true)) {
                    $origin = isset(Memory::ORIGIN_RANK[$r['origin'] ?? '']) && $r['origin'] !== 'import' ? $r['origin'] : 'manual';
                    $mem->learn($r['kind'], (string)$r['en'], (string)$r['az'], $origin);
                    $n['tm']++;
                }
            }
        });
        $mem->rebuildSegments();
        $this->db->log('info', 'backup', "Yedek geri yüklendi: {$n['tm']} çift, {$n['channels']} kanal, {$n['settings']} ayar");
        return $n;
    }
}
