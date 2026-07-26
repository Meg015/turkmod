# Ban Sirasinda Kullanici Yorumlarini Silme Tasarimi

## Amac

Admin panelindeki hem `Kullanicilar` hem de `Yorumlar` ekraninda acilan kullanici banlama modalina, kullanicinin yorumlarini da geri alinabilir bicimde silme secenegi eklemek.

Secenek varsayilan olarak kapali olacak. Isaretlendiginde kullanici banlanirken yalnizca halen gorunur olan yorumlari `deleted_at` alani doldurularak `Silinenler` gorunumune tasinacak.

## Kullanici Arayuzu

Iki ban modalinda da `Kullanicinin yorumlarini da sil` etiketli bir onay kutusu bulunacak.

- Kutu her modal acilisinda isaretsiz baslayacak.
- Kutu yalnizca hem kullaniciyi banlama hem de `comments.delete` yetkisine sahip yoneticilere gosterilecek.
- Modal acilirken kutu etiketi genel metinle gosterilecek; mevcut kullanici detay istegi tamamlandiginda `Kullanicinin 12 yorumunu da sil` biciminde aktif yorum sayisini gosterecek.
- Sayac, mevcut `api/user-details.php` yanitindaki `stats.total_comments` degerini kullanacak. Bu deger `deleted_at IS NULL` olan tum yorum durumlarini kapsayacak ve yeni bir ag istegi eklenmeyecek.
- Aktif yorum sayisi sifirsa kutunun varsa isareti kaldirilacak, kutu pasiflestirilecek ve etiket `Silinecek aktif yorum yok` olacak.
- Pozitif yorum sayisinda kutu etkin kalacak. Her modal acilisinda kutu etkin ve genel etiketle sifirlanacak; onceki kullanicinin sifir yorum durumu sonraki kullaniciya tasinmayacak.
- Kullanici detay istegi basarisiz olursa kutu genel etiketiyle kullanilabilir kalacak. Arayuzdeki sayi yalnizca bilgilendirme amacli olacak; gercek sonuc sunucunun tasidigi yorum sayisina dayanacak.
- Kutu isaretliyken son onay metni, banla birlikte yorumlarin da `Silinenler`e tasinacagini acikca belirtecek.
- Basarili sonuc mesaji `Silinenler`e tasinan yorum sayisini gosterecek.

## Yetkilendirme

Arayuzdeki gorunurluk tek basina guvenlik siniri sayilmayacak. Her iki ban endpoint'i de yorum silme secenegi gonderildiginde aktif yoneticinin `comments.delete` yetkisini sunucu tarafinda yeniden dogrulayacak.

Banlama yetkisi olup `comments.delete` yetkisi olmayan bir yonetici kullaniciyi normal sekilde banlayabilecek, ancak yorum silme secenegini kullanamayacak. Istegin elle degistirilerek bu secenegin gonderilmesi durumunda islem reddedilecek ve hicbir yorum silinmeyecek.

## Uygulama Yaklasimi

Yorumlari silme davranisi ortak bir yorum moderasyon yardimcisinda tutulacak ve iki ban akisi da ayni kodu kullanacak. Yardimci hedef kullanicinin `deleted_at IS NULL` kosulunu saglayan yorumlarini okuyacak, her yorumu geri alinabilir olarak silecek ve etkilenen yorum sayisini dondurecek.

Ortak yardimci mevcut tekil yorum silme davranisinin yan etkilerini koruyacak:

- Gorunen/onayli yorumlar icin konu yorum sayacini duzeltecek.
- Ayar etkinse yorumla acilan indirme erisimini geri alacak.
- Onayli yorumlardan kazanilan etkinlik puanlarini geri alacak.
- Islem tamamlandiginda genel icerik onbellegini gecersiz kilacak.

Yorum medyasi ve iliskili kayitlar kalici olarak silinmeyecek. Mevcut soft-delete davranisinda oldugu gibi yorum `Silinenler` gorunumunden geri yuklenebilir olacak.

## Islem Akisi ve Tutarlilik

Ban kaydi ile secili yorum silme islemleri tek veritabani transaction'i icinde calisacak:

1. Hedef kullanici, ban gerekcesi ve secenek dogrulanir.
2. Yorum silme istenmisse `comments.delete` yetkisi dogrulanir.
3. Kullanici banlanir.
4. Yorum silme istenmisse kullanicinin aktif yorumlari `Silinenler`e tasinir ve gerekli yan etkiler uygulanir.
5. Ban audit kaydina yorum silme secenegi ile etkilenen yorum sayisi eklenir.
6. Transaction basariyla tamamlanir ve sonuc dondurulur.

Banlama veya yorumlardan herhangi birini isleme adimi basarisiz olursa transaction geri alinacak; kullanici banlanmis fakat yorumlari kismen silinmis gibi ara bir durum olusmayacak.

Ban kaldirma islemi yorumlari otomatik olarak geri yuklemeyecek. Yorumlarin geri alinmasi, mevcut `Yorumlar > Silinenler` akisi uzerinden ayri bir moderasyon karari olacak.

## Kapsam Sinirlari

- Yalnizca hedef kullanicinin kendi yorumlari silinecek; bu yorumlara baska kullanicilar tarafindan verilen yanitlar topluca silinmeyecek.
- Daha once silinmis yorumlara dokunulmayacak ve basari sayisina dahil edilmeyecek.
- Kalici silme yapilmayacak.
- Toplu kullanici islemleri ve kullanici duzenleme sayfasindaki ban alani bu degisikligin disinda kalacak.
- Ban geri alma islemi yorum silme islemini geri almayacak.

## Hata Yonetimi

- Bos ban gerekcesi mevcut davranistaki gibi reddedilecek.
- Gecersiz hedef kullanici veya yoneticinin kendi hesabini banlama girisimi reddedilecek.
- Yorum silme yetkisi olmayan yoneticinin degistirilmis istegi acik bir yetki hatasi dondurecek.
- Veritabani hatalari transaction'i geri alacak ve mevcut admin hata sunumuyla guvenli bir mesaj gosterecek.
- Silinecek aktif yorum bulunmamasi banlamayi engellemeyecek; sonuc sifir yorum tasindigini belirtecek.

## Testler

Otomatik testler su davranislari kapsayacak:

- Secenek kapaliyken kullanici banlanir ve yorumlar degismez.
- Secenek acikken aktif yorumlar soft-delete edilir, daha once silinen yorumlar degismez.
- Konu yorum sayaclari, indirme erisimleri ve etkinlik puanlari mevcut tekil silme kurallariyla uyumlu guncellenir.
- `comments.delete` yetkisi olmayan yoneticinin yorum silmeli ban istegi reddedilir.
- Yorum silme sirasinda hata olursa ban ve yorum degisikliklerinin tamami geri alinir.
- Her iki modal da ayni alan adini gonderir, varsayilan olarak isaretsizdir ve yetkiye gore gosterilir.
- Her iki modal mevcut kullanici detay yanitindan aktif yorum sayisini etikete yansitir; sifir, pozitif ve yukleme hatasi durumlari dogru gosterilir.
- Sifir yorumda checkbox isaretsiz ve disabled olur; modal yeniden acildiginda veya pozitif sayili kullaniciya gecildiginde yeniden etkinlesir.
- Basari mesaji tasinan yorum sayisini dogru bildirir.
