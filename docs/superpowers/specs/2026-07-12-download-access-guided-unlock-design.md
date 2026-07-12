# İndirme Erişimi Yönlendirmeli Kilit Açma Tasarımı

## Amaç

Üyelik ve yorum şartlı indirme akışını; görünür ilerleme bilgisi, yorum sonrası sayfa yenilemeden kilit açma, onay bekleme durumu, erişilebilir durum geri bildirimi ve indirmeye yönlendiren açık eylemlerle tamamlamak.

## Durum modeli

Mevcut `auth_required`, `comment_required` ve açık erişim durumlarına `comment_pending` eklenecek.

- `auth_required`: Kullanıcı giriş yapmamış.
- `comment_required`: Kullanıcı giriş yapmış fakat geçerli bir yorumu yok.
- `comment_pending`: Yorum onayı gerekli ve kullanıcının gönderilmiş fakat onaylanmamış yorumu var.
- `none/open`: Bütün şartlar tamamlanmış.

`topicDownloadAccessState()` sunucu tarafındaki tek doğruluk kaynağı olmaya devam edecek. API, legacy topic render ve public tema renderer aynı durum, aşama ve ilerleme verisini kullanacak.

## İlerleme bilgisi

Erişim bildirimi tamamlanan ve toplam aşama sayılarını taşıyacak. Arayüzde admin ayarı açıksa “3 adımdan 2’si tamamlandı” metni gösterilecek.

- Giriş yapılmamış: 0/3.
- Giriş yapılmış, yorum gerekli: 1/3.
- Yorum onay bekliyor: 2/3; yorum aşaması bekliyor olarak işaretlenecek.
- Erişim açık: 3/3.

Üyelik gerektiren fakat yorum gerektirmeyen modda kullanıcıya yanıltıcı yorum zorunluluğu gösterilmeyecek; ilerleme metni etkin aşama sayısına göre hesaplanacak.

## Canlı kilit açma

Yorum oluşturma olayı sonrasında mevcut erişim izleyicisi API durumunu sorgulayacak. Erişim açıldığında sayfa yenilenmeden:

- Bildirim başarı durumuna dönüşecek.
- Kilitli kartlar aktifleşecek.
- Kartlardaki kilit mesajları kaldırılacak.
- İlk görünür indirme kartı kısa süreli vurgulanacak.
- Başarı animasyonu çalışacak.
- Bekleyen otomatik indirme işlemi kullanıcı tıklaması olmadan harici bağlantı açmayacak.

Yorum onayı gerekiyorsa API `comment_pending` döndürecek ve izleyici kilidi açılmış saymayacak. İzleyici sınırlı aralıklarla durumu yenilemeye devam edecek ve mevcut maksimum deneme sınırına uyacak.

## Başarı ve yönlendirme davranışı

Başarı bildirimi admin ayarı açıksa kısa bir giriş animasyonu alacak. `prefers-reduced-motion: reduce` ortamında animasyon uygulanmayacak.

Başarı alanındaki “İndirmeye Başla” butonu:

- İlk görünür indirme kartına kaydıracak.
- Karta kısa vurgu uygulayacak.
- Klavye odağını ancak buton etkileşimi kullanıcı tarafından başlatıldığı için karta taşıyabilecek.
- Harici bağlantıyı veya geri sayımı otomatik başlatmayacak.

Başarı alanı admin ayarı açıksa varsayılan 5 saniye sonra kompakt hale gelecek. Alanın üzerinde fare, klavye odağı veya dokunma etkileşimi varsa daralma ertelenecek. Yeni erişim durumu geldiğinde süre yeniden başlayacak.

## Yorum alanı yönlendirmesi

`comment_required` durumunda mevcut yorum alanına kaydırma/odak davranışı korunacak ve forma kısa süreli görsel vurgu eklenecek. `comment_pending` durumunda kullanıcı tekrar yorum alanına gönderilmeyecek; açık biçimde yönetici onayı beklendiği bildirilecek.

## Kart mesajları

Her kilitli kart sunucudan gelen gerçek eksik şart mesajını gösterecek:

- Giriş yapmanız gerekiyor.
- Yorum yapmanız gerekiyor.
- Yorumunuz yönetici onayı bekliyor.

Kart butonu `comment_pending` durumunda pasif “Onay Bekleniyor” metni taşıyacak ve tekrar yorum eylemi başlatmayacak.

## Erişilebilirlik

- Durum alanı `role=status` ve `aria-live=polite` kullanmaya devam edecek.
- Ana durum başlığı ayrı bir erişilebilir metin olarak bulunacak.
- Aşama öğeleri tamamlandı, etkin, bekliyor ve sırada durumlarını yalnızca renkle değil metin/ikon ve erişilebilir etiketlerle açıklayacak.
- İndirmeye başla eylemi gerçek bir `button` olacak.
- Animasyonlar `prefers-reduced-motion` altında kapatılacak.
- Otomatik daralma odaklanmış içerikleri gizlemeyecek.

## Admin ayarları

`Admin > Gelişmiş Ayarlar > İndirme Yöneticisi > İndirme Erişim Kilidi` grubuna şu ayarlar eklenecek:

- `download_access_progress_enabled`: İlerleme bilgisini göster; varsayılan `1`.
- `download_access_success_animation_enabled`: Başarı animasyonunu etkinleştir; varsayılan `1`.
- `download_access_success_auto_compact`: Başarı alanını otomatik daralt; varsayılan `1`.
- `download_access_success_compact_delay`: Daralma süresi; varsayılan `5`, sınır `0-60` saniye.
- `download_access_highlight_first_card`: İlk kartı vurgula; varsayılan `1`.
- `download_access_start_button_enabled`: İndirmeye başla butonunu göster; varsayılan `1`.
- `download_access_start_button_label`: Buton metni; varsayılan `İndirmeye Başla`.
- `download_access_pending_message`: Onay bekleme mesajı; varsayılan `Yorumunuz gönderildi ve yönetici onayı bekliyor. Onaylandığında indirme bağlantıları otomatik açılacak.`
- `download_access_pending_button_text`: Bekleyen kart buton metni; varsayılan `Onay Bekleniyor`.

Mevcut ayar tablosu kullanılacak; migration gerekmeyecek.

## Bileşen sınırları

- `Topics/Legacy/helpers.php`: yorum ve erişim durumunun sunucu tarafı hesabı.
- `api/download-access.php`: durumun istemciye iletilmesi.
- `PublicThemeRenderer.php` ve legacy topic controller: ilk render verileri.
- `topic-downloads.tpl`: semantik başarı/kilit/ilerleme/eylem yapısı.
- `topic-downloads.js`: canlı durum geçişleri, daralma ve vurgular.
- `ui-foundation.css`: durum, hareket ve erişilebilir görsel stiller.
- `admin/helpers.php` ve `admin/settings.php`: ayar kataloğu ve form grubu.

## Hata davranışı

- API sorgusu başarısızsa mevcut kilit durumu güvenli biçimde korunacak.
- Bilinmeyen bir neden `locked` durumuna düşecek.
- Eksik DOM öğeleri ana indirme akışını bozmayacak.
- Daralma veya animasyon hatası indirme kartlarının kilit durumunu etkilemeyecek.

## Doğrulama

- PHP ve JavaScript sözdizimi kontrolleri.
- `auth_required`, `comment_required`, `comment_pending` ve açık erişim durumlarının sunucu testleri.
- İlk render ile API cevabının aşama/ilerleme eşitliği.
- Yorum sonrası sayfa yenilemeden başarıya geçiş.
- Onay bekleyen yorumda tekrar yorum yönlendirmesi olmaması.
- Başarı animasyonu ve hareket azaltma kontrolü.
- Otomatik daralma, hover/focus ertelemesi ve sıfır saniye davranışı.
- İlk kart ve yorum formu vurgularının temizlenmesi.
- İndirmeye başla butonunun ilk karta yönlendirmesi ve otomatik indirme başlatmaması.
- Admin alanlarının render edilmesi ve değerlerinin kaydedilebilir olması.
- Mobil görünüm, klavye kullanımı ve tarayıcı konsolu kontrolü.

