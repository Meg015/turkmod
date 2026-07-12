# Topbar Bildirimlerini Tümüyle Okundu İşaretleme Tasarımı

## Amaç

Turkmod temasındaki topbar bildirim menüsünde bulunan "Tümünü okundu işaretle" bağlantısının, yalnızca arayüzü değiştirmek yerine kullanıcının erişebildiği bütün okunmamış bildirimleri kalıcı olarak okundu durumuna getirmesi.

## Mevcut sorun

Topbarın kullandığı tema bundle kodundaki `markAllNotificationsAsRead()` fonksiyonu okunmamış sınıflarını ve rozeti istemci tarafında temizliyor, fakat `api/notifications-read.php` uç noktasına POST isteği göndermiyor. Sayfa veya bildirim listesi yenilendiğinde sunucudaki okunmamış durum tekrar görünüyor.

## Tasarım

- Mevcut topbar event delegasyonu korunacak; ikinci bir bildirim betiği yüklenmeyecek.
- İşlem, topbar kökündeki `data-notif-read-api` adresine POST isteği gönderecek.
- İstek gövdesinde mevcut meta CSRF değeri `_token`, hedef ise `id=all` olarak gönderilecek.
- Yalnızca başarılı API cevabından sonra okunmamış sınıfları ve rozet kalıcı biçimde temizlenecek.
- Başarılı işlemden sonra bildirim listesi sunucudan yeniden çekilerek istemci ve veritabanı durumu eşitlenecek.
- Ağ, JSON, CSRF, oturum veya API hatalarında mevcut görünüm korunacak/yeniden yüklenecek ve uygun hata bildirimi gösterilecek.
- İşlem sürerken aynı bağlantıya art arda basılması yinelenen istek üretmeyecek.
- Tema kaynak bundle ve çalıştırılan bundle çıktısı aynı davranışı taşıyacak.

## Güvenlik ve kapsam

- Var olan `api/notifications-read.php` CSRF ve oturum doğrulaması kullanılacak.
- Bildirim sahipliği ve genel bildirim erişimi `NotificationCenterService::markRead()` sınırında kalacak.
- API içine doğrudan SQL veya yeni bildirim iş mantığı eklenmeyecek.
- Bildirim merkezi sayfasının ayrı "tümünü okundu yap" akışı değiştirilmeden regresyon açısından kontrol edilecek.

## Doğrulama

- Değiştirilen JavaScript dosyaları sözdizimi açısından kontrol edilecek.
- PHP API ve servis dosyaları sözdizimi açısından kontrol edilecek.
- Oturumlu tarayıcı testinde okunmamış bildirim bulunan hesapla bağlantıya basılacak; rozetin kaybolması, öğelerin okunmuş görünmesi ve yeniden yüklemeden sonra durumun korunması doğrulanacak.
- İkinci tıklamanın hatasız ve idempotent çalıştığı kontrol edilecek.
- Başarısız istek senaryosunda yanlış başarı görünümü kalmadığı kontrol edilecek.

