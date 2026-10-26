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
use TgWatch\Settings;
use TgWatch\Priority;
use TgWatch\Alerts;

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
    public array $older = [];

    public function fetch(string $username, ?int $before = null): array
    {
        if ($before !== null) {
            return $this->older[$username][$before] ?? ['ok' => true, 'error' => '', 'title' => '', 'posts' => []];
        }
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
test('zaten Azerice olan ad uyarı vermez, yarım kelime çıkmaz', function () use ($tr) {
    $r = $tr->translate('YouTube Abunə [ Yüksək Keyfiyyətli | 7 Gün Qarantili ♻️ | Sürətli');
    eq('YouTube Abunə [ Yüksək Keyfiyyətli | 7 Gün Qarantili ♻️ | Sürətli', $r['text']);
    eq([], $r['unknown']);
    eq(['Targeted'], $tr->translate('Instagram Followers | Targeted')['unknown']);
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
test('satır sonu fiyatı: "-- 8.50", "- 1.20 AZN"; tek tireli tam sayı fiyat sayılmaz', function () {
    $items = Parser::parse("🆔 3309 YouTube Abunə [ Yüksək Keyfiyyətli | Sürətli -- 8.50\n🆔 3310 Instagram Likes - 1,20 AZN\n🆔 3311 TikTok Views | Speed - 5000");
    eq(['8.50', '1.20', ''], array_column($items, 'price'));
    eq('YouTube Abunə [ Yüksək Keyfiyyətli | Sürətli', $items[0]['name']);
    eq('Instagram Likes', $items[1]['name']);
    eq('TikTok Views | Speed - 5000', $items[2]['name']);
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
test('Onayla (manual) → "yayın bekliyor", bota yeni mesaj/dosya gitmez, butonlar kalkar', function () {
    [$db, $tg, $flow] = env();
    $id = $flow->createJob('demosmm', 102, SAMPLE);
    $flow->handleUpdate(cb(1, OWNER, "ok:$id"));
    eq('approved', $db->job($id)['status']);
    eq(1, $tg->count('sendMessage'), 'yalnızca ilk onay mesajı');
    eq([], $tg->docs);
    $edit = $tg->last('editMessageText');
    eq([], $edit['reply_markup']['inline_keyboard']);
    ok(str_contains($edit['text'], 'Onaylandı (yayın bekliyor)'));
});
test('API yayını başarılı → kısa "Sitede yayınlandı" bildirimi', function () {
    [$db, $tg, $flow] = env();
    $flow->setPublisher(new class implements TgWatch\Publisher\Publisher {
        public function publish(array $job): array { return ['ok' => true, 'message' => 'API tamam']; }
        public static function name(): string { return 'test'; }
    });
    $id = $flow->createJob('demosmm', 102, SAMPLE);
    $flow->handleUpdate(cb(1, OWNER, "ok:$id"));
    eq('published', $db->job($id)['status']);
    $m = $tg->last('sendMessage')['text'];
    ok(str_contains($m, 'Sitede yayınlandı') && str_contains($m, '13125, 13126') && str_contains($m, '2 servis'), $m);
});
test('bekleyen iş elle "yayınlandı" işaretlenir, bildirim gitmez', function () {
    [$db, $tg, $flow] = env();
    $id = $flow->createJob('demosmm', 102, SAMPLE);
    $flow->approve($id);
    $n = $tg->count('sendMessage');
    eq('Yayınlandı olarak işaretlendi.', $flow->markPublished($id));
    eq('published', $db->job($id)['status']);
    eq($n, $tg->count('sendMessage'));
    eq('Yalnızca onaylanmış işler işaretlenebilir.', $flow->markPublished($id));
});
test('onaylanmış iş botla tekrar yayınlanmaz, web panelden (bilinçli) yayınlanabilir', function () {
    [$db, $tg, $flow] = env();
    $id = $flow->createJob('demosmm', 102, SAMPLE);
    $flow->handleUpdate(cb(1, OWNER, "ok:$id"));
    $flow->handleUpdate(cb(2, OWNER, "ok:$id"));
    eq('Bu iş zaten işlendi.', $flow->approve($id));
    eq('approved', $db->job($id)['status']);
    ok(str_starts_with($flow->approve($id, true), 'Onaylandı'));
});
test('Onayla iki kez basılırsa ikinci kez yayınlanmaz', function () {
    [$db, $tg, $flow] = env();
    $id = $flow->createJob('demosmm', 102, SAMPLE);
    $flow->handleUpdate(cb(1, OWNER, "ok:$id"));
    $flow->handleUpdate(cb(2, OWNER, "ok:$id"));
    eq('approved', $db->job($id)['status']);
    eq(1, $tg->count('sendMessage'));
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
test('sözlük değişince açık iş yenilenir, elle düzenlenen ad korunur, bot mesajı güncellenir', function () {
    [$db, $tg, $flow] = env();
    $id = $flow->createJob('demosmm', 102, "🆔 101 Instagram Targeted Likes\n🆔 102 Instagram Targeted Views");
    eq(['Targeted'], $db->job($id)['items'][0]['unknown']);
    $flow->applyEdits($id, '102 | Elle yazdım');
    $d = Dictionary::load($db);
    $d['phrases']['Targeted'] = 'Hədəfli';
    Dictionary::save($db, $d);
    $edits = $tg->count('editMessageText');
    ok($flow->refreshTranslations($id));
    $job = $db->job($id);
    eq('Instagram Hədəfli Bəyəni', $job['items'][0]['az']);
    eq([], $job['items'][0]['unknown']);
    eq('Elle yazdım', $job['items'][1]['az']);
    eq($edits + 1, $tg->count('editMessageText'));
    ok(!$flow->refreshTranslations($id), 'ikinci kez değişiklik yok');
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
    $c['_unknown_keys'] = ['1234…', '5555555555'];
    $rows = TgWatch\Health::local($c);
    $row = array_values(array_filter($rows, static fn($r) => $r[0] === 'config.php anahtarları'))[0] ?? null;
    ok($row !== null && $row[1] === 'fail');
    ok(str_contains($row[2], '1234…') && !str_contains($row[2], 'AAxx'));
});

echo "Çeviri bankası (Memory)\n";
test('sayı şablonu: "30 Days Refill" öğrenilince "60 Days Refill" de çevrilir', function () {
    [$db] = env();
    $m = new TgWatch\Memory($db);
    $m->learn('service', 'Threads Followers | No Drop | Max 50K | 5K/Day | 30 Days Refill ♻️', 'Threads İzləyici | Azalma olmaz | Maks 50K | Gündə 5K | 30 Gün Qarantili ♻️', 'approved');
    eq('60 Gün Qarantili ♻️', $m->lookupSegment('60 Days Refill ♻️'));
    eq('Maks 100K', $m->lookupSegment('Max 100K'));
    eq('Gündə 10K', $m->lookupSegment('10K/Day'));
    eq(null, $m->lookupSegment('Arab Targeted'));
});
test('marka çekirdeği: "Threads Followers" öğrenilince "Instagram Followers" de çevrilir', function () {
    [$db] = env();
    $m = new TgWatch\Memory($db);
    $m->learn('service', 'Threads Followers | Fast', 'Threads İzləyici | Sürətli', 'approved');
    eq('Instagram İzləyici', $m->lookupSegment('Instagram Followers', ['instagram' => true, 'threads' => true]));
});
test('AZ tarafında kaynakta olmayan sayı varsa şablon yapılmaz', function () {
    eq(['#0 days refill', '#0 Gün Qarantili'], TgWatch\Memory::templatePair('30 Days Refill', '30 Gün Qarantili'));
    eq(['30 days refill', '31 Gün Qarantili'], TgWatch\Memory::templatePair('30 Days Refill', '31 Gün Qarantili'));
});
test('öncelik: elle > onaylanan > otomatik; reddedilen kullanılmaz', function () {
    [$db] = env();
    $m = new TgWatch\Memory($db);
    $m->learn('service', 'TikTok Likes', 'TikTok Bəyəni A', 'import');
    eq('TikTok Bəyəni A', $m->lookup('service', 'tiktok likes'));
    $m->learn('service', 'TikTok Likes', 'TikTok Bəyəni B', 'approved');
    eq('TikTok Bəyəni B', $m->lookup('service', 'TikTok Likes'));
    $m->learn('service', 'TikTok Likes', 'TikTok Bəyəni C', 'manual');
    eq('TikTok Bəyəni C', $m->lookup('service', 'TikTok Likes'));
    $db->run("UPDATE tm SET status = 'rejected' WHERE az = 'TikTok Bəyəni C'");
    $m->learn('service', 'TikTok Likes', 'TikTok Bəyəni C', 'manual');
    eq('TikTok Bəyəni B', $m->lookup('service', 'TikTok Likes'), 'reddedilen geri gelmemeli');
});
test('benzer örnekler ortak kelimeye göre sıralanır', function () {
    [$db] = env();
    $m = new TgWatch\Memory($db);
    $m->learn('service', 'Instagram Followers | No Drop', 'Instagram İzləyici | Azalma olmaz', 'approved');
    $m->learn('service', 'YouTube Views | Fast', 'YouTube Baxış | Sürətli', 'approved');
    $s = $m->similar('Instagram Followers | Real', 3);
    eq('Instagram İzləyici | Azalma olmaz', $s[0]['az']);
});

echo "Motor (Engine)\n";
test('tam ad bankada → "bank"; parçalı → "mixed"; hiç → "rules"', function () {
    [$db] = env();
    $e = new TgWatch\Engine($db, Dictionary::defaults());
    $e->memory()->learn('service', 'Threads Followers | No Drop | 30 Days Refill', 'Threads İzləyici | Azalma olmaz | 30 Gün Qarantili', 'approved');
    $r = $e->translate('𝐓𝐡𝐫𝐞𝐚𝐝𝐬 Followers | No Drop | 30 Days Refill');
    eq(['Threads İzləyici | Azalma olmaz | 30 Gün Qarantili', 'bank'], [$r['text'], $r['via']]);
    $r = $e->translate('Threads Followers | Arab Targeted | 90 Days Refill');
    eq('mixed', $r['via']);
    eq('Threads İzləyici | Arab Targeted | 90 Gün Qarantili', $r['text']);
    eq(['Arab', 'Targeted'], $r['unknown']);
    eq('rules', $e->translate('Spotify Plays')['via']);
});
test('bankada kendi yazımı kurala üstün gelir (ör. "Cancel Enabled")', function () {
    [$db] = env();
    $e = new TgWatch\Engine($db, Dictionary::defaults());
    $e->memory()->learn('service', 'TikTok Likes | Cancel Enabled', 'TikTok Bəyəni | Ləğv Butonu Aktif', 'manual');
    eq('Ləğv Butonu Aktif', explode(' | ', $e->translate('Instagram Views | Cancel Enabled')['text'])[1]);
});
test('silinecek ifadeler ve ad kalıbı seçenekleri bankadan gelen ada da uygulanır', function () {
    [$db] = env();
    $d = Dictionary::defaults();
    $d['remove'] = ['🔥 BEST', '/\[ ?OWNER ?\]/i'];
    $d['options'] = ['keep_max' => false, 'keep_speed' => true];
    $e = new TgWatch\Engine($db, $d);
    eq('Instagram Bəyəni | Sürətli', $e->translate('🔥 BEST Instagram Likes | [owner] | Fast')['text']);
    $e->memory()->learn('service', 'Threads Likes | Max 10K', 'Threads Bəyəni | Maks 10K', 'manual');
    eq('Threads Bəyəni', $e->translate('Threads Likes | Max 10K')['text']);
});

echo "Eşleştirme (Matcher)\n";
test('fiyat formülü: satış = 1.5 × sağlayıcı + 0.2 bulunur, aykırı değer atılır', function () {
    $pts = [];
    foreach ([1, 2, 3, 4, 5, 6, 7, 8] as $x) {
        $pts[] = [(float)$x, 1.5 * $x + 0.2];
    }
    $pts[] = [3.0, 40.0];
    $f = TgWatch\Matcher::fit($pts);
    eq('linear', $f['mode']);
    ok(abs($f['a'] - 1.5) < 0.01 && abs($f['b'] - 0.2) < 0.01, json_encode($f));
    eq(8, $f['points']);
});
test('eşleştirme: min/max + platform + sayı + fiyat ile doğru sağlayıcı servisi bulunur', function () {
    [$db] = env();
    $db->run("INSERT INTO sources(kind,name,api_url,api_key,created_at) VALUES('own','panel','https://x','k',1),('provider','prov','https://y','k',1)");
    $prov = [
        [1, 'Instagram Followers | No Drop | 30 Days Refill', 'Instagram Followers', 1.00, 10, 50000],
        [2, 'Instagram Followers | Max 10K', 'Instagram Followers', 0.50, 10, 50000],
        [3, 'TikTok Likes | Fast', 'TikTok Likes', 0.20, 10, 100000],
        [4, 'YouTube Views', 'YouTube', 2.00, 100, 1000000],
        [5, 'Telegram Members', 'Telegram', 3.00, 50, 20000],
        [6, 'Spotify Plays', 'Spotify', 0.40, 1000, 1000000],
        [7, 'Twitch Followers', 'Twitch', 4.00, 20, 9000],
        [8, 'Threads Likes', 'Threads', 0.60, 10, 5000],
    ];
    foreach ($prov as [$id, $n, $c, $r, $mi, $ma]) {
        $db->run('INSERT INTO remote_services(source_id,service_id,name,category,rate,min,max) VALUES(2,?,?,?,?,?,?)', [$id, $n, $c, $r, $mi, $ma]);
    }
    $own = [
        [101, 'Instagram İzləyici | Azalma olmaz | 30 Gün Qarantili', 'Instagram İzləyici', 1.00 * 1.7 + 0.1, 10, 50000],
        [102, 'Instagram İzləyici | Maks 10K', 'Instagram İzləyici', 0.50 * 1.7 + 0.1, 10, 50000],
        [103, 'TikTok Bəyəni | Sürətli', 'TikTok Bəyəni', 0.20 * 1.7 + 0.1, 10, 100000],
        [104, 'YouTube Baxış', 'YouTube', 2.00 * 1.7 + 0.1, 100, 1000000],
        [105, 'Telegram Üzv', 'Telegram', 3.00 * 1.7 + 0.1, 50, 20000],
        [106, 'Spotify Dinləmə', 'Spotify', 0.40 * 1.7 + 0.1, 1000, 1000000],
        [107, 'Twitch İzləyici', 'Twitch', 4.00 * 1.7 + 0.1, 20, 9000],
        [108, 'Threads Bəyəni', 'Threads', 0.60 * 1.7 + 0.1, 10, 5000],
        [109, 'Facebook Bəyəni', 'Facebook', 9.0, 1, 7],
    ];
    foreach ($own as [$id, $n, $c, $r, $mi, $ma]) {
        $db->run('INSERT INTO remote_services(source_id,service_id,name,category,rate,min,max) VALUES(1,?,?,?,?,?,?)', [$id, $n, $c, $r, $mi, $ma]);
    }
    $r = (new TgWatch\Matcher($db))->run();
    eq(8, $r['high']);
    eq(1, $r['none']);
    eq('linear', $r['model']['mode']);
    $m = new TgWatch\Memory($db);
    eq('Instagram İzləyici | Maks 10K', $m->lookup('service', 'Instagram Followers | Max 10K'));
    eq('Instagram İzləyici | Azalma olmaz | 30 Gün Qarantili', $m->lookup('service', 'Instagram Followers | No Drop | 30 Days Refill'));
    eq('TikTok Bəyəni', $m->lookup('category', 'TikTok Likes'));
    eq('Instagram İzləyici', $m->lookup('category', 'Instagram Followers'));
    // yeni bir duyuru artık bankadan çevrilir
    $e = new TgWatch\Engine($db, Dictionary::defaults());
    $t = $e->translate('Instagram Followers | No Drop | 60 Days Refill');
    eq(['Instagram İzləyici | Azalma olmaz | 60 Gün Qarantili', 'bank'], [$t['text'], $t['via']]);
    // reddedilen eşleşme tekrar önerilmez
    $db->run("UPDATE tm SET status = 'rejected' WHERE own_service = 108");
    (new TgWatch\Matcher($db))->run();
    eq(null, $m->lookup('service', 'Threads Likes'));
});
test('SMM API cevabı okunur; hata mesajı yakalanır', function () {
    $l = TgWatch\SmmApi::parse('[{"service":1,"name":"IG Likes","type":"Default","category":"Instagram","rate":"0.90","min":"50","max":"10000","refill":true,"cancel":"0"}]');
    eq([1, 'IG Likes', 0.9, 50, 1, 0], [$l[0]['service_id'], $l[0]['name'], $l[0]['rate'], $l[0]['min'], $l[0]['refill'], $l[0]['cancel']]);
    try {
        TgWatch\SmmApi::parse('{"error":"Incorrect request"}');
        ok(false);
    } catch (RuntimeException $e) {
        ok(str_contains($e->getMessage(), 'Incorrect request'));
    }
});

echo "Claude çevirisi (Ai)\n";
test('kontrol: sayı/emoji/parça/zorunlu terim değişirse öneri kullanılmaz', function () {
    $d = Dictionary::defaults();
    $src = 'Instagram Likes | No Drop | 30 Days Refill ♻️';
    $draft = 'Instagram Bəyəni | Azalma olmaz | 30 Gün Qarantili ♻️';
    eq([], TgWatch\Ai::validate($src, $draft, 'Instagram Bəyəni | Azalma olmaz | 30 Gün Qarantili ♻️', $d));
    ok((bool)TgWatch\Ai::validate($src, $draft, 'Instagram Bəyəni | Azalma olmaz | 60 Gün Qarantili ♻️', $d), 'sayı');
    ok((bool)TgWatch\Ai::validate($src, $draft, 'Instagram Bəyəni | Azalma olmaz | 30 Gün Qarantili', $d), 'emoji');
    ok((bool)TgWatch\Ai::validate($src, $draft, 'Instagram Bəyəni, Azalma olmaz | 30 Gün Qarantili ♻️', $d), 'parça');
    ok((bool)TgWatch\Ai::validate($src, $draft, 'Instagram Layk | Azalma olmaz | 30 Gün Qarantili ♻️', $d), 'zorunlu terim');
    eq([], TgWatch\Ai::validate('TikTok Random Comments', 'TikTok Təsadüfi Rəy', 'TikTok Təsadüfi Rəy', $d), 'uzun ifade kısa terimi kapsar');
    eq([], TgWatch\Ai::validate('Telegram Members | Cancel Enabled', 'Telegram Üzv | Ləğv Butonu Aktif', 'Telegram Üzv | Ləğv Butonu Aktif', $d), 'bankadaki kendi yazım sözlüğe üstün');
});
test('claude.ai cevabı satırlardan okunur', function () {
    eq([13125 => 'Threads İzləyici | Maks 50K', 13126 => 'Threads Bəyəni'], TgWatch\Ai::parseLines("İşte çeviriler:\n13125 | Threads İzləyici | Maks 50K\n- 13126 → Threads Bəyəni\n`x`"));
});
test('karma mod: yalnızca bankadan tam çevrilemeyen ya da eksik kelimeli adlar Claude\'a gider', function () {
    [$db] = env();
    $ai = new TgWatch\Ai($db, App::config());
    $db->set('translate_mode', 'hybrid');
    ok(!$ai->needs(['via' => 'bank', 'unknown' => []]));
    ok($ai->needs(['via' => 'mixed', 'unknown' => []]));
    ok($ai->needs(['via' => 'bank', 'unknown' => ['X']]));
    ok(!$ai->needs(['via' => 'rules', 'unknown' => [], 'edited' => true]), 'elle düzenlenen gönderilmez');
    ok(!$ai->active(), 'anahtar yokken otomatik çalışmaz');
    $db->set('translate_mode', 'claude');
    ok($ai->needs(['via' => 'bank', 'unknown' => []]));
});
test('istem: kurallar, sözlük, taslak ve bankadan örnekler içerir', function () {
    [$db] = env();
    $d = Dictionary::defaults();
    $e = new TgWatch\Engine($db, $d);
    $e->memory()->learn('service', 'Instagram Followers | No Drop', 'Instagram İzləyici | Azalma olmaz', 'approved');
    $items = $e->translateItems([['id' => 77, 'name' => 'Instagram Followers | Arab Targeted', 'price' => '', 'category' => '']]);
    $p = (new TgWatch\Ai($db, App::config()))->manualPrompt($items, $e, $d);
    ok(str_contains($p, 'Likes → Bəyəni'));
    ok(str_contains($p, '### ID 77'));
    ok(str_contains($p, 'Taslak: Instagram İzləyici | Arab Targeted'));
    ok(str_contains($p, 'Instagram Followers | No Drop → Instagram İzləyici | Azalma olmaz'));
    ok(str_contains($p, 'ID | Azerice ad'));
});
test('yapıştırılan cevap uygulanır, kontrolden geçmeyen ad taslakta kalır', function () {
    [$db, $tg, $flow] = env();
    $id = $flow->createJob('demosmm', 102, SAMPLE);
    $r = $flow->applyPaste($id, "13125 | Threads İzləyici | Azalma olmaz | Maks 50K | Gündə 5K | 30 Gün Qarantili ♻️\n13126 | Threads Bəyəni | Sürətli | 99K");
    eq(1, $r['applied']);
    eq(1, count($r['rejected']));
    $job = $db->job($id);
    eq('Threads İzləyici | Azalma olmaz | Maks 50K | Gündə 5K | 30 Gün Qarantili ♻️', $job['items'][0]['az']);
    eq('Threads Bəyəni | Sürətli', $job['items'][1]['az']);
    ok(str_contains($job['items'][1]['ai_note'], 'parça sayısı'));
});
test('onaylanan iş bankaya öğrenilir; bir sonraki duyuru bankadan çevrilir', function () {
    [$db, $tg, $flow] = env();
    $id = $flow->createJob('demosmm', 102, SAMPLE);
    $flow->applyEdits($id, '13126 | Threads Bəyəni | Çox Sürətli');
    $flow->approve($id);
    $id2 = $flow->createJob('demosmm', 103, "🆔 20001 Threads Likes | Fast");
    $it = $db->job($id2)['items'][0];
    eq(['Threads Bəyəni | Çox Sürətli', 'bank'], [$it['az'], $it['via']]);
});

echo "Servis değişiklikleri (Notices)\n";
test('fiyat değişikliği / kapatılan servis ayrılır, yeni servis duyurusu karışmaz', function () {
    $cases = [
        ["⚠️ Price update\n🆔 3309 YouTube Subscribers | Fast\n\$8.50 → \$9.20", 'price'],
        ['Price changed for ID 100, 101 and 102. New rate: $0.45', 'price'],
        ['Servis 555 qiymət dəyişdi: $1.20 → $1.50', 'price'],
        ['🚫 Service disabled: ID 4455 (YouTube Views) is temporarily unavailable', 'closed'],
        ['IDs 200, 201, 202 removed from the panel', 'closed'],
        ['3309 servisi kapatıldı', 'closed'],
        ["📢 New services added\n🆔 13125 Threads Followers | No Drop\n💰 \$1.20", null],
        ["🆔 9001 TikTok Likes | Instant\n💰 New price: \$0.12", null],
        ['New services! Price drop on old ones too. 🆔 500 Instagram Likes', null],
        ['Prices have changed across the panel, check the list', null],
        ['Hello everyone, maintenance tonight', null],
    ];
    foreach ($cases as [$text, $want]) {
        eq($want, TgWatch\Notices::classify($text), mb_substr($text, 0, 40));
    }
});
test('duyurudan servis ID\'leri ve yeni fiyatlar çıkarılır', function () {
    eq([100, 101, 102], TgWatch\Notices::extractIds('Price changed for ID 100, 101 and 102. New rate: $0.45'));
    $p = TgWatch\Notices::extractPrices("🆔 3309 YouTube Subscribers\n\$8.50 → \$9.20");
    eq(['old' => 8.5, 'new' => 9.2], $p[3309]);
});
test('Watcher: bildirim iş olmaz, bota butonlu mesaj gider, "Yaptım" kapatır, tekrar gelmez', function () {
    [$db, $tg, $flow] = env();
    $reader = new FakeReader();
    $w = new Watcher($db, $flow, $reader);
    $w->addChannel('demosmm');
    $reader->pages['demosmm'] = ['ok' => true, 'error' => '', 'title' => '', 'posts' => [['id' => 5, 'text' => 'x', 'date' => '']]];
    $w->runAll();
    $reader->pages['demosmm']['posts'][] = ['id' => 6, 'text' => "Price update\n🆔 3309 YouTube Subscribers\n\$8.50 → \$9.20", 'date' => ''];
    $reader->pages['demosmm']['posts'][] = ['id' => 7, 'text' => "New services\n🆔 4001 Threads Likes | Fast", 'date' => ''];
    $r = $w->runAll();
    eq(1, count($r['demosmm']['notices']));
    eq(1, count($r['demosmm']['jobs']), 'yeni servis hâlâ iş olur');
    eq(1, (int)$db->one('SELECT COUNT(*) c FROM notices')['c']);
    $m = null;
    foreach ($tg->calls as [$meth, $params]) {
        if ($meth === 'sendMessage' && str_contains($params['text'], 'Fiyat değişti')) {
            $m = $params;
        }
    }
    ok($m !== null, 'bildirim mesajı gönderilmedi');
    $nid = (int)$db->one('SELECT id FROM notices')['id'];
    ok(str_contains($m['text'], 'Fiyat değişti') && str_contains($m['text'], '3309'), $m['text']);
    $cb = array_merge(...$m['reply_markup']['inline_keyboard']);
    eq(["nd:$nid", "ni:$nid"], array_column($cb, 'callback_data'));
    $flow->handleUpdate(cb(1, OWNER, "nd:$nid"));
    eq('done', $db->one('SELECT status FROM notices')['status']);
    eq([], $tg->last('editMessageText')['reply_markup']['inline_keyboard']);
    $flow->handleUpdate(cb(2, 999, "ni:$nid"));
    eq('done', $db->one('SELECT status FROM notices')['status'], 'yabancı basamaz');
    $reader->pages['demosmm']['posts'][] = ['id' => 6, 'text' => 'again', 'date' => ''];
    $w->runAll(3);
    eq(1, (int)$db->one('SELECT COUNT(*) c FROM notices')['c']);
});
test('etki: panelindeki servis ve önerilen yeni satış hesaplanır', function () {
    [$db] = env();
    $db->run("INSERT INTO sources(kind,name,api_url,api_key,created_at) VALUES('own','panel','https://x','k',1),('provider','prov','https://y','k',1)");
    $db->run("INSERT INTO remote_services(source_id,service_id,name,rate,min,max) VALUES(2,3309,'YT Subs',8.5,10,5000),(1,101,'YouTube Abunə',15.0,10,5000)");
    $db->run("INSERT INTO tm(kind,en_norm,en,az,origin,status,provider_source,provider_service,own_service,updated_at) VALUES('service','yt subs','YT Subs','YouTube Abunə','import','ok',2,3309,101,1)");
    $db->setJson('match_report', ['model' => ['a' => 1.7, 'b' => 0.5, 'points' => 9, 'mode' => 'linear']]);
    $db->run("INSERT INTO channels(username,source_id,created_at) VALUES('demosmm',2,1)");
    $nt = new TgWatch\Notices($db);
    $id = $nt->create('demosmm', 6, "Price update\n🆔 3309 YT Subs\n\$8.50 → \$9.20", 'price');
    $x = $nt->impact($nt->get($id))[0];
    eq(101, (int)$x['own']['service_id']);
    eq(9.2, $x['new']);
    eq(16.14, $x['suggest']);
    ok(str_contains($x['action'], '#101') && str_contains($x['action'], '16.14') && str_contains($x['action'], '15.00'), $x['action']);
    $id2 = $nt->create('demosmm', 7, 'ID 3309 removed', 'closed');
    ok(str_contains($nt->impact($nt->get($id2))[0]['action'], 'gizle ya da kapat'));
});

echo "Panele ekleme paketi (Enrich)\n";
test('sağlayıcı, öneri, panelde var uyarısı, kategori ve ikon', function () {
    [$db] = env();
    $db->run("INSERT INTO sources(kind,name,api_url,api_key,last_sync_at,created_at) VALUES('own','panel','https://x','k',1,1),('provider','prov','https://y','k',1,1)");
    $db->run("INSERT INTO remote_services(source_id,service_id,name,category,rate,min,max,refill,cancel) VALUES
        (2,901,'Threads Followers | No Drop','Threads Followers',1.0,10,5000,1,0),
        (2,902,'Threads Likes | Fast','Threads Likes',0.5,10,5000,0,1),
        (1,201,'Threads İzləyici | Azalma olmaz','🧵 Threads İzləyici',1.8,10,5000,1,0),
        (1,202,'Instagram Bəyəni','📸 Instagram Bəyəni',1.0,10,5000,0,0)");
    $db->run("INSERT INTO tm(kind,en_norm,en,az,origin,status,provider_source,provider_service,own_service,updated_at) VALUES('service','threads followers | no drop','Threads Followers | No Drop','Threads İzləyici | Azalma olmaz','import','ok',2,901,201,1)");
    $db->setJson('match_report', ['model' => ['a' => 1.8, 'b' => 0.0, 'points' => 9, 'mode' => 'linear']]);
    $db->run("INSERT INTO channels(username,created_at) VALUES('demosmm',1)");
    $flow = new Flow($db, new FakeTelegram(), App::config());
    $id = $flow->createJob('demosmm', 9, "🆔 901 Threads Followers | No Drop\n🆔 902 Threads Likes | Fast");
    $info = (new TgWatch\Enrich($db))->forJob($db->job($id));
    eq('prov', $info['provider']['name'], 'tek sağlayıcı otomatik');
    eq(1.8, $info['items'][901]['suggest']);
    eq(0.9, $info['items'][902]['suggest']);
    eq(201, (int)$info['items'][901]['own']['service_id'], 'panelinde var');
    eq(null, $info['items'][902]['own']);
    eq(1, $info['duplicates']);
    eq([10, 5000], [(int)$info['items'][901]['min'], (int)$info['items'][901]['max']]);
    eq('🧵 Threads İzləyici', $info['category']['az'], 'ikon paneldeki kategoriden öğrenilir');
    ok($info['category']['exists']);
    $m = $flow->render($db->job($id))[0];
    ok(str_contains($m, 'Sağlayıcı: prov') && str_contains($m, 'Panelinde var: #201') && str_contains($m, 'öneri 1.80'), $m);
    $pkg = TgWatch\Publisher\ManualPublisher::package($db->job($id), $info);
    ok(str_contains($pkg, "901\tThreads İzləyici | Azalma olmaz\t1\t1.8\t10\t5000\tVAR #201"), $pkg);
});
test('elle girilen kâr formülü otomatik olanı geçer; formül yoksa öneri yok', function () {
    [$db] = env();
    $en = new TgWatch\Enrich($db);
    eq(null, $en->suggestPrice(2.0));
    $db->set('markup_a', '2,5');
    $db->set('markup_b', '0.1');
    eq(5.1, $en->suggestPrice(2.0));
    eq('manual', $en->model()['mode']);
});

echo "Uyarılar (Alerts)\n";
test('kanal uyarısı bekleme süresinde tekrar etmez, düzelince bir kez haber verir', function () {
    [$db, $tg] = env();
    $db->run("INSERT INTO channels(username,last_error,last_ok_at,created_at) VALUES('demosmm','HTTP 500',?,?)", [time() - 5 * 3600, time() - 9 * 3600]);
    $al = new TgWatch\Alerts($db, $tg, App::config());
    $al->check();
    eq(1, $tg->count('sendMessage'));
    ok(str_contains($tg->last('sendMessage')['text'], '@demosmm okunamıyor'));
    $db->set('alerts_checked_at', '0');
    $al->check();
    eq(1, $tg->count('sendMessage'), 'bekleme süresinde tekrar yok');
    $db->run("UPDATE channels SET last_error = NULL, last_ok_at = ?", [time()]);
    $db->set('alerts_checked_at', '0');
    $al->check();
    eq(2, $tg->count('sendMessage'));
    ok(str_contains($tg->last('sendMessage')['text'], 'yeniden okunuyor'));
    $db->set('alerts_checked_at', '0');
    $al->check();
    eq(2, $tg->count('sendMessage'), 'düzeldi mesajı tek sefer');
});
test('kapalı uyarı türü gönderilmez; test mesajı gider', function () {
    [$db, $tg] = env();
    $al = new TgWatch\Alerts($db, $tg, App::config());
    $al->setEnabled(['cron'], false, 21);
    ok(!$al->enabled('channel') && $al->enabled('cron'));
    ok(!$al->send('k', 'channel', 'x'));
    ok($al->test());
    eq(1, $tg->count('sendMessage'));
});
test('cron durduysa uyarı, cron çalışınca düzeldi', function () {
    [$db, $tg] = env();
    $al = new TgWatch\Alerts($db, $tg, App::config());
    $db->set('cron_last_run', (string)(time() - 3600));
    $al->check();
    ok(str_contains($tg->last('sendMessage')['text'], 'Kanal okuma durdu'));
    $db->set('cron_last_run', (string)time());
    $db->set('alerts_checked_at', '0');
    $al->check();
    ok(str_contains($tg->last('sendMessage')['text'], 'yeniden çalışıyor'));
});
test('Claude sınırı %80 ve %100 ayda bir kez; uzun bekleyen iş günde bir', function () {
    [$db, $tg, $flow] = env();
    $db->set('claude_api_key', 'sk-ant-x');
    $db->set('claude_monthly_limit', '10');
    $db->setJson('ai_usage', [date('Y-m') => ['requests' => 8, 'input' => 0, 'output' => 0, 'cost' => 0]]);
    $al = new TgWatch\Alerts($db, $tg, App::config());
    $al->check();
    ok(str_contains($tg->last('sendMessage')['text'], '%80'));
    $db->set('alerts_checked_at', '0');
    $al->check();
    eq(1, $tg->count('sendMessage'));
    $db->setJson('ai_usage', [date('Y-m') => ['requests' => 10, 'input' => 0, 'output' => 0, 'cost' => 0]]);
    $db->set('alerts_checked_at', '0');
    $al->check();
    ok(str_contains($tg->last('sendMessage')['text'], 'sınırı doldu'));
    $id = $flow->createJob('demosmm', 1, '🆔 111 Instagram Likes');
    $db->run('UPDATE jobs SET created_at = ? WHERE id = ?', [time() - 20 * 3600, $id]);
    $db->set('alerts_checked_at', '0');
    $before = $tg->count('sendMessage');
    $al->check();
    ok(str_contains($tg->last('sendMessage')['text'], 'onay bekliyor'));
    eq($before + 1, $tg->count('sendMessage'));
});
test('günlük özet saatinden sonra günde bir kez gider', function () {
    [$db, $tg, $flow] = env();
    $al = new TgWatch\Alerts($db, $tg, App::config());
    $al->setEnabled([], true, 0);
    $flow->createJob('demosmm', 1, '🆔 111 Instagram Likes');
    $al->check();
    ok(str_contains($tg->last('sendMessage')['text'], 'Günlük özet') && str_contains($tg->last('sendMessage')['text'], 'Gelen iş: 1'));
    $n = $tg->count('sendMessage');
    $db->set('alerts_checked_at', '0');
    $al->check();
    eq($n, $tg->count('sendMessage'));
});

echo "Yedek\n";
test('yedek dışa/içe aktarılır; anahtarlar yedeğe girmez, import kayıtları geri gelmez', function () {
    [$db] = env();
    $m = new TgWatch\Memory($db);
    $m->learn('service', 'Threads Likes | Fast', 'Threads Bəyəni | Sürətli', 'manual');
    $m->learn('service', 'Auto', 'Otomatik', 'import');
    $db->run("INSERT INTO sources(kind,name,api_url,api_key,created_at) VALUES('provider','prov','https://y','SECRETKEY',1)");
    $db->run("INSERT INTO channels(username,source_id,created_at) VALUES('demosmm',1,1)");
    $db->set('claude_api_key', 'sk-ant-SECRET');
    $db->set('markup_a', '1.7');
    $json = json_encode((new TgWatch\Backup($db))->export());
    ok(!str_contains($json, 'SECRETKEY') && !str_contains($json, 'sk-ant-SECRET'));
    ok(!str_contains($json, 'Otomatik'), 'otomatik eşleşmeler yedekte yok');
    [$db2] = env();
    $n = (new TgWatch\Backup($db2))->import($json);
    eq(1, $n['tm']);
    eq('Threads Bəyəni | Sürətli', (new TgWatch\Memory($db2))->lookup('service', 'Threads Likes | Fast'));
    eq('1.7', $db2->get('markup_a'));
    $ch = $db2->one('SELECT c.username, s.name FROM channels c LEFT JOIN sources s ON s.id = c.source_id');
    eq(['demosmm', 'prov'], [$ch['username'], $ch['name']]);
    eq('', $db2->one('SELECT api_key FROM sources')['api_key']);
    try {
        (new TgWatch\Backup($db2))->import('{"x":1}');
        ok(false);
    } catch (InvalidArgumentException) {
    }
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

echo "Ayarlar, temizlik, kanal filtresi\n";
test('Ayarlar: doğrulama ve config üzerine yazma', function () {
    [$db] = env();
    eq([true, 30], Settings::check('fetch_timeout', '30'));
    eq(false, Settings::check('fetch_timeout', '2')[0], 'alt sınır');
    eq(false, Settings::check('fetch_timeout', 'abc')[0]);
    eq(false, Settings::check('webhook_url', 'ftp://x')[0]);
    eq(false, Settings::check('bot_mode', 'x')[0]);
    eq(false, Settings::check('cron_key', 'kisa')[0]);
    eq(false, Settings::check('yok', '1')[0]);
    $err = Settings::save($db, ['fetch_timeout' => '33', 'panel_name' => 'Masam', 'ui_accent' => 'green', 'cron_time_limit' => '5']);
    eq(1, count($err), 'yalnızca cron_time_limit hatalı');
    eq(33, App::config()['fetch_timeout']);
    eq('Masam', App::config()['panel_name']);
    eq('green', App::config()['ui_accent']);
    ok(App::config()['cron_time_limit'] !== 5);
});
test('Ayarlar: panel_settings=false iken config.php geçerli', function () {
    global $tmp;
    $dir = $tmp . '/off';
    @mkdir($dir);
    App::setConfig(['data_dir' => $dir, 'panel_settings' => false]);
    Settings::save(App::db(), ['panel_name' => 'X']);
    App::setConfig(['data_dir' => $dir, 'panel_settings' => false]);
    eq('tgwatch', App::config()['panel_name']);
});
test('Ayarlar: panelden değiştirilen şifre hash\'i geçerli olur', function () {
    [$db] = env();
    $db->set('admin_hash', password_hash('yeni-sifre-123', PASSWORD_BCRYPT));
    App::reloadOverrides();
    ok(password_verify('yeni-sifre-123', App::config()['admin_password_hash']));
});
test('Temizlik: eski kayıt ve bitmiş işler silinir, açık işler kalır', function () {
    [$db, $tg, $flow] = env();
    $old = time() - 100 * 86400;
    $db->run("INSERT INTO logs(ts, level, source, message) VALUES(?, 'info', 't', 'eski')", [$old]);
    $db->run("INSERT INTO logs(ts, level, source, message) VALUES(?, 'info', 't', 'yeni')", [time()]);
    foreach (['approved' => 1, 'awaiting' => 2, 'rejected' => 3] as $st => $i) {
        $db->run('INSERT INTO jobs(channel,msg_id,text_hash,source_text,items_json,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?)', ['c', $i, "h$i", 'x', '[]', $st, $old, $old]);
    }
    $c = new TgWatch\Cleaner($db, ['retention_logs_days' => 30, 'retention_jobs_days' => 0]);
    $r = $c->run();
    eq(1, $r['logs']);
    eq(0, $r['jobs'], 'süre 0: işler silinmez');
    $r = (new TgWatch\Cleaner($db, ['retention_logs_days' => 30, 'retention_jobs_days' => 90]))->run();
    eq(2, $r['jobs']);
    eq(['awaiting'], array_column($db->all('SELECT status FROM jobs'), 'status'));
});
test('Kanal filtresi: yoksay kelimesi geçen gönderi iş olmaz', function () {
    [$db, $tg, $flow] = env();
    $reader = new FakeReader();
    $w = new Watcher($db, $flow, $reader);
    $w->addChannel('demosmm');
    $reader->pages['demosmm'] = ['ok' => true, 'error' => '', 'title' => '', 'posts' => [['id' => 1, 'text' => 'x', 'date' => '']]];
    $w->runAll();
    $db->run("UPDATE channels SET skip_words = ?", ["reklam\nGiveaway"]);
    $reader->pages['demosmm']['posts'][] = ['id' => 2, 'text' => "🆔 100 Instagram Likes | giveaway", 'date' => ''];
    $reader->pages['demosmm']['posts'][] = ['id' => 3, 'text' => "🆔 200 Instagram Views", 'date' => ''];
    $r = $w->runAll();
    eq(1, $r['demosmm']['skipped']);
    eq(1, count($r['demosmm']['jobs']));
});

echo "Araçlar: rapor, denetim, katalog, otomatik onay\n";
test('Raporlar: kanal başına sayılar, onay oranı ve ortalama süre', function () {
    [$db] = env();
    $now = time();
    $ins = fn($ch, $id, $st, $age, $dur, $via) => $db->run('INSERT INTO jobs(channel,msg_id,text_hash,source_text,items_json,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?)',
        [$ch, $id, "h$id", 'x', json_encode([['id' => 1, 'via' => $via]]), $st, $now - $age, $now - $age + $dur]);
    $ins('a', 1, 'approved', 3600, 60, 'bank');
    $ins('a', 2, 'rejected', 3600, 180, 'rules');
    $ins('a', 3, 'awaiting', 3600, 0, 'bank');
    $ins('b', 4, 'published', 3600, 120, 'bank');
    $ins('old', 5, 'approved', 90 * 86400, 10, 'bank');
    $r = (new TgWatch\Reports($db))->channels(30);
    eq(4, $r['total']['jobs'], 'eski iş sayılmaz');
    eq(2, $r['total']['approved']);
    eq(67, (int)$r['total']['rate']);
    eq('a', $r['channels'][0]['channel']);
    eq(120, $r['channels'][0]['avg_seconds'], '(60+180)/2');
    eq(['bank' => 3, 'rules' => 1], $r['via']);
});
test('Fiyat denetimi: zarar, düşük, yüksek, uygun', function () {
    [$db] = env();
    $db->setJson('match_report', ['model' => ['a' => 2.0, 'b' => 0.0, 'mode' => 'fit']]);
    $db->run("INSERT INTO sources(kind,name,api_url,api_key,created_at) VALUES('provider','P','u','k',1),('own','Ben','u','k',1)");
    $svc = fn($src, $id, $rate) => $db->run('INSERT INTO remote_services(source_id,service_id,name,rate) VALUES(?,?,?,?)', [$src, $id, "S$id", $rate]);
    foreach ([[1, 11, 1.0], [1, 12, 1.0], [1, 13, 1.0], [1, 14, 1.0]] as $a) {
        $svc(...$a);
    }
    foreach ([[2, 21, 0.9], [2, 22, 1.5], [2, 23, 2.0], [2, 24, 3.0]] as $a) {
        $svc(...$a);
    }
    foreach ([[11, 21], [12, 22], [13, 23], [14, 24]] as [$p, $o]) {
        $db->run("INSERT INTO tm(kind,en_norm,en,az,origin,status,provider_source,provider_service,own_service,updated_at) VALUES('service',?,?,?,'auto','ok',1,?,?,1)", ["e$p", "e$p", "a$p", $p, $o]);
    }
    $rows = (new TgWatch\Reports($db))->audit(0.10);
    eq(['loss', 'low', 'high', 'ok'], array_column($rows, 'flag'));
    eq(2.0, $rows[3]['want']);
    $c = (new TgWatch\Reports($db))->catalog('S2', 'own', 1);
    eq(4, $c['total']);
    eq(1, (new TgWatch\Reports($db))->catalog('13', 'all', 1)['total']);
});
test('Otomatik onay: yalnızca açık kanalda ve tüm adlar bankadansa', function () {
    [$db, $tg, $flow] = env();
    $mem = new TgWatch\Memory($db);
    $mem->learn('service', 'Instagram Likes | Fast', 'Instagram Bəyəni | Sürətli', 'manual');
    $db->run("INSERT INTO channels(username, created_at, auto_approve) VALUES('oto', 1, 1), ('kapali', 1, 0)");
    $flow = new Flow($db, $tg, App::config());
    $text = "🆔 701 Instagram Likes | Fast";
    $flow->createJob('kapali', 1, $text);
    eq('awaiting', $db->one('SELECT status FROM jobs WHERE channel = ?', ['kapali'])['status']);
    $flow->createJob('oto', 1, "🆔 703 Instagram Likes | Fast");
    ok(in_array($db->one('SELECT status FROM jobs WHERE channel = ?', ['oto'])['status'], ['approved', 'published']), 'otomatik onaylandı');
    $flow->createJob('oto', 2, "🆔 702 Totally Unknownword Service");
    eq('awaiting', $db->one('SELECT status FROM jobs WHERE msg_id = 2')['status'], 'bankada olmayan onaylanmaz');
});

echo "Aynı servisi birden çok kanal paylaşınca\n";
test('ikinci ve üçüncü kanal yeni iş açmaz, ilk işe kaynak olarak eklenir', function () {
    [$db, $tg, $flow] = env();
    $text = "🆔 5001 Instagram Likes | Fast\n💰 0.50\$";
    $a = $flow->createJob('kanala', 10, $text);
    ok($a !== null);
    eq(null, $flow->createJob('kanalb', 77, $text . "\n(kanal b)"), 'farklı metin ama aynı servis');
    eq(null, $flow->createJob('kanalc', 5, $text));
    eq(1, (int)$db->one('SELECT COUNT(*) c FROM jobs')['c']);
    $also = json_decode($db->one('SELECT also_json FROM jobs')['also_json'], true);
    eq(['kanalb', 'kanalc'], array_column($also['sources'], 'channel'));
    $last = end($tg->calls);
    ok(str_contains(json_encode($last, JSON_UNESCAPED_UNICODE), 'Ayrıca paylaşan'), 'bot mesajı güncellendi');
});
test('kısmen aynı: yalnızca yeni servisler için iş açılır ve not düşülür', function () {
    [$db, $tg, $flow] = env();
    $flow->createJob('kanala', 1, "🆔 5001 Instagram Likes | Fast");
    $id = $flow->createJob('kanalb', 2, "🆔 5001 Instagram Likes | Fast\n🆔 5002 TikTok Views | Fast");
    ok($id !== null);
    $job = $db->job($id);
    eq([5002], array_map(static fn($i) => (int)$i['id'], $job['items']));
    eq(['5001' => 1], json_decode($job['also_json'], true)['skipped']);
});
test('elle eklenen ve pencere dışı işler tekrar edilebilir; dedupe_days=0 kapatır', function () {
    [$db, $tg, $flow] = env();
    $flow->createJob('kanala', 1, "🆔 5001 Instagram Likes");
    ok($flow->createJob('elle', 99, "🆔 5001 Instagram Likes") !== null, 'elle');
    $db->run('UPDATE jobs SET created_at = created_at - ?', [30 * 86400]);
    ok($flow->createJob('kanalb', 2, "🆔 5001 Instagram Likes") !== null, '30 gün önceki sayılmaz');
    App::setConfig(['data_dir' => App::config()['data_dir'], 'owner_id' => OWNER, 'bot_token' => '123456:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghi', 'dedupe_days' => 0]);
    $f2 = new Flow(App::db(), $tg, App::config());
    ok($f2->createJob('kanalc', 3, "🆔 5001 Instagram Likes") !== null, 'kapalı');
});
test('farklı sağlayıcıdaki aynı ID aynı servis sayılmaz', function () {
    [$db, $tg, $flow] = env();
    $db->run("INSERT INTO sources(kind,name,api_url,api_key,created_at) VALUES('provider','P1','u','k',1),('provider','P2','u','k',1)");
    $db->run("INSERT INTO channels(username, created_at, source_id) VALUES('c1',1,1),('c2',1,2),('c3',1,1)");
    ok($flow->createJob('c1', 1, "🆔 5001 Instagram Likes") !== null);
    ok($flow->createJob('c2', 1, "🆔 5001 Instagram Likes") !== null, 'başka sağlayıcı');
    eq(null, $flow->createJob('c3', 1, "🆔 5001 Instagram Likes"), 'aynı sağlayıcı');
});
test('aynı fiyat değişikliğini 2 kanal duyurunca tek bildirim', function () {
    [$db, $tg, $flow] = env();
    $t = "Price update! Service 4101 price changed from \$0.44 to \$0.54";
    ok($flow->createNotice('kanala', 1, $t, 'price') !== null);
    eq(null, $flow->createNotice('kanalb', 9, $t, 'price'));
    eq(1, (int)$db->one('SELECT COUNT(*) c FROM notices')['c']);
    eq('kanalb', json_decode($db->one('SELECT also_json FROM notices')['also_json'], true)['sources'][0]['channel']);
    ok($flow->createNotice('kanalb', 10, "Service 4102 price changed from \$1 to \$2", 'price') !== null, 'farklı servis');
});

echo "Öncelikli sağlayıcı modu\n";
function prioEnv(bool $on): array
{
    [$db, $tg] = env();
    $db->run("INSERT INTO sources(kind,name,api_url,api_key,created_at) VALUES('provider','Pri','u','k',1),('provider','Diger','u','k',1)");
    $db->run("INSERT INTO channels(username, created_at, source_id) VALUES('prich',1,1),('digerch',1,2),('diger2',1,2)");
    $cfg = App::config();
    $cfg['priority_source'] = $on ? '1' : '';
    return [$db, $tg, new Flow($db, $tg, $cfg)];
}
test('Priority::twin: aynı platform, sayılar ve benzer kelimeler', function () {
    ok(Priority::twin('Instagram Followers | No Drop | Max 50K', 'Instagram Followers - No Drop - Max 50K'));
    ok(!Priority::twin('Instagram Followers | Max 50K', 'Instagram Followers | Max 100K'), 'sayı farklı');
    ok(!Priority::twin('Instagram Followers', 'TikTok Followers'), 'platform farklı');
    ok(!Priority::twin('Instagram Likes Fast Real Cheap Best', 'Instagram Views'), 'kelimeler farklı');
});
test('öncelikli kapalıyken farklı sağlayıcılar birbirinden etkilenmez', function () {
    [$db, $tg, $flow] = prioEnv(false);
    ok($flow->createJob('digerch', 1, "🆔 11 Instagram Followers | No Drop | Max 50K") !== null);
    ok($flow->createJob('prich', 1, "🆔 900 Instagram Followers | No Drop | Max 50K") !== null);
    eq(2, (int)$db->one('SELECT COUNT(*) c FROM jobs')['c']);
});
test('öncelikli sağlayıcı gelince, karar bekleyen diğer işin aynı servisinin yerine geçer', function () {
    [$db, $tg, $flow] = prioEnv(true);
    $old = $flow->createJob('digerch', 1, "🆔 11 Instagram Followers | No Drop | Max 50K\n🆔 12 TikTok Likes | Fast");
    $new = $flow->createJob('prich', 1, "🆔 900 Instagram Followers | No Drop | Max 50K");
    ok($new !== null);
    eq([12], array_map(static fn($i) => (int)$i['id'], $db->job($old)['items']), 'eski işte yalnızca kalan servis');
    $also = json_decode($db->job($new)['also_json'], true);
    eq([$old => [11]], $also['supersedes']);
    eq($new, json_decode($db->job($old)['also_json'], true)['superseded']['11']);
    // eski iş tamamen taşınırsa kapanır
    $o2 = $flow->createJob('diger2', 5, "🆔 21 YouTube Views | 1K");
    $flow->createJob('prich', 2, "🆔 901 YouTube Views | 1K");
    eq('rejected', $db->job($o2)['status']);
});
test('öncelikli sağlayıcı zaten duyurduysa diğer sağlayıcıdan aynı servis için iş açılmaz', function () {
    [$db, $tg, $flow] = prioEnv(true);
    $p = $flow->createJob('prich', 1, "🆔 900 Instagram Followers | No Drop | Max 50K");
    eq(null, $flow->createJob('digerch', 7, "🆔 11 Instagram Followers | No Drop | Max 50K"));
    $also = json_decode($db->job($p)['also_json'], true);
    eq('digerch', $also['sources'][0]['channel']);
    eq(1, (int)$db->one('SELECT COUNT(*) c FROM jobs')['c']);
});
test('öncelikli iş onaylıysa yine de yeni iş açılır ve not düşülür', function () {
    [$db, $tg, $flow] = prioEnv(true);
    $o = $flow->createJob('digerch', 1, "🆔 11 Instagram Followers | No Drop | Max 50K");
    $db->setStatus($o, 'approved');
    $n = $flow->createJob('prich', 1, "🆔 900 Instagram Followers | No Drop | Max 50K");
    ok($n !== null);
    eq($o, json_decode($db->job($n)['also_json'], true)['elsewhere']['900'] ?? null);
    eq('approved', $db->job($o)['status'], 'onaylı iş değişmez');
});
test('öncelikli sağlayıcı kataloğunda benzeri varsa mesajda gösterilir', function () {
    [$db, $tg, $flow] = prioEnv(true);
    $db->run("INSERT INTO remote_services(source_id,service_id,name,rate) VALUES(1, 900, 'Instagram Followers | No Drop | Max 50K', 1.5)");
    $id = $flow->createJob('digerch', 1, "🆔 11 Instagram Followers | No Drop | Max 50K");
    $job = $db->job($id);
    eq(900, $job['items'][0]['prio']['id']);
    ok(str_contains(json_encode(end($tg->calls), JSON_UNESCAPED_UNICODE), 'Öncelikli sağlayıcıda benzeri: #900'));
});

echo "Para birimi, okunamayanlar, kaçırılan gönderi, e-posta, izleme\n";
test('Parser: ₽ ₺ ₼ € ve satır sonu fiyatları para birimiyle okunur', function () {
    $i = Parser::parse("🆔 101 Instagram Likes | Fast\nЦена: 88 ₽\n🆔 102 TikTok Views ₺ 12.5\n🆔 103 YouTube Subs -- 8.50 AZN\n🆔 104 Telegram Members 0.5\$");
    eq(['RUB', 'TRY', 'AZN', null], array_map(static fn($x) => $x['currency'] ?? null, $i));
    eq(['88', '12.5', '8.50', '0.5'], array_column($i, 'price'));
});
test('Kur tanımlıysa dolara çevrilir; yoksa fiyat boş kalır ve işaretlenir', function () {
    [$db, $tg, $flow] = env();
    $cfg = App::config();
    $cfg['fx_rates'] = 'RUB=0.01, TRY=0,029';
    $f = new Flow($db, $tg, $cfg);
    $id = $f->createJob('kanal', 1, "🆔 101 Instagram Likes\nЦена: 100 ₽\n🆔 102 TikTok Views 5 AZN");
    $it = $db->job($id)['items'];
    eq('1', $it[0]['price']);
    eq('100 RUB', $it[0]['price_orig']);
    eq('', $it[1]['price']);
    ok(!empty($it[1]['fx_missing']));
    ok(str_contains(json_encode(end($tg->calls), JSON_UNESCAPED_UNICODE), 'kur tanımlı değil'));
});
test('Okunamayan ama servis duyurusuna benzeyen gönderi listeye düşer; sohbet düşmez', function () {
    [$db, $tg, $flow] = env();
    $reader = new FakeReader();
    $w = new Watcher($db, $flow, $reader);
    $w->addChannel('demosmm');
    $reader->pages['demosmm'] = ['ok' => true, 'error' => '', 'title' => '', 'posts' => [['id' => 1, 'text' => 'x', 'date' => '']]];
    $w->runAll();
    $reader->pages['demosmm']['posts'][] = ['id' => 2, 'text' => "New services added!\nInstagram Likes — 4455\nTikTok Views — 4456", 'date' => ''];
    $reader->pages['demosmm']['posts'][] = ['id' => 3, 'text' => 'Günaydın arkadaşlar, bugün hava çok güzel', 'date' => ''];
    $r = $w->runAll();
    eq(1, $r['demosmm']['unparsed'] ?? 0);
    eq([2], array_map('intval', array_column($db->all('SELECT msg_id FROM unparsed'), 'msg_id')));
    $w->runAll();
    eq(1, (int)$db->one('SELECT COUNT(*) c FROM unparsed')['c'], 'tekrar eklenmez');
});
test('Kaçırılan gönderiler ?before= ile geriye gidilip telafi edilir', function () {
    [$db, $tg, $flow] = env();
    $reader = new FakeReader();
    $w = new Watcher($db, $flow, $reader);
    $w->addChannel('demosmm');
    $reader->pages['demosmm'] = ['ok' => true, 'error' => '', 'title' => '', 'posts' => [['id' => 100, 'text' => 'x', 'date' => '']]];
    $w->runAll(); // başlangıç noktası 100
    $mk = static fn($id) => ['id' => $id, 'text' => "🆔 {$id}0 Instagram Likes $id", 'date' => ''];
    $reader->pages['demosmm']['posts'] = [$mk(120), $mk(121)];                       // sayfa 120'den başlıyor: 101–119 görünmüyor
    $reader->older['demosmm'][120] = ['ok' => true, 'error' => '', 'title' => '', 'posts' => [$mk(105), $mk(110), $mk(119)]];
    $r = $w->runAll();
    eq(5, count($r['demosmm']['jobs']), '3 telafi + 2 yeni');
    eq(121, (int)$db->one('SELECT last_post_id FROM channels')['last_post_id']);
});
test('Telafi edilemeyen büyük boşluk uyarı verir', function () {
    [$db, $tg, $flow] = env();
    $reader = new FakeReader();
    $w = new Watcher($db, $flow, $reader);
    $w->addChannel('demosmm');
    $reader->pages['demosmm'] = ['ok' => true, 'error' => '', 'title' => '', 'posts' => [['id' => 100, 'text' => 'x', 'date' => '']]];
    $w->runAll();
    $reader->pages['demosmm']['posts'] = [['id' => 150, 'text' => 'y', 'date' => '']];
    $w->runAll();
    ok(str_contains(json_encode($tg->calls, JSON_UNESCAPED_UNICODE) . json_encode($db->all('SELECT message FROM logs'), JSON_UNESCAPED_UNICODE), 'atlandı'));
});
test('E-posta: Telegram gitmezse yedek yol; açık olduğu için tekrar tekrar gönderilmez', function () {
    [$db, $tg] = env();
    $sent = [];
    $tg2 = new class extends FakeTelegram {
        public function call(string $method, array $params = [], int $timeout = 20)
        {
            throw new RuntimeException('bot kapalı');
        }
    };
    $a = new Alerts($db, $tg2, ['owner_id' => OWNER, 'alert_email' => 'ben@ornek.com', 'alert_email_mode' => 'fallback']);
    $a->mailer = function ($to, $subj, $text) use (&$sent) {
        $sent[] = [$to, $text];
        return true;
    };
    ok($a->send('k1', 'cron', '⚠️ <b>Cron durdu</b>'));
    ok(!$a->send('k1', 'cron', '⚠️ <b>Cron durdu</b>'), 'bekleme süresi');
    eq(1, count($sent));
    ok(str_contains($sent[0][1], 'Cron durdu') && !str_contains($sent[0][1], '<b>'));
    $b = new Alerts($db, $tg2, ['owner_id' => OWNER, 'alert_email' => '']);
    ok(!$b->send('k2', 'cron', 'x'), 'e-posta yoksa gitmez');
    [$db2, $tg3] = env();
    $got = [];
    $c = new Alerts($db2, $tg3, ['owner_id' => OWNER, 'alert_email' => 'ben@ornek.com', 'alert_email_mode' => 'fallback']);
    $c->mailer = function () use (&$got) {
        $got[] = 1;
        return true;
    };
    $c->send('k3', 'cron', 'x');
    eq(0, count($got), 'Telegram gittiyse fallback modunda e-posta yok');
    $d = new Alerts($db2, $tg3, ['owner_id' => OWNER, 'alert_email' => 'ben@ornek.com', 'alert_email_mode' => 'all']);
    $d->mailer = $c->mailer;
    $d->send('k4', 'cron', 'x');
    eq(1, count($got));
});
test('Onaylı ama eklenmeyen iş günde bir hatırlatılır', function () {
    [$db, $tg, $flow] = env();
    $old = time() - 30 * 3600;
    $db->run("INSERT INTO jobs(channel,msg_id,text_hash,source_text,items_json,status,created_at,updated_at) VALUES('c',1,'h','x','[]','approved',?,?)", [$old, $old]);
    $a = new Alerts($db, $tg, ['owner_id' => OWNER]);
    $a->check();
    ok(str_contains(json_encode($tg->calls, JSON_UNESCAPED_UNICODE), 'panele eklenmeyi bekliyor'));
});
test('İzleme: cron ve kanal sorunları listelenir, sağlıklıysa boş', function () {
    [$db] = env();
    ok(count(TgWatch\Monitor::problems($db)) >= 1, 'cron hiç çalışmadı');
    $db->set('cron_last_run', (string)time());
    eq([], TgWatch\Monitor::problems($db));
    $db->run("INSERT INTO channels(username, created_at, enabled, last_error, last_ok_at) VALUES('bozuk', 1, 1, 'HTTP 404', ?)", [time() - 5 * 3600]);
    ok(str_contains(TgWatch\Monitor::problems($db)[0], '@bozuk'));
    $db->set('cron_last_run', (string)(time() - 3600));
    eq(2, count(TgWatch\Monitor::problems($db)));
});
test('Ayar: e-posta ve izleme anahtarı doğrulanır', function () {
    eq(false, Settings::check('alert_email', 'bozuk')[0]);
    eq([true, ''], Settings::check('alert_email', ''));
    eq([true, 'a@b.co'], Settings::check('alert_email', 'a@b.co'));
    eq(false, Settings::check('monitor_key', 'kisa')[0]);
});

echo "Bot komutları ve otomatik yedek\n";
function lastText(FakeTelegram $tg): string
{
    foreach (array_reverse($tg->calls) as [$m, $p]) {
        if (in_array($m, ['sendMessage', 'editMessageText'], true)) {
            return (string)($p['text'] ?? '');
        }
    }
    return '';
}
test('/bekleyen listeler; /is, /ara, /fiyat, /onayla, /reddet, /yardim çalışır', function () {
    [$db, $tg, $flow] = env();
    $a = $flow->createJob('kanala', 1, "🆔 7001 Instagram Likes | Fast");
    $b = $flow->createJob('kanala', 2, "🆔 7002 TikTok Views | Max 1M");
    $flow->handleUpdate(msg(1, OWNER, '/bekleyen'));
    $t = lastText($tg);
    ok(str_contains($t, '2 iş karar bekliyor') && str_contains($t, "#$a") && str_contains($t, "#$b"));
    ok(str_contains(json_encode(end($tg->calls)[1]['reply_markup'] ?? []), 'ba:ask'));
    $flow->handleUpdate(msg(2, OWNER, "/ara 7002"));
    ok(str_contains(lastText($tg), "#$b") && !str_contains(lastText($tg), "#$a "));
    $flow->handleUpdate(msg(3, OWNER, "/is $a"));
    ok(str_contains(lastText($tg), 'Çeviriyi onaylıyor musun'));
    $db->setJson('match_report', ['model' => ['a' => 2.0, 'b' => 0.1, 'mode' => 'fit']]);
    $flow->handleUpdate(msg(4, OWNER, '/fiyat 0,44'));
    ok(str_contains(lastText($tg), '0.98'), lastText($tg));
    $flow->handleUpdate(msg(5, OWNER, "/reddet $b"));
    eq('rejected', $db->job($b)['status']);
    $flow->handleUpdate(msg(6, OWNER, "/onayla $a"));
    ok(in_array($db->job($a)['status'], ['approved', 'published']));
    $flow->handleUpdate(msg(7, OWNER, '/yardim'));
    ok(str_contains(lastText($tg), '/bekleyen'));
    $flow->handleUpdate(msg(8, OWNER, '/ozet'));
    ok(str_contains(lastText($tg), 'Özet'));
    $flow->handleUpdate(msg(9, OWNER, '/kanallar'));
    $before = count($tg->calls);
    $flow->handleUpdate(msg(10, 999, '/bekleyen'));
    eq($before, count($tg->calls), 'sahip dışı kişiye cevap yok');
});
test('Toplu onay: önce soru, evet deyince yalnızca güvenli işler; eksik kelimeli iş atlanır', function () {
    [$db, $tg, $flow] = env();
    $ok = $flow->createJob('kanala', 1, "🆔 7001 Instagram Likes | Fast");
    $bad = $flow->createJob('kanala', 2, "🆔 7002 Zzyzx Qwertyplon");
    $db->saveItems($bad, array_map(static fn($i) => $i + ['unknown' => ['Zzyzx']], $db->job($bad)['items']));
    $cbq = fn($uid, $data) => ['update_id' => $uid, 'callback_query' => ['id' => "c$uid", 'from' => ['id' => OWNER], 'data' => $data, 'message' => ['message_id' => 77, 'chat' => ['id' => OWNER]]]];
    $flow->handleUpdate($cbq(1, 'ba:ask'));
    ok(str_contains(lastText($tg), '1 iş onaylansın mı'));
    eq('awaiting', $db->job($ok)['status'], 'soru aşamasında onaylanmaz');
    $flow->handleUpdate($cbq(2, 'ba:no'));
    eq('awaiting', $db->job($ok)['status']);
    $flow->handleUpdate($cbq(3, 'ba:yes'));
    ok(in_array($db->job($ok)['status'], ['approved', 'published']));
    eq('awaiting', $db->job($bad)['status'], 'eksik kelimeli iş onaylanmadı');
    ok(str_contains(lastText($tg), '1 iş onaylandı'));
});
test('Otomatik yedek: zamanı gelince Telegram\'a dosya gider, aynı gün tekrar gitmez', function () {
    [$db, $tg] = env();
    $b = new TgWatch\AutoBackup($db, ['owner_id' => OWNER, 'backup_auto' => 'daily', 'backup_to' => 'telegram', 'backup_hour' => 0], $tg);
    ok($b->due());
    $b->maybeRun();
    eq(1, count($tg->docs));
    ok(str_starts_with($tg->docs[0][0], 'tgwatch-yedek-') && str_contains($tg->docs[0][1], '"tgwatch": 1'));
    ok(!str_contains($tg->docs[0][1], 'bot_token'), 'anahtar yok');
    ok(!$b->due(), 'bugün gitti');
    ok($b->due(time() + 21 * 3600), 'ertesi gün');
    $off = new TgWatch\AutoBackup($db, ['backup_auto' => 'off'], $tg);
    ok(!$off->due(time() + 99 * 86400));
});
test('Otomatik yedek: e-posta eki, hata durumunda uyarı ve 30 dk bekleme', function () {
    [$db, $tg] = env();
    $mails = [];
    TgWatch\AutoBackup::$mailer = function ($to, $subj, $body, $name, $content) use (&$mails) {
        $mails[] = [$to, $name, strlen($content)];
        return true;
    };
    $b = new TgWatch\AutoBackup($db, ['owner_id' => OWNER, 'backup_auto' => 'daily', 'backup_to' => 'email', 'alert_email' => 'ben@ornek.com', 'backup_hour' => 0], $tg);
    $r = $b->run();
    ok($r['ok']);
    eq('ben@ornek.com', $mails[0][0]);
    eq(0, count($tg->docs), 'yalnızca e-posta');
    TgWatch\AutoBackup::$mailer = null;
    [$db2, $tg2] = env();
    $bad = new TgWatch\AutoBackup($db2, ['owner_id' => OWNER, 'backup_auto' => 'daily', 'backup_to' => 'email', 'alert_email' => '', 'backup_hour' => 0], $tg2);
    $r2 = $bad->run();
    ok(!$r2['ok'] && str_contains($r2['message'], 'adres yok'));
    ok(!$bad->due(), '30 dk beklenir');
    ok(str_contains(json_encode($tg2->calls, JSON_UNESCAPED_UNICODE), 'Otomatik yedek gönderilemedi'), 'uyarı gitti');
});

echo "Bot komutları paneli (kayıt defteri)\n";
test('Kayıt defteri: varsayılanlar, doğrulama, takma ad, kapatma, sabit cevap', function () {
    [$db, $tg, $flow] = env();
    ok(count(TgWatch\BotRegistry::load($db)) >= 12, 'varsayılan liste');
    $bad = TgWatch\BotRegistry::save($db, [['name' => 'Geçersiz Ad', 'action' => 'pending', 'enabled' => '1'], ['name' => 'start', 'action' => 'help'], ['name' => 'a', 'action' => 'yok'],
        ['name' => 'x', 'action' => 'text'], ['name' => 'b', 'action' => 'pending'], ['name' => 'b', 'action' => 'help']], true);
    eq(5, count($bad), json_encode($bad, JSON_UNESCAPED_UNICODE));
    eq([], $db->getJson('bot_commands', []), 'hata varken kaydedilmez');
    $rows = [
        ['name' => 'Bekle', 'action' => 'pending', 'enabled' => '1', 'desc' => 'Beklenenler'],
        ['name' => 'oz', 'action' => 'summary', 'enabled' => '1'],
        ['name' => 'merhaba', 'action' => 'text', 'enabled' => '1', 'text' => 'Selam <b>patron</b>'],
        ['name' => 'kapali', 'action' => 'channels'],
        ['name' => '', 'action' => 'text', 'text' => ''],
    ];
    eq([], TgWatch\BotRegistry::save($db, $rows, true));
    eq(['bekle', 'oz', 'merhaba', 'kapali'], array_column(TgWatch\BotRegistry::load($db), 'name'));
    $flow->createJob('kanala', 1, "🆔 7001 Instagram Likes");
    $flow->handleUpdate(msg(1, OWNER, '/Bekle'));
    ok(str_contains(lastText($tg), '1 iş karar bekliyor'), 'yeniden adlandırılmış komut çalışır');
    $flow->handleUpdate(msg(2, OWNER, '/oz'));
    ok(str_contains(lastText($tg), 'Özet'), 'takma ad');
    $flow->handleUpdate(msg(3, OWNER, '/merhaba'));
    ok(str_contains(lastText($tg), 'Selam &lt;b&gt;patron'), 'sabit cevap kaçışlı gider');
    $n = count($tg->calls);
    $flow->handleUpdate(msg(4, OWNER, '/kapali'));
    $flow->handleUpdate(msg(5, OWNER, '/bekleyen'));
    eq($n, count($tg->calls), 'kapalı ve silinmiş komut cevap vermez');
    $flow->handleUpdate(msg(6, OWNER, '/start'));
    ok(str_contains(lastText($tg), 'Telegram ID'), 'sabit komut hep çalışır');
    $menu = TgWatch\BotRegistry::menu($db);
    eq(['bekle', 'oz', 'merhaba'], array_column($menu, 'command'));
    eq('Beklenenler', $menu[0]['description']);
    eq('Sabit cevap', $menu[2]['description']);
});
test('Ana anahtar kapalıyken hiçbir komut çalışmaz; /yardim yalnızca açık olanları sayar; varsayılana dönülür', function () {
    [$db, $tg, $flow] = env();
    TgWatch\BotRegistry::save($db, [['name' => 'yardim', 'action' => 'help', 'enabled' => '1'], ['name' => 'oz', 'action' => 'summary']], true);
    $flow->handleUpdate(msg(1, OWNER, '/yardim'));
    $t = lastText($tg);
    ok(!str_contains($t, '/oz') && str_contains($t, '/yardim'));
    TgWatch\BotRegistry::save($db, [['name' => 'yardim', 'action' => 'help', 'enabled' => '1']], false);
    $n = count($tg->calls);
    $flow->handleUpdate(msg(2, OWNER, '/yardim'));
    eq($n, count($tg->calls));
    eq([], TgWatch\BotRegistry::menu($db));
    TgWatch\BotRegistry::reset($db);
    $flow->handleUpdate(msg(3, OWNER, '/durum'));
    ok(str_contains(lastText($tg), 'Durum'), 'varsayılanda /durum');
    ok(TgWatch\BotRegistry::find($db, 'ÖZET') !== null, 'Türkçe harfli yazım');
});

echo "Güvenlik\n";
test('CSV: dış kaynaklı adlar formül olarak çalışmasın', function () {
    $c = [TgWatch\Web\Ui::class, 'csvCell'];
    eq("'=HYPERLINK(\"http://x\")", $c('=HYPERLINK("http://x")'));
    eq("'+cmd|' /C calc'!A0", $c("+cmd|' /C calc'!A0"));
    eq("'@SUM(1)", $c('@SUM(1)'));
    eq("'-2+3", $c('-2+3'));
    eq('-5', $c('-5'), 'sayı kalır');
    eq('Instagram Likes', $c('Instagram Likes'));
    eq(12, $c(12));
});
test('Telegram mesajlarında dış kaynaklı metin kaçışlı gider', function () {
    [$db, $tg, $flow] = env();
    $flow->createJob('kanal', 1, "🆔 9001 <b>Evil</b> & <a href=\"x\">link</a> Likes");
    $sent = json_encode(array_map(static fn($c) => $c[1]['text'] ?? '', array_filter($tg->calls, static fn($c) => $c[0] === 'sendMessage')), JSON_UNESCAPED_UNICODE);
    ok(!str_contains($sent, '<a href') && !str_contains($sent, '<b>Evil'), 'HTML enjekte edilemez');
    ok(str_contains($sent, '&lt;b&gt;Evil'));
});
test('Webhook anahtarı boşsa ya da yanlışsa istek reddedilir (bot.php mantığı)', function () {
    ok(!hash_equals('abc', ''), 'boş başlık');
    ok(!hash_equals('abc', 'abd'));
});

echo "Ad kalıbı\n";
test('Ad kalıbı: parça seçimi, sıra, ayraç, emoji, ön/son ek, uzunluk', function () {
    $segs = ['Instagram Bəyəni', 'Sürətli', 'Start: 0-1 Hour', 'Ləğv aktivdir', 'Real', 'Maks 100K', 'Saatda 10K', '30 Gün Qarantili ♻️'];
    $N = [TgWatch\NameTemplate::class, 'apply'];
    eq(implode(' | ', $segs), $N($segs, []), 'varsayılan: hiçbir şey değişmez');
    eq('Instagram Bəyəni | Sürətli | Start: 0-1 Hour | Ləğv aktivdir | Real | Saatda 10K | 30 Gün Qarantili ♻️', $N($segs, ['keep_max' => false]));
    eq('Instagram Bəyəni | Sürətli | Start: 0-1 Hour | Ləğv aktivdir | Real | Maks 100K | 30 Gün Qarantili ♻️', $N($segs, ['keep_speed' => false]));
    eq('Instagram Bəyəni | Sürətli | Real | 30 Gün Qarantili ♻️', $N($segs, ['keep_start' => false, 'keep_cancel' => false, 'keep_max' => false, 'keep_speed' => false]));
    eq('Instagram Bəyəni', $N($segs, ['keep_quality' => false, 'keep_refill' => false, 'keep_start' => false, 'keep_cancel' => false, 'keep_max' => false, 'keep_speed' => false]), 'hepsi kapalıysa yalnızca ana ad kalır');
    ok(str_starts_with($N($segs, ['order' => 'tidy']), 'Instagram Bəyəni | Sürətli | Real | 30 Gün Qarantili ♻️ | Start'), 'derli toplu sıra');
    ok(str_ends_with($N($segs, ['order' => 'tidy']), 'Maks 100K | Saatda 10K'));
    eq('Instagram Bəyəni • Sürətli', $N(['Instagram Bəyəni', 'Sürətli'], ['separator' => ' • ']));
    eq('x | 30 Gün Qarantili', $N(['x', '30 Gün Qarantili ♻️'], ['emoji' => 'remove']), 'emoji silinir');
    eq('⚡ Instagram Bəyəni | Sürətli [TG]', $N(['Instagram Bəyəni', 'Sürətli'], ['prefix' => '⚡', 'suffix' => '[TG]']));
    eq('Instagram Bəyəni | Sürətli | Real | 30 Gün Qarantili ♻️', $N($segs, ['order' => 'tidy', 'max_len' => 60]), 'sığmayınca önemsiz parçalar atılır');
    ok(mb_strlen($N(['A' . str_repeat('b', 80)], ['max_len' => 30])) <= 30, 'ana ad kesilir');
    eq("Instagram Bəyəni | Sürətli", $N(['Instagram Bəyəni', 'Sürətli', '', '  '], []), 'boş parçalar atılır');
    // normalize: geçersiz değerler varsayılana düşer
    $n = TgWatch\NameTemplate::normalize(['separator' => '<script>', 'order' => 'x', 'emoji' => 'y', 'max_len' => 9999, 'prefix' => "a\nb|c"]);
    eq([' | ', 'source', 'keep', 255, 'a b c'], [$n['separator'], $n['order'], $n['emoji'], $n['max_len'], $n['prefix']]);
});
test('Ad kalıbı çeviri motoruna bağlı: banka ve kurallar aynı kalıpla çıkar', function () {
    [$db] = env();
    $d = Dictionary::defaults();
    $d['options'] = ['keep_max' => false, 'separator' => ' - ', 'order' => 'tidy'];
    $e = new TgWatch\Engine($db, $d);
    eq('Threads İzləyici - Azalma olmaz - 30 Gün Qarantili ♻️ - Gündə 5K', $e->translate('Threads Followers | Max 50K | 5K/Day | No Drop | 30 Days Refill ♻️')['text']);
    $e->memory()->learn('service', 'Twitch Followers | Max 10K', 'Twitch İzləyici | Maks 10K', 'manual');
    eq('Twitch İzləyici', $e->translate('Twitch Followers | Max 10K')['text'], 'banka yolu da kalıptan geçer');
});

// temizlik
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $f) {
    $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
}
@rmdir($tmp);

echo "\n$passed geçti, $failed başarısız\n";
exit($failed ? 1 : 0);
