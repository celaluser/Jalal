# Panel Araçları 2.0 (Perfect Panel)

Üç araç tek pencerede: **Kategori** (boş pasif kategorileri sil / dolu pasif kategorileri Not Assigned'a taşı), **Pasifle** (Not Assigned'daki aktif servisleri pasife al), **Taşı** (aktif kategorilerdeki pasif servisleri Not Assigned'a taşı).

## Sayfada
- Sağ altta küçük **Panel Araçları** hapı. Tıkla → pencere açılır: başlık, araç sekmeleri, seçili aracın içeriği. **–** ile tekrar hapa döner.
- Pencere **başlığından sürüklenir**, yeri hatırlanır; başlığa **çift tık** sağ alt köşeye döndürür. Seçili araç hatırlanır.
- Başlıkta ve hapta durum: sarı nokta çalışıyor, yeşil otomatik mod açık, kırmızı uyarı var.
- Tasarım: nötr yüzeyler, tek vurgu rengi (çivit); **kırmızı yalnız gerçek modda** (geri alınamaz işlerde). Açık/koyu tema.

## Her araçta aynı düzen
1. **Bul** → kısa özet
2. **Liste:** başlıkta seçili/toplam; **Ara** (ad ya da ID), **Hepsi / Hiçbiri / Ters** (aramadayken yalnız görünenlere), **ID yapıştır** (yapıştırdığın ID'ler seçilir, gerisi bırakılır), **ID kopyala** (`62727,26272,2627`)
3. **Taşı / Sil / Pasife al** + **Durdur**; çalışırken **ilerleme çubuğu** ve kalan süre
4. Durum, **Sonuç** (her grubun ID'leri ayrı kopyalanır)
5. **Ayarlar** (katlanır; kapalıyken özet görünür)
6. **Teşhisi kopyala** · **Günlüğü indir (.txt)**

## Kaldığı yerden devam (2.2.0)
İş sürerken sayfa yenilenir ya da sekme kapanırsa, aracı yeniden açınca **"Yarım kalan iş: N / M öğe kaldı"** kutusu çıkar.
**Kaldığı yerden devam** → liste yeniden taranır, yalnız kalan öğeler seçilir ve iş sürer (yapılmış olanlar taramada kendiliğinden ayıklanır,
aynı öğe iki kez işlenmez). **Unut** kaydı siler. Kayıt 24 saat tutulur; hatalı kalanlar da "kalan" sayılır. Durdur'a bastıysan da aynı kutu çıkar.

## Taşı: hedef kategori (2.2.0)
Ayarlar → **Hedef kategori**: boşsa Not Assigned; bir kategori adı yazarsan pasif servisler oraya taşınır (öneri listesi son taramadaki kategorilerden).
Hedef listede yoksa tarama durur. Onay penceresindeki hedef ve sondaki doğrulama da bu kategoriye göre yapılır; hedefteki servisler listeye alınmaz.

## Mini panel (Alt+Shift+P)
Sekmeler: Kategori · Pasifle · Taşı · **Geçmiş** · **Koruma**.
- **Koruma:** buraya yazdığın servis/kategori ID'lerine hiçbir araç dokunmaz (otomatik mod dahil); listelerde "KORUMALI" görünür, işaretlenemez.
- **Geçmiş:** biten işler (filtre, kopyala, temizle). **Masaüstü bildirimi** ve **simge rozeti** (● çalışıyor, OTO otomatik).

## Diğer
- Araçlar arası kilit: biri çalışırken diğeri başlamaz. Otomatik mod (Pasifle, Taşı): her N dk yenile + kontrol; iki alarm art arda gelirse bir kez yenilenir.
- **Deneme modu yok (2.1.0):** araçlar her zaman gerçek çalışır. Güvenlik için yine önce liste gösterilir, sen düğmeye basmadan hiçbir şey değişmez; her işlemden hemen önce yeniden kontrol edilir; koruma listesi ve "en çok N" sınırı geçerlidir.
- Kurulum: `chrome://extensions` → Paketlenmemiş öğe yükle → `panel-araclari`. Eski üç ayrı eklentiyi kaldır.
- Kaynak: ayrı eklentiler (`kategori-temizle/`, `servis-pasifle/`, `servis-tasi/`); bu klasör `python3 build-panel-araclari.py` ile üretilir.
