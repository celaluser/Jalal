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
Çubukta (sol alt) ve eklenti simgesindeki mini panelde iki ayrı mod var; düğmeler ve seçenekler seçili moda göre değişir:

**1. Boş kategori sil**
- **Boş pasif kategorileri bul** → listeyi "yalnızca kategoriler" görünümüne alır, baştan sona gezer (liste sanallaştırılmış), boş pasifleri listeler. **Hiçbir şey silmez.**
- **Sil (N)** → her biri için: Actions → (*Enable category* görülmeli = pasif) → *Delete category* → onay penceresinde *Delete*.

**2. Pasif → Not Assigned**
- **Dolu pasif kategorileri bul** → servisi olan pasif kategorileri servis sayılarıyla listeler.
- **Taşı + sil (N)** → her biri için: Actions → *Select all category services* → üstteki "N services selected" = Expand (N) mi? →
  toplu **Actions → Assign category** → "Not assi" yaz → **Not Assigned** → **Save changes** → kategori boşaldı mı? → (seçenek açıksa) boş kategoriyi sil.
- **Taşıdıktan sonra boş kategoriyi sil** (yalnız bu modda; varsayılan açık). Kapatırsan yalnızca taşır. Düğme "Taşı (N)" olur.
- Toplu menüdeki **"Delete all"a asla basılmaz**; yalnızca tam "Assign category" yazısına basılır.

**İki modda ortak**
- Listede her kategorinin yanında kutucuk var: **işaretini kaldırdığın işlenmez** (ör. *PASSİF*, *Təmirdə Olan Servislər* gibi bekletme kategorileri). "hepsi / hiçbiri" kısayolları var.
- **Deneme modu** (varsayılan AÇIK, her sayfa yüklenişinde yeniden açılır): son düğmeye (Delete / Save changes) kadar gider, onun yerine **Cancel / Close** der, seçimi temizler.
- **En çok N** (varsayılan 50) · **Durdur** (sıradaki işlemden önce durur) · **Teşhisi kopyala**.
- Mod, "taşıdıktan sonra sil" ve "en çok N" hatırlanır.

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
(Playwright'ın kurulu olması gerekir; gerçek eklenti Chromium'a yüklenir.) 22 test: iki mod, işareti kaldırılan kategori, yalnız taşıma, boş silme, taşıma + silme, deneme, durdur, sunucu hatası, seçim uyuşmazlığı, panelin "Disable category" demesi, sanallaştırma, koyu tema, arka plan sekmesi.
