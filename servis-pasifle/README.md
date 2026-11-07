# Servis Pasifle — Not Assigned (Chrome, Manifest V3)

Perfect Panel **Services** sayfasında (`https://panelbaku.com/admin/services`) listenin sonundaki **Not Assigned** kategorisinde
**aktif** (Enabled) kalan servisleri bulur; senin onayınla tek tek **pasife** alır (Actions → *Disable service*).

## Kurulum
1. `chrome://extensions` → **Geliştirici modu** açık → **Paketlenmemiş öğe yükle** → bu `servis-pasifle/` klasörü.
2. Panelde `/admin/services` sayfasını yenile. Sol altta "⏻ Servis Pasifle" çubuğu çıkar (Kategori Temizle de yüklüyse onun yanına oturur).
   Eklenti simgesindeki mini panel aynı işi yapar.

İzin: yalnızca `https://panelbaku.com/*`. Ayar, token, sunucu yok.

## Kullanım
1. **Not Assigned'daki aktif servisleri bul** → kategorileri kapatır, listeyi sona kadar gezer, **Not Assigned** başlığını bulup açar
   (kendi *Expand*'i yoksa tümünü açar), altındaki servisleri okur. Aktif olanları (ID — ad) listeler. **Hiçbir şeye basmaz.**
2. Listede işaretini kaldırdığın servis işlenmez ("hepsi / hiçbiri" kısayolları var).
3. **Pasife al (N)** → her servis için: satırı kimliğinden (`service-NNNN`) bul → hâlâ Not Assigned'da ve **Enabled** mı? →
   Actions → yalnızca tam **Disable service** → (onay penceresi çıkarsa *Disable/Yes*) → satır **Disabled** oldu mu?
4. **Deneme modu** (varsayılan AÇIK, her sayfa yüklenişinde yeniden açılır): menüyü açar, *Disable service*'i görür, **basmadan** kapatır.
5. **En çok N** (varsayılan 100, hatırlanır) · **Durdur** (sıradaki servisten önce durur) · **Teşhisi kopyala**.

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
```
