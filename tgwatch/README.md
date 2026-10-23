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
| Gerçek `t.me/s` sayfası | **Denenmedi** (geliştirme ortamından Telegram kapalıydı). Test sayfası Telegram'ın bilinen yapısına göre yazıldı |
| Telegram Bot API, webhook | **Denenmedi** |
| Claude API (Yeniden oluştur) | **Denenmedi**, isteğe bağlı |

## Dosyalar

```
tgwatch/                 → /home/KULLANICI/tgwatch   (public_html DIŞINDA)
  config.sample.php      → config.php olarak kopyala ve doldur
  cron.php               → her dakika cron
  src/                   → kod
  tools/check.php        → kurulum kontrolü
  tools/make_hash.php    → panel şifresi hash'i / webhook gizli anahtarı
  tools/set_webhook.php  → webhook kur/kaldır/bilgi
  tests/run.php          → testler
  data/                  → veritabanı (otomatik oluşur)
  public/                → içeriğini public_html/tgadmin/ altına kopyala
    index.php            → web panel
    bot.php              → Telegram webhook
    app_path.php         → APP_DIR burada (tek düzenlenecek satır)
```

## Kurulum (DirectAdmin)

### 0. Eski token
Daha önce bir sohbete token yapıştırdıysan: BotFather → `/mybots` → bot → **API Token → Revoke current token**.
Token'ı, Claude anahtarını ve şifre hash'ini **yalnızca hosting'deki config.php'ye** yaz. Sohbete yazma.

### 1. Bot
1. BotFather → `/newbot` → token'ı al.
2. Kendi botuna `/start` yaz. Kurulum bitince bot sana ID'ni söyler (adım 6). Önce öğrenmek istersen
   tarayıcıda `https://api.telegram.org/bot<TOKEN>/getUpdates` aç, `"from":{"id":…}` senin ID'n. (Webhook kurulunca bu adres çalışmaz.)

### 2. Dosyalar
1. File Manager → `/home/KULLANICI/` → `tgwatch` klasörüne zip'i aç.
2. `tgwatch/public/` **içindeki** dosyaları `public_html/tgadmin/` klasörüne kopyala (`.htaccess` dahil).
3. `public_html/tgadmin/app_path.php` içinde `/home/KULLANICI/tgwatch` yolunu kendi kullanıcı adınla düzelt.

### 3. PHP
DirectAdmin'de **Select PHP Version** / **PHP Selector** (hosting'e göre adı değişir): **8.1+** seç,
`curl`, `mbstring`, `dom`, `pdo_sqlite` eklentilerini aç.

### 4. config.php
`config.sample.php` → `config.php` olarak kopyala, izinleri **600** yap. Doldur:
- `bot_token`, `owner_id`
- `admin_password_hash`: `php tools/make_hash.php` çıktısı
- Webhook modu (önerilen): `webhook_url` = `https://ALANADIN/tgadmin/bot.php`, `webhook_secret` = `php tools/make_hash.php --secret` çıktısı
- HTTPS yoksa ya da webhook çalışmazsa: `'bot_mode' => 'polling'` (butonlar cron aralığında işlenir)

**SSH yoksa** komutları geçici bir cron satırıyla çalıştırıp çıktıyı dosyaya yazdır, işin bitince satırı ve dosyayı sil:
```
/usr/local/bin/php /home/KULLANICI/tgwatch/tools/check.php > /home/KULLANICI/tgwatch/data/out.txt 2>&1
```
PHP CLI yolu hosting'e göre değişir: `/usr/local/bin/php`, `/usr/local/php81/bin/php`, `/opt/alt/php81/usr/bin/php`… Hangisi olduğunu
hosting'in cron sayfası ya da desteği söyler. `check.php` ilk satırda kullandığı PHP yolunu ve sürümünü yazar.
Şifreyi cron satırına `--password='...'` ile yazdıysan, hash'i aldıktan hemen sonra o satırı sil.

### 5. Kontrol
`php tools/check.php` → ✗ olan satırları düzelt. Çıktıyı paylaşacaksan önce gözden geçir (token yazmaz).

### 6. Webhook ve kanal
1. `php tools/set_webhook.php` (polling modunda gerekmez).
2. Bota `/start` yaz: "✅ tgwatch hazır" gelmeli.
3. Panel `https://ALANADIN/tgadmin/` → **Kanallar** → kanal ekle → **Test**. Ya da `php cron.php --add-channel=kanaladi`.

### 7. Cron
DirectAdmin → **Cron Jobs** → her dakika:
```
* * * * * /usr/local/bin/php /home/KULLANICI/tgwatch/cron.php >/dev/null 2>&1
```
Dakikalık izin yoksa `*/5 * * * *`.

### 8. İlk deneme
```
php cron.php --test          # okur ve çevirir, hiçbir şey kaydetmez/göndermez
php cron.php --backfill=3    # her kanalın son 3 gönderisini gerçekten işler → bota onay mesajı gelir
```
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

- `tgwatch/` ve `data/` public_html dışında. Yanlışlıkla web'e açılırsa `.htaccess` erişimi engeller.
- Panel: şifre (Argon2id/bcrypt hash), aynı IP'den 5 yanlış denemede 15 dk kilit, CSRF, sıkı CSP, 2 saat hareketsizlikte oturum kapanır.
- Webhook: `X-Telegram-Bot-Api-Secret-Token` doğrulanır.
- `config.php` git'e girmez (`.gitignore`).
