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

### Güncelleme (Faz 2 tamam, 30 Ekim) — PulsePop 3.29.0

- Faz 2 — Pazarlama sitesi (TAMAM, 3.29.0): tüm açık sayfalarda mega menü (Product / Use cases / Templates / Pricing / Resources, telefonda burger) + footer sütunları; `/features` + 12 özellik sayfası (örnek şablonlar sayfada canlı); `/use-cases` + 10 sektör sayfası; `/integrations` dizini (paneldeki katalogdan + platformlar ve AI site kurucular, kurulum adımları, varsa eklenti indirme); `/compare` ("Why us", rakip adı yok); `/contact` demo/iletişim formu (honeypot, süre ve hız sınırı, yeni sistem e-postası `contact_request`); "What's new" paneli (updates etiketli blog yazıları); erişilebilirlik çubuğu; sohbet widget'ı (Crisp/Tawk/Tidio/WhatsApp, id doğrulanır, CSP'ye eklenir); dil seçici (`pp_lang` çerezi); yeni koyu tema **Spotlight**; panelde Public site → Pages & widgets (her sayfa/widget aç-kapa, kapalı sayfa 404 ve menü/sitemap'ten çıkar). Türkçe çeviri %100.
- Düzeltme: blog, galeri ve fiyat sayfası `noindex` başlığıyla gidiyordu ve analitik CSP'ye takılıyordu; artık indekslenebilir.
- Doğrulama: yeni senaryo `sitepages.php` 35/35; 39 senaryonun hepsi geçti (1271 denetim, sqlite), sitepages/showcase/seo MariaDB'de geçti; tüm lint'ler + budget + version + sdk/builder/admin/site testleri geçti; Spotlight için contrast/long-text/mobile-nav geçti; temiz zip'ten kurulum, yeni 13 adres 200; YARA 0. `tools/test-fresh-install.sh` artık Laravel router'ı ile çalışıyor (eskiden /robots.txt rotasına ulaşamıyordu).
- Sırada: Faz 3 (editör, form, tetikleyici, hedefleme, kupon), Faz 4 (Kişiler + E-posta), Faz 5 (ajans, entegrasyonlar, taşıma).

### Güncelleme (Faz 3 tamam, 30 Ekim) — PulsePop 3.30.0

- Faz 3 — Tetikleyici, hedefleme, kupon, form, editör (TAMAM, 3.30.0):
  - Tetikleyiciler: Autopilot (çıkış niyeti / %70 kaydırma / 45 sn — hangisi önce), tıklama sayısı, sayfa sayısı (derleyicide `session_pages` kuralına çevrilir), sepete ekleme (Shopify/Woo/Magento/PrestaShop/OpenCart butonları, tıklamayı engellemeden).
  - Hedefleme: sayfa başlığı, sayfada metin, sayfada öğe (CSS seçici), başka kampanyayı görmüş/kapatmış/dönüşmüş.
  - Kampanya sınırları (sunucuda): toplam gösterim / kayıt sınırı, IP ve CIDR hariç tutma. IP listesi tarayıcıya gitmez, yanıt `private` olur.
  - Kuponlar: panelde oluşturma/düzenleme/silme, CSV ile kod yükleme, bitiş tarihi, dağıtılan kodları CSV indirme, "kullanılan" ve gelir sütunları (siparişlerden). Kod siparişte kullanılınca artık işaretleniyor (önceden hiç işaretlenmiyordu). Kişiler sayfasında kupon segmenti (almış / kullanmış / almış ama kullanmamış). Shopify'da gerçek indirim kodu oluşturma zaten vardı.
  - Formlar: uluslararası telefon alanı (yeni `x/fields.js` parçası, 1,9 KB), canlı e-posta kontrolü (yazım önerisi + MX), 4 Evet/Hayır şablonu (toplam 171 şablon).
  - Editör: marka kiti (logo, 4 renk, gövde ve başlık yazı tipi; tek tıkla uygula), 28 Google Fonts ailesi müşterinin kendi sunucusundan sunuluyor (GDPR), kayıtlı bloklar ("Blok olarak kaydet" → "Bloklarım").
  - Çekirdek SDK: çark, paylaşım ve öneri istekleri kendi parçalarına taşındı; derleme ayarı `wrap_func_args:false` sabitlendi. Çekirdek 3.29.0'dan 484 bayt küçük (gzip 15 890 bayt; "16 KB" iddiası korunuyor).
- Doğrulama:
  - Yeni senaryo `phase3.php` 36/36. 40 senaryonun hepsi geçti (1307 kontrol, sqlite). phase3, sitepages, showcase ve seo MariaDB'de de geçti.
  - Tüm lint'ler, budget, version, claims, contrast/long-text/mobile-nav geçti. sdk/builder/admin/site testleri geçti (yeni SDK testleri: tıklama sayısı, sepete ekleme, başlık/öğe/metin kuralları, kampanya etkileşimi, telefon alanı). 171 şablon tarayıcıda temiz.
  - Temiz zip'ten kurulum çalıştı; YARA 0.
- Sırada: Faz 4 (Kişiler/Audience + E-posta pazarlama), Faz 5 (ajans alt hesapları, entegrasyonlar, taşıma).

- Faz 4 — Kişiler (Audience) + E-posta pazarlama + dinamik sayılar (TAMAM, 3.31.0):
  - Dinamik sayılar: sitedeki tüm sayılar (şablon, tetikleyici, hedefleme kuralı, operatör, blok, oyun, dil, entegrasyon, runtime KB) koddan okunuyor. Açık sayfalarda `data-live` ile her istekte, statik sayfalarda derlemede; panel de aynı kaynaktan (`facts`). Eski "12 triggers" / "37 rule subjects" yazıları düzeldi. `lint-claims` artık README ve dokümantasyonu da denetliyor.
  - Kişiler: e-posta başına tek kişi (tüm form ve siteler), etiketler, özel alanlar, zaman çizelgesi (kayıt + UTM, kupon, sipariş, e-posta açma/tıklama), toplu işlem, CSV içe/dışa aktarma, GDPR silme. Mevcut lead'ler migrate'te kişiye dönüşüyor.
  - Segmentler: AND grupları / OR, 20+ konu (etiket, alanlar, sipariş, gelir, kupon, e-posta açma/tıklama, ziyaret edilen sayfa...), canlı sayım, 8 hazır segment.
  - E-posta kampanyaları: blok editör (8 blok tipi) + canlı önizleme, 21 şablon, segment + hariç segment, zamanlama, A/B konu testi (kazanan kalan listeye), rapor. `email_flows` özelliği ve aylık e-posta kotasıyla.
  - Otomasyon: akış kayıtta veya etiket eklenince başlıyor; adımlar e-posta / etiket / push; her adıma segment kurallarıyla koşul; 4 hazır akış.
  - Tercih merkezi (`/pp/prefs/{id}/{token}`): konu seçimi, abonelikten çıkma + neden; tek tıkla List-Unsubscribe başlığı.
  - Teslim edilebilirlik: SPF/DKIM/DMARC/MX kontrolü + önerilen DMARC kaydı, konular, e-posta logu.
  - Panel düzeltmesi: bir sayfadan açılan diyalog artık sayfa değişince kapanıyor (önceden bir sonraki sayfanın üstünde kalıyordu).
- Doğrulama:
  - Yeni senaryo `audience.php` 54/54. 41 senaryonun hepsi geçti (1373 kontrol, sqlite). audience, phase3, sitepages, seo ve emailflows MariaDB'de de geçti.
  - Tüm lint'ler (api, security, columns, wiring, i18n, translations, claims, version, budget...), sdk/builder/admin/site testleri, contrast/long-text/mobile-nav ve 171 şablon geçti. Nebula temasındaki "16 KB" kontrastı düzeltildi.
  - 373 yeni metin Türkçeye çevrildi. Temiz zip'ten kurulum çalıştı; YARA 0.
- Sırada: Faz 5 (ajans alt hesapları, entegrasyonlar, taşıma).

- Faz 5 — Ajans, entegrasyonlar, taşıma (TAMAM, 3.32.0):
  - Ajans müşteri hesapları: her müşteri için ayrı hesap (`organizations.parent_id`); üst çubukta hesap değiştirici, "müşteri hesabındasınız" çubuğu, geçişte eski panel token'ı silinir. Ajans üyesi kendi rolüyle çalışır; müşterinin kendi kullanıcıları yalnızca kendi hesabını görür. Hesap başına saat dilimi ve tarih biçimi. Duraklatma, isim yazarak kapatma, müşteriler genelinde rapor + CSV. Plan özelliği `agency` + `sub_accounts` limiti; müşteri hesabı ajansın planını kullanır, içinde satın alma/kod/fatura profili engelli. Tek site kurulumunda da çalışır. Token her istekte "bu müşteri hâlâ bu ajansın mı" diye kontrol edilir.
  - 12 yeni entegrasyon: Pipedrive, Zoho CRM, Freshsales, Kit, AWeber, Moosend, Mailjet, SendGrid, SendPulse, Customer.io, Sendlane, Slack (toplam 28). OAuth token'ları kendiliğinden yenilenir. Meta/TikTok pixel'e kayıt olayları (SDK 15,6 KB, "16 KB" iddiası korunuyor).
  - Taşıma: kampanyaları JSON olarak dışa/içe aktarma (taslak olarak; kupon havuzları taşınmaz), ajans için "müşteriye kopyala". Açık sitede `/migrate` sayfası, 9 yeni platform rehberi.
  - Entegrasyon sayısı artık katalogdan (paneldeki liste) sayılıyor; README'deki eski "14" düzeldi ve lint-claims bunu da denetliyor.
- Düzeltme: 3.31.0 paketine MariaDB test betiği yanlış argümanla çalıştırıldığı için `tests/Scenarios/bootstrap.php` ve `isolation.php` MariaDB'ye göre değiştirilmiş hâlde girmişti. 3.31.0 zip'i düzeltilip yeniden gönderildi, betik artık kopyalayamazsa duruyor.
- Doğrulama:
  - Yeni senaryolar: agency 42/42, integrations32 32/32, transfer 17/17. 44 senaryonun hepsi geçti (1467 kontrol, sqlite). agency, transfer, integrations32, audience, sitepages, saas MariaDB'de de geçti.
  - Tüm lint'ler, sdk/builder/admin/site testleri (yeni pixel testleri dahil), contrast/long-text/mobile-nav, 171 şablon geçti. Telefonda yatay taşma: hesap değiştirici telefonda gizlendi (Client accounts sayfasından açılıyor).
  - 138 yeni metin Türkçeye çevrildi. Temiz zip'ten kurulum çalıştı; YARA 0.
- Beş fazın hepsi tamam. Sonraki adım önerisi: diğer 15 dilin yeni metinleri (şu an İngilizce gösteriliyor) ve gerçek sağlayıcı hesaplarıyla canlı entegrasyon denemesi.

- 3.32.1 — Diğer diller + çeviri hataları (TAMAM):
  - 3.28–3.32'de eklenen 1326 metin kalan 14 dile çevrildi (ar, az, bn, de, es, fr, hi, id, it, pt_BR, ru, ur, vi, zh_CN). Her satırda yer tutucular (`:name`, `{code}`, `**`) ve sondaki boşluklar otomatik denetlendi. Çeviriler ana dili konuşan biri tarafından gözden geçirilmedi (CHANGELOG ve docs/LANGUAGES.md'de belirtildi).
  - Bulunan ve düzeltilen eski hatalar:
    - Arapça/Urduca açık sayfalarda (`/pricing`, `/migrate`, galeri, özellikler, karşılaştırma, blog…) `dir="rtl"` yoktu; eklendi.
    - Fiyat kartlarındaki limitler ("5 sites", "Unlimited popups"…) çeviri dosyalarına hiç girmemişti, Türkçe dahil her dilde İngilizce kalıyordu. Artık tam cümle olarak çevrilebilir.
    - Plan özellik kataloğundaki 58 ad/açıklama da hiç çıkarılmamıştı; çıkarıcı artık katalogu okuyor ve 15 dile çevrildi.
    - Fiyat sayfasındaki özellik listesi dilden bağımsız önbellekleniyordu (ilk gelen dil herkese gösteriliyordu); artık dil başına.
    - "Free" fiyatı Türkçe/Azerice "Boşalt", Almanca "Freigeben", Rusça "Освободить" çıkıyordu; düzeltildi.
  - Panel demo'sundaki Diller ekranı gerçek sayıları gösteriyor (hepsi %100) + satın alanın eklediği yarım bir dil örneği.
- Doğrulama: 44 senaryo / 1467 kontrol / 0 hata (sqlite). Tüm lint'ler, sdk/builder/admin/site testleri, contrast/long-text/mobile-nav/readability geçti. de/ru/ar/ur/vi/hi açık sayfalarda telefonda yatay taşma yok. YARA 0; temiz zip'ten kurulum çalıştı.

- Faz 6 — Rapordan kalan yarım maddeler (TAMAM, 3.33.0):
  - Ziyaretçi bazlı plan: yeni limit `visitors` (ayda tekil ziyaretçi, tarayıcı başına ayda bir sayım); dolunca popuplar ayın 1'ine/yükseltmeye kadar durur, hiçbir şey silinmez. Plan & usage, kodlar, fiyat kartları. Fiyat sayfasında "Ayda kaç ziyaretçi?" kaydırıcısı (en ucuz yeten planı işaretler; ziyaretçi limiti yoksa aylık gösterime göre çalışır).
  - 7 yeni entegrasyon (toplam 35): Keap, Agile CRM, Nutshell, Benchmark Email, Intercom, Crisp, Drift. Dizinde yeni "Canlı sohbet ve destek" grubu.
  - Fatura/hesap e-postaları sahibe ek olarak 5 adrese daha gidebiliyor. Shopify sipariş webhook'u müşteri etiketlerini Kişiler'e ekliyor.
  - Düzeltilen eski hatalar: Shopify/WooCommerce'te sipariş sayısı, toplam harcama ve müşteri etiketi hedefleme kuralları hiç veri almıyordu (identify artık besliyor; snippet/eklenti harcama ve etiketleri de gönderiyor). Ziyaretçinin ilk kaynak/UTM/ilk görülme tarihi her ziyarette eziliyordu. Fiyat kartında popup başına limit "ayda" diye yanlış yazıyordu. Plan & usage etiketleri, entegrasyon dizini başlıkları ve entegrasyon alan adları hiç çevrilmiyordu.
  - 89 yeni metin 15 dile çevrildi.
- Doğrulama: 47 senaryo / 1538 kontrol / 0 hata (sqlite); visitors, storedata, integrations33, sitelive MariaDB'de de geçti. Tüm lint'ler, 12 tarayıcı testi (yeni: mağaza müşteri verisi → hedefleme) geçti. YARA 0, temiz kurulum çalıştı.
- Orijinal 26 maddelik planın durumu: 23 tam, 3 yarım (ekran görüntüsü üstüne bindirme, e-posta şablonu 21/30+, sürükle-bırak otomasyon tuvali).

- Faz 7 — Yarım kalanlar + plan dışı + hatalar (TAMAM, 3.34.0):
  - Yarım kalan 3 madde bitti:
    - Görsel otomasyon tuvali: E-posta akışlarında "Harita" görünümü (başlangıç/son düğümleri, bekleme süreleri bağlantılarda, koşullu adım "Eğer" dalı, sürükle-bırak sıralama, bağlantıdaki + ile adım ekleme).
    - "Kendi sitende gör": şablon sayfasında site adresi yazılınca site çerçevede açılıp popup üstüne biniyor. Adres önce denetleniyor (sunucunun kendi ağı asla, yönlendirmeler tek tek), çerçeve sandbox'lı, frame-src yalnızca o sayfada o adrese açılıyor; çerçevelemeyi reddeden sitelere neden + yer imi.
    - E-posta şablonları 21 → 40 (SaaS ve bağış kategorileri yeni).
  - Plan dışı: /help yardım merkezi (bölümler, arama, 8 başlangıç makalesi — yalnızca makale yoksa), /customers müşteri hikâyeleri (hazır hikâye yok, ilk gerçek hikâye yayınlanınca görünür), 11 yeni entegrasyon (toplam 46): EmailOctopus, beehiiv, Sender, Loops, Close, Copper, Insightly, Airtable, Discord, Microsoft Teams, Telegram.
  - Düzeltilen hatalar: sayılar her dilde İngilizce ayırıcıyla yazılıyordu (artık dile göre); 148 hazır şablon adı çevrilmiyordu; sitemap yardım/hikâye yazılarını /blog/ altında veriyordu; geniş ekranda footer tek dar sütuna sıkışıyordu (tüm açık sayfalar); "kendi sitende gör" sayfasının ilk CSS kuralı düşüyordu ve adres kutusu stilsizdi; paket içindeki documentation/index.html 3.33.0'da güncellenmemişti; sohbet uygulamaları dizinde "imzalı istek" diye anlatılıyordu.
  - 329 yeni metin 15 dile çevrildi (16 dil %100, 6262 metin). Ana dili konuşan biri tarafından gözden geçirilmedi.
- Doğrulama: 50 senaryo / 1629 kontrol / 0 hata (sqlite); helpcenter, integrations34, onsite MariaDB'de de geçti. Tüm lint'ler, 12 tarayıcı testi geçti. YARA 0 (7976 dosya). Temiz zip'ten kurulum + migrate/seed; /help, /help/{slug}, /integrations/telegram 200, hikâye yokken /customers 404.
- Orijinal 26 maddelik plan: 26/26 tam. Bilinen kalan: Almanca gibi uzun dillerde 1440 px'te üst menü iki satıra kayıyor (3.34'te değişmedi); entegrasyonlar gerçek sağlayıcı hesaplarıyla canlı denenmedi; "kendi sitende gör" gerçek dış siteyle denenemedi (ortamda dış ağ kapalı, testler sahte yanıtlarla).

- 3.34.1 — Kurulumda "500 Server Error" (kullanıcı bildirdi, DÜZELDİ):
  - Neden: paket web köküne bir klasör olarak açılınca (alanadi.com/klasor/ veya zip'in kendi PulsePop-3.34.x/pulsepop/ yapısı) Laravel klasör adını sayfa adresi sanıyordu; sayfa yakalayıcısı kurulumdan önce olmayan veritabanına sorgu atıp 500 veriyordu. Testlerimiz hep kök dizinde ve dışarıdan APP_KEY verilerek yapıldığı için yakalanmamıştı.
  - Düzeltmeler: public/index.php alt klasörü temel adres olarak tanıyor; yeni RequireInstallation ara katmanı kurulumdan önce her adresi kuruluma yönlendiriyor (API'ye 503 JSON); kurulumdan önce çıkan hata artık genel "Server Error" yerine nedeni, dosyayı, PHP sürümünü ve olası çözümleri gösteriyor.
  - Aynı denemede bulunan ikinci hata: mod_headers kapalı Apache'de sıkıştırılmış .js.br dosyası başlıksız gidiyor, panel boş kalıyor ve popup betiği yüklenmiyordu. Artık sıkıştırılmış dosya yalnızca mod_headers varken veriliyor. Kök dizin kurulumunda adres çubuğuna sızan /public/ da düzeldi.
  - Doğrulama: Apache 2.4 + PHP 8.3 üzerinde 4 yerleşim (alt klasör, zip'in iç içe klasörü, uygulama web kökünde, kök public/'e yönlendirilmiş) — her birinde kurulum ekranı, sihirbaz baştan sona, giriş, panel, site sayfaları, sdk.js; mod_headers açık ve kapalı. tools/test-fresh-install.sh artık dokunulmamış bir kopyayı da (anahtar verilmeden) deniyor. 50 senaryo / 1629 kontrol geçti, lint'ler ve sdk/admin testleri geçti, YARA 0.

- 3.35.0 — Alt klasör tam desteği + çıkış + ana sayfa menüsü (kullanıcı bildirdi, DÜZELDİ):
  - Alt klasör (alanadi.com/klasor/): panel ve editördeki çıkış kök adrese (/logout) gidiyordu → 404, oturum açık kalıyordu, eski anahtar çalışıyordu. Popup kodu klasörü atlıyordu (müşteri sitelerinde hiçbir şey yüklenmezdi). Ana sayfadaki Sign in / Get started ve operatörün "/..." bağlantıları domain köküne gidiyordu. Panelde robots/sitemap/llms, blog/yardım/hikâye bağlantıları, ödeme bağlantıları, site-studio bağlantıları, PWA manifest/worker/kapsam ve push test worker'ı da kökü varsayıyordu. Hepsi düzeldi (pp_base_path/pp_prefix_path, panelde appBase()). sdk.js doğrudan eklendiğinde de klasörü buluyor.
  - Ana sayfa menüsü: temalar kendi menüsünü (sayfa içi birkaç bağlantı) gösteriyordu, diğer sayfalar mega menüyü — her sayfada farklı başlık. Artık ana sayfada da aynı ortak menü (SitePages::sharedNav), temanın renkleri ve açık/koyu düğmesiyle; 14 temada masaüstü+telefon denendi.
  - Mega menü ikonları kutunun sol üst köşesine kaymış ve rengini kaybetmişti (.mm-link span kuralı ikon kutusunu da etkiliyordu). Fareyle açılan menüye tıklayınca menü kapanıyordu. İkisi de düzeldi.
  - Bulunan ek hatalar: standalone.html'de popup betiği String.replace'teki "$&" yüzünden sözdizimi hatasıyla ölüydü; kılavuz "vendor/ indirmede yok, Composer gerekli" diyordu (paket içeriyor), satın alma kodu istendiğini söylüyordu ve iki adım "4" numaralıydı.
  - Doğrulama: yeni senaryo subfolder.php 15/15; sitepages menünün /pricing ile birebir aynı olduğunu denetliyor. 51 senaryo / 1647 kontrol 0 hata (sqlite), subfolder/sitepages/seo MariaDB'de de geçti. Tüm lint'ler ve 12 tarayıcı testi geçti (kontrast testi bir kez animasyon zamanlamasıyla düştü, tekrarında geçti; spotlight.html 3.34.1 ile aynı). YARA 0 (7980 dosya). Temiz kurulum testi geçti. Apache'de zip olduğu gibi alt klasöre açılıp (mod_headers kapalı) kurulum, 3 çıkış yolu, 48 panel ekranı, editör, 70 public sayfa (kök adrese kaçan bağlantı yok) ve başka bir siteden popup yüklemesi denendi.

- Faz 9 — Kalan yarım maddeler + bilinen boşluklar (TAMAM, 3.36.0):
  - E-posta editörü: bloklar sürükle-bırak ile sıralanıyor; "Blok olarak kaydet" / "Bloklarım" (popup oluşturucu ile ortak kütüphane, iki yönlü dönüştürme; karşı tarafın gösteremediği parçalar atlanıp sayısı söyleniyor). Şablonlar 40 → 60 (yerel işletme ve eğitim kategorileri yeni).
  - Kişi kartı zaman çizelgesinde PulsePop.track() olayları; segmentlerde "olayı yaptı / yapmadı" ve "kaç kez" kuralları.
  - Ekip üyesini bazı sitelerle sınırlama (Ayarlar → Ekip → Siteler): kampanya, kayıt, kişi, kupon, akış, push aboneleri — panel, API ve dışa aktarmada. Sahip/admin hep tüm siteler; erişim değişince oturum kapanıyor.
  - /developers + /developers.json (334 uç nokta rotalardan okunuyor, yetkisiyle), /features/accessibility, ProveSource entegrasyonu (toplam 47).
  - Ana sayfa temalarının kendi metinleri ziyaretçinin dilinde (operatör metni hep önde; Arapça/Urduca metin RTL işaretli, tema düzeni bozulmadan). Yardım merkezinin 8 başlangıç makalesi 16 dilde (düzenlenene kadar; veritabanına yazılmıyor).
  - Uzun dillerde üst menü taşması: önce sıkışıyor, sığmazsa telefon menüsüne geçiyor (16 dil × 5 genişlikte tek satır ölçüldü).
  - Bulunan eski hatalar: kişi zaman çizelgesinde "ambiguous column" SQL hatası; kök .htaccess tüm .xml'leri engellediği için klasör web kökü olunca /sitemap.xml 403 veriyordu; classic tema "PHP 8.2" diyordu (gereksinim 8.3).
  - 379 yeni metin 15 dile çevrildi (16 dil %100, 6642 metin). Ana dili konuşan biri tarafından gözden geçirilmedi.
- Doğrulama: 56 senaryo / 1709 kontrol / 0 hata (sqlite); yeni 5 senaryo + audience/helpcenter MariaDB'de de geçti. Tüm lint'ler ve 12 tarayıcı testi geçti. YARA 0 (8008 dosya). Temiz kurulum testi geçti. Apache'de zip alt klasöre açılıp sihirbazla kuruldu; yeni sayfalar, de/fr/ar yardım, çıkış ve sitemap denendi.
- Bilinen kalan: çevirilerin ana dil kontrolü; ana sayfada canlı sayı içeren birkaç metin (ör. "45 blocks · 6 games") İngilizce kalıyor; entegrasyonlar gerçek sağlayıcı hesaplarıyla denenmedi.

- 3.36.1 — Güvenlik ve hata taraması (kullanıcı istedi: "script genel tara, bug'ları kapat, hacker saldıramasın"):
  - Güvenlik: paylaşım linkinde (/p/...) müşterinin özel HTML'i kurulumun kendi adresinde betik çalıştırabiliyordu (SaaS'ta operatöre karşı XSS; tarayıcıda kanıtlandı) → sayfaya nonce'lu sıkı CSP. Başka cihazdan çıkış ve şifre sıfırlama diğer tarayıcının web oturumunu kapatmıyordu → oturum kaydı silinince panel o tarayıcıyı atıyor. SVG temizleyicide ad alanlı script vb. açıklar kapatıldı. Şifremi unuttum/sıfırla/2FA formlarına IP limiti, sıfırlama mesajı hesap varlığını sızdırmıyor. API yanıtlarında no-store/nosniff/DENY. Site sınırı entegrasyon ve webhook'lara da uygulandı. Güncelleyici uygulama dışına yazamıyor.
  - Yeni: Ayarlar → Güvenlik → Şifreyi değiştir (diğer cihazlar çıkış yapar).
  - Kullanıcı hataları: telefonda panel 40–220 px yana taşıyordu; Site studio canlı önizlemesinde popup/yazı tipi yüklenmiyordu (her kurulumda); video bloğunda normal YouTube/Vimeo linkleri açılmıyordu; Arapça/Urduca iletişim sayfası 9999 px genişti; şifremi unuttum mesajı çevrilmiyordu; Almanca içerik sekmeleri telefonda taşıyordu.
  - Tarama: 289 public sayfa × 5 ayar (en/de/ar masaüstü, tr/ru telefon) ve 48 panel ekranı × 2 boyut × 3 dil; düzeltmeler sonrası temiz.
  - Doğrulama: 57 senaryo / 1738 kontrol 0 hata (sqlite), güvenlik senaryoları MariaDB'de de; 16 lint, 12 JS test paketi geçti; YARA 0 (8010 dosya); temiz kurulum + Apache alt klasör kurulumu.
