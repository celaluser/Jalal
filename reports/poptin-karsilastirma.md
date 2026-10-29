# Poptin ve PulsePop 3.27.1: derin karşılaştırma raporu

**Tarih:** 29.10.2026

**Nasıl incelendi:**
- poptin.com'un 23 sayfası Chromium ile açıldı; metinleri ve ekran görüntüleri alındı. Sayfalar: ana sayfa, Popups, Gallery, Features, Pricing, Forms, Coupons, Agencies, Integrations, Segments, Properties, Website Tracking, Automations, Campaigns, Email Builder, Migration, Comparisons, API, Ecommerce, A/B testing, Analytics, Forms gallery, Demo.
- PulsePop tarafı kaynak kod üzerinden, dosya dosya çıkarıldı.

**İncelenemeyen kısım:** `app.popt.in` bu ortamın ağ izninde yok.
- Galerideki **"Show"** düğmesi `app.popt.in/api/display/click/...` adresine gidiyor. Bu yüzden "kayıt olmadan popup'ı canlı görme" deneyiminin kendisini açıp deneyemedim. Bu konuda aşağıda yazdıklarım, sayfanın kodundan ve metinlerinden çıkardığım bilgiler.
- Kayıt ekranı ve panel de aynı adreste olduğu için Poptin'in editörünü içeriden göremedim.
- Tam inceleme için `app.popt.in` adresinin de izin listesine eklenmesi gerekiyor.

---

## 1. Kısa özet

Poptin artık yalnızca bir popup aracı değil. Kendini üç ayaklı bir pazarlama platformu olarak satıyor: **Grow** (popup ve formlar), **Audience** (kişiler ve segmentler) ve **Email** (kampanyalar ve otomasyon).

Popup motorunda PulsePop çoğu yerde **onlarla eşit, bazı yerlerde önde**: daha fazla oyun, daha fazla tetikleyici, AI, akıllı A/B, web push ve ürün önerileri.

Bizim asıl eksiğimiz üç alanda:

1. **Vitrin ve "deneme hissi".** Herkese açık şablon galerisi, popup'ı tıklayınca canlı gösterme, paylaşılabilir popup ve form linki, çok sayfalı ve çok dilli pazarlama sitesi, mega menü, kullanım alanı sayfaları, entegrasyon dizini.
2. **Audience ve Email ayakları.** Kişi profili ve zaman çizelgesi, etiket ve segmentler, toplu e-posta kampanyası, sürükle-bırak e-posta editörü, görsel otomasyon akışı.
3. **Ajans yapısı.** Alt hesaplar, tek tıkla hesap değiştirme, müşteri kullanıcıları.

---

## 2. Poptin'in site tasarımı: o "şık" hissi nereden geliyor?

Ekran görüntüleri sohbette ayrıca gönderildi (repoya konmadı, Poptin içeriği).

### Hero
- Koyu **orman videosu** arka planda (jungle.mp4).
- Ortada büyük, kalın, beyaz başlık: "Grow Revenue With Pop Ups & Email Marketing Automation".
- Üstte küçük bir rozet: "250K+ marketers…" ve yanında üç avatar.
- Tek CTA: papağan ikonlu, parlayan kenarlı **"Start Your Journey"** düğmesi.

### Hikâye anlatımı
Sayfa aşağı kaydırıldıkça metin değişiyor:
- "The marketing world can feel like a dark, uncharted jungle…"
- "Poptin lights the path…"

Bu sırada orman kararmış halden aydınlanmış hale geçiyor. Bu, scroll'a bağlı animasyonla yapılmış. Altta "TRUSTED BY 60,000+ BUSINESSES" başlığı ve kayan logo bandı var (Kia, Marriott, NBA, Philips…).

### Üç ayak kartları
- "What Poptin Can Do for You" başlığı pembe → mor → turkuaz **gradyan** yazıyla.
- Altında koyu zemin üzerinde üç kart: Grow / Audience / Email. Her kartta ürünün gerçek arayüzünden bir parça (popup kolajı, segment kuralları, otomasyon akışı) ve iki etiket düğmesi var.

### Canlı sayaçlar
"Big reach. Bigger impact." başlığı altında:
- 10B+ ziyaretçi
- 3B+ gösterim
- 90M+ dönüşüm
- 100M+ e-posta

### Açık bölümler
- Beyaz ve lila zemin; mor vurgu rengi `#7761DF`.
- Bölüm etiketleri küçük rozet şeklinde: GROW / AUDIENCE / EMAIL.
- Editörün kendisi sayfada **canlı gibi** duruyor: genişlik/yükseklik alanları, "On Submit" seçenekleri ve "Get 25% off your first order" popup'ı.

### Diğer bölümler
- **"Explore Our Poptins":** sekmeli bir vitrin (Lightbox, Floating bar, Full screen, Slide in, Gamified, Social widgets, Mobile, Email capture, Contact form). Sekme seçilince solda o türün örneği (Cyber Monday neon popup) gösteriliyor.
- **Kart ızgarası:** Targeting / Triggers / A/B. Her kartta küçük bir illüstrasyon var: tetikleyici ikonları, A/B anahtarları.
- **Entegrasyon logo duvarı:** renkli kare karolar.
- **Büyük yüzde rakamları:** +20%, 40X, 15%. Arka planda papağan fotoğrafı.
- **Diğerleri:** vaka çalışmaları kaydırıcısı, 18 yorum kartı, G2 / Capterra / Trustpilot rozetleri. Platform puanları: WordPress 4.9, Shopify 4.9, Wix 4.9…

### Mega menü (Product)
- Üç numaralı sütun: 1 Grow, 2 Audience, 3 Email. Her sütunda üç alt ürün var, her biri ikon + kısa açıklama ile.
- Her sütunun altında bir önizleme görseli.
- Sağda "Features" listesi ve "Book A Demo" kutusu.
- En altta gradyan bir çizgi.

### Diğer menüler
- **Use Cases:** 17 sektör sayfası (Ecommerce, SaaS, Food, Real estate, Medical, Education, Events, Travel…).
- **Resources:** Blog, Academy, Help Center, API docs, Case studies, Comparisons, Migration, What's new.

### Dil ve erişilebilirlik
- Site **40 dilde** (Türkçe dahil: `/tr/`).
- Sol altta **erişilebilirlik araç çubuğu** (OneTap): yazı boyutu, kontrast, okuma maskesi.

### Pricing sayfası
- Üstte "Emails & Poptins" / "Poptins" anahtarı ve **kişi sayısı seçici**: 251–500 … 200.000. Fiyat bu seçime göre değişiyor.
- Üç kart: Free $0, Growth $19/ay (500 kişi seviyesinde), Enterprise (Let's talk).
- Altında açılır kapanır **"Compare Our Plans"** tablosu ve SSS.

### "Kayıt olmadan popup ekliyorum" hissi
Bu his üç şeyden geliyor:
1. **Galeri:** 58 şablonun her birinde, üzerine gelince çıkan **"Show"** düğmesi var. Düğme popup'ı gerçek haliyle (Poptin'in kendi sunucusunda) açıyor.
2. **Features sayfası:** "Shareable Poptin Link: Preview and share live popups on a hosted page" diyor.
3. **Ana sayfa:** editör ekranı, sanki zaten içindeymişsin gibi gösteriliyor.

---

## 3. Özellik karşılaştırma tablosu

Durum işaretleri:
- ✅ = bizde var
- 🟡 = kısmen var
- ❌ = bizde yok
- ➕ = bizde var, Poptin'de yok veya bizde daha güçlü

### 3.1 Popup türleri ve içerik
| Özellik | Poptin | PulsePop | Not |
|---|---|---|---|
| Lightbox, bar, slide-in, full screen, mobil | ✅ | ✅ | Bizde 16 format |
| Teaser (küçük ikon → popup) | ✅ | ✅ | `teaser` formatı ve launcher |
| Countdown, video, sosyal, CTA | ✅ | ✅ | |
| Yes/No formlar | ✅ | 🟡 | `choices` bloğuyla yapılabiliyor; hazır "Yes/No" şablonu yok |
| Oyunlar | 3 (çark, kazı, hediye seç) | ➕ 6 | Slot, zar ve bardak oyunu da var; ödül sunucuda çekiliyor |
| Ürün önerisi popup'ı | ✅ | ➕ | Bizde 7 öneri stratejisi |
| Anket / NPS / Quiz | anket | ➕ | NPS, emoji, quiz (sonuca göre kupon) |
| Web push | ❌ | ➕ | |
| Stokta yok / fiyat düşüşü uyarısı | ❌ | ➕ | |
| Çok adımlı ve dallanan popup | ? | ➕ | |

### 3.2 Tetikleyiciler
| Tetikleyici | Poptin | PulsePop |
|---|---|---|
| Exit intent, gecikme, scroll, hareketsizlik, tıklama | ✅ | ✅ |
| Sayfa sayısı | tetikleyici | 🟡 hedefleme kuralı olarak var (`page_views`) |
| **Tıklama sayısı (click count)** | ✅ | ❌ |
| **Autopilot (en iyi tetikleyiciyi kendisi seçer)** | ✅ | ❌ |
| JavaScript tetikleyici | ✅ | ✅ `PulsePop.show()` |
| Shopify sepet tetikleyici | ✅ | 🟡 sepet hedeflemesi var, "sepete eklenince" tetikleyicisi yok |
| Form terk, video izleme %, rage click, öğe görününce | ❌ | ➕ |

### 3.3 Hedefleme
| Kural | Poptin | PulsePop |
|---|---|---|
| Sayfa, cihaz, işletim sistemi/tarayıcı, ülke, trafik kaynağı, gün/saat, tarih, çerez, JS, adblock | ✅ | ✅ |
| **Sayfa başlığı (title tag)** | ✅ | ❌ (yalnızca merge tag olarak var) |
| **Kaynak kodunda X geçiyorsa** | ✅ | ❌ |
| **Diğer popup'larla etkileşim** ("şunu gördü / şuna kaydoldu ise") | ✅ | ❌ |
| **IP engelleme** | ✅ | ❌ (kampanya bazında yok; panelde genel IP ban var) |
| Toplam gösterim kapasitesi (view count capacity) | ✅ | 🟡 kişi başı sınır var, kampanyanın toplam gösterim sınırı yok |
| Önceki ziyaret edilen sayfalar | ✅ | ✅ `visited path` |
| Shopify: giriş yapmış mı, müşteri etiketi, sipariş geçmişi | ✅ | 🟡 genel `customer tag`, `order count`, LTV var; Shopify'dan otomatik besleme sınırlı |
| Şehir/bölge, saat dilimi, UTM, LTV, AOV, sepette ürün/kategori | kısmen | ➕ |

### 3.4 Editör ve tasarım
| Özellik | Poptin | PulsePop |
|---|---|---|
| Sürükle-bırak editör | ✅ | ✅ |
| **Marka kiti** (logo, renk paleti, font kaydedip tüm popup ve e-postalara uygulama) | ✅ | ❌ |
| **Google Fonts / marka fontu** | ✅ | ❌ (yalnızca 5 sistem fontu) |
| **Evrensel özel bloklar** (bir kez yap, popup ve e-postada tekrar kullan) | ✅ | 🟡 kampanyalar arası kopyala-yapıştır var, kayıtlı blok kütüphanesi yok |
| Teşekkür ekranı / formu gizle / yönlendir / otomatik kapat | ✅ | ✅ (adımlar ve aksiyonlarla) |
| Şablon sayısı | "60+" | ➕ 167 (34 kategori) |
| AI ile kampanya oluşturma, metin, çeviri | ❌ | ➕ |
| Yayın öncesi kontrol (Google cezası, kontrast, alt metin) | ❌ | ➕ |
| ADA / erişilebilir tasarım iddiası | ✅ | 🟡 kontrast kontrolü var, ayrıca "erişilebilirlik" pazarlaması yok |

### 3.5 Formlar
| Özellik | Poptin | PulsePop |
|---|---|---|
| Gelişmiş alanlar (radio, checkbox, dropdown…) | ✅ | ✅ |
| **Uluslararası telefon alanı** (ülke seçici ve otomatik algılama) | ✅ | ❌ (yalnızca düz `phone` input; doğrulanmalı) |
| **Gerçek zamanlı e-posta doğrulama** (MX / var mı) | ✅ | 🟡 tek kullanımlık e-posta listesi ve regex var, canlı MX kontrolü yok |
| **Paylaşılabilir form linki** (hosted form sayfası) | ✅ | ❌ |
| Gömülü (inline) form | ✅ | ✅ |
| Double opt-in ve onay kaydı | ✅ | ✅ |
| Spam koruması | ? | ➕ honeypot, Turnstile, reCAPTCHA, skor |
| Form alan analizi (drop-off) | ❌ | ➕ |

### 3.6 Kuponlar
| Özellik | Poptin | PulsePop |
|---|---|---|
| Sabit kod, tek kullanımlık kod | ✅ | ✅ |
| **Toplu kod yükleme (CSV)** | ✅ | 🟡 kod havuzu var; CSV yükleme ekranı doğrulanmalı |
| **Mağazada kupon oluşturma ve senkron** (Shopify/Woo'da gerçekten kod yaratma) | ✅ | 🟡 WooCommerce'te callback var, Shopify'da yok |
| **Kupon son kullanma tarihi** | ✅ | ❌ (doğrulanmalı) |
| **Kupon paneli** (kalan kod, kullanılan, gelir) | ✅ | 🟡 gelir eşleştirme var, ayrı bir panel yok |
| Kupon kullanımına göre segment | ✅ | ❌ |

### 3.7 Audience (kişiler)
| Özellik | Poptin | PulsePop |
|---|---|---|
| Lead listesi, CSV dışa aktarma | ✅ | ✅ |
| **Kişi profili ve aktivite zaman çizelgesi** (ilk görülme, son aktif, UTM, giriş/çıkış sayfası) | ✅ | ❌ |
| **İçe aktarma (import)** | ✅ | ❌ |
| **Etiketler (tags)** | ✅ | ❌ |
| **Özel özellikler (custom properties)** | ✅ | 🟡 `prop:` hedeflemede var, kişi kartında yönetimi yok |
| **Segment oluşturucu** ve hazır segmentler | ✅ | ❌ |
| **Tercihler / abonelik merkezi** | ✅ | 🟡 yalnızca abonelikten çıkma var |
| **Website tracking: özel olaylar** | ✅ | 🟡 `PulsePop.track()` var, ziyaretçi bazında arayüz yok |
| Lead skoru | ❌ | ➕ |

### 3.8 E-posta pazarlama
| Özellik | Poptin | PulsePop |
|---|---|---|
| Hoş geldin / otomatik yanıt | ✅ | ✅ |
| Çok adımlı drip akışı | ✅ | ✅ Email flows |
| **Sürükle-bırak e-posta editörü** ve 60+ e-posta şablonu | ✅ | ❌ |
| **Toplu kampanya (broadcast) gönderimi**, segmente gönder / gönderme | ✅ | ❌ |
| **Görsel otomasyon akışı ve koşullu dallanma (if/else)** | ✅ | 🟡 sıralı akış var, görsel ve koşullu değil |
| E-posta A/B testi | ✅ | ❌ |
| **DMARC oluşturucu ve alan adı doğrulama (SPF/DKIM)** | ✅ | ❌ |
| Toplu e-posta logları | ✅ | 🟡 |
| Abonelikten çıkma anketi | ✅ | ❌ |

### 3.9 Ajans / hesap
| Özellik | Poptin | PulsePop |
|---|---|---|
| Ekip üyeleri ve roller | ✅ | ✅ (5 rol + izinler) |
| **Alt hesaplar** (her müşteri için ayrı hesap, tek tıkla geçiş) | ✅ | ❌ |
| **Müşteriye kullanıcı açma** (yalnızca kendi hesabını görsün) | ✅ | 🟡 siteye göre yetki yok |
| Hesap başına saat dilimi, tarih formatı | ✅ | 🟡 kampanya saat dilimi var |
| Alt kullanıcı başına API anahtarı | ✅ | ✅ scoped token |
| Sistem e-postalarını birden fazla alıcıya göndermek | ✅ | 🟡 haftalık rapor için var |
| Otomatik raporlar | ✅ | ✅ |
| 2FA | ✅ | ✅ (TOTP, e-posta, passkey) |
| White label ve yeniden satış | ❌ | ➕ (bizim asıl avantajımız) |

### 3.10 Entegrasyonlar
| | Poptin | PulsePop |
|---|---|---|
| E-posta ve CRM | ~50 (Pipedrive, Zoho, Freshsales, Kit, AWeber, Moosend, Mailjet, SendGrid, Customer.io, Sendlane, SendPulse, Benchmark, Keap, Agile CRM, Nutshell…) | 16 |
| Platformlar | ~55 (Wix, BigCommerce, Ecwid, Webflow, Framer, Joomla, Drupal, Squarespace, Square, Thinkific, **Lovable, Replit, Bolt, Base44, Vercel**…) | WordPress/Woo, Shopify snippet, Magento, OpenCart, PrestaShop, ayrıca Wix/Squarespace anlatımı |
| Sohbet / diğer | Crisp, Drift, Intercom, Slack, ProveSource | ❌ |
| Zapier, Make, webhook | ✅ | ✅ (imzalı) |

Dikkat: Poptin "AI site kurucuları" (Lovable, Bolt, Replit, Base44) için ayrı sayfalar açmış. Bu yeni bir trend ve ucuz bir fırsat: çoğu sadece "snippet'i şuraya yapıştır" sayfası.

### 3.11 Pazarlama sitesi ve satış
| Özellik | Poptin | PulsePop |
|---|---|---|
| **Herkese açık şablon galerisi + "Show" (canlı gösterme)** | ✅ | ❌ |
| **Paylaşılabilir popup linki** (hosted sayfa) | ✅ | ❌ |
| Her ürün için ayrı sayfa (Popups, Forms, Coupons…) | ✅ | ❌ tek landing sayfası |
| **Mega menü** | ✅ | ❌ |
| **Kullanım alanı / sektör sayfaları** (17 adet) | ✅ | ❌ |
| **Ayrı Pricing sayfası**: kaydırıcı ve karşılaştırma tablosu | ✅ | 🟡 landing'de bölüm olarak var |
| **Entegrasyon dizini** ve her entegrasyon için ayrı sayfa | ✅ | ❌ |
| **Rakip karşılaştırma sayfaları** (44 adet) | ✅ | ❌ |
| Vaka çalışmaları, yorumlar, rozetler | ✅ | 🟡 site stüdyosunda elle eklenebilir |
| Canlı sayaçlar | ✅ | 🟡 şablon/dil sayıları var, gösterim/dönüşüm toplamı yok |
| Site dili | 40 | 🟡 panel 16 dil; landing çok dilli değil (doğrulanmalı) |
| Blog | ✅ | ✅ |
| Help center / Academy / API docs sitesi | ✅ | 🟡 çevrimdışı doküman var, herkese açık değil |
| "What's new" widget'ı (changelog) | ✅ | ❌ |
| Canlı sohbet desteği | ✅ (Crisp) | ❌ |
| Erişilebilirlik araç çubuğu | ✅ | ❌ |
| Book a demo | ✅ | ❌ |
| Hazır landing teması | 1 | ➕ 11 tema ve Site Studio |

### 3.12 Fiyatlandırma modeli
- **Poptin:**
  - Free: 1.000 ziyaretçi/ay, 1 alan adı, sınırsız popup, markalı.
  - Growth: $19'dan başlıyor; fiyat e-posta kişi sayısıyla artıyor; 5.000 ziyaretçi, sınırsız alan adı, markasız, alt hesap.
  - Enterprise: teklifle.
  - Ölçü birimi "ziyaretçi" ve "aktif kişi".
- **PulsePop varsayılanı:** Free / $29 / $99. Ölçü birimi "görüntülenme/gösterim".
- **Öneri:** Planlarda "ziyaretçi/ay" ölçüsünü ve "kişi sayısına göre fiyat" kaydırıcısını opsiyon olarak ekleyelim. Script'i satın alan SaaS sahibi kendi modelini seçebilsin.

---

## 4. Bizim güçlü olduğumuz yerler (satış sayfasında öne çıkaralım)

1. **Kendi sunucunda, tek seferlik lisans, white label ile yeniden satış.** Poptin aylık abonelik ve onların markası.
2. 6 oyun, ödülün sunucuda çekilmesi, stok ve olasılık kontrolü.
3. AI: tek cümleden kampanya; metin ve çeviri; 6 sağlayıcı.
4. Akıllı A/B: Thompson sampling ile trafiği kazanana kaydırma ve kazananı otomatik yayınlama.
5. Web push, stok/fiyat uyarısı, 7 stratejili ürün önerisi, kupondan gelir eşleştirme.
6. Fazladan tetikleyiciler: rage click, form terk, video izleme.
7. 167 şablon, 27+22 animasyon, yayın öncesi kontrol, `?ppdebug=1` hata ayıklayıcı.
8. Shadow DOM ve 0,5 KB yükleyici (hız).
9. KVKK/GDPR araç seti, 16 panel dili, RTL.

---

## 5. Önerilen geliştirme planı (öncelik sırası)

### Faz 1: "Vitrin ve deneme hissi" (en çok satış etkisi, 1–2 sürüm)
1. **Herkese açık Şablon Galerisi (`/templates`).**
   - Kategori çipleri, arama, format filtresi.
   - Her kartta **"Canlı göster"**: SDK, şablon JSON'unu önizleme modunda o sayfanın üstünde gerçekten açar; tetikleyici, animasyon ve ses dahil.
   - **"Bu şablonu kullan"**: şablon misafir taslağı olarak tarayıcıda saklanır. Kayıt sonrası editörde hazır açılır. Kayıt olmadan editörde düzenleme de olabilir (misafir modu), kaydetmek için kayıt istenir.
2. **"Kendi sitende gör".** Ziyaretçi kendi URL'sini yazar:
   - **(a)** Bir **bookmarklet** ya da konsola yapıştırılacak tek satır, popup'ı o sitede açar. Bu her sitede çalışır.
   - **(b)** Arka planda sitenin ekran görüntüsü alınıp popup üzerine bindirilir. Bu sunucuda headless tarayıcı ister, opsiyonel olsun.

   iframe ile yapmak çoğu sitede `X-Frame-Options` yüzünden olmaz.
3. **Paylaşılabilir popup ve form linki** (`/p/{kod}`). Popup veya form, markalı bir sayfada tek başına açılır. Sosyal medyada ve e-postada link olarak paylaşılır.
4. **Yeni pazarlama sitesi teması ("Jungle" benzeri, bizim kimliğimizle).** Bunlar mevcut Site Studio altyapısına yeni tema olarak eklenebilir:
   - Video veya animasyonlu koyu hero, scroll ile değişen hikâye metni.
   - Gradyan başlıklar, logo bandı.
   - Üç ayak kartları, canlı sayaçlar (gerçek DB toplamları).
   - Sekmeli "popup türleri" vitrini, editörün canlı görünümü.
   - Mega menü.
   - Ayrı sayfalar: Popups, Forms, Coupons, Gamification, Push, AI, Integrations, Pricing (kaydırıcı ve karşılaştırma tablosu), Use cases (sektör sayfaları, şablonlardan otomatik).
5. **Entegrasyon dizini**: her entegrasyon için otomatik oluşturulan bir sayfa (SEO).
6. **"What's new" widget'ı.** Changelog'u panelde ve sitede rozetle göstersin. Hub'daki sürüm notlarıyla beslenebilir.
7. **Erişilebilirlik araç çubuğu** (opsiyonel, kendi yazdığımız küçük bir JS).

### Faz 2: Editör ve form eksikleri (küçük ama görünür)
8. **Marka kiti**: logo, 6 renk, başlık ve gövde fontu. Tüm şablonlara tek tıkla uygulanır.
9. **Google Fonts** seçici: yerel olarak barındırılabilir, KVKK dostu.
10. **Kayıtlı bloklar kütüphanesi** ("Evrensel bloklar").
11. **Uluslararası telefon alanı** (ülke bayrağı seçici, IP'den otomatik ülke, E.164 doğrulama).
12. **Canlı e-posta doğrulama** (MX kontrolü, "gmial.com → gmail.com?" önerisi).
13. Hazır **Yes/No** şablon seti.

### Faz 3: Tetikleyici ve hedefleme tamamlama (hızlı kazanç)
14. Tetikleyiciler: **click count**, **sepete eklenince** (Shopify/Woo), **page count** (tetikleyici olarak), **Autopilot** (geçmiş verilere göre tetikleyici seçen mod; A/B motorumuz zaten var).
15. Hedefleme:
    - **sayfa başlığı**
    - **kaynak kodunda metin/seçici var**
    - **diğer kampanyayı gördü / kapattı / dönüştü**
    - **IP/CIDR hariç tutma**
    - **kampanyanın toplam gösterim veya dönüşüm sınırı** (örneğin ilk 100 kişiye)
16. Kupon:
    - CSV toplu yükleme
    - son kullanma tarihi
    - kupon paneli (kalan, kullanılan, gelir)
    - Shopify'da gerçek indirim kodu oluşturma (Admin API)
    - kupon kullanımına göre segment

### Faz 4: Audience ayağı (orta büyüklükte)
17. **Kişiler (Contacts)**: lead'leri tekilleştirip kişi kartına çevirmek; profil, etiketler, özel alanlar, aktivite zaman çizelgesi (görülen popup'lar, sayfalar, UTM, siparişler, push). İçe ve dışa aktarma.
18. **Segment oluşturucu**: mevcut kural motorunu sunucu tarafında kişilere uygulamak. Hazır segmentler: "Fiyat sayfasına girdi ama almadı", "3+ blog okudu", "kuponu aldı ama kullanmadı".
19. **Tercih merkezi**: abonenin hangi listelere ve konulara abone olacağını seçtiği sayfa.

### Faz 5: Email ayağı (büyük; ayrı bir "Email" eklentisi olarak satılabilir)
20. **Sürükle-bırak e-posta editörü**: blok tabanlı, mevcut builder'ın blok mantığıyla. 30–60 e-posta şablonu.
21. **Kampanyalar (broadcast)**: segmente gönder veya hariç tut, zamanla, A/B konu satırı, açılma/tıklama raporu.
22. **Görsel otomasyon akışı**: mevcut Email flows'u, tetikleyici → bekle → koşul (if/else) → e-posta / push / etiket ekle akışına çevirmek. Hazır otomasyon şablonları (sepet terk, hoş geldin serisi, geri kazanma).
23. **Teslim edilebilirlik**: alan adı doğrulama (SPF/DKIM/DMARC kontrolü), DMARC oluşturucu, e-posta logları, abonelikten çıkma anketi.

### Faz 6: Ajans
24. **Alt hesaplar**: bir ajans hesabının altında müşteri hesapları; tek tıkla geçiş; müşteriye yalnızca kendi hesabını gören kullanıcı; hesap başına saat dilimi ve tarih formatı; toplu kullanım raporu. SaaS sahibine "ajans planı" satma imkânı verir.

### Faz 7: Entegrasyon genişletme (sürekli)
25. Öncelikli entegrasyonlar: Pipedrive, Zoho CRM, Freshsales, Kit, AWeber, Moosend, Mailjet, SendGrid, SendPulse, Customer.io, Sendlane, Slack (yeni lead bildirimi), Meta Pixel / TikTok Pixel olay gönderimi.
26. Platform rehber sayfaları: Wix, Webflow, Framer, BigCommerce, Ecwid, Joomla, Drupal, Squarespace, Lovable, Bolt, Replit.

---

## 6. Önerilen ilk adım

Satışa en hızlı etki edecek ve en az risk taşıyan iş: **Faz 1, madde 1–3**. Yani herkese açık galeri, "Canlı göster", "Bu şablonu kullan" ile misafir taslağı, ve paylaşılabilir popup linki.

Altyapının çoğu hazır: SDK, 167 şablon, `pp-preview` önizleme modu. Kullanıcının istediği "kayıt olmadan siteme popup ekliyorum" hissini tam olarak bu verir.

Ardından **Faz 1, madde 4**: yeni pazarlama teması ve ayrı sayfalar.

---

## 7. Notlar ve sınırlar

- `app.popt.in` (Poptin paneli ve "Show" önizlemeleri) bu ortamdan açılamadı. Editörün iç özelliklerini ve "Show" deneyimini sayfa metinlerinden ve kodundan çıkardım, kendim kullanmadım.
- Rakip karşılaştırma sayfalarında başka markaların adını kullanmak hukuken dikkat ister. Doğru ve kaynaklı bilgi gerekir, logo kullanılmamalı.
- Tasarımı **kopyalamıyoruz**; aynı hissi (koyu hero, scroll ile anlatım, canlı ürün parçaları, gradyan) kendi kimliğimizle yapacağız. Poptin'in görselleri, videoları ve metinleri kullanılmayacak.
- 🟡 "doğrulanmalı" yazan maddeler, kodda kesin göremediğim yerler. Uygulamaya geçmeden önce tek tek kontrol edeceğim.
