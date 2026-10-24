# tgwatch — Telegram servis duyurusu → Azerice çeviri → bot onayı → yayın

Herkese açık Telegram kanallarındaki yeni servis duyurularını algılar, kurallarınla Azericeye çevirir,
botun üzerinden **✅ Onayla / ✏️ Düzenle / 🔄 Yeniden oluştur / ❌ Reddet** butonlarıyla onayına sunar.
Onaydan sonra yayın yapar. Her şeyi web panelden izleyebilirsin.

> **Yayın şu an "manual":** Perfect Panel Admin API v2'de servis/kategori/sağlayıcı metotları henüz yok.
> Onaydan sonra iş "Onaylandı (yayın bekliyor)" olur; ekleme paketi web panelde durur, bota ayrıca mesaj gitmez.
> Metotlar yayınlanınca `src/Publisher/AdminApiPublisher.php` doldurulacak.

## Denenmiş / denenmemiş

| Parça | Durum |
|---|---|
| Çeviri, ayrıştırma, bot akışı, cron mantığı, web panel | Otomatik testler (`php tests/run.php`) + yerel PHP sunucusunda denendi |
| `public_html/system/tgwatch` düzeni, `.htaccess` koruması, web kurulum ekranı, webhook ucu (gizli anahtar) | Apache 2.4 + mod_php ile denendi. LiteSpeed'de **denenmedi** |
| Gerçek `t.me/s` sayfası | **Denenmedi** (geliştirme ortamından Telegram kapalıydı). Test sayfası Telegram'ın bilinen yapısına göre yazıldı |
| Telegram Bot API'ye gerçek istek | **Denenmedi** |
| Claude API çevirisi | Anthropic API'ye erişim var (sahte anahtar 401 döndü); gerçek anahtarla **denenmedi** |
| Çeviri bankası, eşleştirme, motor, kontrol | Otomatik testler + yerel panel |
| Sağlayıcı/panel API'sinden gerçek senkron | **Denenmedi** (bu ortamdan erişim yok) |
| Ayarlar sayfası, şifre değiştirme, oturumlar, tema/vurgu, temizlik, CSV, kanal filtresi | Otomatik testler + tarayıcıda denendi (Chromium) |
| Ekleme paketi, servis değişikliği duyuruları, uyarılar, Telegram ile giriş, yedek | Otomatik testler + sahte Telegram ile yerel panel. Gerçek Telegram'la **denenmedi** |

## Dosyalar

```
tgwatch/                 → zip'i İSTEDİĞİN klasöre aç (public_html içi ya da dışı)
  public/                → web panel (index.php) ve Telegram webhook (bot.php)
  config.sample.php      → config.php olarak kopyala ve doldur
  cron.php               → komut satırı cron'u (SSH varsa)
  public/webcron.php     → web cron ucu (cron-job.org)
  src/ tools/ tests/     → kod ve araçlar (web'den erişilemez)
  data/                  → veritabanı (otomatik oluşur, adı rastgele, web'den erişilemez)
```

Yol ayarı gerekmez: `public/app_path.php` tgwatch klasörünü kendisi bulur.
Örnek: zip `public_html/system/tgwatch` altına açıldıysa
panel `https://ALANADIN/system/tgwatch/public/`, webhook `https://ALANADIN/system/tgwatch/public/bot.php` olur.

**public_html içine açarsan:** Yalnızca `public/` web'den açılır. `config.php`, `cron.php`, `src/`, `tools/`, `tests/`, `data/`
`.htaccess` ile kapalıdır (Apache/LiteSpeed). Ayrıca veritabanı dosyasının adı rastgeledir, `.htaccess` çalışmasa bile tahmin edilemez.
Zip'i açarken gizli dosyaların (`.htaccess`) da çıktığından emin ol. **Sağlık** sayfasındaki "Web koruması" satırı bunu kontrol eder.
İstersen tgwatch'ı public_html dışına koyup sadece `public/` içeriğini web'e kopyalayabilirsin (o zaman `app_path.php` içindeki yolu düzelt).

## Kurulum (DirectAdmin, SSH gerekmez)

### 0. Eski token
Daha önce bir sohbete token yapıştırdıysan: BotFather → `/mybots` → bot → **API Token → Revoke current token**.
Token'ı, Claude anahtarını ve şifre hash'ini **yalnızca hosting'deki config.php'ye** yaz. Sohbete yazma.

### 1. Bot
BotFather → `/newbot` → token'ı al.

### 2. Dosyalar ve PHP
1. File Manager → istediğin klasöre zip'i yükle ve **Extract** et.
2. DirectAdmin'de **Select PHP Version** / **PHP Selector** (adı hosting'e göre değişir): **8.1+** seç,
   `curl`, `mbstring`, `dom`, `pdo_sqlite` eklentilerini aç.

### 3. config.php ve panel şifresi
1. `config.sample.php` dosyasını aynı klasörde **Copy** ile `config.php` adıyla kopyala, izinlerini **600** (ya da 640) yap.
2. Tarayıcıda paneli aç (`…/tgwatch/public/`). Şifre henüz ayarlı olmadığı için **Kurulum** ekranı gelir:
   şifreyi iki kez yaz → `admin_password_hash`, `webhook_secret`, `webhook_url` ve `cron_key` satırları üretilir.
   Bu satırları File Manager → `config.php` → **Edit** ile aynı satırların yerine yapıştır. Bu ekran hiçbir dosyaya yazmaz.
3. `bot_token` yaz. `owner_id` için şimdilik 0 bırak (adım 4'te öğreneceksin).
4. Sayfayı yenile → giriş ekranı gelir → giriş yap.

Yazım hatası yaparsan panel hangi satırda hata olduğunu söyler.

### 4. Webhook ve Telegram ID
1. Panel → **Sağlık** → **Webhook'u kur** → **Webhook bilgisi** ("Son hata: yok" görmelisin).
2. Bota `/start` yaz → bot "Telegram ID'niz: …" der. Bu sayıyı `config.php` → `owner_id` olarak yaz.
3. Tekrar `/start` → "✅ tgwatch hazır" gelmeli.

HTTPS sertifikan yoksa ya da webhook hata veriyorsa: `'bot_mode' => 'polling'` yap ve **Webhook'u kaldır**.
Butonlar o zaman cron her çalıştığında işlenir.

### 5. Kontrol
Panel → **Sağlık** → **Ağ testlerini çalıştır**. ✗ olan satırları düzelt. (SSH varsa aynısı: `php tools/check.php`.)

### 6. Kanal ve ilk deneme
1. Panel → **Kanallar** → kanal ekle → **Test** (kaydetmeden okur ve çeviriyi gösterir).
2. Aynı sayfada **"son 3 gönderiyi gerçekten işle"** → servis duyurusu varsa bota onay mesajı gelir.
   (SSH varsa: `php cron.php --test`, `php cron.php --backfill=3`.)

### 7. Cron (cron-job.org — SSH gerekmez)
1. Panel → **Sağlık → Kurulum bilgileri** → **cron-job.org adresi**ni kopyala:
   `https://ALANADIN/…/tgwatch/public/webcron.php?key=CRON_KEY` (`cron_key` config.php'de olmalı, Kurulum ekranı üretir).
2. cron-job.org → **Create cronjob** → URL'yi yapıştır → **Every minute** → kaydet.
   İstersen anahtarı URL yerine **Advanced → Headers** kısmında `X-Cron-Key: CRON_KEY` olarak gönder, URL'den `?key=` kısmını sil.
3. **Özet** sayfasında "Cron son çalışma" 1 dakika içinde güncellenmeli.

Uç her zaman HTTP 200 döner (cron-job.org art arda hata alınca işi kapatıyor). Gövdede `ok` ya da `errors: N` ve kanal özeti yazar,
ayrıntılar paneldeki **Loglar**'dadır. Bir tur en fazla `web_cron_time_limit` (20 sn) sürer, cron-job.org 30 sn bekler.
Anahtar yanlışsa 403 döner. Bu adres anahtar içerdiği için gizli tutulmalı.

Hosting'de cron varsa onun yerine: `* * * * * PHP_YOLU /TAM/YOL/tgwatch/cron.php >/dev/null 2>&1`
(PHP_YOLU: `/usr/local/bin/php`, `/usr/local/php81/bin/php`, `/opt/alt/php81/usr/bin/php`…).

Normal çalışmada kanal ilk kez okunduğunda **eski gönderiler işlenmez**, sadece sonrakiler işlenir.

## Web panel

Tasarım Apple'ın arayüz ilkelerini (HIG) izler. Sistem yazı tipi kullanılır (Apple cihazlarda SF Pro, Windows'ta Segoe UI), dış yazı tipi ya da kütüphane yüklenmez. Buzlu cam efekti yalnızca kenar menü, üst çubuk, mobil sekme çubuğu, komut paleti ve alttaki işlem çubuğunda kullanılır; içerik opaktır. Görünüm sistemin açık/koyu ayarını izler. Azaltılmış saydamlık, yüksek kontrast ve azaltılmış hareket tercihleri desteklenir.

- **Genel bakış:**
  - Kararını bekleyen iş sayısı ve dört kutucuk, her birinde 14 günlük eğilim çizgisi: bugün gelen servis, sitede yayın bekleyen, bankadan çevrilme oranı, Claude kullanımı.
  - Son 14 gün grafiği: üzerine gelince ya da klavyeyle odaklanınca ayrıntı gösterir, tablo görünümü vardır.
  - Kanal sağlığı, sıradaki işler ve hızlı onay.
  - Sistem durumu: kanal okuma, bot, banka, yayın.
  - Çevirinin kaynağı dağılımı ve son etkinlikler.
- **Onay kuyruğu:** Karar bekleyen tüm işler. Her serviste Azerice ad büyük, kaynak ad altında küçük yazılır, çevirinin nereden geldiği gösterilir.
- **İşler:** Arama (servis ID, iş numarası, kelime), durum ve kanal filtresi, sayfalama, toplu onaylama, reddetme ve "sitede yayınlandı say".
- **İş sayfası:** Her ad ayrı bir kutuda düzenlenir, "Kaydet ve onayla" tek adımdır. Eksik kelimeler oradan sözlüğe eklenir. Onaylanan işte ID'ler ve adlar kopyalanabilir ya da `.txt` olarak indirilebilir. "claude.ai için hazırla" butonu da buradadır.
- **Sözlük, Çeviri bankası, Çeviri ayarları, Kanallar, Kayıtlar, Sağlık:** Önceki bölümlerde anlatıldığı gibi.
- **Ekleme paketi (iş sayfası ve bot mesajı):** Kanalın sağlayıcısı Kanallar sayfasında seçilir (tek sağlayıcı varsa otomatik). Pakette sağlayıcıdaki servis ID'si, sağlayıcı fiyatı, önerilen satış fiyatı (Eşleştirme'den öğrenilen kâr formülü; Çeviri ayarları > Fiyat önerisi'nden elle de verilebilir), min/max, kategori (ikonu panelindeki kategorilerden öğrenilir, panelde var/yeni) ve "panelinde zaten var" uyarısı bulunur. Formülün çalışması için "Eşleştirmeyi çalıştır" bir kez çalıştırılmalı.
- **Servis değişiklikleri:** Kanalda "fiyat değişti" ya da "servis kapatıldı" duyurusu gelirse yeni servis sayılmaz. Panelindeki etkilenen servisler bulunur, ne yapman gerektiği yazılır, bota ✅ Yaptım / 🚫 Yoksay butonlu mesaj gider.
- **Uyarılar ve güvenlik:** Telegram uyarıları (kanal okunamıyor, cron durdu, webhook hatası, Claude limiti, uzun süre servis gelmedi, servis değişikliği, giriş) tür tür açılıp kapatılır; günlük özet saati seçilir. **Telegram ile iki adımlı giriş:** Şifreden sonra bota 6 haneli kod gelir (5 dk, 5 deneme). Açarken önce test kodu doğrulanır, kilitlenme olmaz. Yine de kilitlenirsen `config.php` içine `'login_2fa' => false,` yaz. Hosting Telegram'a doğrudan erişemiyorsa `'telegram_api_base'` ile ara sunucu adresi verilir.
- **Yedek:** Sağlık sayfasından indir/geri yükle (ayarlar, kanallar, çeviri bankası; API anahtarları yedeğe girmez).
- **Ayarlar:** `config.php`'deki her şey panelden değişir (Telegram, yayın, kanal okuma, güvenlik, görünüm, veri). Panelden kaydedilen değer `config.php`'nin üstüne yazılır; her satırda değerin nereden geldiği yazar. Token, anahtar gibi gizli değerler ekrana yazılmaz (boş bırakmak = değiştirme). Burada ayrıca:
  - **Telegram:** botu sına, test mesajı.
  - **Güvenlik:** şifre değiştirme (açık oturumlar kapanır), "tüm oturumları kapat", son girişler ve hatalı denemeler, oturum süresi ve kilit ayarı. Kilitlenirsen `config.php`'ye `'panel_settings' => false` yaz: yalnızca `config.php` geçerli olur.
  - **Görünüm:** açık/koyu/sistem teması, 5 vurgu rengi, rahat/sıkı yoğunluk.
  - **Veri ve bakım:** eski kayıtları ve (istenirse) bitmiş işleri otomatik silme, veritabanını sıkıştırma, tüm servisleri CSV indirme, yedek, sistem bilgisi.
- **Kanal filtresi ve not:** Kanallar sayfasında her kanal için "şu kelimeler geçen gönderileri yoksay" ve kısa not.
- **Kısayollar:**
  - **⌘K / Ctrl K:** Komut paleti; sayfaya git ya da iş ara, ok tuşları ve Enter ile.
  - **/:** Aramaya odaklanır.
  - Kenar menü üst çubuktaki düğmeyle gizlenebilir, tercih tarayıcıda hatırlanır.
- **Dinamik arayüz (JavaScript açıkken):** Sayfalar tam yenilenmeden değişir (yumuşak geçiş, geri tuşu çalışır); onay/ret gibi işlemler sayfayı yenilemeden yapılır ve köşede bildirim çıkar. Üstteki **Canlı** düğmesi açıkken 15 sn'de bir hafif bir sorgu yapılır: yeni duyuru gelince bildirim çıkar, rozetler güncellenir, Genel bakış/Kuyruk/Değişiklikler/İşler/Kayıtlar sayfaları yazı yazarken ya da seçim yaparken bozulmadan kendini yeniler. Sayılar sayarak açılır, grafikler büyür, kartlar sırayla belirir (azaltılmış hareket tercihinde kapalı). JavaScript kapalıyken her şey eskisi gibi çalışır.
- **Mobil:** Altta sekme çubuğu (Genel, Kuyruk, İşler, Kanallar, Menü).
- **JavaScript'siz de çalışır:** Kopyalama, onay sorusu, komut paleti, ipucu ve menü gizleme JavaScript ile gelir. Formlar, arama ve gezinme JavaScript kapalıyken de çalışır.

## Çeviri bankası ve çeviri yöntemi

**Çeviri bankası** (panel → Çeviri bankası) önceki çevirilerini tutar. Yeni bir ad gelince şu sırayla çevrilir:

1. Ad bankada birebir varsa bankadaki karşılık kullanılır ("Bankadan").
2. Yoksa ad ` | ` ile parçalara bölünür. Her parça bankadaki parçalarda aranır. Sayılar şablona çevrildiği için "30 Days Refill" bir kez öğrenilince "60 Days Refill" de çevrilir. "Threads Followers" öğrenilince "Kick Followers" da çevrilir ("Kısmen bankadan").
3. Bankada olmayan parçalar kurallar ve sözlükle çevrilir ("Kurallarla").

Bankayı doldurmak için:
- **Kaynaklar:** Kendi panelini (Azerice adlar) ve sağlayıcıları (İngilizce adlar) API adresi ve anahtarıyla ekle. Standart SMM API kullanılır (`action=services`, ör. `https://panel.com/api/v2`).
- **Eşleştirme:** Kendi servislerini sağlayıcı servisleriyle eşleştirir. Kullandığı sinyaller: aynı min/max, aynı platform, addaki sayılar, iade/iptal özellikleri ve fiyat. Fiyat formülünü (kâr yüzdesi, sabit kâr, kur) kesin eşleşmelerden kendisi bulur ve sayfada gösterir. Emin olamadığı eşleşmeleri "kontrol bekleyen" listesine koyar; Doğru/Yanlış ile karar verirsin, reddettiğin bir daha önerilmez.
- **Kendi kendine öğrenme:** Onayladığın her iş bankaya eklenir. Elle düzelttiğin adlar en yüksek öncelikle eklenir.
- Bankadaki her çifti arayabilir, düzeltebilir, silebilir ya da elle ekleyebilirsin.

**Çeviri yöntemi** (panel → Çeviri ayarları):

| Yöntem | Ne yapar | Maliyet |
|---|---|---|
| Kurallar + banka (varsayılan) | Yukarıdaki sıra | Ücretsiz |
| Karma | Bankadan tam çevrilemeyen ya da eksik kelimeli adlar Claude'a gider | Claude API, sadece gerektiğinde |
| Tüm adlar Claude ile | Hepsi Claude'a gider, bankadan örneklerle | Claude API |
| claude.ai için hazırla (her zaman) | İş sayfasında hazır metin oluşur. Bunu claude.ai'ye (Pro aboneliğin) yapıştırır, cevabı geri yapıştırırsın | Pro planın, ek ücret yok |

- **Claude API, Pro aboneliğinden ayrıdır:** console.anthropic.com'da hesap açılır, kredi yüklenir. Anahtar panelden girilir, sadece sunucudaki veritabanında saklanır.
- **Model seçimi:** Claude Opus 5.5 (varsayılan, en kaliteli), Sonnet 5.5 ya da Haiku 5.5 (en ucuz). Aylık istek sınırı ve tahmini harcama panelde görünür.
- **Claude'a her istekte giden:** senin kuralların (panelden düzenlenir), sözlük, kalıplar, her ad için bankadan benzer örnekler ve kural tabanlı taslak.
- **Kontrol:** Claude'un önerisi kullanılmadan önce denetlenir: sayılar, emojiler ve parça sayısı değişmemiş olmalı, onaylı sözlük terimleri korunmuş olmalı. Geçmeyen öneri kullanılmaz, nedeni iş sayfasında ve botta görünür.
- **Zamanlama:** Karma ya da Claude modunda yeni iş önce banka ve kurallarla anında bota gider. Claude iyileştirmesi bir sonraki kanal okumada yapılır ve bottaki mesaj güncellenir.
- **Silinecek ifadeler:** Reklam etiketleri gibi ifadeler çeviriden önce addan çıkarılır (Çeviri ayarları).
- **Denenmedi:** Gerçek bir anahtarla Claude çağrısı. Bu ortamdan Anthropic API'ye ulaşılabildi (sahte anahtar 401 döndü), ama başarılı bir çeviri isteği denenmedi. Ayarlar sayfasındaki **Kaydet ve Claude'u dene** butonu bunu iki örnek adla test eder.

## Kullanım

- **✅ Onayla** → bota yeni mesaj gitmez; onay mesajı "✅ Onaylandı (yayın bekliyor)" olur. Import paketi (ID listesi, ID → ad, `.txt`)
  web panelde işin sayfasındadır. Elle ekledikten sonra **Sitede yayınlandı olarak işaretle**.
  Perfect Panel API'si gelince yayın otomatik olur ve bota yalnızca kısa bir **"✅ Sitede yayınlandı"** bildirimi gelir.
  Botta iki kez basmak aynı işi iki kez yayınlamaz.
- **✏️ Düzenle** → bot listeyi `ID | ad` biçiminde gönderir. Sadece değiştirdiğin satırları geri yaz. `/iptal` ile vazgeçersin.
- **🔄 Yeniden oluştur** → güncel sözlükle tekrar çevirir. `claude_api_key` varsa Claude ile çevirir (elle düzenlemeler sıfırlanır).
- **❌ Reddet** → iş kapanır.
- Bot komutları: `/start` (ID'ni gösterir), `/durum`, `/iptal`.
- Butonları yalnızca `owner_id` kullanabilir. Başkası basarsa "Yetkiniz yok" görür, durum değişmez, log'a yazılır.

## Çeviri

Sırasıyla: süslü Unicode → normal harf, `|` ile parçalara bölme, **kurallar** (regex, sayılı kalıplar), **sözlük**
(en uzun ifade önce, tam kelime, büyük/küçük harf duyarsız). Sözlükte olmayan Latin kelimeler **⚠️ Sözlükte yok**
olarak gösterilir. Hepsi web panelden **Sözlük** sayfasında düzenlenir (önizleme dahil).

Örnek: `Threads Followers | No Drop | Max 50K | 5K/Day | 30 Days Refill ♻️`
→ `Threads İzləyici | Azalma olmaz | Maks 50K | Gündə 5K | 30 Gün Qarantili ♻️`

Ad kalıbı seçenekleri: "Maks 50K" ve "Gündə 5K" parçaları ayrı ayrı açılıp kapatılabilir.

**Senin terimlerin:** Bəyəni, Rəy, Azalma olmaz, Qarantili, Sürətli.
**Tahminler (kontrol et):** İzləyici, Baxış, Yadda Saxla, Paylaşım, Təsadüfi Rəy, Özəl Rəy ve sözlükteki diğer ifadeler
(Abunəçi, Üzv, Hekayə, Ömürlük Qarantili, Qarantisiz, Dərhal Başlayır…).

## Duyuru biçimi

Tanınan servis satırları: `🆔 13125 Ad…`, `ID: 13125 | Ad`, `13125 - Ad`, `Ad - ID: 13125`, ya da tek başına ID
satırının altında ad. Fiyat aynı satırda ya da altındaki satırlarda (`$1.20`, `0.35$`, `Price: …`). Kategori olarak
ID satırlarından önceki kısa başlık satırı alınır. Gerçek kanallarda farklı biçim çıkarsa `src/Parser.php` güncellenir.

## Güvenlik

- public_html içindeyken yalnızca `public/` açıktır. Diğer her şey `.htaccess` ile kapalıdır. CLI betikleri web'den çağrılırsa çalışmaz,
  veritabanı adı rastgeledir (`data/secret.php`, silme).
- Panel: şifre (Argon2id/bcrypt hash), aynı IP'den 5 yanlış denemede 15 dk kilit, CSRF, sıkı CSP, 2 saat hareketsizlikte oturum kapanır.
- Webhook: `X-Telegram-Bot-Api-Secret-Token` doğrulanır.
- `config.php` git'e girmez (`.gitignore`).
