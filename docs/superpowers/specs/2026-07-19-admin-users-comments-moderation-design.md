# Admin Kullanıcı ve Yorum Moderasyonu Tasarımı

## Amaç

Admin Paneli'nde kullanıcı ve yorum moderasyonu akışını daha bağlamlı ve güvenilir hale getirmek.

Bu çalışma üç ana sorunu çözecek:

- Kullanıcılar sekmesindeki üç nokta işlem menüsü tablo içinde kaybolmayacak.
- Ban, ban kaldırma ve kısıtlama popupları aktif durumu ve son 5 geçmiş kaydı gösterecek.
- Yorum Yönetimi varsayılan olarak nested yorum bağlamı gösterecek ve yorum üzerinden kullanıcı banlama/kısıtlama yapılabilecek.

## Mevcut Durum

Kullanıcılar sekmesinde işlem menüsü `admin/users-tabs/users.php` içinde native `<details>` popover olarak açılıyor. Menü tablo ve scroll container sınırları içinde kaldığı için bazı satırlarda sayfada kaybolabiliyor veya kesilebiliyor.

Ban durumu `users` tablosunda `is_banned`, `banned_at` ve `ban_reason` alanlarıyla tutuluyor. Ban ve ban kaldırma geçmişi için en uygun mevcut kaynak `admin_action_log` içindeki `ban` ve `unban` kayıtları.

Kısıtlamalar `user_restrictions` tablosunda tutuluyor. Aktif kısıtlamalar zaten helper fonksiyonlarıyla okunabiliyor; geçmiş görünümü için aynı tablodan son kayıtlar alınabilir.

Yorum Yönetimi `admin/comments-manager.php` içinde düz liste halinde çalışıyor. Sorgu yorum sahibini alıyor, ancak konu başlığı/slug bilgisini ve parent yorum bağlamını liste görünümüne taşımıyor. Sayfanın altında "Yorum Önizlemesi" ve "Hızlı İşlem" blokları bulunuyor; bu bloklar gerçek moderasyon akışına yeterince bağlı değil.

## Kullanıcılar Sekmesi İşlem Menüsü

Üç nokta işlem menüsü tabloya bağlı kalmadan açılacak şekilde düzenlenecek.

Davranış:

- Menü tetikleyici butonla açılıp kapanacak.
- Açılan menü viewport sınırına göre konumlanacak.
- Yeterli alt boşluk yoksa yukarı doğru açılacak.
- Dışarı tıklama, Escape tuşu ve başka bir menüyü açma mevcut menüyü kapatacak.
- Mobilde tablo yatay scroll kullanırken menü satır içinde okunabilir kalacak.

Bu davranış `admin/assets/users-tab.js` içinde küçük bir menü yöneticisiyle kurulacak. CSS tarafında `admin/assets/admin.css` menünün açık, yukarı açılan ve mobil durumlarını destekleyecek.

## Ban ve Ban Kaldırma Popupları

Ban modalı ve ban kaldırma onayı, işlem öncesinde kullanıcı hakkında moderasyon bağlamı gösterecek.

Gösterilecek bilgiler:

- Aktif ban varsa tarih ve gerekçe.
- Aktif ban yoksa açık bir "Aktif ban yok" durumu.
- Son 5 ban/ban kaldırma işlemi.
- Her geçmiş kaydında işlem türü, tarih, admin adı ve gerekçe.

Veri kaynağı:

- Aktif ban: `users.is_banned`, `users.banned_at`, `users.ban_reason`.
- Geçmiş: `admin_action_log` filtreleri `target_type = user`, `target_id = user_id`, `action_type IN (ban, unban)`.

Ban geçmişi bulunamazsa popup boş liste göstermek yerine "Geçmiş kayıt yok" mesajı verecek.

## Kısıtlama Popupları

Kısıtla modalı aktif kısıtlamaları ve son 5 kısıtlama geçmişini gösterecek.

Gösterilecek bilgiler:

- Aktif kısıtlamalar: tür, bitiş tarihi, gerekçe ve ekleyen admin.
- Son 5 geçmiş kayıt: tür, başlangıç tarihi, bitiş tarihi, aktif/geçmiş durumu, gerekçe ve admin.
- Aktif kısıtlama yoksa "Aktif kısıtlama yok" durumu.

Veri kaynağı:

- Aktif kısıtlamalar: mevcut `usersGetRestrictions()` davranışı.
- Geçmiş: `user_restrictions` tablosundan ilgili kullanıcı için `created_at DESC, id DESC LIMIT 5`.

Kısıtlamalar eklenirken mevcut çoklu seçim davranışı korunacak.

## Yorum Yönetimi Varsayılan Nested Görünüm

Yorum Yönetimi'nde varsayılan görünüm nested konuşma bağlamı olacak. Amaç, adminin yalnızca yorumu değil, yorumun hangi konuya ve hangi parent yoruma bağlı olduğunu görebilmesi.

Davranış:

- Liste filtreleri korunacak: tümü, bekleyen, onaylı, reddedilmiş, silinmiş.
- Filtreye uyan yorumlar ana sonuç setini belirleyecek.
- Eğer sonuçtaki yorum bir cevapsa parent yorum kısa bağlam olarak gösterilecek.
- Aynı kök yorum altında görünen çocuk yanıtlar içe girintili gösterilecek.
- Derin zincirlerde okunabilirliği korumak için görsel girinti sınırlı tutulacak.
- Moderasyon işlemleri hedef yorum üzerinde yapılacak; parent context yalnızca bağlam olacak.

Sorgu tarafında yorumlarla birlikte şu bilgiler alınacak:

- `topics.id`, `topics.title`, `topics.slug`
- `comments.parent_id`
- Parent yorumun kısa gövdesi ve yazarı

Sayfadaki sonuçların parent kayıtları ikinci bir sorguyla tamamlanacak. Böylece ana filtre sonucu bozulmadan nested bağlam sağlanacak.

## Yorum Kartı İçeriği

Her yorum kartında şu bilgiler bulunacak:

- Yorum sahibi.
- Yorum tarihi.
- Yorum durumu.
- Reaksiyon sayısı.
- Yorum ID'si.
- Konu başlığı.
- Cevap ise yanıtlanan yorumdan kısa alıntı.
- Yorum gövdesi.
- `Yoruma git` butonu.

`Yoruma git` bağlantısı `topicUrl(slug, id) . '#comment-' . comment_id` formatında açılacak. Konu silinmiş veya slug eksikse buton güvenli fallback ile konu ID tabanlı URL üretmeye çalışacak; URL üretilemiyorsa buton gösterilmeyecek.

## Yorum İşlemleri Dropdownu

Yorum kartlarında da tek bir işlem dropdownu kullanılacak.

Yorum işlemleri:

- Düzenle.
- Bekleyen yorum için onayla.
- Bekleyen yorum için reddet.
- Sil.
- Silinmiş yorum için geri yükle.
- Yoruma git.

Kullanıcı işlemleri:

- Yorum sahibini banla.
- Kullanıcı banlıysa ban kaldır.
- Yorum sahibini kısıtla.

Kurallar:

- Yorum sahibinin kullanıcı ID'si yoksa kullanıcı moderasyon işlemleri gösterilmeyecek.
- Admin kendi hesabına karşı ban/kısıtlama işlemi göremeyecek.
- İşlemler mevcut izin kontrollerine bağlı kalacak.
- Yorum işlemleri `comments.edit` veya `comments.delete`, kullanıcı işlemleri `users.edit` izni gerektirecek.

Yorum Yönetimi, kullanıcı sayfasındaki ban ve kısıtlama modal davranışını aynı alan adları ve aynı POST aksiyonlarıyla kullanacak. İlk uygulama dar kapsamlı kalmak için gerekli modal HTML'ini yorum yönetimi sayfasına ekleyecek; ortak partial çıkarma bu çalışmanın kapsamı dışında kalacak.

## Kaldırılacak Alanlar

Yorum Yönetimi'nden şu alanlar kaldırılacak:

- Yorum Önizlemesi.
- Hızlı İşlem.
- Gerçek iş akışına katkı sağlamayan moderasyon tanıtım metni.

Sol filtre ve özet alanları korunacak, ancak sayfa dili liste ve aksiyon odaklı sadeleştirilecek.

## Veri Akışı

Kullanıcılar sekmesinde:

1. Satırdaki işlem butonu açılır.
2. Ban veya kısıtla seçildiğinde kullanıcı ID'siyle moderasyon özeti yüklenir.
3. Modal aktif durum ve son 5 geçmiş kaydıyla açılır.
4. Form gönderildiğinde mevcut `users.php` POST aksiyonları çalışır.

Yorum Yönetimi'nde:

1. Filtre ve sayfalama ana yorum sonuçlarını belirler.
2. Sonuçlar konu ve parent bağlamıyla zenginleştirilir.
3. Kartlar nested görsel yapı içinde render edilir.
4. Yorum dropdownu yorum ve kullanıcı işlemlerini tek menüde sunar.
5. Ban/kısıtla işlemleri mevcut kullanıcı moderasyon POST akışına bağlanır.

## Hata ve Boş Durumlar

- Moderasyon geçmişi yüklenemezse modal işlem yapmayı engellemez; yalnızca geçmiş alanında hata/boş durum gösterir.
- Parent yorum silinmişse "Yanıtlanan yorum silinmiş" benzeri kısa bağlam gösterilir.
- Konu bulunamazsa konu alanı "Konu bulunamadı" olarak görünür ve `Yoruma git` butonu gizlenir.
- Kullanıcı silinmiş veya anonimse kullanıcı moderasyon butonları gizlenir.
- Admin kendi hesabına ban/kısıt işlemi başlatamaz.

## Test ve Doğrulama

Otomatik veya komut satırı kontrolleri:

- Değişen PHP dosyaları için `php -l`.
- Değişen JavaScript dosyalarında temel syntax kontrolü.
- Mevcut test komutu hızlı çalıştırılabiliyorsa ilgili dar kapsamlı testler.

Manuel doğrulama:

- Kullanıcılar sekmesinde ilk, orta ve son satırdaki üç nokta menüsü kesilmeden açılmalı.
- Menü dışarı tıklama ve Escape ile kapanmalı.
- Ban modalı aktif ban ve son 5 ban geçmişini göstermeli.
- Ban kaldırma akışı mevcut ban bağlamını göstermeli.
- Kısıtla modalı aktif kısıtları ve son 5 kısıtlama kaydını göstermeli.
- Yorum Yönetimi nested görünümle açılmalı.
- Cevap yorumlarında parent context görünmeli.
- Konu başlığı ve `Yoruma git` butonu doğru çalışmalı.
- Yorum dropdownundan yorum işlemleri çalışmalı.
- Yorum dropdownundan kullanıcı ban/kısıt işlemleri çalışmalı.
- "Yorum Önizlemesi" ve "Hızlı İşlem" alanları artık görünmemeli.

## Kapsam Dışı

- Yeni veritabanı tablosu eklemek.
- Ban ve kısıtlama sistemini baştan yazmak.
- Sınırsız derinlikte tam konuşma ağacı kurmak.
- Ayrı bir yorum moderasyon API'si oluşturmak.
- Kullanıcı detay modalını kapsamlı şekilde yeniden tasarlamak.
