<?php
declare(strict_types=1);

namespace TgWatch;

/**
 * Admin API v2'nin servis işlemleri (Kasım 2026'da eklendi):
 *   GET /services                    servis listesi: kategori, sağlayıcı, sağlayıcı servis ID'si, fiyatlar, durum
 *   GET /services/names-descriptions servis adları ve açıklamaları (dillere göre)
 *   (POST /services/change-status    servis aç/kapat: henüz kullanılmıyor)
 *
 * Bununla eşleştirme tahmin olmaktan çıkar: panel her servisin hangi sağlayıcının hangi servisi olduğunu söyler.
 *   - Ad çifti:       sağlayıcı servisinin adı (remote_services) → senin panelindeki ad      (tm kind=service, origin=panel)
 *   - Kategori çifti: sağlayıcı kategorisi → senin kategorin (en çok oy alan)                 (tm kind=category, origin=panel)
 *   - Açıklama çifti: sağlayıcının açıklaması (API veriyorsa) → senin Azerice açıklaman,
 *                     ve panelindeki başka dil sürümü (en/tr/ru) → Azerice sürüm                (tm kind=desc, origin=panel)
 * Panelde bu işlemlere API anahtarının ayarlarından izin verilmeli; sağlayıcı bilgisi gizlenirse (anahtar ayarı) kesin eşleşme yapılamaz.
 * DENENMEDİ: gerçek panele istek bu ortamdan yapılamadı; alan adları Perfect Panel'in yayımladığı OpenAPI belgesinden.
 */
final class PanelServices
{
    public const LANGS = 'az,en,tr,ru';
    private const REPORT = 'panel_exact_report';

    public function __construct(private Db $db, private array $config)
    {
    }

    public function configured(): bool
    {
        return $this->url() !== '' && $this->key() !== '';
    }

    private function url(): string
    {
        return rtrim(trim((string)($this->config['panel_api_url'] ?? '')), '/');
    }

    private function key(): string
    {
        return trim((string)($this->config['panel_api_key'] ?? ''));
    }

    /** GET isteği; PanelAdmin::$fake testlerde ağın yerine geçer. @return array<mixed> */
    private function get(string $path, array $query): array
    {
        if (!preg_match('~^https://~i', $this->url())) {
            throw new \InvalidArgumentException('Panel API adresi https:// ile başlamalı (Ayarlar → Yayın ve panel API).');
        }
        $full = $this->url() . $path . '?' . http_build_query($query);
        if (PanelAdmin::$fake !== null) {
            $body = (string)(PanelAdmin::$fake)($full);
            $status = 200;
        } else {
            $r = Http::request('GET', $full, ['X-Api-Key: ' . $this->key(), 'Accept: application/json'], null, 30);
            if ($r['error'] !== '') {
                throw new \RuntimeException('Bağlantı hatası: ' . $r['error']);
            }
            $body = $r['body'];
            $status = (int)$r['status'];
        }
        $d = json_decode($body, true);
        $msg = is_array($d) ? trim((string)($d['error_message'] ?? '')) : '';
        if ($status === 401 || $status === 403) {
            throw new \RuntimeException('Yetki yok (HTTP ' . $status . ')' . ($msg !== '' ? ': ' . $msg : '') . '. Panelde Admin API anahtarının ayarlarından bu işleme izin ver.');
        }
        if ($status === 429) {
            throw new \RuntimeException('Panel istek sınırı (saniyede 5) aşıldı; biraz sonra tekrar dene.');
        }
        if (!is_array($d)) {
            throw new \RuntimeException('Beklenmeyen cevap (HTTP ' . $status . '): ' . mb_substr(trim(strip_tags($body)), 0, 160));
        }
        if ($status !== 200 || (int)($d['error_code'] ?? 0) !== 0) {
            throw new \RuntimeException('Panel hatası' . ($msg !== '' ? ': ' . $msg : ' (HTTP ' . $status . ')'));
        }
        return $d;
    }

    private static function money($v): ?float
    {
        if (is_array($v)) {
            $v = $v['value'] ?? null;
        }
        return is_numeric($v) ? (float)$v : null;
    }

    private static function intOrNull($v): ?int
    {
        return is_numeric($v) ? (int)$v : null;
    }

    /** Bir sayfa servis. @return array{list:list<array>,raw:int} */
    public function servicesPage(int $offset, int $limit = 1000): array
    {
        $d = $this->get('/services', ['offset' => $offset, 'limit' => $limit]);
        $list = $d['data']['list'] ?? ($d['data'] ?? []);
        $out = [];
        foreach (is_array($list) ? $list : [] as $s) {
            if (!is_array($s) || !isset($s['id'])) {
                continue;
            }
            $psid = $s['provider_service_id'] ?? null;
            $out[] = [
                'id' => (int)$s['id'],
                'name' => trim((string)($s['name'] ?? '')),
                'category_id' => self::intOrNull($s['category_id'] ?? null),
                'category_name' => trim((string)($s['category_name'] ?? '')),
                'category_status' => (string)($s['category_status'] ?? ''),
                'provider' => trim((string)($s['provider'] ?? '')),
                'provider_service_id' => $psid === null || $psid === '' ? '' : trim((string)$psid),
                'rate' => self::money($s['rate'] ?? null),
                'provider_rate' => self::money($s['provider_rate'] ?? null),
                'currency' => (string)(is_array($s['rate'] ?? null) ? ($s['rate']['currency_code'] ?? '') : ''),
                'min' => self::intOrNull($s['min'] ?? null),
                'max' => self::intOrNull($s['max'] ?? null),
                'status' => (string)($s['status'] ?? ''),
                'hidden' => !empty($s['is_hidden']),
            ];
        }
        return ['list' => $out, 'raw' => is_array($list) ? count($list) : 0];
    }

    /** Bütün servisler (1000'erli sayfalar, saniyede 5 istek sınırına uyarak). @return list<array> */
    public function services(int $max = 20000): array
    {
        $all = [];
        for ($offset = 0; $offset < $max; $offset += 1000) {
            if ($offset > 0) {
                usleep(250000);
            }
            $p = $this->servicesPage($offset);
            array_push($all, ...$p['list']);
            if ($p['raw'] < 1000) {
                break;
            }
        }
        return $all;
    }

    /**
     * Ad ve açıklamalar, dillere göre.
     * @param list<int> $ids
     * @return array<int,array<string,array{name:string,description:string}>> id => lang => …
     */
    public function namesDescriptions(array $ids, string $langs = self::LANGS): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_unique(array_map('intval', $ids))), 100) as $i => $chunk) {
            if ($i > 0) {
                usleep(250000);
            }
            $d = $this->get('/services/names-descriptions', ['ids' => implode(',', $chunk), 'lang_codes' => $langs, 'limit' => 100]);
            foreach ((array)($d['data']['list'] ?? []) as $row) {
                if (!is_array($row) || !isset($row['id'])) {
                    continue;
                }
                foreach ((array)($row['content'] ?? []) as $c) {
                    if (!is_array($c) || empty($c['lang_code'])) {
                        continue;
                    }
                    $out[(int)$row['id']][strtolower((string)$c['lang_code'])] = [
                        'name' => trim((string)($c['name'] ?? '')),
                        'description' => trim(str_replace(["\r\n", "\r"], "\n", (string)($c['description'] ?? ''))),
                    ];
                }
            }
        }
        return $out;
    }

    /** Sağlayıcı adı/takma adı ya da alan adı → tgwatch'taki sağlayıcı kaynağı. @return array<string,int> */
    private function providerIndex(): array
    {
        $idx = [];
        $norm = static fn(string $s): string => preg_replace('/[^a-z0-9]+/', '', strtolower($s)) ?? '';
        foreach ($this->db->all("SELECT id, name, api_url FROM sources WHERE kind = 'provider'") as $s) {
            $host = strtolower((string)parse_url((string)$s['api_url'], PHP_URL_HOST));
            $host = preg_replace('/^www\./', '', $host) ?? $host;
            foreach (array_filter([$norm($host), $norm((string)preg_replace('/\.[a-z]{2,}$/', '', $host)), $norm((string)$s['name'])]) as $k) {
                $idx[$k] ??= (int)$s['id'];
            }
        }
        return $idx;
    }

    private function sourceFor(string $provider, array $idx): ?int
    {
        if ($provider === '') {
            return null;
        }
        $p = strtolower(trim($provider));
        $p = (string)(parse_url(str_contains($p, '://') ? $p : 'https://' . $p, PHP_URL_HOST) ?: $p);
        $p = preg_replace('/^www\./', '', $p) ?? $p;
        $n = preg_replace('/[^a-z0-9]+/', '', $p) ?? '';
        $bare = preg_replace('/[^a-z0-9]+/', '', (string)preg_replace('/\.[a-z]{2,}$/', '', $p)) ?? '';
        return $idx[$n] ?? $idx[$bare] ?? null;
    }

    /**
     * Panelden kesin eşleştir ve öğren. $withDesc: ad/açıklama işlemini de kullan (açıklama ve diğer dillerden öğrenme).
     * @return array<string,mixed> rapor
     */
    public function run(bool $withDesc = true): array
    {
        @set_time_limit(180);
        if (!$this->configured()) {
            throw new \InvalidArgumentException('Önce Ayarlar → Yayın ve panel API\'de panel API adresi ve anahtarını gir.');
        }
        $t0 = microtime(true);
        $list = $this->services();
        $idx = $this->providerIndex();
        $mem = new Memory($this->db);
        $rep = ['at' => time(), 'services' => count($list), 'with_provider' => 0, 'exact' => 0, 'names' => 0, 'same' => 0, 'categories' => 0,
            'desc' => 0, 'desc_lang' => 0, 'names_lang' => 0, 'langs' => [], 'unknown_providers' => [], 'not_synced' => 0, 'provider_hidden' => false, 'desc_error' => ''];
        $rows = [];
        $catVotes = [];
        $remoteDesc = [];
        $hasDesc = in_array('description', array_column($this->db->all('PRAGMA table_info(remote_services)'), 'name'), true);
        foreach ($list as $s) {
            $src = null;
            $remote = null;
            if ($s['provider'] !== '' || $s['provider_service_id'] !== '') {
                $rep['with_provider']++;
            }
            if ($s['provider'] !== '' && $s['provider_service_id'] !== '') {
                $src = $this->sourceFor($s['provider'], $idx);
                if ($src === null) {
                    $rep['unknown_providers'][$s['provider']] = ($rep['unknown_providers'][$s['provider']] ?? 0) + 1;
                } else {
                    $remote = $this->db->one('SELECT * FROM remote_services WHERE source_id = ? AND service_id = ?', [$src, (int)$s['provider_service_id']]);
                    if (!$remote) {
                        $rep['not_synced']++;
                    }
                }
            }
            $rows[] = [$s, $src, $remote ? (int)$remote['service_id'] : null];
            if (!$remote) {
                continue;
            }
            $rep['exact']++;
            if ($s['name'] !== '' && !Learner::same((string)$remote['name'], $s['name'])) {
                $mem->learn('service', (string)$remote['name'], $s['name'], 'panel', ['provider_source' => $src, 'provider_service' => (int)$remote['service_id'], 'own_service' => $s['id']]);
                $rep['names']++;
            } else {
                $rep['same']++;
            }
            if ((string)$remote['category'] !== '' && $s['category_name'] !== '' && !Learner::same((string)$remote['category'], $s['category_name'])) {
                $catVotes[(string)$remote['category']][$s['category_name']] = ($catVotes[(string)$remote['category']][$s['category_name']] ?? 0) + 1;
            }
            if ($hasDesc && trim((string)($remote['description'] ?? '')) !== '') {
                $remoteDesc[$s['id']] = (string)$remote['description'];
            }
        }
        $rep['provider_hidden'] = $list !== [] && $rep['with_provider'] === 0;
        foreach ($catVotes as $pc => $votes) {
            arsort($votes);
            $mem->learn('category', $pc, (string)array_key_first($votes), 'panel');
            $rep['categories']++;
        }
        $this->saveSnapshot($rows);
        if ($withDesc && $list) {
            try {
                $this->learnDescriptions(array_column($list, 'id'), $remoteDesc, $rep);
            } catch (\Throwable $e) {
                $rep['desc_error'] = $e->getMessage();
            }
        }
        if ($rep['names'] || $rep['names_lang']) {
            $mem->rebuildSegments();
        }
        $rep['unknown_providers'] = array_slice($rep['unknown_providers'], 0, 20, true);
        $rep['ms'] = (int)((microtime(true) - $t0) * 1000);
        $this->db->setJson(self::REPORT, $rep);
        $this->db->log('info', 'bank', "Panelden kesin eşleşme: {$rep['exact']} / {$rep['services']} servis, {$rep['names']} ad, {$rep['categories']} kategori, " . ($rep['desc'] + $rep['desc_lang']) . ' açıklama öğrenildi');
        return $rep;
    }

    /** Açıklamalar: sağlayıcının açıklaması → Azerice açıklaman; panelindeki başka dil (en/tr/ru) → Azerice. */
    private function learnDescriptions(array $ids, array $remoteDesc, array &$rep): void
    {
        $nd = $this->namesDescriptions($ids);
        $desc = new DescTranslator($this->db, $this->config);
        $mem = new Memory($this->db);
        $langs = [];
        foreach ($nd as $id => $byLang) {
            foreach ($byLang as $lang => $c) {
                if ($c['name'] !== '' || $c['description'] !== '') {
                    $langs[$lang] = ($langs[$lang] ?? 0) + 1;
                }
            }
            $az = $byLang['az'] ?? null;
            if (!$az) {
                continue;
            }
            if ($az['description'] !== '' && isset($remoteDesc[$id]) && $desc->learn($remoteDesc[$id], $az['description'], 'panel')) {
                $rep['desc']++;
            }
            foreach (['en', 'tr', 'ru'] as $l) {
                $o = $byLang[$l] ?? null;
                if (!$o) {
                    continue;
                }
                if ($o['description'] !== '' && $az['description'] !== '' && $desc->learn($o['description'], $az['description'], 'panel')) {
                    $rep['desc_lang']++;
                }
                if ($o['name'] !== '' && $az['name'] !== '' && !Learner::same($o['name'], $az['name'])) {
                    $mem->learn('service', $o['name'], $az['name'], 'panel', ['own_service' => (int)$id]);
                    $rep['names_lang']++;
                }
            }
        }
        arsort($langs);
        $rep['langs'] = $langs;
    }

    /** Son servis listesi (kâr, kapatma, kanal paylaşımı gibi sonraki işler için). */
    private function saveSnapshot(array $rows): void
    {
        $this->db->tx(function () use ($rows): void {
            $this->db->run('DELETE FROM panel_services');
            $st = $this->db->pdo->prepare('INSERT OR REPLACE INTO panel_services(id, name, category_id, category_name, category_status, provider, provider_service_id, source_id, remote_service, rate, provider_rate, currency, min, max, status, hidden, at)
                VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $now = time();
            foreach ($rows as [$s, $src, $remote]) {
                $st->execute([$s['id'], $s['name'], $s['category_id'], $s['category_name'], $s['category_status'], $s['provider'], $s['provider_service_id'], $src, $remote,
                    $s['rate'], $s['provider_rate'], $s['currency'], $s['min'], $s['max'], $s['status'], $s['hidden'] ? 1 : 0, $now]);
            }
        });
    }

    /** @return list<int> kesin eşleşmesi olan kendi servis ID'leri (eşleştirme bunları tahmin etmez) */
    public static function exactOwn(Db $db): array
    {
        return array_map('intval', array_column($db->all("SELECT DISTINCT own_service FROM tm WHERE origin = 'panel' AND kind = 'service' AND own_service IS NOT NULL AND provider_source IS NOT NULL"), 'own_service'));
    }

    public function report(): ?array
    {
        $r = $this->db->getJson(self::REPORT);
        return is_array($r) ? $r : null;
    }

    /**
     * Bağlantı testi: üç işlemden ikisini (okuma) dener, hangi alanların geldiğini söyler.
     * @return list<array{0:string,1:string,2:string}> [ad, ok|warn|bad, açıklama]
     */
    public function test(): array
    {
        $r = [];
        try {
            $p = $this->servicesPage(0, 5);
            $s = $p['list'][0] ?? null;
            $r[] = ['Get service list', 'ok', $p['raw'] ? 'çalışıyor (' . $p['raw'] . ' örnek servis)' : 'çalışıyor ama panelde servis yok'];
            if ($s) {
                $hidden = $s['provider'] === '' && $s['provider_service_id'] === '';
                $r[] = ['Sağlayıcı bilgisi', $hidden ? 'bad' : 'ok', $hidden
                    ? 'gelmiyor: anahtar ayarlarında sağlayıcı bilgisi gizlenmiş ya da "View provider service data" izni yok. Kesin eşleşme bunsuz yapılamaz.'
                    : 'geliyor (örnek: ' . $s['provider'] . ' · ' . $s['provider_service_id'] . ')'];
                $r[] = ['Sağlayıcı fiyatı', $s['provider_rate'] === null ? 'warn' : 'ok', $s['provider_rate'] === null ? 'gelmiyor (kâr hesabı için gerekli)' : 'geliyor'];
                if ($s['provider'] !== '') {
                    $src = $this->sourceFor($s['provider'], $this->providerIndex());
                    $r[] = ['Sağlayıcı tgwatch\'ta', $src ? 'ok' : 'warn', $src ? 'bulundu' : '"' . $s['provider'] . '" Ayarlar → Kaynaklar\'da yok ya da adı/alan adı farklı'];
                }
                try {
                    $nd = $this->namesDescriptions([$s['id']]);
                    $langs = array_keys(array_filter($nd[$s['id']] ?? [], static fn($c) => $c['name'] !== '' || $c['description'] !== ''));
                    $r[] = ['Get service names and descriptions', 'ok', 'çalışıyor' . ($langs ? ' (diller: ' . implode(', ', $langs) . ')' : ' (içerik boş)')];
                    if ($langs && !in_array('az', $langs, true)) {
                        $r[] = ['Azerice içerik', 'warn', 'bu serviste "az" dilinde ad/açıklama yok; açıklamalardan öğrenme Azerice sürüm ister'];
                    }
                } catch (\Throwable $e) {
                    $r[] = ['Get service names and descriptions', 'bad', $e->getMessage()];
                }
            }
        } catch (\Throwable $e) {
            $r[] = ['Get service list', 'bad', $e->getMessage()];
        }
        return $r;
    }
}
