# Kategori Temizle (Chrome, Manifest V3)

Perfect Panel **Services** sayfasında (`https://panelbaku.com/admin/services`) **pasif VE içinde hiç servis olmayan** kategorileri bulur; senin onayınla tek tek siler.

## Kurulum
1. `chrome://extensions` → sağ üstte **Geliştirici modu** açık.
2. **Paketlenmemiş öğe yükle** → bu `kategori-temizle/` klasörünü seç (zip'ten çıkardıktan sonra).
3. Panelde `/admin/services` sayfasını aç/yenile. Sol altta küçük çubuk görünür (– ile küçülür). Eklenti simgesindeki mini panel aynı işi yapar.

İzin: yalnızca `https://panelbaku.com/*`. Başka izin, ayar, sunucu, token yok. En çok N panel sitesinin `localStorage`'ında hatırlanır; **deneme modu her sayfa yüklenişinde AÇIK başlar** (kalıcı değil).

## Kullanım
1. **Boş pasif kategorileri bul** — servisleri açar, listeyi sonuna kadar kaydırır, adları gösterir. **Hiçbir şey silmez.**
2. **Sil (N)** — sırayla: Actions → Delete category → onay penceresinde Delete. **Durdur** sıradaki silmeden önce durdurur.
3. **Deneme modu** (varsayılan AÇIK): menüyü ve onay penceresini açar, Delete yerine **Cancel** der. Gerçek silmek için kutuyu kapat.
4. **En çok N** (varsayılan 50): bir çalıştırmada en çok N kategori.
5. Bir şey bulunamazsa/takılırsa **Teşhisi kopyala** düğmesi çıkar; panoya kopyalanan metni sohbete yapıştır.

## Güvenlik kuralları
- Her silmeden hemen önce (ve onay penceresi açıkken bir kez daha) kategori adından yeniden bulunur; hâlâ **tek**, **ad aynı**, **pasif** ve **boş** değilse atlanır, nedeni yazılır.
- Servis satırları açık değilse (boşluk doğrulanamaz) hiçbir şey yapılmaz. Hepsi pasif görünüyorsa renk ayrımına güvenilmez, durur.
- `Not Assigned` satırına dokunulmaz. Aynı adlı birden çok kategori atlanır.
- Onay penceresinde başka bir kategori adı görünürse iptal eder. Üst üste 3 hata → durur.
- Sunucu hatasında (satır silinmezse / pencere kapanmazsa / hata bildirimi) kategori "hata" sayılır, sıradakine geçilir.

## Test
`kategori-temizle-test/` — Playwright + React ile taklit panel (koyu tema, portal menü/onay penceresi, her silmeden sonra yeniden çizim, sonradan yüklenen liste, gecikmeli/hatalı sunucu):

```bash
cd kategori-temizle-test && npm i && node run-tests.js      # tek test: node run-tests.js T3
```
(Playwright'ın kurulu olması gerekir; gerçek eklenti Chromium'a yüklenir.)
