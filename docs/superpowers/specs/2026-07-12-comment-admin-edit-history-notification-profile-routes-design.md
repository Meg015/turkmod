# Admin Yorum Duzenleme Gecmisi, Bildirim ve Profil Rotalari Tasarimi

## Amac

Admin panelinden veya yorum duzenleme yetkisine sahip baska bir hesap tarafindan degistirilen yorumlarin public konu sayfasindaki duzenleme gecmisinde gorunmesini saglamak, yorum sahibini Bildirim Servisi ile bilgilendirmek ve tema icinde eski `profile.php` baglantilarindan kaynaklanan 404 hatalarini kaldirmak.

## Kapsam

- Admin yorum yonetimindeki `edit` islemi, public yorum API'siyle ayni revizyon kaydi davranisini kullanacak.
- Degisen yorumun eski ve yeni metni, duzenleyen kullanici ve zaman bilgisi `comment_edit_history` kaydinda tutulacak.
- Public `edit_history` API'si mevcut yetkilendirme kurallarini koruyarak admin kaynakli revizyonlari da dondurecek.
- Yorum sahibi disinda bir admin veya `comments.edit` yetkilisi yorumu degistirdiginde yorum sahibine bildirim gonderilecek.
- Kullanicinin kendi yorumunu duzenlemesi bildirim uretmeyecek.
- Tema ve uygulama icindeki dahili ozel profil baglantilari canonical profil rotasindan uretilecek; sorgu parametreleri ve sekme bilgileri korunacak.

## Mimari

### Yorum duzenleme

Yorum duzenlemenin ortak davranisi yorum motorunun mevcut legacy helper sinirinda toplanacak. Yardimci; hedef yorumu, yeni metni ve duzenleyen kullanici kimligini alacak, metin gercekten degismisse revizyon kaydini olusturacak ve yorumun `body`, `is_edited`, `edited_at` ve `updated_at` alanlarini mevcut semaya uygun bicimde guncelleyecek.

Admin controller ve public API kendi yetki, CSRF ve istek dogrulamalarini koruyacak; kalici degisiklik icin ortak yardimciyi cagiracak. Mevcut indirme erisimi calismasi dahil kirli calisma agacindaki degisiklikler korunacak.

### Bildirim

Bildirim olayi Notifications modulunun `NotificationPreferenceService` tanimlarina `comment_edited_by_staff` anahtariyla eklenecek. Gonderim `notificationDispatch()` uyumluluk yardimcisi uzerinden modulun `NotificationDispatchService` katmanina delege edilecek.

Bildirim kosullari:

- yorumun kayitli bir sahibi bulunmali;
- duzenleyen kullanici yorum sahibinden farkli olmali;
- metin gercekten degismis olmali;
- duzenleme basariyla kaydedilmis olmali.

Bildirim; duzenleyenin gorunen adi, konu basligi, yorumdan kisa bir alinti ve `#comment-{id}` hedefli canonical konu baglantisini tasiyacak. Her basarili farkli revizyon bildirim uretebilmeli; dedupe anahtari revizyon kaydi veya duzenleme zamaniyla benzersizlestirilecek. Bildirim hatasi yorum duzenlemesini geri almayacak, fakat uygulama gunlugune yazilacak.

### Profil ve benzer dahili rotalar

Ozel profil ana yolu `routeCanonicalPath('profile')` ile uretilecek. Tema header verisine `profile_url` eklenecek ve `themes/turkmod/modules/user-menu.tpl` bu degiskeni kullanacak.

Sabit `/profile.php` kullanan dahili yonlendirme, form action ve profil sekmesi baglantilari taranacak. Ozel profil hedefleri canonical profil URL'sine gecirilecek; public kullanici profilleri `publicProfileUrl()` kullanmaya devam edecek. `?tab=...`, filtre ve durum parametreleri URL yardimci fonksiyonuyla guvenli bicimde eklenecek. Eski script yollarinin dis entegrasyon uyumlulugu icin calismasi engellenmeyecek; uygulamanin yeni HTML ve redirect ciktilari friendly URL uretecek.

## Hata ve Guvenlik Davranisi

- Admin ve API yetki kontrolleri gevsetilmeyecek.
- Bos metin ve gecersiz yorum kimligi mevcut davranisla reddedilecek.
- Revizyon tablosu kullanilabilir degilse hata sessizce yutulmayacak; duzenleme akisinin nasil davranacagi mevcut runtime schema politikasina uygun ve loglanabilir olacak.
- Yorum gecmisi yalnizca mevcut API yetki kosullarini saglayan kullanicilara acilacak.
- Bildirim servisi kullanici tercihlerini ve global bildirim ayarlarini uygulamaya devam edecek.

## Dogrulama

- Degisen PHP dosyalarinda `php -l`.
- Ortak yorum duzenleme yardimcisi icin geri alinabilir veritabani smoke testi:
  - admin/yetkili duzenlemesinde tek revizyon kaydi;
  - revizyonda dogru editor kimligi, eski ve yeni metin;
  - yorum sahibi disinda duzenlemede bildirim;
  - kendi yorumunu duzenlemede bildirim olmamasi;
  - ayni metnin yeniden gonderilmesinde gereksiz revizyon/bildirim olmamasi.
- Admin oturumuyla gercek admin duzenleme akisinin calistirilmasi ve public `Geçmişi gör` modalinda editor adinin gorulmesi.
- Topbar `Profilim` baglantisinin `/yenidosyalar/profil` hedefine gitmesi ve 404 vermemesi.
- Kaynak taramasiyla kullaniciya sunulan kalan sabit `profile.php` baglantilarinin siniflandirilmasi ve dahili olanlarin canonical URL'ye gecirilmesi.
- Friendly profil rotasinda HTTP durum kodu, body page-key ve temel profil DOM durumunun kontrolu.

## Basari Olcutleri

- Admin panelinden yapilan yorum duzenlemesi public gecmiste gorunur.
- Gecmiste duzenleyen admin/yetkilinin adi dogru gorunur.
- Yorum sahibi yalnizca baska biri yorumunu degistirdiginde bildirim alir.
- Topbar ve diger dahili ozel profil baglantilari route ayarlarindaki canonical `/profil` yolunu kullanir.
- Mevcut kullanici degisiklikleri ve indirme erisimi calismasi bozulmaz.
