# Konu Raporlama Popup Tasarımı

## Amaç

Konu sayfasındaki "Konuyu Raporla" popup'ını sitenin mevcut Turkmod tasarım diliyle uyumlu, sade, güven veren ve erişilebilir hale getirmek. Rapor gönderme API'si, doğrulama kuralları ve moderasyon iş akışı değişmeyecek.

## Seçilen Yön

"Dengeli ve güven veren" yaklaşımı kullanılacak. Mevcut mor gradyanlı, bağımsız premium görünüm kaldırılacak; sitenin bordo vurgu rengi, ortak yüzeyleri, sınırları, gölgeleri ve form kontrol ritmi kullanılacak.

## Görsel Yapı

- Modal başlığında küçük bir güvenlik simgesi, "Konuyu raporla" başlığı ve tek satırlık açıklama bulunur.
- İçerik alanının başında raporun yalnızca moderasyon ekibi tarafından görüleceğini açıklayan sakin bir bilgi satırı yer alır.
- Ad soyad ve e-posta masaüstünde iki sütun, mobilde tek sütun gösterilir.
- Rapor nedeni ve açıklama alanları tam genişlik kullanır.
- Açıklama alanı isteğe bağlı olarak açıkça etiketlenir.
- Alt eylem satırında kısa bir kullanım notu ve bordo "Raporu gönder" butonu bulunur.
- Modal mobil ekran yüksekliğini aşarsa içerik modal içinde kayar; kapatma düğmesi erişilebilir kalır.

## Bileşen ve Kaynak Sınırları

- Aktif Turkmod görünümü `themes/turkmod/topic-report.tpl` üzerinden düzenlenir.
- Şablondaki modal-özel uzun inline stil bloğu kaldırılır; stiller tema CSS kaynağına taşınır.
- Tema dışı/yedek render yollarındaki rapor modalı aynı içerik hiyerarşisine getirilir.
- `assets/js/topic-report.js` içindeki açma, kapatma, odak yönetimi ve AJAX gönderim davranışı korunur; yalnızca gerekli durum metni veya sınıf uyumu düzenlenir.
- API, CSRF, hız sınırı, neden değerleri ve veri tabanı davranışları kapsam dışıdır.

## Durumlar ve Erişilebilirlik

- `Escape`, arka plana tıklama ve kapatma düğmesi modalı kapatır.
- Açılışta ilk uygun forma odaklanılır; kapanışta odak tetikleyici butona döner.
- Klavye odağı görünür bir halka ile belirtilir.
- Gönderim sırasında buton devre dışı ve yükleniyor durumunda görünür.
- Başarı ve hata mesajları `aria-live` alanında gösterilir; renk yanında ikon/metin de durum bildirir.
- Açık ve koyu tema desteklenir.
- `prefers-reduced-motion` altında modal animasyonu azaltılır.

## Doğrulama

- PHP şablonları için sözdizimi kontrolü yapılır.
- Masaüstü ve mobil genişliklerde modal açılma, kapanma ve taşma davranışı incelenir.
- Açık/koyu tema kontrastı ve form odak durumları kontrol edilir.
- AJAX gönderiminde yükleniyor, hata ve başarı durumlarının sınıfları doğrulanır.
- Aktif tema çıktısı ile yedek render çıktısının temel modal yapısı karşılaştırılır.

## Başarı Ölçütü

Popup sitenin geri kalanından kopuk görünmez; form ilk bakışta anlaşılır, mobilde taşmaz, klavyeyle kullanılabilir ve mevcut rapor gönderme işlevini değiştirmeden çalışır.
