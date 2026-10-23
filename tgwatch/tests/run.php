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
