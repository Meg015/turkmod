# İletişim Modülü Tasarımı

## Özet

Bu çalışma, mevcut projeye ayrı bir `İletişim` modülü ekler.
Amaç, üst menüden erişilebilen, profesyonel görünümlü, kategori bazlı ve spam korumalı bir public form ile mesaj toplamak; admin panelinde bu mesajları tek ekrandan takip etmek, tek seferlik yanıt yazmak ve yanıtı e-posta olarak göndermektir.

Konuşma geçmişi tutulmaz. Kullanıcı tarafında yanıt yazma alanı yoktur. Yönetim tarafı yalnızca kayıt, durum ve tek bir admin cevabı üzerinden ilerler.

## Hedefler

- Public tarafta `/iletisim` sayfası ile ulaşılabilen bir form sunmak.
- Kategorileri admin panelinden eklenip çıkarılabilir yapmak.
- Kategorileri ikonlu ve listeli göstermek.
- Üye kullanıcıları otomatik tanıyıp ad ve e-postayı salt okunur doldurmak.
- Misafir kullanıcılarda ad, e-posta, konu, mesaj alanlarını göstermek.
- Gönderim sonrası toast bildirim göstermek.
- Spam için rate limit ve temel bot koruması uygulamak.
- Admin panelinde mesajları listelemek, filtrelemek, durumlandırmak, kalıcı silebilmek ve tek seferlik yanıt yazabilmek.
- Admin yanıtını e-posta ile göndermek.

## Kapsam Dışı

- Çok adımlı destek talebi sistemi.
- Kullanıcı ile admin arasında gerçek zamanlı sohbet.
- Dosya ekleri.
- Çoklu yanıt geçmişi.
- Canlı bildirim sistemi.

## Public Deneyim

Public form tek sayfada, sade ve işlev odaklı olacak.
Sayfa topbardaki `İletişim` linkiyle açılacak.

Form alanları:

- Kategori
- Ad
- E-posta
- Konu
- Mesaj

Davranış:

- Kullanıcı giriş yapmışsa sistem adı ve e-postayı sunucu tarafında belirler.
- Bu alanlar formda görünür ama salt okunur olur.
- Kullanıcı giriş yapmamışsa ad ve e-posta alanları düzenlenebilir olur.
- Kategori alanı kart/radyo listesi şeklinde görünür.
- Gönderimden sonra toast gösterilir.
- JS varsa gönderim AJAX ile yapılır; JS yoksa klasik POST + flash geri dönüşü çalışır.

## Kategori Yönetimi

Kategoriler admin tarafından yönetilir.
Önerilen başlangıç kategorileri:

- Destek
- Reklam
- Öneri
- Şikayet
- DMCA & Telif

Her kategori şu alanları taşır:

- Ad
- Slug
- Bootstrap Icons sınıfı
- Sıralama
- Aktif/pasif durumu

Kategori kaldırma, varsayılan olarak pasife alma şeklinde çözülür.
Bu, eski mesajların tarihsel görünümünü korur.
Gerekirse kullanılmayan kategoriler için kalıcı silme ayrıca desteklenebilir.

## Admin Deneyimi

Admin tarafında ayrı bir `İletişim` sayfası olacak.
Tek sayfa içinde iki sekme yeterli:

1. Mesajlar
2. Kategoriler

Mesajlar sekmesi:

- Listeleme
- Durum filtresi
- Kategori filtresi
- Arama
- Detay görünümü
- Tek cevap alanı
- `çözüldü` işareti
- Kalıcı silme

Kategoriler sekmesi:

- Kategori ekleme
- Kategori düzenleme
- İkon seçimi
- Sıralama yönetimi
- Aktif/pasif kontrolü

Admin bir mesajı açtığında:

- Mesaj detayını görür
- Gönderici bilgilerini görür
- Tek cevap metni yazar
- İsterse yanıtı e-posta olarak gönderir
- İsterse mesajı çözüldü işaretler

Bu ekranda konuşma zinciri görünmez.
Sadece tek kayıt ve onun mevcut cevabı görünür.

## Veri Modeli

### `contact_categories`

Önerilen alanlar:

- `id`
- `name`
- `slug`
- `icon`
- `sort_order`
- `is_active`
- `created_at`
- `updated_at`

### `contact_messages`

Önerilen alanlar:

- `id`
- `category_id`
- `category_name_snapshot`
- `category_icon_snapshot`
- `user_id`
- `is_member`
- `sender_name`
- `sender_email`
- `subject`
- `message`
- `status`
- `seen_at`
- `admin_reply_body`
- `admin_reply_sent_at`
- `admin_reply_email_status`
- `admin_reply_email_error`
- `submitted_ip`
- `submitted_user_agent`
- `created_at`
- `updated_at`

Notlar:

- Mesaj kaydı kategori adını ve ikonu snapshot olarak saklar.
- Kullanıcı sonradan profil bilgisini değiştirirse eski kayıt bozulmaz.
- `status` için öneri: `new`, `replied`, `resolved`, `deleted`.
- `admin_reply_email_status` için öneri: `pending`, `sent`, `failed`.
- `seen_at` ayrı tutulur; admin kaydı açtığında otomatik işaretlenebilir.
- Tek bir `admin_reply_body` saklanır. Geçmiş tutulmaz.

## İş Akışı

1. Kullanıcı `/iletisim` sayfasını açar.
2. Sistem oturumdan üye kontrolü yapar.
3. Kategoriler aktif olanlardan listelenir.
4. Kullanıcı formu doldurur ve gönderir.
5. Sunucu CSRF, rate limit, honeypot ve alan doğrulaması yapar.
6. Mesaj veri tabanına kaydedilir.
7. Başarı toast'u gösterilir.
8. Admin panelinde mesaj görünür.
9. Admin cevabı yazar.
10. Sistem cevabı e-posta olarak gönderir.
11. Yanıt gönderildiyse mesaj durumu `replied` olur.
12. Admin ayrıca `çözüldü` işaretini verirse durum `resolved` olur.

## Spam / Güvenlik

Koruma katmanı şu parçalardan oluşur:

- CSRF doğrulaması
- IP bazlı rate limit
- E-posta bazlı rate limit
- Giriş yapmış kullanıcı için kullanıcı bazlı limit
- Honeypot alanı
- Alan uzunluğu sınırları

Önerilen yaklaşım, mevcut rate limit altyapısını yeniden kullanmaktır.
Limit anahtarları contact-submit için ayrı tutulur.

Varsayılan eşik önerisi:

- IP başına kısa pencere içinde birkaç gönderim
- Aynı e-posta için daha sıkı ama daha uzun pencere
- Üye kullanıcı için ayrıca kullanıcı bazlı limit

Bu ilk sürümde CAPTCHA eklenmez.
İhtiyaç oluşursa sonra eklenebilir.

## E-posta Yanıtı

Admin yanıtı mevcut `appSendMail()` altyapısıyla gönderilir.
Yanıt e-postası tek seferliktir.
Konu, orijinal konuya göre oluşturulur.

E-posta içeriği:

- Gönderici adı
- Kategori
- Orijinal konu
- Admin yanıtı
- Site imzası

Gönderim başarısız olursa:

- Mesajdaki yanıt metni korunur
- E-posta durumu `failed` olarak işaretlenir
- Admin tekrar gönderebilir

## Navigasyon

`İletişim` sayfası üst menüde sabit bağlantı olarak görünür.
Mobil menüde de erişilebilir olmalıdır.

Bu bağlantı tema menü yapılandırmasına gömülü olmaktan çok, doğrudan header içinde yer almalıdır.
Böylece kullanıcı için her zaman görünür kalır.

## Önerilen Modül Yapısı

- `includes/src/Modules/Contact/module.php`
- `includes/src/Modules/Contact/routes.php`
- `includes/src/Modules/Contact/Services/ContactSchemaService.php`
- `includes/src/Modules/Contact/Services/ContactMessageService.php`
- `includes/src/Modules/Contact/Services/ContactCategoryService.php`
- `includes/src/Modules/Contact/Services/ContactMailService.php`
- `includes/src/Modules/Contact/Database/migrations/...`
- `api/contact.php`
- `admin/contacts.php`

## Yetkiler

Önerilen izinler:

- `contact.view`
- `contact.manage`
- `contact.categories.manage`

Public form için izin gerekmez.
Admin sayfası ve aksiyonları izin kontrolü ister.

## Testler

Doğrulanması gerekenler:

- Üye giriş yaptıysa ad ve e-posta otomatik doluyor.
- Misafir kullanıcıda alanlar görünür ve zorunlu.
- Kategori listesi admin tarafından değişince public form güncelleniyor.
- Rate limit aşımı 429 döndürüyor.
- Mesaj kaydı admin panelinde listeleniyor.
- Admin yanıtı e-posta olarak gidiyor.
- Çözüldü ve silindi durumları çalışıyor.
- Toast başarı / hata durumunda doğru gösteriliyor.

## Başarı Kriteri

Bu iş tamam sayılır, eğer:

- Public kullanıcı tek tıkla iletişim formuna ulaşıyorsa,
- Üye / misafir ayrımı otomatik ve güvenliyse,
- Kategoriler admin tarafından yönetilebiliyorsa,
- Admin tek ekranda mesajı takip edip tek cevap gönderebiliyorsa,
- Yanıt e-posta olarak gönderiliyorsa,
- Spam koruması temel seviyede çalışıyorsa.
