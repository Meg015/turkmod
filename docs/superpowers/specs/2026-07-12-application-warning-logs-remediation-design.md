# Uygulama Uyari Loglarini Giderme ve Guvenli Temizleme Tasarimi

## Amac

`application_logs` tablosundaki `warning` seviyeli kayitlari yalnizca silmek yerine, kayitlari uretebilen gercek sablon veri akisi sorunlarini gidermek; canli dogrulamadan sonra mevcut uyari kayitlarini yedekleyerek temizlemek ve uyarilarin yeniden olusmadigini kanitlamak.

## Mevcut durum

- Tabloda 473 uygulama logu bulunuyor.
- Bunlarin 60 tanesi `warning` seviyesinde ve `template / TPL Missing Variable` turunde.
- Eski bildirim sayfasi kayitlari 12 degisken icin toplam 48 uyari iceriyor.
- Guncel liderlik sayfasi kayitlari 12 degisken icin toplam 12 uyari iceriyor.
- Bildirim degiskenleri mevcut sayfa iceriginde tanimli gorundugunden, bu grubun tarihsel olup olmadigi canli istekle dogrulanacak.
- Liderlik degiskenleri sayfa iceriginde tanimli olmasina ragmen son istekte uyari olusturdugundan, yakalanan sayfa degiskenlerinin tema renderer baglamina aktarimi incelenecek.

## Uygulama tasarimi

- Eksik degisken uyarilarini susturan veya genel varsayilan degerle gizleyen bir cozum kullanilmayacak.
- `leaderboard-page-content.php`, `PublicThemeRenderer.php`, `leaderboard.tpl` ve ilgili yakalama/baglam akisi birlikte incelenecek.
- Tema sablonunun bekledigi liderlik degiskenleri, render aninda `page_vars` ya da mevcut standart veri kanali uzerinden eksiksiz aktarilacak.
- Bildirim sayfasindaki degiskenler canli istekle kontrol edilecek; yeni uyari uretilmiyorsa tarihsel kayitlar icin gereksiz kod degisikligi yapilmayacak.
- Degisiklikler diger public sayfalarin ortak renderer davranisini bozmadan, mumkun olan en dar dogru katmanda uygulanacak.

## Log yedegi ve temizlik

- Temizlikten hemen once `warning` seviyesindeki kayitlar zaman damgali bir SQL veya CSV dosyasina, web kokunun disindaki guvenli bir konuma aktarilacak.
- Yalnizca `level = 'warning'` kayitlari silinecek.
- `info`, `error` ve `critical` dahil diger seviyelerdeki kayitlar korunacak.
- Temizlik, duzeltme ve canli dogrulama basarili olmadan yapilmayacak.

## Dogrulama

- Degistirilen PHP dosyalarinda soz dizimi kontrolu calistirilacak.
- Temizlik oncesi bir log sayaci alinacak ve test baslangic zamani kaydedilecek.
- Bildirimler ve liderlik sayfalari gercek HTTP istekleriyle calistirilacak.
- Test baslangicindan sonra yeni `warning` kaydi olusmadigi veritabanindan dogrulanacak.
- Mevcut 60 uyari yedeklendikten sonra yalnizca warning kayitlari temizlenecek.
- Sayfalar temizleme sonrasinda yeniden calistirilacak ve warning sayisinin sifir kaldigi kontrol edilecek.
- Diger log seviyelerinin temizlik oncesi ve sonrasi sayilari karsilastirilarak korunmus olduklari kanitlanacak.

## Basari olcutleri

- Liderlik ve bildirim sayfalari yeni `TPL Missing Variable` uyarisi uretmez.
- Mevcut warning kayitlarinin geri alinabilir yedegi vardir.
- `application_logs` icindeki warning sayisi sifirdir.
- Warning disindaki uygulama loglari silinmemistir.
- Temizlik sonrasi tekrar edilen canli testlerde warning sayisi sifir kalir.
