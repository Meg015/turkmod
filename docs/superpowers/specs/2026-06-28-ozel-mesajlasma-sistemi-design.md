# Ozel Mesajlasma Sistemi Tasarimi

Tarih: 2026-06-28  
Konu: Uyelere birebir mesajlasma, tek seferde okundu bilgisi ve bildirim baglantisi

## Amac

Uyelere profesyonel, sade ve guvenilir bir birebir mesajlasma alani sunmak.
Kullanici bir baskasina mesaj gonderebilmeli, sohbet listesini gorebilmeli, sohbeti actiginda okunmamis mesajlar tek seferde okundu sayilmali ve gonderici taraf bu durumu gorebilmelidir.

## Kapsam

Bu tasarim sadece birebir mesajlasmayi kapsar.

Kapsama dahil olanlar:
- Iki uye arasinda tekil sohbet olusturma
- Mesaj gonderme ve mesaj gecmisi goruntuleme
- Sohbet acilinca o sohbet icindeki tum okunmamis mesajlari tek seferde okundu isaretleme
- Gonderen taraf icin `Okundu` bilgisinin gorunmesi
- Mesajlar sayfasi, header giri noktasi ve profil sayfasi CTA'si
- Mesajlar icin unread rozet ve kisa onizleme
- Mesajla ilgili in-app bildirim destegi

Kapsama dahil olmayanlar:
- Grup sohbetleri
- Dosya / resim / ses eki
- Mesaj duzenleme veya silme
- Mesaji geri alma
- Kullanici engelleme / sessize alma
- Typing indicator
- Email veya push bildirim zorunlulugu
- Admin moderasyon paneli

## Mevcut Durum

Kod tabani zaten benzer ve saglam bir desen kullaniyor:
- Bildirim merkezi icin ayrik sayfa ve okundu API'si var.
- Header, dropdown ve rozet mantigi module bazli besleniyor.
- Public profil sayfasi eylem CTA'lari icin uygun bir yer sunuyor.
- Module yapisi `module.php`, `routes.php`, `Http/`, `Services/` ve `Database/migrations/` katmanlariyla calisiyor.

Bu nedenle mesajlasma icin paralel bir mimari yerine ayni module desenini yeniden kullanmak en dogru yol.

## Secilen Yaklasim

Seckilen yaklasim:
- Yeni bir `Messages` modulu
- Canonical public rota: `/mesajlar`
- Ayri bir mesaj listesi + aktif sohbet gorunumu
- Sohbet okunma durumunu thread bazli cursor ile tutma
- Bildirim entegrasyonunu mevcut notification altyapisiyla birlikte kullanma

Neden bu yol:
- Var olan public theme ve module yapisina uyuyor
- Sonradan grup sohbetine genisletmek kolay oluyor
- Okundu bilgisini tek seferde ve temiz sekilde islemeye izin veriyor
- Header ve profil gibi mevcut entry point'lerle dogal uyum sagliyor

## Tasarim Ilkeleri

- Arayuz sakin ve islev odakli olacak
- Buyuk hero, dekoratif kart kalabaligi ve gorsel gosteris kullanilmayacak
- Desktop'ta iki kolon, mobilde tek kolon calisacak
- Avatar, isim, son mesaj ve zaman bilgisinin hepsi hizli taranabilir olacak
- Mesaj balonlari, composer ve rozetler mevcut design system ile ayni ritimde kalacak
- Bos durumlar ve hata durumlari inline ve net olacak

## Veri Modeli

### 1. `message_threads`

Iki kullanici arasindaki sohbetin tek kaydini tutar.

Onerilen alanlar:
- `id`
- `thread_key`
- `last_message_id`
- `last_message_at`
- `created_at`
- `updated_at`

Kurallar:
- `thread_key`, iki uye ID'sinden uretilen canonical anahtar olacak
- Ayni iki uye icin ikinci bir thread acilmamasi bu anahtar ile garanti edilecek
- Thread listesi son aktiviteye gore siralanacak

Indexler:
- `UNIQUE(thread_key)`
- `INDEX(last_message_at)`
- `INDEX(last_message_id)`

### 2. `message_thread_participants`

Thread icindeki katilimci durumunu tutar.

Onerilen alanlar:
- `id`
- `thread_id`
- `user_id`
- `last_read_message_id`
- `last_read_at`
- `created_at`
- `updated_at`

Kurallar:
- Her thread icin iki participant satiri olusur
- `UNIQUE(thread_id, user_id)` ile tekrar engellenir
- Okundu durumu bu tablodaki cursor uzerinden hesaplanir

Indexler:
- `UNIQUE(thread_id, user_id)`
- `INDEX(user_id, last_read_at)`

### 3. `message_messages`

Gercek mesaj metni burada tutulur.

Onerilen alanlar:
- `id`
- `thread_id`
- `sender_user_id`
- `body`
- `created_at`
- `updated_at`

Kurallar:
- Mesajlar plain text olarak saklanir
- HTML input kabul edilmez
- Mesaj uzunlugu server tarafinda sinirlanir
- Mesajlar sildirme ve duzenleme olmadan v1 olarak calisir

Indexler:
- `INDEX(thread_id, id)`
- `INDEX(thread_id, created_at)`
- `INDEX(sender_user_id, created_at)`

## Okundu Mantigi

Okundu bilgisi tek tek mesaj bazinda degil, thread bazli cursor ile islenir.

Davranis:
- Kullanici sohbeti actiginda o thread icindeki okunmamis mesajlarin cursor'u en son mesaja cekilir
- Bu islem tek seferde yapilir
- Gonderici, kendi mesajinin okunup okunmadigini karsi tarafin cursor'una bakarak gorur
- Thread listesinde unread sayaci, son okunan mesajdan sonra gelen karsi taraf mesajlari sayarak uretilir

Bu modelin sonucu:
- Sohbet acildi mi, o akistaki tum okunmamislar temizlenir
- Tek mesajlik read receipt yerine daha saglam bir sohbet okuma modeli elde edilir

## UI Akisi

### Header

- Bildirim ikonunun yanina mesaj ikonu eklenir
- Mesaj ikonunda unread rozet bulunur
- Ikon acilinca son konusmalarin hizli bir dropdown gorunumu gosterilir
- Dropdown icinde avatar, isim, son mesaj onizlemesi ve zaman yer alir
- "Tum mesajlari gor" linki ana mesajlar sayfasina gider

### Profil sayfasi

- Public profil ust aksiyonlarina `Mesaj gonder` CTA'si eklenir
- CTA, hedef kullanici icin mevcut thread varsa onu acar, yoksa thread baslatir
- Sikayet aksiyonuyla ayni seviyede konumlanir, ama ondan daha baskin olmaz

### Mesajlar sayfasi

Desktop gorunum:
- Sol kolon: sohbet listesi
- Sag kolon: aktif sohbet

Mobil gorunum:
- Once sohbet listesi
- Sohbet acilinca aktif sohbet tam ekran gorunur
- Geri donus kullanici akisini bozmadan listeden secime doner

Sohbet listesi satiri:
- Avatar
- Kullanici adi
- Son mesaj onizlemesi
- Zaman
- Unread pill

Aktif sohbet:
- Ust baslikta karsidaki kullanicinin adi ve avatar'i
- Mesaj balonlari solda / sagda hizalanir
- Kullanicinin kendi mesajlarinda durum metni gorunur
- Alttaki composer sticky kalir

## Veri Akisi

1. Kullanici mesajlar sayfasini acar.
2. Sistem kullaniciya ait thread listesini ve okunmamis sayisini yukler.
3. Kullanici bir thread secer veya yeni kisi arar.
4. Thread acilinca participant cursor'u guncellenir.
5. Okunmamis mesajlar tek seferde okundu sayilir.
6. Kullanici mesaj yazar ve gonderir.
7. Mesaj transaction icinde kaydedilir, thread son aktivitesi guncellenir.
8. Karsi tarafa mesaj bildirimi uretilir.
9. Gonderici taraf daha sonra bu thread icin `Okundu` etiketini gorur.

## Bildirim Entegrasyonu

Mesaj sistemi kendi unread sayacini ve thread durumunu kendisi tutar.

Ek olarak:
- Yeni mesaj icin mevcut notification altyapisina bir in-app bildirim yazilir
- Bu bildirim thread URL'sine baglanir
- Bildirim tarafi best-effort calisir; mesaj kaydi basariliysa notification hatasi sohbeti bozmaz

Boylece mesaj sistemi, notification merkezinden bagimsiz calisir ama onunla da uyumlu kalir.

## Route ve Entry Point'ler

Onerilen public rota:
- `mesajlar`

Legacy uyumluluk:
- `messages.php` veya baska eski girisler olursa canonical rotaya yonlendirilir

Yeni entry point'ler:
- Header mesaj ikonu
- Kullanici menusu `Mesajlar`
- Public profil `Mesaj gonder`

## API Yuzeyi

Tek, sade bir JSON API controller yeterlidir.

Onerilen isler:
- Thread listesi alma
- Thread acma ve okundu cursor guncelleme
- Mesaj gonderme
- Kullanici arama

Bu controller CSRF, login kontrolu ve standart `ApiResponse` yardimcilari ile calisir.

## Hata Yonetimi

- Bos mesaj reddedilir
- Kullanici kendine mesaj gonderemez
- Hedef kullanici bulunamazsa anlamli hata verilir
- Thread zaten varsa yeni thread yaratilmaz
- Notification entegrasyonu hata verirse mesaj akisi durmaz
- Sayfa / API hatalari kullaniciyi login disinda kirmadan ele alinmali

## Guvenlik ve Kurallar

- Yalnizca giris yapmis uye mesaj gonderebilir
- Pasif, silinmis veya erisim disi hesaplara mesaj akisi acilmaz
- HTML / script kabul edilmez
- CSRF korumasi zorunludur
- Orta duzey rate limit uygulanir
- Her thread icin tek canonical kayit bulunur

## Test Stratejisi

Fonksiyonel testler:
- Iki farkli uye arasinda thread olusur
- Ayni iki uye icin ikinci thread acilmaz
- Mesaj gonderildiginde thread son aktivitesi guncellenir
- Thread acilinca okunmamis mesajlar tek seferde okundu olur
- Gonderici, karsi taraf okudugunda `Okundu` durumunu gorur

UI testleri:
- Header mesaj rozetinin gorunmesi
- Mesajlar sayfasinda desktop iki kolon, mobil tek kolon davranisi
- Profil CTA'sinin thread acma davranisi
- Bos durum ve hata durumlarinin patlamadan gorunmesi

Entegrasyon testleri:
- Notification kaydi olusur
- Notification hatasi mesaj kaydini bozmaz
- Login olmayan kullanici login'e yonlendirilir

## Uygulama Notlari

- Theme tarafinda mevcut notifications diliyle uyumlu ikon, rozet ve panel ritmi korunacak
- Profil, header ve mesajlar sayfasi ayni design system token'larini kullanacak
- Bu ozellik icin yeni admin ekran gerekmez
- V1'de sohbet, okundu bilgisi ve bildirim baglantisi yeterlidir

## Acik Kararlar

- Sadece birebir mesajlasma yapilacak
- Grup sohbeti v1 kapsaminda degil
- Her uye diger uyelere mesaj atabilecek
- Sohbet acilinca tum okunmamis mesajlar tek seferde okundu sayilacak
- Gonderici taraf icin `Okundu` durumu gorunur olacak
- Mesaj sistemi profesyonel ama sade bir arayuzle sunulacak
