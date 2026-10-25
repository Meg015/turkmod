# Günlük temizleme popup uygulama planı

**Amaç:** Günlükler bölümünde tüm temizleme popup'larını yalnızca “Tümünü sil” işlemini sunan ortak tasarıma geçirmek ve başarı popup'ını kaldırmak.

**Tasarım:** `docs/superpowers/specs/2026-10-25-log-clear-modal-design.md`.

**Mimari:** Ortak PHP şablonu sabit metin ve gizli işlem kapsamı üretir. Günlük sayfaları yalnızca endpoint ve tümünü silme işlem bilgilerini geçirir. Ortak JS başarıda doğrudan yeniler; hata davranışı ve paylaşılan AJAX altyapısı korunur.

## Kısıtlar ve inceleme noktaları

- CSRF ve mevcut yetki kontrolleri korunur; gerçek kayıtlar doğrulama sırasında silinmez.
- Masaüstünde ortak 440 × 200 px ölçü; 480 px ve altı ekranlarda 224 px yükseklik ve ekrana uyumlu genişlik.
- Eski tarih/gün/filtre/kullanıcı seçenekleri hiçbir popup'ta kalmaz.
- Uygulama ve rate limit kayıtları `action=clear_all`, diğer günlükler kendi endpoint'leriyle `scope=all` gönderir.
- Tek kayıt ve seçili kayıt işlemleri değişmez.
- Başarıda ek popup veya toast oluşmaz. Hata ve bağlantı hatası görünür kalır, tekrar gönderim engellenir.

## Görevler

- [x] `admin/partials/log-clear-modal.php` içinde başlık, açıklama, uyarı, iptal ve Tümünü sil düğmesini sabitle; seçim ve ek alan üretimini kaldır. Gizli alanları ve CSRF'yi koru.
- [x] `admin/logs.php`, `admin/application-logs.php`, `admin/email-logs.php`, `admin/rate-limits.php`, `admin/users-tabs/activity.php` yapılandırmalarını yalnızca tümünü sil işlemine indir.
- [x] `admin/assets/logs-clear-modal.js` içinde seçenek bağımlılıklarını kaldır; odağı iptale taşı, başarıda kapatıp yenile, başarı bildirimini sustur.
- [x] `admin/assets/admin.css` içinde bu modal için boyut, düzen, tema ve dar ekran kurallarını tek yerde tanımla.
- [x] Değişen PHP/JS dosyalarının sözdizimini, asset build'ini ve şablonun ürettiği form verilerini kontrol et. Gerçek kayıt silmeden JS başarı/hata akışını doğrula.
- [x] Yerel admin ekranında modal ölçüleri, içerik ve iptal davranışını kontrol et; sonucu ve varsa erişim sınırını raporla.
- [x] Kullanıcının kompakt tasarım revizyonu: küçük simge, sade kapatma düğmesi, azalan iç boşluklar, ince ayırıcı ve dolu kırmızı silme düğmesini ortak bileşende uygula; 1920, 390 ve 320 px genişliklerde metnin sığdığını doğrula.
