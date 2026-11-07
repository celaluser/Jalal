# Kategori Temizle (Chrome, Manifest V3)

Perfect Panel **Services** sayfasında (`https://panelbaku.com/admin/services`) **pasif** kategorileri bulur:
- **boş** olanları siler;
- **dolu** olanların (seçenek açıksa) servislerini **Not Assigned**'a taşır, boşalan kategoriyi siler.

## Kurulum
1. `chrome://extensions` → sağ üstte **Geliştirici modu** açık.
2. **Paketlenmemiş öğe yükle** → bu `kategori-temizle/` klasörünü seç (zip'ten çıkardıktan sonra).
3. Panelde `/admin/services` sayfasını aç/yenile. Sol altta küçük çubuk görünür (– ile küçülür). Eklenti simgesindeki mini panel aynı işi yapar.

İzin: yalnızca `https://panelbaku.com/*`. Başka izin, ayar, sunucu, token yok. En çok N panel sitesinin `localStorage`'ında hatırlanır; **deneme modu her sayfa yüklenişinde AÇIK başlar** (kalıcı değil).

## Kullanım
1. **Boş pasif kategorileri bul** — listeyi "yalnızca kategoriler" görünümüne alır, baştan sona kaydırarak gezer (panel listeyi sanallaştırıyor), adları gösterir. **Hiçbir şey silmez.** Boş = satırda "Expand (N)" bağlantısı yok ya da N=0.
2. **Sil (N)** — sırayla: Actions → Delete category → onay penceresinde Delete. **Durdur** sıradaki silmeden önce durdurur.
3. **Dolu pasifleri Not Assigned'a taşı, sonra sil** (varsayılan KAPALI, seçim hatırlanır). Her dolu pasif kategori için:
   Actions → *Select all category services* → üstteki "N services selected" sayısı = Expand (N) mı? → toplu **Actions → Assign category** →
   pencerede "Not assi" yaz → **Not Assigned** → **Save changes** → kategori boşaldı mı? → normal silme. Toplu menüdeki **"Delete all"a asla basılmaz.**
4. **Deneme modu** (varsayılan AÇIK): menüyü ve onay penceresini açar, Delete yerine **Cancel** der. Gerçek silmek için kutuyu kapat.
5. **En çok N** (varsayılan 50): bir çalıştırmada en çok N kategori.
6. Bir şey bulunamazsa/takılırsa **Teşhisi kopyala** düğmesi çıkar; panoya kopyalanan metni sohbete yapıştır.

## Arka planda çalışma
Başka sekmeye geçsen de iş sürer (panel sekmesi açık kalmalı):
- Chrome arka plandaki sekmenin zamanlayıcılarını kısar; eklentinin beklemeleri bu yüzden arka plan service worker'ında yapılır.
- Arka planda geciken kaydırma olayı elle tetiklenir (sanallaştırılmış liste satırları çizsin).
- İş sürerken sekmenin Bellek Tasarrufu ile otomatik kapatılması engellenir; bitince eski hâline döner.
- Sekmeyi kapatırsan ya da sayfayı yenilersen iş durur.

## Güvenlik kuralları
- Her silmeden hemen önce (ve onay penceresi açıkken bir kez daha) kategori adından yeniden bulunur; hâlâ **tek**, **ad aynı**, **pasif** ve **boş** değilse atlanır, nedeni yazılır.
- Hiçbir satırda "Expand (N)" görünmüyorsa (boşluk doğrulanamaz) hiçbir şey yapılmaz. Hepsi pasif görünüyorsa renk ayrımına güvenilmez, durur.
- Panelin kendi durumu esas: kategori menüsünde **"Enable category"** (= pasif) görülmeden hiçbir kategoriye dokunulmaz; "Disable category" görülürse (aktif) atlanır.
- Taşımada: seçilen servis sayısı Expand (N) ile aynı değilse, hedef tek bir "Not Assigned" değilse ya da taşıma sonrası kategori boşalmadıysa → atla/hata, silme yok. Başta ve sonda seçim temizlenir.
- `Not Assigned` satırına dokunulmaz. Aynı adlı birden çok kategori atlanır.
- Onay penceresinde başka bir kategori adı görünürse iptal eder. Üst üste 3 hata → durur.
- Sunucu hatasında (satır silinmezse / pencere kapanmazsa / hata bildirimi) kategori "hata" sayılır, sıradakine geçilir.

## Test
`kategori-temizle-test/` — Playwright + React ile taklit panel (koyu tema, portal menü/onay penceresi, her silmeden sonra yeniden çizim, sonradan yüklenen liste, gecikmeli/hatalı sunucu):

```bash
cd kategori-temizle-test && npm i && node run-tests.js      # tek test: node run-tests.js T3
HEADED=1 xvfb-run -a node run-tests.js T15                # gerçek pencerede sekme değişimi
```
(Playwright'ın kurulu olması gerekir; gerçek eklenti Chromium'a yüklenir.) 20 test: boş silme, taşıma + silme, deneme, durdur, sunucu hatası, seçim uyuşmazlığı, panelin "Disable category" demesi, sanallaştırma, koyu tema, arka plan sekmesi.
