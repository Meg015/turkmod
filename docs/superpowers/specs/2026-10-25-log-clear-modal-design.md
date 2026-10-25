# Günlük temizleme popup standardı

Durum: Kullanıcının yalnızca “Tümünü sil” talimatı doğrultusunda uygulandı.

## Amaç ve kapsam

Admin Paneli > Günlükler altındaki Yönetici İşlem Günlüğü, Kullanıcı İşlem Günlüğü, Uygulama Logları, E-posta Logları, Cron Logları, Sistem Bildirimleri ve Rate Limit İzleme sayfalarında günlük temizleme deneyimini tek merkezden yönetmek.

Kullanıcı İşlem Günlüğü ile ortak şablonu kullanan kullanıcı ayrıntısı ekranı da aynı standardı kullanır. “Tümünü sil”, geçerli günlük türünün tüm kayıtlarını kapsar; diğer günlük türlerini temizlemez. Tek kayıt silme ve seçili kayıt silme işlemleri bu değişikliğin kapsamı dışındadır.

## İncelenen mevcut yapı

- `admin/partials/log-clear-modal.php`: Ortak şablon; sayfalardan farklı seçenekler, ek alanlar ve uyarılar alıyor.
- `admin/assets/logs-clear-modal.js`: Ortak açma, kapama ve AJAX gönderimi; başarılı işlem sonrasında `adminAlert` çağırıyor ve popup kapatılınca sayfayı yeniliyor.
- `admin/helpers.php`: Ortak render ve temizleme yardımcıları; yetki, CSRF ve işlem kaydı kontrolleri mevcut.
- `admin/assets/admin.css`: Küçük popup ve ortak popup kabuğunun genişlik kuralları çakışabiliyor.

## Yaklaşımlar

1. **Önerilen: Mevcut ortak şablonu sadeleştirmek.** Tüm sayfalar aynı markup, CSS ve JS akışını kullanır. Mevcut modal, AJAX ve yetki altyapısı korunur.
2. Her sayfanın popup'ını ayrı düzenlemek. İlk değişiklik daha küçük görünse de içerik ve ölçü farklılıklarının tekrar oluşmasına yol açar.
3. Genel admin onay diyaloğuna taşımak. Bu istek için gereksiz kapsam genişletir ve diğer admin diyaloglarını etkileyebilir.

## Onay popup'ı

- Başlık: **Günlüğü Temizle**.
- Açıklama: **Bu günlükteki tüm kayıtlar kalıcı olarak silinecek.**
- Uyarı: **Bu işlem geri alınamaz.**
- Düğmeler: **Vazgeç** ve kırmızı **Tümünü sil**.
- Tarih sınırı, gün sayısı, kullanıcı seçimi, filtre ve kapsam seçim kutuları gösterilmez.
- İstekler gizli alanlarla ilgili günlüğün tümünü temizleyen mevcut sunucu işlemine gönderilir.
- Masaüstünde bütün sayfalarda ortak 440 × 200 CSS piksel kabuk. 480 px ve altındaki ekranlarda yükseklik 224 px; genişlik 16 px ekran boşluğunu korur. Kısa ekranlarda içerik kaydırılabilir.
- Mevcut admin temasına uygun yüzey, kompakt başlık, 32 px kırmızı çöp kutusu simgesi, ince alt ayırıcı, 36 px düğmeler ve dolu kırmızı silme düğmesi. Mobil düğmeler 40 px. Açık ve koyu tema değişkenleri korunur.
- Başlık ve açıklama erişilebilirlik nitelikleriyle diyaloğa bağlanır. Açılış odağı güvenli iptal düğmesine gider. Mevcut klavye, Escape ve odak geri dönüşü davranışları korunur.

## İşlem davranışı

- Gönderim sırasında düğme kilitlenir ve **Siliniyor…** gösterilir; tekrar gönderim engellenir.
- Başarıda onay popup'ı kapanır ve liste yenilenir. İkinci bir başarı popup'ı veya temizliğe ait ek başarı bildirimi açılmaz.
- Hata halinde hata bilgisi korunur; başarısız işlem başarı gibi kapatılmaz veya yenilenmez. Düğme tekrar kullanılabilir olur.
- Yetki, CSRF, sunucu kapsam kontrolü ve temizleme denetim kaydı korunur. Bu revizyonun uygulanması mevcut günlükleri kendiliğinden silmez.

## Doğrulama

- Değişen PHP dosyalarının sözdizimi kontrolü ve ilgili JS/CSS build kontrolü.
- Yedi günlük ekranında aynı başlık, açıklama, düğmeler ve modal ölçülerinin kontrolü.
- Açık/koyu tema, dar ekran, klavye odağı ve iptal davranışının kontrolü.
- Gerçek kayıt silmeden, denetimli yanıtlarla başarıda popup'sız yenileme, hata halinde görünür hata ve tekrarlı gönderim engelinin kontrolü.

## Kendi incelemesi

Kapsam, silme hedefi, ortak metinler, boyutlar, başarı/hata davranışları ve doğrulama ölçütleri tanımlandı. Belirsiz alan veya uygulama dışı refaktör yok.

## Uygulama doğrulaması

- Yedi günlük ekranında ortak popup açıldı; seçim kutusu ve görünür form alanı bulunmadığı doğrulandı.
- Güncel ortak masaüstü ölçüsü 440 × 200 px. 390 × 844 px görünümde popup 358 × 224 px; 320 × 600 px görünümde 288 × 224 px. Üç görünümde de gövde metni kaydırma gerektirmeden sığıyor.
- PHP sözdizimi kontrolü, JS sözdizimi kontrolü ve `npm run build` başarılı.
- İzole JS kontrolünde başarıda doğrudan yenileme, başarı bildirimi olmaması, yinelenen gönderim engeli, sunucu/bağlantı hatası ve tekrar deneme doğrulandı.
- İzole PHP şablon kontrolünde bütün işlem tipleri için `scope=all`, CSRF ve sessiz başarı alanları doğrulandı. Eski seçenekler şablona verilse bile görünür alan üretilmiyor.
- Doğrulama sırasında temizleme isteği gönderilmedi ve günlük kayıtları silinmedi. Koyu tema görsel olarak ayrıca denenmedi; bileşen mevcut tema değişkenlerini kullanıyor.
