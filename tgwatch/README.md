# tgwatch — Telegram servis duyurusu → Azerice çeviri → bot onayı → yayın

Herkese açık Telegram kanallarındaki yeni servis duyurularını algılar, kurallarınla Azericeye çevirir,
botun üzerinden **✅ Onayla / ✏️ Düzenle / 🔄 Yeniden oluştur / ❌ Reddet** butonlarıyla onayına sunar.
Onaydan sonra yayın yapar. Her şeyi web panelden izleyebilirsin.

> **Yayın şu an "manual":** Perfect Panel Admin API v2'de servis/kategori/sağlayıcı metotları henüz yok.
> Onaydan sonra bot sana panelde yapacağın **import paketini** (ID listesi + ID → Azerice ad, .txt dosyası) gönderir.
> Metotlar yayınlanınca `src/Publisher/AdminApiPublisher.php` doldurulacak.

## Denenmiş / denenmemiş

| Parça | Durum |
|---|---|
| Çeviri, ayrıştırma, bot akışı, cron mantığı, web panel | Otomatik testler (`php tests/run.php`) + yerel PHP sunucusunda denendi |
| `public_html/system/tgwatch` düzeni, `.htaccess` koruması, web kurulum ekranı, webhook ucu (gizli anahtar) | Apache 2.4 + mod_php ile denendi. LiteSpeed'de **denenmedi** |
| Gerçek `t.me/s` sayfası | **Denenmedi** (geliştirme ortamından Telegram kapalıydı). Test sayfası Telegram'ın bilinen yapısına göre yazıldı |
| Telegram Bot API'ye gerçek istek | **Denenmedi** |
| Claude API (Yeniden oluştur) | **Denenmedi**, isteğe bağlı |

## Dosyalar

```
tgwatch/                 → zip'i İSTEDİĞİN klasöre aç (public_html içi ya da dışı)
  public/                → web panel (index.php) ve Telegram webhook (bot.php)
  config.sample.php      → config.php olarak kopyala ve doldur
  cron.php               → her dakika cron
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
   şifreyi iki kez yaz → `admin_password_hash`, `webhook_secret` ve `webhook_url` satırları üretilir.
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

### 7. Cron
DirectAdmin → **Cron Jobs** → her dakika. Komut **Sağlık → Kurulum bilgileri**'nde tam yoluyla yazar:
```
* * * * * PHP_YOLU /TAM/YOL/tgwatch/cron.php >/dev/null 2>&1
```
PHP_YOLU hosting'e göre değişir: `/usr/local/bin/php`, `/usr/local/php81/bin/php`, `/opt/alt/php81/usr/bin/php`…
Cron'un kullandığı PHP sürümü web'dekinden farklı olabilir, 8.1+ olduğundan emin ol.
Doğru çalıştığını **Özet** sayfasındaki "Cron son çalışma" satırından görürsün. Dakikalık izin yoksa `*/5 * * * *`.

Normal çalışmada kanal ilk kez okunduğunda **eski gönderiler işlenmez**, sadece sonrakiler işlenir.

## Kullanım

- **✅ Onayla** → import paketi bota gelir (mesaj + `.txt`). Aynı iş iki kez yayınlanmaz.
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
