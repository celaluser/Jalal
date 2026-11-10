# Panel Araçları (Perfect Panel)

Üç aracı tek eklentide toplar. Services sayfasında **sağ altta tek bir dok** durur: `Panel Araçları [🗑 Kategori] [⊘ Pasifle] [⇄ Taşı]`.
Tıkladığın aracın çubuğu dokun üstünde açılır, diğerleri gizlenir (gizli araç işini sürdürür); aynı düğmeye tekrar basınca kapanır, seçim hatırlanır.
Doktaki nokta: sarı = çalışıyor, yeşil = otomatik mod açık, kırmızı = uyarı var. Mini panelde her araç ayrı sekmededir.

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
