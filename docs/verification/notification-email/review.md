# Bildirim merkezi e-posta doğrulaması — 25 Ekim 2026

Sonuç: 391 PHP regresyon kontrolü ve 3 JavaScript testi başarılı. 24 olay, 7 hesap ve 2 yönetici e-posta şablonu kontrol edildi. Kayıtlı özel metinler de kontrol kapsamına alındı.

`php tests/notification-email-regression.php` gerçek MySQL üzerinde yalnızca bağlantıya özel geçici tablolar kullanır. Gerçek SMTP aktarımı, dışarıya ileti göndermeyen yerel alıcıyla doğrulandı: 57 ileti; UTF-8, HTML/düz metin MIME parçaları ve değişkenler başarılı. Başarısız SMTP yanıtı, yeniden deneme sınırı, kullanıcı tercihi, tekrarlı yayım ve normal konu düzenlemesi senaryoları geçti.

Yapılandırılmış SMTP sunucusuna ayrıca SSL bağlantısı ve AUTH LOGIN kimlik doğrulaması başarılı oldu. Bu kontrolde MAIL FROM / RCPT TO / DATA komutları kullanılmadı; gerçek alıcılara test e-postası gönderilmedi. Sonuç gelen kutusuna teslim garantisi değildir.

İkinci incelemede testle doğrulanıp düzeltilenler: eş zamanlı worker'ın başka worker tarafından ileri tarihe ertelenmiş kaydı erken göndermesi; dry-run sırasında eski kilitlerin değiştirilmesi; tek kanalın Varsayılana Dön işleminin diğer kanal metnini ve tercihlerini sıfırlaması; tarayıcı geri dönüşünde gönderim düğmesinin pasif kalması ve geçici action alanının eski işlemi taşıması. Kuyruk kilidi artık deneme sayısını ve gönderim zamanını tekrar denetler. Dry-run kayıt değiştirmez. Kanal sıfırlaması yalnızca seçilen kanalın metinlerini yeniler ve tercihler korunur. `pageshow` gönderim kontrollerini geri yükler.

Ek test komutu: `node --test tests/notification-email-ui-regression.test.mjs`.

Tarayıcıda 24 olay kartı, 24 etkin Test E-Postası düğmesi, 24 ayrı e-posta anahtarı ve 2 açık konu şablonu görüldü. Yayım şablonunun önizlemesi değişkensiz üretildi. Test ve Kaydet düğmelerinin gerçek form gönderimleri tarayıcıda yakalandı; `action`, `channel` ve son `email_enabled` değerleri doğrulandı. Bu POST istekleri sunucuya iletilmedi. Masaüstü 1440px ve mobil 390px kontrolünde yatay taşma yok. Tarayıcı konsolunda hata/uyarı yok.

Değişen 12 PHP dosyası sözdizimi kontrolünden geçti; JavaScript dosyalarının sözdizimi kontrolü ve diff boşluk kontrolü başarılı. Mevcut kuyruk worker'ı `--dry-run` ile hatasız çalıştı.

Yerel veritabanına `2026_10_25_0001_enable_topic_publication_email` migration'ı uygulandı. `topic_approved` ve `topic_published` e-postaları ile kuyruk açık. Kullanıcı, canlı ortamda mevcut cron'un kullanılacağını belirtti.

Canlıya aktarım: değişen dosyalarla birlikte yeni migration aktarılmalı ve Admin > Veritabanı Senkronizasyonu çalıştırılmalı. Mevcut cron `cron/send-notification-email-queue.php --limit=25` worker'ını dakikada bir çalıştırmalı. Canlı ortama bu oturumda erişilmedi veya dağıtım yapılmadı.

Ekran görüntüleri: `output/playwright/notification-topic-email.png`, `output/playwright/notification-topic-email-preview.png`, `output/playwright/notification-topic-email-mobile.png`.
