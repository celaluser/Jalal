# Servis Pasifle — Not Assigned (Chrome, Manifest V3)

Perfect Panel **Services** sayfasında (`https://panelbaku.com/admin/services`) listenin sonundaki **Not Assigned** kategorisinde
**aktif** (Enabled) kalan servisleri bulur; senin onayınla tek tek **pasife** alır (Actions → *Disable service*).

## Kurulum
1. `chrome://extensions` → **Geliştirici modu** açık → **Paketlenmemiş öğe yükle** → bu `servis-pasifle/` klasörü.
2. Panelde `/admin/services` sayfasını yenile. Sol altta "⏻ Servis Pasifle" çubuğu çıkar (Kategori Temizle de yüklüyse onun yanına oturur).
   Eklenti simgesindeki mini panel aynı işi yapar.

İzinler: `https://panelbaku.com/*` ve **alarms** (yalnızca zamanlayıcı; otomatik mod için, hiçbir siteye erişim vermez). Ayar, token, sunucu yok.

## Kullanım
1. **Not Assigned'daki aktif servisleri bul** → kategorileri kapatır, listeyi sona kadar gezer, **Not Assigned** başlığını bulup açar
   (kendi *Expand*'i yoksa tümünü açar), altındaki servisleri okur. Aktif olanları (ID — ad) listeler. **Hiçbir şeye basmaz.**
2. Listede işaretini kaldırdığın servis işlenmez ("hepsi / hiçbiri" kısayolları var).
3. **Yöntem** (çubukta sekme, mini panelde seçenek; hatırlanır):
   - **Toplu: kutucuk → Disable all** (varsayılan): Not Assigned'daki aktif servislerin **kutucukları işaretlenir** (her biri:
     hâlâ Not Assigned'da ve Enabled mı? sonra "N services selected" tam bir arttı mı?) → toplu **Actions → Disable all** →
     (onay penceresi çıkarsa sayı = seçilen olmalı) → seçilenlerin "Disabled" olduğu doğrulanır. 100'erli gruplar.
     Zaten pasif olanlar işaretlenmez (sonuç aynı: Not Assigned'da aktif kalmaz). **"Delete all", "Enable all"a asla basılmaz.**
   - **Tek tek: Disable service**: aşağıdaki gibi her servis için Actions menüsü.
4. **Pasife al (N)** (tek tek yöntem) → her servis için: satırı kimliğinden (`service-NNNN`) bul → hâlâ Not Assigned'da ve **Enabled** mı? →
   Actions → yalnızca tam **Disable service** → (onay penceresi çıkarsa *Disable/Yes*) → satır **Disabled** oldu mu?
5. **Deneme modu** (varsayılan AÇIK, her sayfa yüklenişinde yeniden açılır): menüyü açar, *Disable service*'i görür, **basmadan** kapatır.
6. **En çok N** (varsayılan 100, hatırlanır) · **Durdur** (sıradaki servisten önce durur) · **Teşhisi kopyala**.

## Otomatik mod (her N dakikada yenile + kontrol et)
- Çubukta ya da mini panelde **Otomatik: her [5] dk yenile + kontrol et** kutusunu işaretle. İlk kontrol hemen yapılır;
  sonra her 5 dakikada (Chrome **alarms**) sayfa yenilenir, Not Assigned yeniden taranır, aktif servis varsa pasife alınır.
- Açarken **deneme modu kapalıysa GERÇEK**, açıksa DENEME çalışır (çubukta "Otomatik AÇIK (GERÇEK)" yazar).
- Yalnızca açtığın **sekmede** çalışır (sekme kapanınca biter; başka panel sekmelerini etkilemez). Sayfa yenilense de sürer.
- **O tur atlanır, sayfa yenilenmez:** sekme Services sayfasında değilse, önceki iş sürüyorsa, panelde açık pencere ya da
  odakta yazı alanı varsa, **son 1 dakikada panelde tıklama/tuş** olduysa (senin işini bölmesin).
- Çubukta sonraki kontrol saati, son turun sonucu ve son 5 turun geçmişi görünür.
- **3 tur üst üste hata/uyarı** (ör. Not Assigned bulunamadı) → otomatik kendini kapatır, nedenini yazar.
- Otomatik açıkken sekme Bellek Tasarrufu ile kapatılmaz. Tarayıcı kapanınca biter; yeniden açman gerekir.

## Güvenlik kuralları
- Yalnızca **Not Assigned** altındaki servisler: servisin üstündeki en yakın kategori başlığı Not Assigned değilse atlanır.
- Aktiflik iki yerden okunur ve uyuşmalı: Status sütunu "**Enabled**" + satırda `service-table__row-disabled` sınıfı yok. Uyuşmazsa dokunulmaz.
- Menüde "**Enable service**" görünürse (zaten pasif) atlanır. **"Delete service", "Hide service", "Duplicate" vb. öğelere asla basılmaz.**
- Onay penceresinde "Delete" geçiyorsa ya da "disable" geçmiyorsa basılmaz, kapatılır, hata yazılır.
- Sunucu hatasında (satır pasife geçmezse) servis "hata" sayılır, sıradakine geçilir; üst üste 3 hata → durur.

## Arka planda çalışma
Başka sekmeye geçsen de iş sürer: beklemeler service worker'da, kaydırma olayı elle tetiklenir, iş sürerken sekme otomatik kapatılmaz.

## Test
`servis-pasifle-test/` — Playwright + React taklit panel (sanallaştırılmış liste, Actions'sız Not Assigned başlığı, satır içi menüler,
"Delete service" tuzağı, onay penceresi, sunucu hatası, her işlemden sonra yeniden çizim):
```bash
cd servis-pasifle-test && npm i && node run-tests.js     # tek test: node run-tests.js S3
HEADED=1 xvfb-run -a node run-tests.js S12               # gerçek pencerede sekme değişimi
HEADED=1 xvfb-run -a node run-tests.js S18               # otomatik mod, sekme arka plandayken
```

> **Not (güncel sürüm):** Deneme modu kaldırıldı; araç her zaman gerçek çalışır. Bu belgedeki deneme modu anlatımları eski sürümler içindir. Güvenlik: önce liste gösterilir, düğmeye basmadan hiçbir şey değişmez; her işlemden önce yeniden kontrol edilir; koruma listesi ve "en çok N" sınırı geçerlidir.
