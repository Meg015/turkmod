# Kalıcı kullanıcı silme

## Onaylanan davranış

Kullanıcı Yönetimi listesinin işlem menüsüne `Kullanıcıyı Sil` eklenir. Kullanıcı iki seçenekli tasarımı 1 Kasım 2026 tarihinde onayladı:

- `İçerikleri koru` varsayılandır. Hesap fiziksel olarak silinir; konular, yorumlar ve korunan içeriklerin dosya/editleme kayıtları oturum açamayan, herkese kapalı `Silinmiş Kullanıcı` hesabına aktarılır.
- `İçerikleri de sil` hesabı, kullanıcının tüm konularını ve yorumlarını fiziksel olarak siler. Silinen konulardaki diğer kullanıcı yorumları da silinir. Diğer konularda, silinen yorumlara verilen başkalarına ait yanıtlar kök yoruma çevrilerek korunur.

Pasife alma ve yalnızca içerik silme yaklaşımları, istenen kalıcı hesap silmeyi karşılamadıkları için seçilmedi.

## Güvenlik ve arayüz

POST ve CSRF doğrulaması gerekir. `users.delete` yetkisi gerekir; içerikleri silme seçeneği ayrıca `topics.delete` ve `comments.delete` ister. Kendi hesabını ve içeriklerin devredildiği sistem hesabını silmek engellenir. Modal, kullanıcı adının yazılmasını, gerekçe ve geri alınamaz işlem onayı ister. Başarısız istek modalı açık tutar; gönderim sürerken ikinci istek engellenir.

## Veri işlemi

Ayrı bir kullanıcı silme servisi tek transaction kullanır; dış transaction varsa savepoint kullanır. Hesap kilitlenerek tekrar okunur. Varsayılan seçenek için sistem hesabı mevcut `users` alanlarıyla oluşturulur: geçersiz parola, pasif durum, `deleted_at`, kapalı profil ve grup üyeliği yok. Sistemin kullanıcı listeleri bu hesabı göstermez.

FK kaskatları bağımlı modül verilerini temizler. FK olmayan grup üyelikleri/geçmişi, itirazlar, bekleyen e-postalar ve diğer açık kullanıcı referansları ayrıca ele alınır. Konu/yorum silmede sayaçlar ve bot içe aktarma ilişkileri düzeltilir. Korunan içerik dosyaları korunur. Kalıcı silinen dosyalar ve avatar yalnızca başarılı commit sonrasında, uploads sınırları içinde ve başka kayıtta kullanılmıyorsa temizlenir. İşlem gerekçesi, yönetici, eski kullanıcı ID'si, seçenek ve sayılar mevcut günlük altyapısına kaydedilir. Hata tüm veritabanı değişikliklerini geri alır.

## Doğrulama

Geçici SQLite ve MySQL veritabanlarında iki seçenek; yanıt koruma; dosya/editleme ilişkileri; izinsiz işlem; kendi hesabı/sistem hesabı; yanlış kullanıcı adı; eksik kullanıcı; transaction geri alma ve tekrar silme denenir. PHP ve JS sözdizimi denetlenir. Gerçek kullanıcı silinmez. Mevcut çalışma alanındaki ilgisiz değişiklikler korunur.

## Kullanıcının istediği arayüz düzeltmesi

Silme sonrasında ikinci bir başarı popupı açılmaz. Liste hemen yenilenir ve sonuç mevcut sayfa içi bildiriminde bir kez gösterilir. Sıfır konu/yorum sayıları mesaja eklenmez; dosya temizleme sorunu varsa bildirim uyarı rengindedir.

Kalıcı silme penceresi masaüstünde 480 px genişlikte, dar ekranda kullanılabilir genişlikte olur. Başlık, kısa geri alınamaz işlem açıklaması, tek grupta iki içerik seçeneği, kısa gerekçe alanı, kullanıcı adı doğrulaması ve son onay kompakt aralıklarla gösterilir. Mobilde gövde kaydırılır ve işlem düğmeleri erişilebilir kalır. Mevcut izin, doğrulama ve çift gönderim engeli korunur.
