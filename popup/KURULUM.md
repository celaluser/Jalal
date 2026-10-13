# Popup Sistemi — Tam Paket (sürüm 3)

Çoklu popup mimarisi, çoklu otomatik popup, segmentasyon, acil durdurma,
ayar yedekleme, değişiklik günlüğü ve genişletilmiş istatistik.

---

## 1) Yükleme

### Mevcut kurulumu güncelliyorsan

`popup/` içindeki **tüm .php ve .js dosyalarını** sunucudaki `public_html/popup/`
klasörüne **üzerine yaz**.

**Bu dosyalara DOKUNMA** (sunucudakiler kalsın):
`settings.json` · `stats.json` · `versions.json` · `popup-cron.log` ·
`.admin-throttle.json` · `popup-config.php` · `.htaccess` dosyaların

> `settings.ornek.json`'u yükleme — o sadece sıfırdan kurulum içindir.
> `embed.js` ve `services.json` pakette yok; cron üretir, site kesintiye girmez.

Yükledikten sonra panele gir → **"Şimdi güncelle"**.

**Ayarların otomatik taşınır.** Sistem eski ayar dosyanı görünce şema v3'e çevirir:
mevcut otomatik popup'ın `autos[]` listesine "Otomatik servis popup" adıyla taşınır,
manuel popupların olduğu gibi korunur. Hiçbir şey kaybolmaz, davranış aynı kalır.

### Sıfırdan kurulum

1. `popup/` klasörünü `public_html/popup/` olarak yükle
2. `popup-config.example.php`'yi **public_html dışına** koy, adını `popup-config.php` yap,
   `api_url` + `api_key` doldur, `cron_token`'a uzun rastgele bir anahtar yaz
3. `settings.ornek.json` → adını `settings.json` yap
4. `htaccess-FINAL.txt` → `.htaccess` · `admin/htaccess-admin.txt` → `admin/.htaccess`
5. Klasör izni 755, dosya izni 644
6. `siten.com/popup/admin/` → şifreni belirle → verilen satırı `popup-config.php`'ye yapıştır
7. Cron (15 dk): `php /yol/public_html/popup/popup-cron.php`
8. Siteye: `<script src="https://siten.com/popup/embed.js" async></script>`

---

## 2) Panel

### Popuplar sekmesi
İki kısa liste: **otomatik servis popupları** ve **manuel popuplar**.
Düzenleme artık sayfayı uzatmıyor — **açılır pencerede (modal)** yapılıyor.
Modal içinde sekmeler var: Genel · Servis&fiyat / İçerik · Zamanlama · Hedefleme ·
Segment · Kurallar.

### YENİ: Çoklu otomatik popup
Artık kategori başına ayrı otomatik popup kurabilirsin — ör. biri TikTok, biri Instagram.
Her birinin kendi kategorisi, servis sayısı, fiyat çarpanı, metinleri, zamanlaması ve
segmenti olur. **API yine tek sefer çağrılır**, kaç tane olursa olsun.

### YENİ: Segmentasyon
Popup başına: giriş durumu · bakiye aralığı · sipariş sayısı · yeni/geri dönen ziyaretçi ·
en az kaçıncı ziyaret · ülke.

Bunun çalışması için panelin sayfaya kullanıcı bilgisi vermesi gerekir. Sitenin
`<head>` kısmına ekle:

```html
<script>
window.PANEL_USER = {
  loggedIn: true,   // giriş yapmış mı
  balance: 12.50,   // bakiye
  orders: 34,       // toplam sipariş
  country: "AZ"     // opsiyonel
};
</script>
```

Alternatif: `<body data-igfp-user='{"loggedIn":1,"balance":12.5}'>`

> Bilgi yoksa ve popup'ta bakiye/sipariş/giriş kuralı varsa popup **gösterilmez**
> (güvenli taraf). Ziyaret sayısı ve yeni/geri dönen bilgisi panel verisi olmadan da çalışır.

### YENİ: Acil durdurma (Durum sekmesi)
Kırmızı **ACİL DURDUR** düğmesi tüm popupları **anında** durdurur — `embed.js`'in
5 dakikalık önbelleğini beklemeden. Ayarların ve popupların silinmez.

Bunu mümkün kılan şey: `embed.js` her sayfa açılışında `data.php?state=1` adresinden
önbelleklenmeyen küçük bir durum sorgusu yapar. Sorgu başarısız olursa popuplar
**çalışmaya devam eder** (fail-open) — yani bu kontrol asla sistemi kilitlemez.
İstemezsen "Gösterim kuralları → Acil durdurma kontrolü"nden kapatabilirsin.

### YENİ: Yedek & günlük sekmesi
- **Ayar yedekleri** — her kayıttan önce otomatik yedek (son 20). Tek tıkla geri al.
- **Değişiklik günlüğü** — kim ne zaman neyi değiştirdi (son 40 işlem).

### YENİ: Genişletilmiş istatistik
Popup bazında · **cihaz** (mobil/masaüstü) · **trafik kaynağı** · **sayfa bazında** ·
**tekil ziyaretçi** · **ortalama açık kalma süresi** ve **hemen kapatma oranı**.

### Diğer (önceki sürümden)
Kuyruk (sırayla gösterim) · tetikleyiciler (açılış / çıkış niyeti / kaydırma) ·
cihaz+kaynak hedefleme · saat-gün-tarih zamanlaması · geri sayım sayacı · kupon kodu ·
popup başına sıklık ve sayfa kuralları.

---

## 3) Bilinmesi gerekenler

- **API çökerse otomatik popuplar artık kaybolmuyor.** Son başarılı veri
  `auto-last.json`'da saklanır ve API erişilemezken o kullanılır; kayıt defterinde
  "UYARI(eski veri)" olarak görünür. API dönünce kendiliğinden tazelenir.
- Zamanlama, tetikleyici, hedefleme, segment ve geri sayım **tarayıcıda** hesaplanır;
  saatlik bir kural için cron'un tekrar çalışmasına gerek yok.
- Sıklık sayaçları popup bazında, ziyaretçinin tarayıcısında tutulur. İstatistikte
  IP veya kişisel veri saklanmaz.
- `cron-test.php` sadece kurulum testi içindir, sonra silebilirsin.

## 4) Yeni dosyalar

Sistem çalışırken şunları kendisi oluşturur (yedeklemende bunlar da olsun):
`kill.json` (acil durdurma durumu) · `settings-history.json` (ayar yedekleri) ·
`audit.json` (değişiklik günlüğü) · `auto-last.json` (son başarılı API verisi)

## 5) Hâlâ yapılmayanlar (istersen sonra)

- Gerçek dönüşüm/gelir takibi (sipariş sayfasına piksel)
- Fiyat düşüşü / yeni servis otomatik kampanyası
- A/B test + otomatik kazanan
- Sticky bar formatı, çok dilli içerik
- Güvenlik: `cron-run.php` içindeki varsayılan token'ı `popup-config.php`'de
  kendi `cron_token`'ınla ez · PHP'yi 8.x'e yükselt
