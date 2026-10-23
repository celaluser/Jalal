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
`kill.json` · `audit.json` · `settings-history.json` · `auto-last.json` · `coupons.json`

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

---

## Kendi popup tasarımını yapmak

Panel → **Şablonlar** → bir yerleşim ya da renk şablonu seç → **Kullan**.
Açılan formda:

- **İçerik** sekmesi: başlık, açıklama, buton, kupon, geri sayım, ikon, liste.
- **Stil** sekmesi: yerleşim (alt panel, yan panel, şerit, çark, kazıma kartı…), renk şablonu ve
  **Özel tasarım** anahtarı. Anahtarı açınca renkler, ölçüler, kenarlık, gölge, hizalama, karartma,
  animasyon ve istersen kendi CSS'ini yazabilirsin. Üstteki **canlı önizleme** kaydetmeden gösterir.
- Özel tasarım **kapalıysa** seçili şablonun hazır ayarları kullanılır.

Güncelleme sonrası panelde **"Şimdi güncelle"** butonuna bas.

---

## Yeni: kupon limiti, kişiye özel metin, kopyalama, şablonlarım, bot filtresi

- **Kupon limiti:** manuel popup → İçerik → "Kupon limiti (sunucuda)". Açıkken kod sayfa kaynağında görünmez,
  `coupon.php` hak kazanınca verir. Toplam kullanım ve "kişi başı 1 kez" ayarlanır. Çarkta stok her satıra yazılır:
  `Etiket | KOD | stok`. Popup listesinde "limit: 37/100 verildi" görünür, "sıfırla" ile sayaç sıfırlanır.
  `coupon.php` için `.htaccess` kuralı pakettedir; `.htaccess` dosyasını da üzerine yazmayı unutma.
- **Kişiye özel metin:** başlık, açıklama, rozet ve buton yazısında `{ad}`, `{bakiye}`, `{siparis}`.
  Veri yoksa yedek: `{ad|Dostum}`. Veri, sayfadaki `PANEL_USER` değişkeninden okunur.
- **Kopyala:** Popuplar sekmesinde her popup'ın yanında. Kopya kapalı gelir.
- **Şablonlarım:** Popuplar → bir manuel popup'ta "Şablon" butonu → Şablonlar sekmesinde "Şablonlarım".
- **Bot filtresi:** Gösterim kuralları'nda açık/kapalı. Botlara popup gösterilmez ve istatistiğe yazılmaz.

---

## Panel düzeni, Düşük bakiye sekmesi, popup'a özel istatistik

- **Düşük bakiye** artık ayrı sekme: aç/kapa anahtarı, bakiye eşiği, günlük/oturum/aralık sınırı, 11 hazır tasarım,
  metin ve tüm renk/ölçü ayarları ve üstte canlı önizleme. Bakiye seçicisi **Gösterim kuralları → Segment verisi**'nde.
- **İstatistik → Popup'a özel istatistik:** bir popup seç; gösterim, tıklama, günlük grafik, cihaz, kaynak, sayfa,
  kapanma süresi ve popup içi eylemler (çark, kazıma, kupon al) görünür. Cihaz/kaynak/sayfa ayrıntısı
  güncellemeden sonraki gösterimler için toplanır.
- **Popuplar** sekmesinde önce otomatik ve manuel liste, sonra katman sırası gelir.

---

## F5 sorunu ve popup istatistik modalı

- Panelde bir işlem yaptıktan sonra sayfa yönlendiriliyor; F5'e basınca artık "formu yeniden gönder" sorusu çıkmaz,
  işlem tekrarlanmaz. Sonuç mesajı bir kez gösterilir.
- Popuplar listesinde her popup'ın yanındaki **İstatistik** butonu (ve Düşük bakiye sekmesindeki buton) o popup'ın
  istatistiğini pencerede açar. Pencerenin üstünden 7 gün / 30 gün / 3 ay / 6 ay / 1 yıl / tümü seçilir.

---

## Sabit adres

Panel adresi her zaman `.../popup/admin/` olarak kalır. İşlem sonrası ve istatistik filtrelerinde adreste
`?tab=...&range=...` gibi ayrıntılar görünmez; seçimler oturumda tutulur. Son açık sekme F5'te korunur.
Eski `?tab=` bağlantıları da çalışır, sade adrese yönlenir.

---

## Aktif popuplar sekmesi

Soldaki menüde **Aktif popuplar (N)**: sadece açık olan popuplar, sitede gösterim sırasıyla. Her satırda
"Yayında / Zaman dışı / Süresi doldu" durumu, son 7 günün gösterim-tıklama sayısı, İstatistik, Düzenle ve Durdur
butonları vardır. Durdurunca popup listeden çıkar. Ana şalter ya da acil durdurma açıksa üstte uyarı görünür.

---

## Arşiv

Popuplar sekmesinde her popup'ın yanındaki **Arşivle** butonu popup'ı yayından kaldırır ve **Arşiv** bölümüne taşır.
Arşivdeki popuplar Popuplar, Aktif popuplar ve Katman sırası listelerinde görünmez, sitede gösterilmez; istatistiği
yine açılabilir. Arşiv bölümünden **Geri al ve yayınla**, **Geri al (kapalı)** ya da kalıcı **Sil** yapılır.
