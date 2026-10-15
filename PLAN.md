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
