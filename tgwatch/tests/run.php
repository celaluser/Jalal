<?php
declare(strict_types=1);

// Otomatik testler:  php tests/run.php
// Ağa çıkmaz: Telegram ve kanal okuyucu sahte sınıflarla değiştirilir.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require dirname(__DIR__) . '/src/bootstrap.php';

use TgWatch\App;
use TgWatch\ChannelReader;
use TgWatch\Db;
use TgWatch\Dictionary;
use TgWatch\Flow;
use TgWatch\Normalizer;
use TgWatch\Parser;
use TgWatch\Publisher\ManualPublisher;
use TgWatch\Telegram;
use TgWatch\Translator;
use TgWatch\Watcher;

class FakeTelegram extends Telegram
{
    public array $calls = [];
    public array $docs = [];
    private int $nextId = 500;

    public function __construct()
    {
        parent::__construct('123456:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghi');
    }

    public function call(string $method, array $params = [], int $timeout = 20)
    {
        $this->calls[] = [$method, $params];
        return $method === 'sendMessage' ? ['message_id' => $this->nextId++] : true;
    }

    public function sendDocument(int|string $chatId, string $filename, string $content, string $caption = ''): void
    {
        $this->docs[] = [$filename, $content];
    }

    public function count(string $method): int
    {
        return count(array_filter($this->calls, static fn($c) => $c[0] === $method));
    }

    public function last(string $method): ?array
    {
        foreach (array_reverse($this->calls) as $c) {
            if ($c[0] === $method) {
                return $c[1];
            }
        }
        return null;
    }
}

final class FakeReader extends ChannelReader
{
    public array $pages = [];

    public function fetch(string $username, ?int $before = null): array
    {
        return $this->pages[$username] ?? ['ok' => false, 'error' => 'yok', 'title' => '', 'posts' => []];
    }

    public function fetchLast(string $username, int $count): array
    {
        $r = $this->fetch($username);
        $r['posts'] = array_slice($r['posts'], -$count);
        return $r;
    }
}

$passed = 0;
$failed = 0;
function test(string $name, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        $passed++;
        echo "  ✓ $name\n";
    } catch (Throwable $e) {
        $failed++;
        echo "  ✗ $name\n      " . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ")\n";
    }
}
function eq($expected, $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(($msg ? "$msg: " : '') . 'beklenen ' . var_export($expected, true) . ', gelen ' . var_export($actual, true));
    }
}
function ok(bool $cond, string $msg = 'koşul sağlanmadı'): void
{
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}

$tmp = sys_get_temp_dir() . '/tgwatch_test_' . getmypid();
@mkdir($tmp);
const OWNER = 777;
function env(): array
{
    global $tmp;
    static $n = 0;
    $dir = $tmp . '/' . (++$n);
    mkdir($dir);
    App::setConfig(['data_dir' => $dir, 'owner_id' => OWNER, 'bot_token' => '123456:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghi', 'claude_api_key' => '']);
    $db = App::db();
    $tg = new FakeTelegram();
    $flow = new Flow($db, $tg, App::config());
    return [$db, $tg, $flow];
}
function cb(int $updateId, int $from, string $data): array
{
    return ['update_id' => $updateId, 'callback_query' => ['id' => 'cb' . $updateId, 'from' => ['id' => $from], 'data' => $data]];
}
function msg(int $updateId, int $from, string $text): array
{
    return ['update_id' => $updateId, 'message' => ['from' => ['id' => $from], 'chat' => ['id' => $from, 'type' => 'private'], 'text' => $text]];
}

const SAMPLE = "📢 New Threads Services\n\n🆔 13125 𝐓𝐡𝐫𝐞𝐚𝐝𝐬 𝐅𝐨𝐥𝐥𝐨𝐰𝐞𝐫𝐬 | No Drop | Max 50K | 5K/Day | 30 Days Refill ♻️\n💰 Price: \$1.20 per 1000\n\n🆔 13126 Threads Likes | Fast\n💰 0.35\$";

echo "Normalizer\n";
test('kalın/italik/sans/çift çizgili harfler', function () {
    eq('Threads', Normalizer::text('𝐓𝐡𝐫𝐞𝐚𝐝𝐬'));
    eq('Threads', Normalizer::text('𝘛𝘩𝘳𝘦𝘢𝘥𝘴'));
    eq('Threads', Normalizer::text('𝗧𝗵𝗿𝗲𝗮𝗱𝘀'));
    eq('Threads', Normalizer::text('𝕋𝕙𝕣𝕖𝕒𝕕𝕤'));
    eq('Threads', Normalizer::text('Ｔｈｒｅａｄｓ'));
});
test('italik h deliği ve kalın rakamlar', function () {
    eq('hello 2026', Normalizer::text('ℎ𝑒𝑙𝑙𝑜 𝟐𝟎𝟐𝟔'));
});
test('emoji ve Azerice harfler korunur', function () {
    eq('♻️ İzləyici 🆔', Normalizer::text('♻️ İzləyici 🆔'));
});
test('sıfır genişlikli ve NBSP boşluklar', function () {
    eq('a b', Normalizer::text("a\u{00A0}\u{200B}b"));
});

echo "Translator\n";
$tr = new Translator(Dictionary::defaults());
test('kullanıcı örneği birebir', function () use ($tr) {
    $r = $tr->translate('Threads Followers | No Drop | Max 50K | 5K/Day | 30 Days Refill ♻️');
    eq('Threads İzləyici | Azalma olmaz | Maks 50K | Gündə 5K | 30 Gün Qarantili ♻️', $r['text']);
    eq([], $r['unknown']);
});
test('kalın Unicode girdi', function () use ($tr) {
    eq('Threads Bəyəni | Sürətli', $tr->translate('𝐓𝐡𝐫𝐞𝐚𝐝𝐬 𝐋𝐢𝐤𝐞𝐬 | 𝐅𝐚𝐬𝐭')['text']);
});
test('uzun ifade önce: Random/Custom Comments', function () use ($tr) {
    eq('Instagram Təsadüfi Rəy | Azalma olmaz', $tr->translate('Instagram Random Comments | Non-Drop')['text']);
    eq('Instagram Özəl Rəy', $tr->translate('instagram custom comments')['text']);
});
test('Lifetime Refill / No Refill', function () use ($tr) {
    eq('TikTok Bəyəni | Ömürlük Qarantili', $tr->translate('TikTok Likes | Lifetime Refill')['text']);
    eq('TikTok Baxış | Qarantisiz', $tr->translate('TikTok Views | No Refill')['text']);
});
test('Speed 1M/Day ve Per Day', function () use ($tr) {
    eq('YouTube Baxış | Gündə 1M', $tr->translate('YouTube Views | Speed 1M/Day')['text']);
    eq('YouTube Bəyəni | Gündə 10K', $tr->translate('YouTube Likes | 10K Per Day')['text']);
});
test('bilinmeyen kelimeler uyarı olur, markalar olmaz', function () use ($tr) {
    $r = $tr->translate('Instagram Followers | Arab Targeted');
    eq('Instagram İzləyici | Arab Targeted', $r['text']);
    eq(['Arab', 'Targeted'], $r['unknown']);
});
test('kelime içinde eşleşme yok (Likes ≠ Likeship)', function () use ($tr) {
    eq('Likeship', $tr->translate('Likeship')['text']);
});
test('ad kalıbı: Maks ve hız kapatılabilir', function () {
    $d = Dictionary::defaults();
    $d['options'] = ['keep_max' => false, 'keep_speed' => false];
    eq('Threads İzləyici | Azalma olmaz | 30 Gün Qarantili ♻️', (new Translator($d))->translate('Threads Followers | No Drop | Max 50K | 5K/Day | 30 Days Refill ♻️')['text']);
});
test('çeviri sonucu tekrar çevrilmez (yer tutucu)', function () {
    $d = Dictionary::defaults();
    $d['phrases']['Gün'] = 'XXX';
    eq('30 Gün Qarantili', (new Translator($d))->translate('30 Days Refill')['text']);
});

echo "Parser\n";
test('örnek duyuru: ID, ad, fiyat, kategori', function () {
    $items = Parser::parse(SAMPLE);
    eq(2, count($items));
    eq(13125, $items[0]['id']);
    eq('Threads Followers | No Drop | Max 50K | 5K/Day | 30 Days Refill ♻️', $items[0]['name']);
    eq('1.20', $items[0]['price']);
    eq('📢 New Threads Services', $items[0]['category']);
    eq(13126, $items[1]['id']);
    eq('0.35', $items[1]['price']);
});
test('farklı ID biçimleri', function () {
    $items = Parser::parse("ID: 20001 | Instagram Likes\n5521 - Instagram Saves\nTikTok Views - ID: 9001\nService ID 4455: YouTube Views");
    eq([20001, 5521, 9001, 4455], array_column($items, 'id'));
    eq(['Instagram Likes', 'Instagram Saves', 'TikTok Views', 'YouTube Views'], array_column($items, 'name'));
});
test('sayıyla başlayan sıradan satır ID sayılmaz', function () {
    eq([], Parser::parse("30 Days Refill guaranteed\n1. Fast start\n2) Cheap price\nContact @admin\n🚀 24-48 Hours\n⏱ 10-20 min start"));
});
test('satır içi fiyat addan temizlenir', function () {
    $items = Parser::parse('🆔 777 Instagram Likes | Fast | $0.50');
    eq('Instagram Likes | Fast', $items[0]['name']);
    eq('0.50', $items[0]['price']);
});
test('ID tek başına satırda, ad altında', function () {
    $items = Parser::parse("🆔 3131\nInstagram Story Views");
    eq(3131, $items[0]['id']);
    eq('Instagram Story Views', $items[0]['name']);
});
test('servis içermeyen gönderi boş döner', function () {
    eq([], Parser::parse('Hello everyone, panel maintenance tonight.'));
});

echo "ChannelReader\n";
$html = file_get_contents(__DIR__ . '/fixtures/channel.html');
test('t.me/s örnek sayfası ayrıştırılır', function () use ($html) {
    $r = ChannelReader::parse($html, 'demosmm');
    ok($r['ok'], 'ok değil: ' . $r['error']);
    eq('Demo SMM Updates', $r['title']);
    eq([101, 102, 103], array_column($r['posts'], 'id'));
    eq('2026-10-21T12:30:00+00:00', $r['posts'][1]['date']);
});
test('<br> satır sonuna, kalın harf metne dönüşür', function () use ($html) {
    $p = ChannelReader::parse($html)['posts'][1]['text'];
    ok(str_contains($p, "\n🆔 13125 𝐓𝐡𝐫𝐞𝐚𝐝𝐬"), 'satır yapısı bozuk: ' . $p);
    $items = Parser::parse($p);
    eq([13125, 13126], array_column($items, 'id'));
    eq('1.20', $items[0]['price']);
});
test('albüm tekrarı atlanır, özel emoji alt metni alınır', function () use ($html) {
    $posts = ChannelReader::parse($html)['posts'];
    eq(3, count($posts));
    ok(str_contains($posts[2]['text'], '🔥 Hot'));
});
test('kapalı/bulunamayan kanal sayfası hata verir', function () {
    $r = ChannelReader::parse('<html><body><div class="tgme_page_title">Demo</div><div class="tgme_page_extra">1 subscriber</div></body></html>');
    ok(!$r['ok']);
    ok(str_contains($r['error'], 'önizlemesi kapalı'));
});
test('tanınmayan sayfa', function () {
    $r = ChannelReader::parse('<html><body>captcha</body></html>');
    ok(!$r['ok']);
    ok(str_contains($r['error'], 'tanınmadı'));
});
test('kanal adı temizleme', function () {
    eq('demosmm', ChannelReader::cleanUsername('@demosmm'));
    eq('demosmm', ChannelReader::cleanUsername('https://t.me/s/demosmm?before=5'));
    eq('demosmm', ChannelReader::cleanUsername('t.me/demosmm/102'));
    eq('', ChannelReader::cleanUsername('bad name!'));
    eq('', ChannelReader::cleanUsername('ab'));
});

echo "Flow (bot akışı)\n";
test('iş oluşturulur ve butonlu önizleme gönderilir', function () {
    [$db, $tg, $flow] = env();
    $id = $flow->createJob('demosmm', 102, SAMPLE);
    ok($id !== null);
    $job = $db->job($id);
    eq('awaiting', $job['status']);
    eq('Threads İzləyici | Azalma olmaz | Maks 50K | Gündə 5K | 30 Gün Qarantili ♻️', $job['items'][0]['az']);
    $m = $tg->last('sendMessage');
    eq(OWNER, $m['chat_id']);
    eq('HTML', $m['parse_mode']);
    ok(str_contains($m['text'], 'Çeviriyi onaylıyor musun?'));
    $btn = array_merge(...$m['reply_markup']['inline_keyboard']);
    eq(["ok:$id", "ed:$id", "rg:$id", "rj:$id"], array_column($btn, 'callback_data'));
    eq(500, (int)$job['tg_message_id']);
});
test('aynı gönderi ve aynı metin iki kez işlenmez', function () {
    [$db, $tg, $flow] = env();
    ok($flow->createJob('demosmm', 102, SAMPLE) !== null);
    eq(null, $flow->createJob('demosmm', 102, SAMPLE));
    eq(null, $flow->createJob('demosmm', 150, SAMPLE . '  '));
    eq(1, $tg->count('sendMessage'));
});
test('servis olmayan gönderiden iş çıkmaz', function () {
    [$db, $tg, $flow] = env();
    eq(null, $flow->createJob('demosmm', 101, 'Hello, maintenance.'));
    eq(0, $tg->count('sendMessage'));
});
test('yabancı kullanıcı butona basarsa hiçbir şey değişmez', function () {
    [$db, $tg, $flow] = env();
    $id = $flow->createJob('demosmm', 102, SAMPLE);
    $flow->handleUpdate(cb(1, 999, "ok:$id"));
    eq('awaiting', $db->job($id)['status']);
    eq('Yetkiniz yok.', $tg->last('answerCallbackQuery')['text']);
    eq([], $tg->docs);
});
test('Onayla → manual yayın paketi gönderilir, durum published', function () {
    [$db, $tg, $flow] = env();
    $id = $flow->createJob('demosmm', 102, SAMPLE);
    $flow->handleUpdate(cb(1, OWNER, "ok:$id"));
    $job = $db->job($id);
    eq('published', $job['status']);
    eq(1, count($tg->docs));
    ok(str_contains($tg->docs[0][1], "13125\tThreads İzləyici | Azalma olmaz | Maks 50K | Gündə 5K | 30 Gün Qarantili ♻️\t1.20"));
    $pkg = null;
    foreach ($tg->calls as [$m, $p]) {
        if ($m === 'sendMessage' && str_contains($p['text'], 'Import paketi')) {
            $pkg = $p['text'];
        }
    }
    ok($pkg !== null && str_contains($pkg, '13125,13126'), 'paket mesajı yok');
    $edit = $tg->last('editMessageText');
    eq([], $edit['reply_markup']['inline_keyboard'], 'butonlar kaldırılmalı');
    ok(str_contains($edit['text'], 'Yayınlandı'));
});
test('Onayla iki kez basılırsa ikinci kez yayınlanmaz', function () {
    [$db, $tg, $flow] = env();
    $id = $flow->createJob('demosmm', 102, SAMPLE);
    $flow->handleUpdate(cb(1, OWNER, "ok:$id"));
    $flow->handleUpdate(cb(2, OWNER, "ok:$id"));
    eq(1, count($tg->docs));
    eq('Bu iş zaten işlendi.', $flow->approve($id));
});
test('aynı update_id tekrar gelirse yok sayılır', function () {
    [$db, $tg, $flow] = env();
    $id = $flow->createJob('demosmm', 102, SAMPLE);
    $flow->handleUpdate(cb(5, OWNER, "rj:$id"));
    $db->setStatus($id, 'awaiting');
    $flow->handleUpdate(cb(5, OWNER, "rj:$id"));
    eq('awaiting', $db->job($id)['status']);
});
test('Düzenle → "ID | ad" mesajı → güncellenir ve onaya döner', function () {
    [$db, $tg, $flow] = env();
    $id = $flow->createJob('demosmm', 102, SAMPLE);
    $flow->handleUpdate(cb(1, OWNER, "ed:$id"));
    eq('editing', $db->job($id)['status']);
    ok(str_contains($tg->last('sendMessage')['text'], "13125 | Threads İzləyici"));
    $flow->handleUpdate(msg(2, OWNER, "13126 | Threads Bəyəni | Sürətli ⚡\n9999 | yok"));
    $job = $db->job($id);
    eq('awaiting', $job['status']);
    eq('Threads Bəyəni | Sürətli ⚡', $job['items'][1]['az']);
    ok($job['items'][1]['edited']);
    ok(str_contains($tg->last('sendMessage')['text'], 'ID 9999 bu işte yok'));
    eq('', $db->get('editing_job'));
});
test('yabancının mesajı düzenleme olarak işlenmez', function () {
    [$db, $tg, $flow] = env();
    $id = $flow->createJob('demosmm', 102, SAMPLE);
    $flow->handleUpdate(cb(1, OWNER, "ed:$id"));
    $flow->handleUpdate(msg(2, 999, '13126 | hack'));
    eq('Threads Bəyəni | Sürətli', $db->job($id)['items'][1]['az']);
});
test('/iptal düzenlemeyi kapatır', function () {
    [$db, $tg, $flow] = env();
    $id = $flow->createJob('demosmm', 102, SAMPLE);
    $flow->handleUpdate(cb(1, OWNER, "ed:$id"));
    $flow->handleUpdate(msg(2, OWNER, '/iptal'));
    eq('awaiting', $db->job($id)['status']);
    eq('', $db->get('editing_job'));
});
test('Yeniden oluştur (Claude yok) güncel sözlükle çevirir, elle düzenlemeyi sıfırlar', function () {
    [$db, $tg, $flow] = env();
    $id = $flow->createJob('demosmm', 102, SAMPLE);
    $flow->applyEdits($id, '13126 | elle');
    $d = Dictionary::defaults();
    $d['phrases']['Likes'] = 'Layk';
    Dictionary::save($db, $d);
    $flow->handleUpdate(cb(1, OWNER, "rg:$id"));
    $job = $db->job($id);
    eq('Threads Layk | Sürətli', $job['items'][1]['az']);
    ok(!$job['items'][1]['edited']);
});
test('Reddet → rejected, sonra onaylanamaz', function () {
    [$db, $tg, $flow] = env();
    $id = $flow->createJob('demosmm', 102, SAMPLE);
    $flow->handleUpdate(cb(1, OWNER, "rj:$id"));
    eq('rejected', $db->job($id)['status']);
    eq('Bu iş zaten işlendi.', $flow->approve($id));
    eq([], $tg->docs);
});
test('yayın hatası → failed, Tekrar yayınla ile düzelir', function () {
    [$db, $tg, $flow] = env();
    $flow->setPublisher(new class implements TgWatch\Publisher\Publisher {
        public int $n = 0;
        public function publish(array $job): array { return ++$this->n === 1 ? ['ok' => false, 'message' => 'test hatası'] : ['ok' => true, 'message' => 'tamam']; }
        public static function name(): string { return 'test'; }
    });
    $id = $flow->createJob('demosmm', 102, SAMPLE);
    $flow->approve($id);
    eq('failed', $db->job($id)['status']);
    ok(str_contains($tg->last('editMessageText')['text'], 'test hatası'));
    eq("ok:$id", $tg->last('editMessageText')['reply_markup']['inline_keyboard'][0][0]['callback_data']);
    $flow->approve($id);
    eq('published', $db->job($id)['status']);
});
test('bota gönderilemeyen iş "new" kalır ve tekrar denenir', function () {
    [$db, , ] = env();
    $broken = new class extends FakeTelegram {
        public bool $down = true;
        public function call(string $method, array $params = [], int $timeout = 20)
        {
            if ($this->down) {
                throw new RuntimeException('ağ yok');
            }
            return parent::call($method, $params, $timeout);
        }
    };
    $flow = new Flow($db, $broken, App::config());
    $id = $flow->createJob('demosmm', 102, SAMPLE);
    eq('new', $db->job($id)['status']);
    $broken->down = false;
    $flow->retryUnsent();
    eq('awaiting', $db->job($id)['status']);
});
test('/start herkese kendi ID\'sini söyler', function () {
    [$db, $tg, $flow] = env();
    $flow->handleUpdate(msg(1, 4242, '/start'));
    ok(str_contains($tg->last('sendMessage')['text'], '<code>4242</code>'));
});
test('uzun liste 4096 sınırını aşmaz', function () {
    [$db, $tg, $flow] = env();
    $text = '';
    for ($i = 0; $i < 80; $i++) {
        $text .= '🆔 ' . (30000 + $i) . " Instagram Followers | No Drop | Max 100K | 10K/Day | 30 Days Refill ♻️\n";
    }
    $flow->createJob('demosmm', 200, $text);
    $m = $tg->last('sendMessage');
    ok(mb_strlen($m['text']) <= 4096);
    ok(str_contains($m['text'], 'servis daha'));
    ok(isset($m['reply_markup']), 'butonlar kaybolmamalı');
});
test('import paketi biçimi', function () {
    $pkg = ManualPublisher::package(['id' => 3, 'channel' => 'c', 'msg_id' => 9, 'items' => [
        ['id' => 1, 'az' => 'A', 'price' => '0.5', 'category_az' => 'Kat'], ['id' => 2, 'az' => 'B', 'price' => ''],
    ]]);
    ok(str_contains($pkg, "# Kategori: Kat\n"));
    ok(str_contains($pkg, "1\tA\t0.5\n2\tB\t\n"));
});

echo "Watcher (cron)\n";
test('ilk okuma eski gönderileri işlemez, sonra yenileri işler', function () {
    [$db, $tg, $flow] = env();
    $reader = new FakeReader();
    $w = new Watcher($db, $flow, $reader);
    $w->addChannel('@demosmm');
    $reader->pages['demosmm'] = ['ok' => true, 'error' => '', 'title' => 'Demo', 'posts' => [['id' => 102, 'text' => SAMPLE, 'date' => '']]];
    $w->runAll();
    eq(0, (int)$db->one('SELECT COUNT(*) c FROM jobs')['c']);
    eq(102, (int)$db->one('SELECT last_post_id FROM channels')['last_post_id']);
    eq('Demo', $db->one('SELECT title FROM channels')['title']);
    $reader->pages['demosmm']['posts'][] = ['id' => 103, 'text' => "🆔 555 TikTok Likes | Fast", 'date' => ''];
    $r = $w->runAll();
    eq(1, count($r['demosmm']['jobs']));
    eq(103, (int)$db->one('SELECT last_post_id FROM channels')['last_post_id']);
    $w->runAll();
    eq(1, (int)$db->one('SELECT COUNT(*) c FROM jobs')['c']);
});
test('--backfill son N gönderiyi işler, --test hiçbir şey yazmaz', function () {
    [$db, $tg, $flow] = env();
    $reader = new FakeReader();
    $w = new Watcher($db, $flow, $reader);
    $w->addChannel('demosmm');
    $reader->pages['demosmm'] = ['ok' => true, 'error' => '', 'title' => '', 'posts' => [
        ['id' => 1, 'text' => '🆔 100 Instagram Likes', 'date' => ''],
        ['id' => 2, 'text' => 'hello', 'date' => ''],
        ['id' => 3, 'text' => '🆔 300 Instagram Views', 'date' => ''],
    ]];
    $dry = $w->runAll(3, true);
    eq(2, count($dry['demosmm']['found']));
    eq(0, (int)$db->one('SELECT COUNT(*) c FROM jobs')['c']);
    eq(null, $db->one('SELECT last_post_id FROM channels')['last_post_id']);
    $w->runAll(2);
    eq(1, (int)$db->one('SELECT COUNT(*) c FROM jobs')['c'], 'yalnızca son 2 gönderi');
});
test('okuma hatası kanala yazılır ve loglanır', function () {
    [$db, $tg, $flow] = env();
    $w = new Watcher($db, $flow, new FakeReader());
    $w->addChannel('missing1');
    $w->runAll();
    eq('yok', $db->one('SELECT last_error FROM channels')['last_error']);
    ok($db->one("SELECT id FROM logs WHERE level = 'error'") !== null);
});
test('geçersiz kanal adı reddedilir', function () {
    [$db, $tg, $flow] = env();
    $w = new Watcher($db, $flow, new FakeReader());
    try {
        $w->addChannel('a b');
        ok(false, 'hata bekleniyordu');
    } catch (InvalidArgumentException) {
    }
});

echo "Kurulum\n";
test('token: boşluk/satır sonu temizlenir, hata mesajı token\'ı göstermez', function () {
    $good = '123456:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghi';
    ok((new Telegram(" $good\n"))->configured());
    ok((new Telegram("123456: ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghi"))->configured());
    ok(str_contains(Telegram::describeToken(''), 'boş'));
    ok(str_contains(Telegram::describeToken('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghi'), '":" yok'));
    ok(str_contains(Telegram::describeToken('123456:ABCDEF'), '6 karakter'));
    ok(str_contains(Telegram::describeToken("123456:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghi'"), 'geçersiz karakter'));
    ok(!str_contains(Telegram::describeToken('123456:ABCDEF'), 'ABCDEF'));
    try {
        (new Telegram(''))->call('getMe');
        ok(false);
    } catch (RuntimeException $e) {
        eq('bot_token: bot_token boş (config.php içinde doldurun)', $e->getMessage());
    }
});
test('veritabanı adı rastgele, secret.php bir kez oluşur, eski dosya taşınır', function () {
    global $tmp;
    $dir = $tmp . '/dbp';
    mkdir($dir);
    file_put_contents($dir . '/tgwatch.sqlite', 'eski');
    $a = App::dbPath($dir);
    ok(preg_match('~/tgwatch_[a-f0-9]{24}\.sqlite$~', $a) === 1, $a);
    eq($a, App::dbPath($dir));
    eq('eski', file_get_contents($a));
    ok(!is_file($dir . '/tgwatch.sqlite'));
    ok(str_starts_with(file_get_contents($dir . '/secret.php'), '<?php'));
});

test('config.php: token anahtar olarak yazılırsa Sağlık uyarır (token görünmez)', function () {
    global $tmp;
    $dir = $tmp . '/cfg';
    mkdir($dir);
    App::setConfig(['data_dir' => $dir]);
    $c = App::config();
    $c['_unknown_keys'] = ['7304…', '7073923993'];
    $rows = TgWatch\Health::local($c);
    $row = array_values(array_filter($rows, static fn($r) => $r[0] === 'config.php anahtarları'))[0] ?? null;
    ok($row !== null && $row[1] === 'fail');
    ok(str_contains($row[2], '7304…') && !str_contains($row[2], 'AAGS'));
});

echo "Dictionary\n";
test('metin kutusu ↔ sözlük', function () {
    $p = Dictionary::phrasesFromText("Likes = Bəyəni\n# yorum\n\nCustom Comments = Özəl Rəy\nbozuk satır");
    eq(['Likes' => 'Bəyəni', 'Custom Comments' => 'Özəl Rəy'], $p);
    eq($p, Dictionary::phrasesFromText(Dictionary::phrasesToText($p)));
});
test('kurallar: geçersiz regex yakalanır', function () {
    [$rules, $errors] = Dictionary::rulesFromText("(\\d+)\\s*Days => \$1 Gün\n(bozuk => x\nayraç yok");
    eq([['(\d+)\s*Days', '$1 Gün']], $rules);
    eq(2, count($errors));
    eq(Dictionary::defaults()['rules'], Dictionary::rulesFromText(Dictionary::rulesToText(Dictionary::defaults()['rules']))[0]);
});
test('veritabanına kaydet/yükle, eksik alanlar varsayılanla dolar', function () {
    [$db] = env();
    Dictionary::save($db, ['phrases' => ['A' => 'B']]);
    $d = Dictionary::load($db);
    eq(['A' => 'B'], $d['phrases']);
    eq(Dictionary::defaults()['rules'], $d['rules']);
    eq(true, $d['options']['keep_max']);
});

// temizlik
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $f) {
    $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
}
@rmdir($tmp);

echo "\n$passed geçti, $failed başarısız\n";
exit($failed ? 1 : 0);
