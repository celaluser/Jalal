# Envato Hazırlık Planı (PulsePop · Hub · QR Menu)

Öncelik sırası: P0 = yayını engeller, P1 = reddedilme/şikâyet riski, P2 = kalite/temizlik.

## A. PulsePop 3.27.0

| # | Öncelik | İş | Yapılacak |
|---|---|---|---|
| A1 | P0 | Lisans çelişkisi | `config/pulsepop.php`: `required => true` ve `enforce => 'lock'` ile yorumlar ("varsayılan notify, kimseyle iletişime geçmez") çelişiyor. Karar ver: `required=false` + `enforce='notify'` (Envato'ya en güvenli) ya da kodu/yorumu/dokümanı bilinçli lock davranışına göre hizala. |
| A2 | P0 | Hub'a bağlanmayan paket kurulamıyor | `config/hub.php` seal boş. Reviewer'ın satın alma kodu yoksa kurulum yok. Kodsuz demo yolunu (`/hub/demo`) installer'a bağla ya da lisans adımını opsiyonel yap. Paketlemeden önce `pulsepop:connect-hub` adımını yayın kontrol listesine ekle. |
| A3 | P0 | clean pakette minify JS, kaynak yok | `admin.js` / `builder.js` / `sdk` için kaynakları pakete ekle (veya full'ü yükle). clean README'sindeki olmayan `docs/` ve `node ...build.mjs` atıflarını düzelt. |
| A4 | P1 | README eski | "What ships in 2.4.0" → 3.27.0; "vendor/ pakette yok, Composer çalıştır" ifadesini düzelt (vendor var). |
| A5 | P1 | Kurulum belgesi Azerice | `KURULUM.txt` → İngilizce `INSTALL.txt` (isteğe bağlı olarak Azerice kalsın). |
| A6 | P2 | `composer test` çalışmıyor | `vendor/` dev'siz geliyor, `Tests\` autoload yok. Test için `composer install` (dev dahil) adımını docs/TESTING.md'ye yaz. |
| A7 | P2 | Hub dokümanı/uç tutarlılığı | EXTERNAL-CONNECTIONS ve LICENSING.md metinlerini A1 kararına göre güncelle. |
| A8 | P2 | `$guarded = []` modelleri | Controller'larda mass-assignment girdisinin validasyonlu olduğunu tek tek doğrula. |
| A9 | P2 | PHPUnit'siz kalan 6 senaryo | Gerçek ortamda koştur: forms, journeys, sell, emailflows, invoices, identity. |

## B. PulsePop Hub 3.1.1 (satılmaz, kendi altyapın)

| # | Öncelik | İş | Yapılacak |
|---|---|---|---|
| B1 | P0 | Tek hata noktası | `/hub/monitor/{token}` ucunu bir uptime servisine bağla; yedekleme ve dönüş planı yaz. |
| B2 | P1 | Kapsam şişmesi | Lisans uçlarını (`/hub/*`) mağaza/affiliate/bülten/ticket kodundan ayır (ayrı uygulama ya da ayrı alan adı). |
| B3 | P2 | Google Fonts | Console ve mağaza layout'larından Google Fonts `<link>`'lerini kaldır, yazı tipini yerel sun. |
| B4 | P2 | Sürüm tutarsızlığı | `pulsepop-hub/CHANGELOG.md` en üstü 3.1.0 → 3.1.1 hizala. |
| B5 | P2 | Hub senaryo testi | PHPUnit ile `tests/Scenarios/hub.php` tam koştur. |

## C. QR Menu 1.0.0

| # | Öncelik | İş | Yapılacak |
|---|---|---|---|
| C1 | P0 | Lisans varsayılanı `format` | `LICENSE_DRIVER=format` herhangi bir UUID'yi kabul ediyor. Karar: `server` modu + kendi sunucun, ya da bilinçli olarak lisanssız yayın. `.env.example` ve docs'u kararla hizala. |
| C2 | P0 | Testler pakette yok | `FEATURE_STATUS.md` "548 test" diyor ama `tests/` yok. Testleri pakete ekle ya da iddiayı kaldır; ekleyince gerçekten koştur. |
| C3 | P1 | Paketten çıkarılacak dosyalar | `documentation/CODECANYON.md` (ilan metni) ve `FEATURE_STATUS.md` içindeki iç "Phase..." jargonu pakette olmamalı. |
| C4 | P1 | `.env.example` | `APP_ENV=production`, `APP_DEBUG=false` yap; tekrar eden `UPDATER_PUBLIC_KEY` satırını sil. |
| C5 | P2 | Üçüncü taraf lisans listesi eski | `php artisan docs:licenses` yeniden çalıştır (Livewire notu yanlış). |
| C6 | P2 | Bağımlılık sürümleri `*` | `composer.json`'da `*` olan paketlere sürüm aralığı ver. |
| C7 | P2 | Paket boyutu | `vendor/` 101 MB: kullanılmayan AWS SDK parçalarını (S3 isteğe bağlıysa) buda. |
| C8 | P2 | robots.txt / sitemap.xml | Kurulumdan hemen sonra açık gelsin (varsayılan ayar). |

## D. Ortak (yayın öncesi)

1. Envato'nun güncel kurallarını kontrol et: lisans doğrulama ve "sunucu erişilemezse kilit" davranışı, İngilizce doküman, kaynak kod şartı.
2. Final paketleri yeniden zip'le, **temiz dizinde** kurup gez (installer → panel → örnek veri).
3. Her üründe son kontrol: gizli dosya (`.env`, log, veritabanı) yok, sürüm numaraları tutarlı.

## Önerilen sıra

1. Kararlar: A1, A2, C1 (lisans stratejisi). Tüm diğer işler bunlara bağlı.
2. Paket içeriği: A3, C2, C3, C4.
3. Doküman: A4, A5, A7, C5.
4. Altyapı/test: B1, B2, A6, A9, B5.
5. Cila: kalan P2'ler.

---

## Durum (15 Ekim 2026)

Paketler `dist/` klasöründe (git'e eklenmez): `PulsePop-3.27.1.zip`, `PulsePop-Hub-3.1.2.zip`, `qr-menu-1.0.1.zip`,
`QR-Menu-listing-material.md` (QR Menu ilan metni, paketten çıkarıldı).

**Yapıldı:** A1 A2 A3 A4 A5 A6 A7 A8 · B1 B2 B3 B4 · C1 C3 C4 C5 C6 C8 (C8: sorun yokmuş, `php -S` kaynaklı yanlış alarm; C7: SDK zaten budanmış, değişiklik gerekmedi).
**Yapılamadı / senin elinde:** A9 ve B5 (PHPUnit bu ortamda kurulamadı), C2 (QR Menu testleri bu paketlerde yok; kendi deponuzdan eklemelisiniz),
D1 (Envato kurallarının güncel halini kendin kontrol et).

### Güncelleme (madde 10–15)

- 10 Envato kuralları: kural sayfalarına bu ortamdan erişilemedi (ağ engeli); doğrulanmadı, CHECKLIST.md'ye yazıldı.
- 11 Yasal: PulsePop `LICENSE.txt` §4 düzeltildi (paketlenen bağımlılıklar, dompdf LGPL, yazı tipleri); QR Menu için OFL yazı tipi lisansı eklendi.
- 12 Güvenlik: `composer audit` üç üründe temiz; QR Menu'ye CSRF/XSS/SQLi/brute-force/fiyat manipülasyonu probları temiz.
- 13 Performans: yerel ölçüm yapıldı (QR Menu menü sayfası ~55 ms, 21–36 sorgu); gerçek sunucu yükü ölçülmedi.
- 14 Çeviriler: QR Menu 12 dilde %100 anahtar tamam, PulsePop 15 dilde tam; kalite örneklemesi iyi, native kontrol yok.
- 15 Gönderim: `dist/submission/` altında ilan taslakları, ekran görüntüleri ve kontrol listesi.
- Düzeltilen gerçek hata: QR Menu misafir menüsünde bilgi kartı arama çubuğunun altında kalıyordu.

### Güncelleme ("yapabildiklerini yap" turu, 16 Ekim)

Yapıldı ve doğrulandı:
- MariaDB 10.11 kuruldu; **QR Menu** gerçek veritabanında kuruldu, 147 sayfa tarandı (5xx yok), sipariş ve idempotency denendi.
- **PulsePop'un 36 senaryosu** MariaDB'de geçti (6 eksik senaryo dahil; PHPUnit 9.6 sınıfları apt ile eklendi). **Hub** senaryosu 463 kontrolle MariaDB ve sqlite'ta geçti.
- Bulunan ve düzeltilen gerçek hatalar: PulsePop yedekleme (çok veritabanlı MySQL hesabı), QR Menu kurulum (aynı neden), QR Menu web-cron'da kayıtsız komutlar
  (haftalık özet, win-back, A/B kazanan), QR Menu sipariş panosu kontrastı, PulsePop panel/builder kontrast ve ARIA, QR Menu'de eksik `print-bridge.php`.
- İki PulsePop test satırı sqlite'ta boşa geçiyordu (var olmayan sütun adı); düzeltildi (`identity`, `customers`).
- Güncelleme paketi akışı (imza, kurcalama, tekrar) QR Menu'de uçtan uca denendi.
- `$guarded = []` taraması: ham istekten yazma yok; takım yönetimi uçları yalnızca sahibe açık.
- Dokümanlardaki eski sayılar düzeltildi (PulsePop README), bozuk bağlantı yok.

Hâlâ yapılamayanlar: Envato kural sayfaları, gerçek ödeme/e-posta/SMS, MySQL 8 ve gerçek barındırma, PHP 8.2/8.4, tarayıcı çeşitliliği (Safari/Firefox),
resmi PHPUnit 12 kurulumu, QR Menu otomatik testleri, native çeviri kontrolü, hukuki inceleme, Hub'ın canlıya alınması.

### Güncelleme (antivirüs / zararlı-site algısı kontrolü, 17 Ekim)

`dist/submission/SECURITY-SCAN-REPORT.md` ve `dist/submission/tools/av/` içinde. Özet: YARA web kabuğu imzaları (PulsePop, QR Menu, Hub, `vendor/` dahil) son paketlerde 0 eşleşme;
reklam engelleyici listeleri PulsePop'un 25 ve QR Menu'nün 15 adresini engellemiyor; ziyaretçi JS'inde madencilik/obfuscation/gizli iframe yok. Düzeltilenler: `vendor/` içinden
Windows ikilisi ve `.bat`/`.sh`/test dosyaları, iki yanlış alarm (test dosyasındaki webshell benzeri metin, SecurityHeaders ara katmanı). Yapılamayan: ClamAV imza DB'si, VirusTotal,
Google Safe Browsing/SmartScreen (alan adı değerlendirmesi), hosting tarayıcıları, Envato'nun kendi tarayıcısı.

### Güncelleme (rakip listesindeki eksiklerin kapatılması, 18 Ekim) — QR Menu 1.1.0

Yapıldı ve doğrulandı:
- **Japonca** eklendi (14 dil, 3717 anahtarın tamamı; yer tutucu ve çoğul sözdizimi otomatik doğrulandı, makine destekli — native kontrol yok).
- **Reklam ve canlı sohbet** ayarı: Google AdSense (+ `/ads.txt`) ve Tawk.to. Yalnızca herkese açık tanıtım sitesinde ve çerez onayından sonra yüklenir; panellerde ve restoran menülerinde yüklenmez. Tarayıcıda denendi (onaydan önce istek yok, sonra iki betik de yükleniyor); CSP ana makineleri yalnızca ayar doluyken ekleniyor.
- **Ödeme geçitleri ek ücretli eklenti olarak**: Telr, PayTabs, PayU (PayUmoney), CCAvenue, Paytm, 2Checkout. Çekirdek değişmedi; `examples/gateway-addon` şablonu pakette. `dist/addons/*.zip` (imzasız; satmak için kendi anahtarınla `package:build --addon=<slug>`). Her eklentinin `tests/verify.php` dosyası simüle yanıtlarla geçti (7–11 kontrol); **canlı/sandbox hesapla denenmedi**.
- Gerçek hatalar: `config/version.php` paketlerde **boştu** (eklentiler "1.0.0 gerekli" diyerek açılmıyordu) → 1.1.0 yazıldı; pakette `.env` (ortak APP_KEY, APP_ENV=local), test görselleri ve eski bootstrap önbelleği vardı → çıkarıldı; çerez bannerında bir betik yüklenince diğerinin yüklenmesi atlanıyordu → düzeltildi; admin ayar rotasında bölüm beyaz listesi yeni bölümü engelliyordu → eklendi.
- Doğrulama: temiz kurulum MariaDB'de (demo veriyle), 510 sayfa (en/ja/ar, restoran sahibi) + 316 sayfa (süper yönetici) 5xx yok; YARA son pakette ve eklentilerde 0 eşleşme.

Paketler: `dist/qr-menu-1.1.0.zip` (eski 1.0.1 silindi), `dist/addons/`.
Bilinen sınırlar: 2Checkout IPN yanıt biçimi en belirsiz kısım; Paytm/PayU/CCAvenue/Paytabs/Telr canlı denenmedi; Japonca çeviri native kontrol bekliyor; çeviri sözlüğü (degisken) özelliği henüz yapılmadı (kullanıcı kodlama adımı istedi).

### Güncelleme (ödeme eklentileri Hub'da, 20 Ekim) — QR Menu 1.2.0, Hub 3.2.0

- **Hub 3.2.0**: `php artisan hub:addon-products` altı eklentiyi (kapalı, yer tutucu fiyatlı, 1 site/koltuk) lisanslı ürün olarak açar. Yeni uç nokta gerekmedi; mevcut `activate`/`update` ürün anahtarıyla çalışıyor. Hub senaryosu 463 → 469 denetim, hepsi geçti.
- **QR Menu 1.2.0**: *Admin → Eklentiler → Eklenti mağazası* (isteğe bağlı; `ADDON_STORE_URL` boşken görünmez, hiçbir yere bağlanmaz). Satın alma kodu girilir → imzalı paket indirilir (Hub imzası + paket imzası), kurulur, açılır; günlük `addons:store-update` yeni sürümleri kurar. Eklenti kaldırılınca koltuk Hub'a geri verilir.
- Gerçek Hub süreciyle uçtan uca denendi: kod kullanımı, güncelleme 1.0.0→1.0.1, yanlış anahtarla imzalı paket reddi, ikinci siteye red, koltuğun iadesi, tarayıcıdan arayüz.
- Yapılması gereken (yazar): gerçek paket imza anahtarı üret (`package:keys`), 6 paketi imzala, Hub'da Konsol → Güncellemeler'e yükle, fiyat belirle, ürünleri aç; QR Menu `.env.example`/config'e Hub adresi ve genel anahtarları yaz (şu an boş).

### Güncelleme (e-posta ile 2FA + ikinci admin paneli, 20 Ekim) — QR Menu 1.3.0, PulsePop 3.27.1 (aynı sürüm, henüz yayımlanmadı)

- **E-posta ile 2FA** (QR Menu ve PulsePop): 6 haneli rastgele kod (`random_int`), tek kullanım, 10 dk, tarayıcı oturumuna bağlı, yalnızca anahtarlı hash saklanır; kod başına 5 yanlış, hesap başına 15 dk'da 10 yanlış → 15 dk kilit; dakikada 1 / saatte 5 kod; açarken posta kutusu kanıtlanır, kurtarma kodu üretilir; kapatmak parola ister. Yarım oturum 10 dk sonra ve 8 yanlışta biter (eskiden bitmiyordu). QR: `app:reset-two-factor` komutu. Testler: QR 19 servis denetimi + tarayıcıda uçtan uca; PulsePop yeni senaryo `emailtwofactor.php` 25 denetim (sqlite+MariaDB), komşu senaryolar geçti, SPA arayüzü tarayıcıda denendi. PulsePop yönetim paketi terser ile yeniden derlendi (+4 KB).
- **İkinci panel tasarımı (üst sekmeler)** QR Menu'de: Ayarlar → Genel → Panel tasarımı (varsayılan) + hesap menüsünden kişisel seçim (çerez). Yeni dashboard (4 istatistik kartı + trend + mini çubuk, 7/30/90 gün çizgi grafik, son restoranlar, aranabilir/sıralanabilir/filtrelenebilir/CSV dışa aktarılabilir tablo). Açık/koyu, masaüstü/telefon tarayıcıda denendi; 296 sayfa × iki tasarım × (süper yönetici, restoran sahibi) 5xx yok. İç sayfalar (liste/form) eski görünümünü yeni çerçevede korur.
- Not: tasarım isteğindeki turuncu rozet beyaz yazıyla kontrast ölçütünü geçmez; koyu yazı kullanıldı.

### Güncelleme (74 sayfa incelemesi, masaüstü + mobil, 20 Ekim) — QR Menu 1.3.1

Her sayfa 1440 px ve 390 px'te açık/koyu modda gözden geçirildi. Bulunan ve düzeltilenler: aktif filtre çiplerinde koyu üstüne koyu yazı (global `a{color:inherit}` Tailwind katmanlı sınıfları eziyordu); ek sekme açıkken son sekmenin kesilmesi (sığmayanlar artık *More*'a taşınıyor); telefonda sayfanın fazla geniş çizilip uzaklaştırılması (tablodaki gizli `sr-only` etiketi kaydırma kutusunun dışına taşıyordu); telefonda üst sekmeler yana kayıyor, *More* sayfa olarak açılıyor; iç sayfaların turuncu düğmeleri yeni mavi vurguyla uyumlu; kat planı telefonda masaları üst üste yığıyordu (haritaya yatay kaydırma). Doğrulama: 296 sayfa × süper yönetici/restoran sahibi, 5xx yok; YARA 0.

- QR Menu 1.3.2: top-tabs panel design is now the default (cookie/platform setting still switch back). dist/qr-menu-1.3.2.zip.

### Güncelleme (Poptin karşılaştırması → 5 faz, Faz 1 tamam, 30 Ekim) — PulsePop 3.28.0

Rapor: `reports/poptin-karsilastirma.md` (72 eksik madde, 5 faza bölündü).
- Faz 1 — Vitrin ve deneme (TAMAM, 3.28.0): `/templates` herkese açık galeri (167 şablon, gerçek önizleme görselleri, "Canlı göster" popup'ı sayfada açar, demo modu), `/templates/{slug}` tekil sayfa + paylaşım, `/pp/try.js` "kendi sitende dene" (yer imi/konsol), `/try/{slug}` kayıtsız editör → kayıt → `/builder?import=guest` taslak aktarma, `/p/{token}` kampanya paylaşım linki (gerçek runtime, lead aynı listeye; panelde Campaigns → ⋯ → Share link…; API GET/PUT /campaigns/{id}/share; migration share_token), `/pricing` fiyat sayfası (karşılaştırma tablosu, SSS), sitemap + tema menüsüne "Templates" linki, canlı sayaçlar (conversions/visitors/leads). SDK değişmedi (16 KB iddiası korunuyor).
- Doğrulama: yeni senaryo `showcase.php` 43/43 (sqlite + MariaDB); 37 senaryonun hepsi geçti; lint/budget/version/i18n/security/inline-js/api/claims geçti; builder, admin, sdk testleri geçti; tarayıcıda uçtan uca (galeri, çark, try.js başka origin'de, kayıtsız editör→kayıt→aktarma, paylaşım linkinden lead kaydı, panel penceresi); temiz zip'ten sıfır kurulum; YARA 0.
- Sırada: Faz 2 (pazarlama sitesi: mega menü, ürün/sektör/entegrasyon sayfaları, yeni tema, yenilikler widget'ı, erişilebilirlik), Faz 3 (editör/form/tetikleyici/hedefleme/kupon), Faz 4 (Kişiler + E-posta), Faz 5 (ajans, entegrasyonlar, taşıma).
