# Kurulum — extract et, çalışsın

Bu zip **public_html içine açılacak** şekilde hazırlandı.
İçindeki `popup/` klasörü doğrudan yerine oturur, hiçbir dosyayı yeniden
adlandırman ya da taşıman gerekmez.

```
public_html/
└── popup/          ← zip'ten çıkan klasör tam buraya
```

`.htaccess` dosyaları da hazır ve doğru isimle içinde.

---

## A) Mevcut kurulumu güncelliyorsan  (senin durumun)

1. Zip'i `public_html/` içine yükle ve **extract** et → mevcut dosyaların üzerine yazılır
2. Panele gir → sağ üstteki **"Şimdi güncelle"** butonuna bas

Bitti.

### Neden ayarların kaybolmaz

Bu pakette **kasıtlı olarak bulunmayan** dosyalar:

`settings.json` · `stats.json` · `versions.json` · `popup-cron.log` ·
`.admin-throttle.json` · `embed.js` · `services.json` · `popup-config.php` ·
`kill.json` · `audit.json` · `settings-history.json` · `auto-last.json`

Bunlar senin verilerin. Pakette olmadıkları için extract sırasında
**üzerlerine yazılamaz.** Ayarların, istatistiklerin ve API anahtarın olduğu gibi kalır.

`embed.js` de pakette yok — bu yüzden yükleme sırasında sitendeki popup
kesintiye girmez; "Şimdi güncelle" dediğinde yeni sürümüyle yeniden üretilir.

Eski ayar dosyan (v2) otomatik olarak yeni şemaya (v3) taşınır:
mevcut otomatik popup'ın `autos[]` listesine geçer, manuel popupların korunur.
Hiçbir şey kaybolmaz.

---

## B) Sıfırdan kurulum

1. Zip'i `public_html/` içine aç
2. `popup/popup-config.example.php` dosyasını **`public_html`'in bir üstüne** kopyala
   ve adını `popup-config.php` yap. (Güvenlik için webroot dışında olmalı.)
   İçini doldur:
   - `api_url` ve `api_key` → panel API bilgilerin
   - `cron_token` → uzun rastgele bir anahtar
     (üretmek için: `php -r "echo bin2hex(random_bytes(24));"`)
3. `siten.com/popup/admin/` adresine gir → şifreni belirle →
   sana verilen `'admin_pass' => '...'` satırını `popup-config.php` içine yapıştır
4. Cron kur (15 dakikada bir):
   ```
   php /home/KULLANICI/domains/SITEN/public_html/popup/popup-cron.php
   ```
   CLI cron çalışmazsa HTTP ile:
   ```
   wget -q -O /dev/null "https://siten.com/popup/cron-run.php?key=CRON_TOKENIN"
   ```
5. Sitenin şablonuna tek satır ekle:
   ```html
   <script src="https://siten.com/popup/embed.js" async></script>
   ```

`settings.json` şart değil — yoksa sistem varsayılan bir otomatik popup ile çalışır
ve panelden ilk kaydettiğinde dosya kendiliğinden oluşur. Hazır bir başlangıç
dosyası istersen `settings.ornek.json`'un adını `settings.json` yap.

---

## İzinler

Extract sonrası izinler bozulduysa:

- Klasörler: **755**
- Dosyalar: **644**

`popup/` klasörü **yazılabilir** olmalı — sistem `settings.json`, `stats.json`,
`embed.js` gibi dosyaları buraya yazar.

---

## .htaccess görünmüyorsa

Bazı dosya yöneticileri nokta ile başlayan dosyaları gizler ya da açmaz.
Kontrol et: `popup/.htaccess` ve `popup/admin/.htaccess` yerinde mi?

Yoksa aynı içerik yedek olarak duruyor:
- `popup/htaccess-yedek.txt`        → `popup/.htaccess` olarak kopyala
- `popup/admin/htaccess-yedek.txt`  → `popup/admin/.htaccess` olarak kopyala

Bu dosyalar `settings.json`, log ve istatistik dosyalarının dışarıdan
okunmasını engeller — kurulu olduklarından emin ol.

---

## Kurulumdan sonra

- `cron-test.php` sadece cron çalışıyor mu testi içindir, silebilirsin.
- Güvenlik: `popup-config.php` içine kendi `cron_token`'ını yaz
  (`cron-run.php` içindeki varsayılan anahtar kaynak kodda yazılı olduğu için güvenli değil).
- PHP 8.x öneririm — 7.4 güvenlik yaması almıyor.

Panelin nasıl kullanılacağı ve tüm özellikler için: **KURULUM.md**
