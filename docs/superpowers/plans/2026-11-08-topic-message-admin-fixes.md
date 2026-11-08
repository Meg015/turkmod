# Konu, mesajlaşma ve admin düzeltme planı

**Amaç:** Bildirilen beş akışı mevcut mimaride düzeltmek ve regresyonlarını doğrulamak.
**Tasarım:** `docs/superpowers/specs/2026-11-08-topic-message-admin-fixes-design.md`
**Teknoloji:** PHP 8.2+, PDO, JavaScript, TürkMod TPL, esbuild.

## Görevler

- [x] 404: `includes/partials/not-found.php`, `assets/css/not-found.css`, `NotFoundPage.php`, `topic-page-content.php`, `404.tpl` ve ortak header/tema bağlamı. 404/noindex, kullanılabilir bağlantılar, tam genişlik ve açık/koyu mobil görünüm doğrulandı.
- [x] Revizyon: `admin/topics.php`, `admin/partials/topics-list.php`, `admin/assets/topics-page.js/css`, bildirim dispatch ve ayrı moderasyon teslimat servisi. Üç kanalın tüm kombinasyonları, seçimsiz kayıt, yinelenen revizyonlar ve teslimat hataları yalıtılmış veritabanıyla doğrulandı.
- [x] Mesajlaşma: `MessageService.php`, `api/messages.php`, `api/notifications.php`, `messages-page.js`, `public-topbar-realtime.js`, `public-notifications-menu.js`, `NotificationCenterService.php`. 300'den uzun sohbet, geçmiş, okundu eşitleme, kopuk bağlantı ve geç çalışan script başlangıcı doğrulandı.
- [x] Yükleme: `upload-topic-form.js`, `upload-topic-rich-editor.js`, `themes/turkmod/js/bundle.js`. Eski iki taslak temizleniyor; yeni form ve adımlar arası mevcut içerik doğrulandı.
- [x] Admin: ortak `admin/assets/admin-ui.js` düzeltildi. `users-tab.js` dahil tüm admin alert çağrıları tarandı; boş mesaj, success, hata ve prompt ayrımı tarayıcıda doğrulandı. Sayfa bazında aynı düzeltmeyi çoğaltmak gerekmedi.
- [x] Regresyonlar, PHP lint, JavaScript testleri, build ve diff kontrolleri geçti. Sonuçlar `docs/verification/2026-11-08-topic-message-admin-fixes.md` içinde.
- [x] Kullanıcının takip denetimi: geciken mesaj cevapları, yeni taslağın korunması, eski mesajların düzenlenmesi/silinmesi, geçmiş isteği hatası, geri tuşunda form restorasyonu ve üst üste admin popupı senaryoları düzeltildi. 17 ek tarayıcı kontrolü ve toplam 73 veritabanı kontrolü geçti; temel 31 tarayıcı senaryosu tekrar doğrulandı.

## İnceleme odağı

- Yetkisiz bir sohbeti açmak veya bildirimlerini okumak mümkün olmamalı.
- E-posta veya özel mesaj seçilmezse yan etkisi oluşmamalı; gönderim hatası saklanmamalı.
- Eski/geciken poll yanıtı yeni mesajı veya okunmamış durumunu geri almamalı.
- Yeni form temizlenirken CSRF/gönderim tokeni ve kategori seçenekleri korunmalı.
- Ortak admin değişikliği gerekli hata metinlerini veya prompt girişlerini gizlememeli.
