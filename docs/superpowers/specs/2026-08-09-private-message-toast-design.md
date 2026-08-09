# Özel Mesaj Toast Bildirimi Tasarımı

## Amaç

Oturum açmış kullanıcıya, sitenin herhangi bir genel sayfasındayken yeni bir özel mesaj geldiğinde göndereni belirten ve ilgili konuşmaya yönlendiren bir toast göstermek.

## Kullanıcı Davranışı

- Yeni mesaj geldiğinde “{KullanıcıAdı} tarafından bir mesaj gönderildi.” metni gösterilecek.
- Toast tıklandığında mesajın ait olduğu konuşma açılacak.
- Kullanıcı mesaj sayfasında aynı konuşmayı açık tutuyorsa toast gösterilmeyecek.
- Kullanıcı mesaj sayfasında farklı bir konuşmayı açık tutuyorsa toast gösterilecek.
- Sayfa ilk açıldığında önceden okunmamış mesajlar yeniden toast olarak gösterilmeyecek.
- Aynı mesaj için birden fazla toast gösterilmeyecek.

## Teknik Yaklaşım

### Gerçek Zamanlı Akış

Mevcut `new_message` WebSocket olayına toast için gereken gönderen kimliği, gönderen adı ve konuşma URL'si eklenecek. Genel üst menünün gerçek zamanlı istemcisi, olayın mevcut kullanıcıdan gelmediğini doğrulayacak ve mesaj kimliği üzerinden yinelenen bildirimleri engelleyecek.

Mesaj sayfası açık olduğunda istemci, `data-active-thread-id` değeriyle olayın `thread_id` değerini karşılaştıracak. Değerler aynıysa mesaj akışı normal şekilde yenilenecek fakat toast oluşturulmayacak.

### Bağlantı Yedeği

WebSocket bağlantısı yokken çalışan mevcut 30 saniyelik mesaj menüsü yenilemesi korunacak. İlk API yanıtı yalnızca başlangıç durumu olarak kaydedilecek. Sonraki yanıtlarda yeni bir gelen son mesaj algılanırsa aynı toast üretim ve yinelenme önleme yolu kullanılacak.

Yedek sorgu bir konuşmada iki kontrol arasında birden fazla mesaj gelirse o konuşmanın en son mesajını tek toast olarak bildirebilir. WebSocket bağlantısı çalışırken her mesaj olayı ayrı ayrı işlenir.

### Toast Etkileşimi

Mevcut `showToast` altyapısına `clickUrl` seçeneği eklenecek. Kapatma düğmesi yönlendirme yapmayacak; yalnızca toast gövdesine tıklama güvenli konuşma URL'sine götürecek.

## Hata Davranışı

- Eksik veya geçersiz mesaj kimliği, gönderen kimliği ya da konuşma URL'si olan olay toast üretmeyecek.
- Toast altyapısı hazır değilse gerçek zamanlı menü ve rozet yenilemesi çalışmaya devam edecek.
- WebSocket bağlantısı kesilirse mevcut HTTP yedeği devreye girecek.
- İstemci hataları mesaj gönderme ve mesaj okuma akışını engellemeyecek.

## Doğrulama

- PHP ve JavaScript sözdizimi kontrolleri çalıştırılacak.
- Farklı bir sayfadayken gelen mesajın gönderen adıyla toast oluşturduğu doğrulanacak.
- Toast gövdesinin doğru konuşmayı açtığı doğrulanacak.
- Aynı konuşma açıkken toast oluşmadığı doğrulanacak.
- Başka bir konuşma açıkken toast oluştuğu doğrulanacak.
- İlk sayfa yüklemesinin eski okunmamış mesajları toast olarak göstermediği doğrulanacak.
- WebSocket olayı ve HTTP yenilemesi aynı mesajı gördüğünde tek toast oluştuğu doğrulanacak.
