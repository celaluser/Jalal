# Panel Araçları (Perfect Panel)

Üç aracı tek eklentide toplar. Services sayfasında **tek bir dok** durur: `⠿ Panel Araçları [🗑 Kategori] [⊘ Pasifle] [⇄ Taşı] –`.
- Tıkladığın aracın çubuğu dokun yanında açılır (dok ekranın alt yarısındaysa üstünde, üst yarısındaysa altında), diğerleri gizlenir; gizli araç işini sürdürür. Aynı düğmeye tekrar basınca kapanır, seçim hatırlanır.
- **⠿ tutamacından sürükle**: dokun yeri hatırlanır (ok tuşlarıyla da taşınır); **çift tık** sağ alt köşeye döndürür. **–** ile tek logo düğmesine küçülür.
- Doktaki nokta: sarı = çalışıyor, yeşil = otomatik mod açık, kırmızı = uyarı var (küçükken en önemlisi logonun köşesinde).

| Sekme | Araç | Ne yapar |
|---|---|---|
| 🗑 Kategori | Kategori Temizle | Boş pasif kategorileri siler; dolu pasif kategorilerin servislerini Not Assigned'a taşır |
| ⊘ Pasifle | Servis Pasifle | Not Assigned'daki aktif servisleri pasife alır. Otomatik mod (her N dk) |
| ⇄ Taşı | Pasif Servis Taşı | Aktif kategorilerdeki pasif servisleri Not Assigned'a taşır. Otomatik mod (her N dk) |

- **Kurulum:** `chrome://extensions` → Geliştirici modu → **Paketlenmemiş öğe yükle** → `panel-araclari` klasörü.
  Eski üç ayrı eklentiyi (Kategori Temizle, Servis Pasifle, Pasif Servis Taşı) **kaldır**: ikisi birden açıksa çubuklar ve işlemler iki kez çalışır.
- **Araçlar arası kilit:** bir araç çalışırken diğeri başlamaz ("… çalışıyor: önce o bitsin"); otomatik tur bekler ya da o turu atlar.
- **Otomatik mod:** iki aracın alarmı art arda gelirse sayfa bir kez yenilenir, iki araç da yenilenen sayfada turunu yapar.
  Kullanıcı son 1 dakikada panelde çalıştıysa, pencere açıksa ya da başka iş sürüyorsa tur atlanır. 3 tur üst üste hata → kapanır.
- Deneme modu her sayfa yüklenişinde **açık** başlar (otomatik mod kendi ayarını saklar).
- Ayrı eklentiler kaynak olarak kalır; bu klasör `python3 build-panel-araclari.py` ile onlardan üretilir.

## Mini panel (1.2.0)
- Üstte araç sekmeleri + **Geçmiş**. Sekmelerdeki nokta aracın durumunu gösterir. Kısayol: **Alt+Shift+P**.
- **Geçmiş:** her biten iş (deneme dahil) saati, araç, gerçek/deneme/otomatik etiketi ve işlenen öğelerle saklanır (son 150). Araç ve "yalnız gerçek" filtresi, metin olarak kopyala, temizle (iki tık).
- **Masaüstü bildirimi:** gerçek değişiklik ya da hata olan iş bitince (otomatik turlar dahil) bildirim gelir; tıklayınca panel sekmesine gider. Mini panelin altından kapatılır.
- **Simge rozeti:** `●` (sarı) bir araç çalışıyor, `OTO` (yeşil) bu sekmede otomatik mod açık.

## Tasarım
Sayfadaki çubuklar ve mini paneller ortak, açık/koyu temaya uyan bir tasarıma geçti (araç renkleri: Kategori mavi, Pasifle yeşil, Taşı turuncu).
Ayrı eklentiler de aynı tasarımı kullanır; onların çubukları da artık **sağ altta** durur (soldaki tgwatch logosuyla çakışmaz).
