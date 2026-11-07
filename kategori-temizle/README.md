# Kategori Temizle (Chrome, Manifest V3)

Perfect Panel **Services** sayfasında (`https://panelbaku.com/admin/services`) **pasif VE içinde hiç servis olmayan** kategorileri bulur; senin onayınla tek tek siler.

## Kurulum
1. `chrome://extensions` → sağ üstte **Geliştirici modu** açık.
2. **Paketlenmemiş öğe yükle** → bu `kategori-temizle/` klasörünü seç (zip'ten çıkardıktan sonra).
3. Panelde `/admin/services` sayfasını aç/yenile. Sol altta küçük çubuk görünür (– ile küçülür). Eklenti simgesindeki mini panel aynı işi yapar.

İzin: yalnızca `https://panelbaku.com/*`. Başka izin, ayar, sunucu, token yok. En çok N panel sitesinin `localStorage`'ında hatırlanır; **deneme modu her sayfa yüklenişinde AÇIK başlar** (kalıcı değil).

## Kullanım
1. **Boş pasif kategorileri bul** — listeyi "yalnızca kategoriler" görünümüne alır, baştan sona kaydırarak gezer (panel listeyi sanallaştırıyor), adları gösterir. **Hiçbir şey silmez.** Boş = satırda "Expand (N)" bağlantısı yok ya da N=0.
2. **Sil (N)** — sırayla: Actions → Delete category → onay penceresinde Delete. **Durdur** sıradaki silmeden önce durdurur.
3. **Deneme modu** (varsayılan AÇIK): menüyü ve onay penceresini açar, Delete yerine **Cancel** der. Gerçek silmek için kutuyu kapat.
4. **En çok N** (varsayılan 50): bir çalıştırmada en çok N kategori.
5. Bir şey bulunamazsa/takılırsa **Teşhisi kopyala** düğmesi çıkar; panoya kopyalanan metni sohbete yapıştır.

## Arka planda çalışma
Başka sekmeye geçsen de iş sürer (panel sekmesi açık kalmalı):
- Chrome arka plandaki sekmenin zamanlayıcılarını kısar; eklentinin beklemeleri bu yüzden arka plan service worker'ında yapılır.
- Arka planda geciken kaydırma olayı elle tetiklenir (sanallaştırılmış liste satırları çizsin).
- İş sürerken sekmenin Bellek Tasarrufu ile otomatik kapatılması engellenir; bitince eski hâline döner.
- Sekmeyi kapatırsan ya da sayfayı yenilersen iş durur.

## Güvenlik kuralları
- Her silmeden hemen önce (ve onay penceresi açıkken bir kez daha) kategori adından yeniden bulunur; hâlâ **tek**, **ad aynı**, **pasif** ve **boş** değilse atlanır, nedeni yazılır.
- Hiçbir satırda "Expand (N)" görünmüyorsa (boşluk doğrulanamaz) hiçbir şey yapılmaz. Hepsi pasif görünüyorsa renk ayrımına güvenilmez, durur.
- `Not Assigned` satırına dokunulmaz. Aynı adlı birden çok kategori atlanır.
- Onay penceresinde başka bir kategori adı görünürse iptal eder. Üst üste 3 hata → durur.
- Sunucu hatasında (satır silinmezse / pencere kapanmazsa / hata bildirimi) kategori "hata" sayılır, sıradakine geçilir.

## Test
`kategori-temizle-test/` — Playwright + React ile taklit panel (koyu tema, portal menü/onay penceresi, her silmeden sonra yeniden çizim, sonradan yüklenen liste, gecikmeli/hatalı sunucu):

```bash
cd kategori-temizle-test && npm i && node run-tests.js      # tek test: node run-tests.js T3
HEADED=1 xvfb-run -a node run-tests.js T15                # gerçek pencerede sekme değişimi
```
(Playwright'ın kurulu olması gerekir; gerçek eklenti Chromium'a yüklenir.)
