# Pasif Servis Taşı — Not Assigned (Chrome, Manifest V3)

Perfect Panel **Services** sayfasında (`https://panelbaku.com/admin/services`) **aktif kategorilerdeki pasif (Disabled) servisleri** bulur;
soldaki kutucuklarını işaretleyip toplu **Actions → Assign category → Not Assigned** ile taşır.

## Kurulum
1. `chrome://extensions` → **Geliştirici modu** → **Paketlenmemiş öğe yükle** → bu `servis-tasi/` klasörü.
2. Panelde `/admin/services` sayfasını yenile. Sol altta "⇄ Pasif Servis Taşı" çubuğu çıkar (diğer eklentilerin çubukları varsa yanlarına oturur).
   Eklenti simgesindeki mini panel aynı işi yapar.

İzin: yalnızca `https://panelbaku.com/*`.

## Kullanım
1. **Pasif servisleri bul** → tüm servisleri açar, listeyi baştan sona gezer (liste sanallaştırılmış), her servisi üstündeki kategori
   başlığına bağlar. Pasif servisleri listeler: **ID — ad · kategori**. **Hiçbir şeye basmaz.**
   - Varsayılan: yalnızca **aktif kategorilerdeki** pasif servisler. **Not Assigned**'dakiler hiçbir zaman listelenmez.
   - **Pasif kategorilerdekileri de dahil et** kutusu listeyi anında genişletir.
   - İşaretini kaldırdığın servis işlenmez ("hepsi / hiçbiri").
2. **Taşı (N)** → servisler **gruplar hâlinde** (bir seferde K, varsayılan 50) işlenir. Her grup:
   seçimi sıfırla → listeyi gezip gruptaki her servisin **kutucuğunu işaretle** → toplu **Actions → Assign category** →
   "Not assi" yaz → **Not Assigned** → **Save changes** → onay "assign **N** services to category **Not Assigned**?" → **Assign**.
3. Sonda liste yeniden gezilir: taşınan her servisin gerçekten **Not Assigned** altında olduğu doğrulanır (✓).
4. **Deneme modu** (varsayılan AÇIK, her sayfa yüklenişinde yeniden açılır): kutucukları işaretler, pencerede Not Assigned'ı seçer,
   **Save changes yerine Close** der ve seçimi temizler.
5. **Bir seferde K** · **En çok N** (varsayılan 500) · **Durdur** (o anki grup bitince durur) · **Teşhisi kopyala**.

## Güvenlik kuralları
- Kutucuğa basmadan hemen önce: servis hâlâ **pasif** mi ("Disabled" yazısı **ve** `service-table__row-disabled` sınıfı), hâlâ
  **aynı kategoride** mi, zaten işaretli değil mi? Tutmazsa atlanır, nedeni yazılır.
- Her tıklamadan sonra "**N services selected**" tam bir artmalı; artmazsa (ör. fazladan servis seçildi) o grup **iptal**, seçim temizlenir.
- Onay penceresindeki **sayı = işaretlenen** ve **hedef = Not Assigned** olmalı; değilse **Cancel**.
- Toplu menüde yalnızca tam **"Assign category"** yazısına basılır; **"Delete all", "Disable all"** vb. asla.
- Sunucu hatası / pencere kapanmazsa grup "hata" sayılır; üst üste 2 hatalı grup → durur.

## Arka planda çalışma
Başka sekmeye geçsen de iş sürer: beklemeler service worker'da, kaydırma olayı elle tetiklenir, iş sürerken sekme otomatik kapatılmaz.

## Test
`servis-tasi-test/` — Playwright + React taklit panel (sanallaştırma, kutucuklar ve "N services selected" çubuğu, "Delete all" tuzağı,
Assign penceresi + onay, taşımadan sonra yeniden çizim, tümü açıkken kapalı kalmış kategori, sunucu hatası, fazladan seçen kutucuk):
```bash
cd servis-tasi-test && npm i && node run-tests.js        # tek test: node run-tests.js P4
HEADED=1 xvfb-run -a node run-tests.js P12               # gerçek pencerede sekme değişimi
```

## Otomatik mod (1.1.0)
Çubukta ya da mini panelde **Otomatik: her N dk yenile + taşı** (varsayılan 10 dk). Açılınca ilk tur hemen yapılır; sonra her N dakikada
sayfa yenilenir ve pasif servisler yeniden aranıp Not Assigned'a taşınır. Deneme modu açıkken otomatik mod da yalnız dener.
Sekme Services sayfasında değilse, son 1 dakikada panelde tıkladıysan/yazdıysan, pencere açıksa ya da başka bir araç
(Kategori Temizle / Servis Pasifle) çalışıyorsa o tur atlanır. 3 tur üst üste hata → otomatik kapanır. Durum sekmeye bağlıdır.
