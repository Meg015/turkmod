# Günlükler — Bölünmüş İnceleme doğrulaması

25.10.2026, Chrome, yerel XAMPP uygulaması.

- Yönetici, kullanıcı, uygulama, e-posta, cron, sistem bildirimi ve rate limit sayfaları; 1440, 1024, 390 ve 320 px: 28 görünüm kontrolü geçti. Kayıt listesi, filtreler, özet kartları ve sayfa sınırlarında yatay taşma bulunmadı.
- Başlangıçta ayrıntı kapalı; ilk kayıt otomatik seçilmiyor. Kayıt seçme, başka kayda geçme, kapatma, Escape, odak dönüşü ve listenin tam genişliğe dönmesi doğrulandı.
- Bildirimlerde 9, rate limitte 10 kutunun toplu seçimi ve özgün formla ilişkisi doğrulandı. Silme işlemleri gönderilmedi.
- E-posta aramasıyla boş sonuç, filtre sıfırlama ve gerçek sayfalama doğrulandı. E-posta listesindeki 10 kaydın her birinde 18 teknik alan açılarak kontrol edildi. 320 px genişlikte açılan teknik alanlarda da taşma yok.
- Temizleme penceresi açılıp iptal edildi: tarih/gün seçenekleri yok; eylem “Tümünü sil”. Başarılı temizleme davranışına ait mevcut sessiz akış korundu.
- Açık/koyu tema kontrol edildi; koyu temada vurgu yazıları aydınlatıldı. Önceki tema geri yüklendi.
- Paylaşılan `users.php?tab=activity` sekmesinde de ortak liste, filtreler ve kapalı başlangıç doğrulandı.
- Değişen PHP dosyaları lint kontrolünden, yeni JavaScript/CSS sözdizimi kontrolünden ve `npm run build` başarıyla geçti. `git diff --check` hata vermedi.

Ham görünüm ölçümleri: [browser-checks.json](browser-checks.json).

Veri silme, geri alma veya sunucuya dağıtım yapılmadı. Bunlara ait mevcut yetki/CSRF/POST akışları değişmedi; etkileşim kontrolleri okuma ve seçimle sınırlı tutuldu.

## Ortak admin tasarımı revizyonu

- Günlükler için tanımlanan sade başlık ve küçük tipografi kuralları kaldırıldı. `page-heading.css`, ortak admin kartları, form kontrolleri, butonlar ve rozetler kullanılıyor.
- Kategori Yönetimi referansıyla 1440, 1024, 390 ve 320 px boyutlarında yedi günlük sayfasının başlık arka planı, başlık/açıklama/kicker yazıları ve başlık butonu karşılaştırıldı: 28 eşleşme. Form yazıları ve istatistik yazıları da ortak ölçekte.
- Aynı 28 görünümde yatay taşma yok. Ek filtreler ve kayıt seçimi/kapatma 1024 ve 390 px boyutlarında ayrıca doğrulandı.
- Kullanıcı İşlem Günlüğü'nde ek Güvenlik/Yönetici İşlemleri/İçerikler/Admin Notları panelleri ve bunlara ait sorgular kapatıldı. Kullanıcı filtresinde de tek ana liste var. Kullanıcı yönetiminin paylaşılan Hareketler sekmesi kendi ek bilgilerini koruyor.
- PHP lint, JavaScript/CSS sözdizimi kontrolü ve `npm run build` başarılı. Silme işlemi gönderilmedi.

Revizyon ölçümleri: [admin-style-alignment.json](admin-style-alignment.json).

## Kompakt kartlar ve filtreler

- `logs-summary-notes` çıktısı ve stilleri kaldırıldı.
- Özet kartları 104 px yerine 80 px; ikonlar 32 px. Kart aralıkları, iç boşlukları ve etiket satır aralıkları sıkılaştırıldı; yazı boyutları korunuyor.
- Filtre kutularının iç boşluğu 20 px yerine 10 × 12 px. Masaüstü kontrolleri 40 px, mobil kontrolleri 44 px; ek filtrelerin aralıkları azaltıldı.
- Kullanıcı işlem günlüğündeki fazladan bölüm boşlukları kaldırıldı. Bordo başlık ve ortak buton/font stilleri korunuyor.
- Yedi sayfada 1440, 1024, 390 ve 320 px: kartlar 80 px ve eşit; filtreler açık/kapalı yatay taşma yok. CSS sözdizimi kontrolü ve build başarılı.

Ölçümler: [compact-layout.json](compact-layout.json).
