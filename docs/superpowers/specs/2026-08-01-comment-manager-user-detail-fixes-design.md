# Yorum Yonetimi Kullanici Detay Modal Duzeltmeleri

## Kapsam

Yorum yonetimindeki kullanici detay API'si ve modal akisi, mevcut UI tasarimini koruyarak duzeltilir.

## Tasarim

- `admin/api/user-details.php` endpoint'i yalnizca `comments.view` yetkisine sahip oturum acmis kullanicilara izin verir.
- Kullanici bulunamazsa yapay kullanici uretmek yerine 404 donulur.
- Kullanici raporlarinda gercek sema kolonu olan `reporter_user_id` kullanilir.
- Aktif kisitlamalar ve gecmis moderasyon kayitlari response'ta ayrik ve tutarli alanlarda korunur; array merge sirasinda aktif kisitlamalar ezilmez.
- Modal tetikleyicisi tek bir event delegation yoluyla calisir; ayni tiklamada ikinci API istegi baslatilmaz.
- API yaniti gecersiz veya eski kullanici durumlarinda modal hata durumunu gostermeye devam eder.

## Dogrulama

- PHP ve JavaScript syntax kontrolleri.
- Yetkisiz endpoint isteginde 403 ve gecersiz ID isteginde 404.
- Rapor sorgusunun canli semayla hazirlanmasi.
- Modal event yolunda tek listener ve mevcut sekme/veri render akisinin korunmasi.
