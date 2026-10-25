# Bildirim merkezi e-posta uygulama planı

Amaç: Olay e-postalarının test edilmesini sağlamak ve konu onay/yayım e-postalarını tamamlamak.
Tasarım: `docs/superpowers/specs/2026-10-25-notification-email-design.md`.

- [x] Test gönderimini `NotificationEmailQueueService::sendTest(PDO, int, string, array, array): bool` üzerinden doğrudan posta sürücüsüne bağla. Yönetici formunda kuyruk bağımlılığını kaldır ve e-posta anahtarını ekle.
- [x] `topic_published` olayını ve iki konu olayının e-posta varsayılanlarını tanımla. Konu sahibi/site adı değişkenlerini gerçek verilerle doldur.
- [x] Ön arayüz yayım geçişlerinde yazar ve önceki durumu taşı; yönetici düzenleme ve toplu yayımlamayı ortak yayım bildirimi yardımcısına bağla. Tekrarları engelle.
- [x] Veri migration'ıyla yeni şablonları ekle, konu e-postalarını ve kuyruğu aç; özel metinleri koru.
- [x] `tests/notification-email-regression.php` ile tüm şablonları, SMTP aktarımını ve izole kuyruk akışlarını doğrula. Değişen PHP dosyalarını lint et; ekranı kontrol et ve doğrulama sonuçlarını kaydet.

Özellikle kontrol edilecekler: kapalı kuyrukta test, e-posta kapalıyken site içi açık kalması, aynı konuya tekrarlı yayım, konu sahibinin e-posta tercihi, başarısız SMTP denemesi ve yeniden deneme sınırı.
