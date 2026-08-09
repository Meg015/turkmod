# Özel Mesaj Bildirim Deneyimi Tasarımı

## Amaç

Mevcut özel mesaj toast bildirimini çoklu sekme kontrolü, kısa süreli mesaj birleştirme, isteğe bağlı ses ve isteğe bağlı tarayıcı bildirimiyle genişletmek. Bildirimler mesaj içeriğini hiçbir zaman göstermeyecek.

Bu tasarım, site en az bir tarayıcı sekmesinde açıkken çalışan mevcut WebSocket ve HTTP yedek akışını kapsar. Site tamamen kapalıyken bildirim gönderecek servis worker/push altyapısı bu kapsamda değildir.

## Kullanıcı Davranışı

- Aktif ve odaklanmış sekmede yeni özel mesaj toast olarak gösterilecek.
- Aynı hesap birden fazla sekmede açıksa aynı mesaj yalnızca bir sekmede bildirilecek.
- Kullanıcı aktif sekmede mesajın ait olduğu konuşmayı izliyorsa toast, ses ve masaüstü bildirimi üretilmeyecek.
- Aynı kullanıcıdan iki saniye içinde gelen mesajlar tek bildirimde birleştirilecek.
- Tek mesaj için “{KullanıcıAdı} tarafından bir mesaj gönderildi.” metni kullanılacak.
- Birleştirilmiş bildirim için “{KullanıcıAdı} tarafından {N} yeni mesaj gönderildi.” metni kullanılacak.
- Toast tıklandığında ilgili konuşma açılacak.
- Mesaj içeriği ve önizlemesi toast, ses veya tarayıcı bildiriminde gösterilmeyecek.

## Kullanıcı Tercihleri

Mevcut Bildirim Ayarları sayfasındaki site içi tercihlere iki seçenek eklenecek:

- `message_notification_sound_enabled`: “Özel mesaj sesi”, varsayılan `0`.
- `message_desktop_notifications_enabled`: “Masaüstü mesaj bildirimi”, varsayılan `0`.

Mevcut `notif_event_direct_message_received` tercihi ana kontrol olarak korunacak. Bu tercih veya site içi olay grubu kapalıysa toast, ses ve masaüstü mesaj bildirimi birlikte devre dışı kalacak.

Masaüstü bildirimi seçeneği hesap tercihi olarak saklanacak, tarayıcı izni ise cihaz/tarayıcı bazında yönetilecek. Kullanıcı seçeneği açtığında `Notification.requestPermission()` doğrudan bu kullanıcı etkileşimi içinde çağrılacak. Hesap tercihi açık fakat mevcut cihazda izin henüz verilmemişse ayarlar ekranı “Bu cihazda izin ver” eylemi gösterecek. İzin reddedilmişse kullanıcıya tarayıcı ayarlarından izin vermesi gerektiği açıklanacak.

Mesaj dropdown API yanıtı şu etkin değerleri döndürecek:

- `enabled`
- `sound_enabled`
- `desktop_enabled`

Bu değerler Bildirimler modülündeki tercih servisi tarafından hesaplanacak; Mesajlar servisine bildirim tercih mantığı taşınmayacak.

## Çoklu Sekme Koordinasyonu

Her sekme benzersiz bir sekme kimliği oluşturacak. Sekmeler kullanıcı kimliğine özel bir `BroadcastChannel` üzerinden haberleşecek; desteklenmeyen tarayıcılarda `localStorage` olayları yedek olacak.

Odak ve görünürlük değişimlerinde en son aktif sekme kaydedilecek. Görünür durumda yalnızca odaklanmış sekme toast gösterecek. Tüm sekmeler arka plandaysa en son aktif sekme masaüstü bildirimi üretmeye yetkili olacak.

İşlenen mesaj kimlikleri zaman damgalı ve sınırlandırılmış bir istemci önbelleğinde tutulacak. Aynı WebSocket olayının, HTTP yedeğinin veya başka bir sekmenin aynı mesaj için yeniden bildirim üretmesi engellenecek. Aktif konuşma nedeniyle bastırılan mesaj da işlenmiş sayılacak; başka bir sekme bu mesajı tekrar göstermeyecek.

Sekmeler arası API'ler kullanılamazsa sistem görünürlük ve `document.hasFocus()` kontrolüne gerileyecek; temel toast akışı çalışmaya devam edecek.

## Mesaj Birleştirme

Bildirimler gönderen kullanıcı kimliğine göre gruplanacak. Uygun yeni mesaj ilk geldiğinde iki saniyelik kısa bir pencere başlatılacak. Aynı gönderenden bu pencere içinde gelen ek mesajlar sayacı artıracak; süre sonunda tek bildirim üretilecek.

Farklı gönderenlerin bildirimleri ayrı tutulacak. Birleştirilmiş bildirim tıklandığında gruptaki en son mesajın konuşması açılacak. Ses açıksa birleşmiş grup başına yalnızca bir kez çalacak.

HTTP yedeği iki sorgu arasında aynı konuşmada birden fazla mesajı yalnızca son mesaj üzerinden görebilirse, okunmamış sayı farkını kullanarak mümkün olan doğru toplamı bildirecek. İlk sayfa yüklemesi yalnızca başlangıç durumu olacak ve eski okunmamış mesajlar tekrar bildirilmeyecek.

## Ses Davranışı

Harici ses dosyası eklemek yerine Web Audio API ile kısa ve düşük seviyeli iki tonlu bir bildirim sesi üretilecek. Bu yaklaşım ek medya isteği ve dosya yönetimi gerektirmez.

Ses yalnızca tercih açıksa, aktif görünür sekmede ve gerçekten gösterilen birleşmiş bildirim için çalacak. Tarayıcı otomatik ses politikasının oynatmayı engellemesi mesaj veya toast akışını bozmayacak. İlk kullanıcı etkileşiminde ses bağlamı hazırlanacak; oynatma hataları sessizce yoksayılacak.

## Masaüstü Bildirimi

Aktif bir görünür sekme yoksa, masaüstü tercihi açık ve `Notification.permission === "granted"` ise sekmeler arası sahibi olan tek sekme bir tarayıcı bildirimi oluşturacak.

Bildirim başlığı gönderen adını ve mesaj sayısını içerecek; gövde yalnızca “Yeni özel mesajınız var.” diyecek. Mesaj içeriği kullanılmayacak. Bildirime tıklanınca ilgili sekme odaklanacak ve konuşma URL'si açılacak. Tarayıcı API'si yoksa veya izin verilmemişse masaüstü bildirimi atlanacak; kullanıcı siteye döndüğünde HTTP yedeği temel toastı gösterebilecek.

## Bileşen Sınırları

- `NotificationPreferenceService`: hesap ayarlarını okuyup etkin mesaj bildirim tercihlerini hesaplar.
- Bildirim Ayarları sayfası ve JavaScript'i: iki tercihi kaydeder, cihaz izni durumunu gösterir ve izin ister.
- Mesaj dropdown API'si: hesap için hesaplanmış etkin tercihleri istemciye taşır.
- Genel gerçek zamanlı istemci: sekme sahipliği, yinelenme önleme, aktif konuşma filtresi, birleştirme, toast, ses ve masaüstü bildirimini yönetir.
- Mesaj menüsü istemcisi: ilk durum ve HTTP yedek mesaj farklarını gerçek zamanlı yöneticisine iletir.
- Toast altyapısı: mevcut güvenli `clickUrl` davranışını kullanır; mesaj içeriği hakkında bilgi taşımaz.

## Hata Davranışı

- Eksik mesaj, gönderen veya konuşma kimliği olan olaylar yoksayılacak.
- Geçersiz ya da farklı origin'e ait konuşma URL'si açılmayacak.
- Tercihler yüklenemezse güvenli varsayılan kullanılacak: temel toast açık, ses ve masaüstü bildirimi kapalı.
- BroadcastChannel, localStorage, Web Audio veya Notification API hataları mesaj okuma ve gönderme akışını etkilemeyecek.
- WebSocket bağlantısı yoksa mevcut 30 saniyelik HTTP yenilemesi çalışmaya devam edecek.

## Doğrulama

- PHP ve JavaScript sözdizimi kontrolleri çalıştırılacak.
- Tercihlerin varsayılan olarak kapalı ve kaydedilebilir olduğu doğrulanacak.
- Masaüstü izni `default`, `granted`, `denied` ve desteklenmiyor durumlarında test edilecek.
- İki sekmede aynı mesajın yalnızca bir kez bildirildiği doğrulanacak.
- Aktif konuşmadaki mesajın bütün kanallarda bastırıldığı doğrulanacak.
- Aynı gönderenden iki saniye içinde gelen mesajların tek bildirim ve tek ses ürettiği doğrulanacak.
- Farklı gönderenlerin ayrı bildirim oluşturduğu doğrulanacak.
- Gizli sekmede izinli masaüstü bildiriminin doğru konuşmayı açtığı doğrulanacak.
- Mesaj içeriğinin istemci bildirim metinlerine taşınmadığı doğrulanacak.
- WebSocket ve HTTP yedeğinin aynı mesajı çift bildirmediği doğrulanacak.
