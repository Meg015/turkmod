# Mods.club ATS Kategori Eslemeleri

## Kapsam

Mods.club uzerindeki 10 American Truck Simulator kategori URL'si, mevcut ETS 2 esleme modeliyle icerik botuna eklenir.

## Esleme Kurallari

- Truck -> `ats-cekici-modlari`
- Trailers -> `ats-dorse-modlari`
- Sound -> `ats-ses-modlari`
- Skins -> `ats-skinler`
- Parts/Tuning ve Interior -> `ats-modifiye-parca-modlari`
- Other -> `ats-diger-modlar`
- Maps -> `ats-harita-modlari`
- Car ve Bus -> `ats-araba-otobus-modlari`

Tum eslemelerde baslik on eki `ATS -` olur.

## Uygulama

Yeni ve idempotent bir migration, mevcut `mods-club` bot kaynagini bulur. Her URL icin kayit varsa ad, baslik on eki, yerel kategori ve durum alanlarini gunceller; yoksa aktif esleme olusturur. Gerekli yerel kategori bulunamazsa eksik slug acikca raporlanarak migration durdurulur.

## Dogrulama

- Migration PHP syntax kontrolunden gecer.
- Migration iki kez calistirildiginda tekrarli kayit uretmez.
- Canli veritabaninda 10 ATS URL'si aktif, `ATS -` on ekli ve beklenen yerel kategori slug'larina bagli olur.
