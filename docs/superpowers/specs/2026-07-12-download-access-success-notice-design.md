# Kısıtlı İndirme Erişim Başarı Bildirimi Tasarımı

## Amaç

Üyelik veya üyelik + yorum şartlı indirme akışlarında kullanıcı bütün şartları tamamladığında mevcut `.topic-dl-access-notice` bileşenini aynı yerleşim ve aşama düzeniyle yeşil bir başarı durumuna dönüştürmek. Şartları tamamlanmayan kullanıcıların mevcut kilit görünümü ve davranışı değişmeyecek.

## Kapsam

- Başarı bildirimi yalnızca `members` ve `members_comment` erişim modlarında değerlendirilecek.
- `public` erişim modunda ek başarı bildirimi gösterilmeyecek.
- Mevcut bildirim bileşeninin ölçüleri, gövde yapısı ve üç aşamalı göstergesi korunacak.
- İlk sunucu render'ı ve sayfa yenilenmeden gerçekleşen erişim güncellemeleri aynı sonucu üretecek.

## Başarı durumu

Kısıtlı erişim modu etkin ve `topicDownloadAccessState()` sonucu kilitsiz olduğunda:

- Bildirim alanı gizlenmeyecek ve `is-success` sınıfını alacak.
- Ana ikon kilit ikonundan onay ikonuna dönüşecek.
- Başarı metni admin ayarından okunacak.
- Giriş, yorum ve aç aşamalarının tamamı tamamlanmış olarak gösterilecek.
- İndirme kartları mevcut kilitsiz davranışlarıyla çalışmaya devam edecek.

Yorum sonrası erişim AJAX ile açılırsa `topic-downloads.js`, bildirim alanını kaldırmak yerine aynı başarı durumuna dönüştürecek. Sunucu veya API hatasında mevcut güvenli kilit durumu korunacak.

## Eksik şart durumu

Kullanıcının giriş veya yorum şartı eksikse mevcut metin, ikon, renkler, aşama sınıfları, giriş penceresi, yorum alanına odaklanma ve kilit davranışı aynen korunacak.

## Admin ayarları

`Admin > Gelişmiş Ayarlar > İndirme Yöneticisi > İndirme Erişim Kilidi` grubuna iki ayar eklenecek:

- `download_access_success_notice_enabled`: Başarı bildirimini göster; varsayılan `1`.
- `download_access_success_message`: Başarı metni; varsayılan `Tüm erişim şartlarını tamamladınız. İndirme bağlantıları kullanıma hazır.`

Başarı bildirimi kapatılırsa kilitsiz kullanıcı için mevcut davranıştaki gibi bildirim alanı gösterilmeyecek. Kilitli kullanıcılara ait mevcut bildirim her durumda çalışmaya devam edecek.

Yeni ayarlar admin ayar kataloğu, kaydetme izin listesi ve public tema renderer değişkenlerine eksiksiz bağlanacak. Yeni tablo veya migration gerekmeyecek; mevcut ayar saklama altyapısı kullanılacak.

## Görsel davranış

- Kullanıcının çalışma alanında mevcut olan `.topic-dl-access-notice.is-success` başlangıç stili korunacak ve bileşenin metin, ikon ve aşama durumlarını kapsayacak şekilde tamamlanacak.
- Koyu/açık tema değişkenleri ve mevcut `color-mix` yaklaşımı kullanılacak.
- Başarı hali yeşil vurgu taşıyacak ancak mevcut bileşenin spacing, radius ve responsive düzenini değiştirmeyecek.

## Doğrulama

- PHP ve JavaScript sözdizimi kontrolleri çalıştırılacak.
- Admin ayarlarının kaydedilip yeniden yüklenebildiği doğrulanacak.
- `public` modunda başarı bildiriminin görünmediği kontrol edilecek.
- `members` modunda giriş yapmış kullanıcı için yeşil başarı durumu doğrulanacak.
- `members_comment` modunda giriş yapmamış ve yorum yapmamış kullanıcıların mevcut kilit görünümü doğrulanacak.
- Yorum şartını tamamlamış kullanıcıda ilk render ve canlı AJAX açılma sonrası yeşil başarı durumu doğrulanacak.
- Başarı bildirimi kapalıyken kilitsiz kullanıcıda alanın gizli kaldığı doğrulanacak.
- Mobil yerleşim ve tarayıcı konsolu regresyon açısından kontrol edilecek.

