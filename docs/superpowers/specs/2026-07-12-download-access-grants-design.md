# Süreli İndirme Erişimi ve Yorum Yaşam Döngüsü Tasarımı

## Amaç

Yorum şartlı indirme erişimini kalıcı veya belirli süreli hale getirmek; süre dolduğunda kullanıcıdan yeni yorum istemek; yorum silme/reddetme işlemlerini erişim hakkına yansıtmak; yorum onaylandığında kullanıcıya bildirim göndermek ve admin ayarlarının tüm çalışma katmanlarına anında ulaşmasını doğrulamak.

## Kapsam

Bu tasarım üç geliştirmeyi birlikte ele alır:

1. Admin ayarları kaydedildiğinde indirme erişimi ayarlarının tüm önbellek katmanlarında anında yenilenmesi.
2. Yorum onayı indirme erişimini açtığında kullanıcıya site içi bildirim gönderilmesi.
3. Yorumla kazanılan indirme erişiminin kalıcı veya süreli yönetilmesi; yorum silinmesi, reddedilmesi, geri yüklenmesi ve sürenin dolması durumlarının güvenli biçimde işlenmesi.

## Tercih edilen mimari

Erişim hakkı yalnızca yorum tablosundan her istekte yeniden türetilmeyecek. Yorum ile kazanılan erişim için ayrı bir `topic_download_access_grants` tablosu kullanılacak.

Bu tablo aşağıdaki nedenlerle tercih edilir:

- Kalıcı erişim ile süreli erişim aynı modelde açıkça temsil edilir.
- Yorum silme sonrası kilitleme kapalıysa erişim, yorumdan bağımsız olarak korunabilir.
- Reddetme, silme, geri yükleme ve süre dolması ayrı yaşam döngüsü olayları olarak izlenebilir.
- Kullanıcının süre dolduktan sonra yeni bir yorumla yeni hak kazanması güvenilir biçimde doğrulanabilir.
- Bildirim tekrarları ve erişim denetimleri yorum kimliğiyle ilişkilendirilebilir.

## Veri modeli

Yeni tablo en az aşağıdaki alanları içerir:

- `id`
- `topic_id`
- `user_id`
- `comment_id`
- `grant_mode`: `permanent` veya `timed`
- `granted_at`
- `expires_at`: kalıcı erişimde `NULL`
- `revoked_at`: aktif hakta `NULL`
- `revoke_reason`: `comment_deleted`, `comment_rejected` veya başka tanımlı neden
- `created_at`
- `updated_at`

İndeksler:

- Kullanıcı ve konuya göre en son hakkı bulmak için `(topic_id, user_id, granted_at)`.
- Aynı yorumun ikinci kez hak üretmesini engellemek için benzersiz `(comment_id)`.
- Süresi dolan hak kontrolleri için `(expires_at)`.

Bir yorum yalnızca kendi konusu ve yorum sahibi için erişim hakkı oluşturabilir. Anonim yorumlar erişim hakkı oluşturmaz.

## Admin ayarları

Gelişmiş Ayarlar → İndirme Yöneticisi → Erişim Kilidi ve Metinler bölümüne aşağıdaki ayarlar eklenecek:

### Erişim süresi modu

`download_access_grant_mode`

- `permanent`: Erişim süre dolması nedeniyle kapanmaz.
- `timed`: Erişim belirlenen sürenin sonunda kapanır.

Varsayılan: `permanent`.

### Süre değeri

`download_access_grant_duration_value`

- Pozitif tam sayı.
- Varsayılan: `24`.
- Güvenli üst sınır admin ayar normalizasyonunda uygulanır.

### Süre birimi

`download_access_grant_duration_unit`

- `minutes`
- `hours`
- `days`

Varsayılan: `hours`.

Süre değeri ve birimi yalnızca `timed` modunda kullanılır. Arayüz, kalıcı mod seçildiğinde bu iki alanı pasif veya koşullu görünür hale getirir.

### Yorum silinince tekrar kilitle

`download_access_relock_on_comment_delete`

- Açık: Erişimi sağlayan yorum silindiğinde hak iptal edilir.
- Kapalı: Yorum silinse bile daha önce kazanılmış hak korunur.

Varsayılan: `1`.

Yorumun reddedilmesi bu ayardan bağımsızdır; reddedilen yorum erişim şartını karşılamadığı için hak her zaman iptal edilir.

### Süresi dolan erişim metinleri

`download_access_expired_title`

- Varsayılan: `Yorum erişim süreniz doldu`

`download_access_expired_message`

- Varsayılan: `İndirme bağlantılarını yeniden açmak için yeni bir yorum gönderin.`

### Aktif süre bilgisi

`download_access_active_until_template`

- Varsayılan: `İndirme erişiminiz {{expires_at}} tarihine kadar açık.`
- `{{expires_at}}` zorunlu değişkendir.
- Değişken yoksa güvenli varsayılan şablon kullanılır.

## Erişim hakkının başlangıcı

Yorum şartı `submitted` ise hak, yorum başarıyla oluşturulduğu anda başlar.

Yorum şartı `approved` ise hak, yönetici yorumu onayladığı anda başlar. Yorumun eski oluşturulma tarihi kullanılmaz.

Süreli erişimde `expires_at`, `granted_at + süre` olarak hesaplanır. Ayar daha sonra değişirse mevcut hakların bitiş zamanı geriye dönük değiştirilmez; yeni haklar yeni süreyle oluşturulur.

## Mevcut yorumların geçiş davranışı

Süreli erişim ilk kez devreye girdiğinde mevcut uygun yorumlar eski yorum tarihleriyle değerlendirilir.

- Yorum tarihi belirlenen sürenin içindeyse bu tarihe dayalı bir başlangıç hakkı oluşturulur.
- Yorum tarihi sürenin dışındaysa erişim açık sayılmaz ve kullanıcıdan yeni yorum istenir.
- Kullanıcıya `comment_expired` durumu gösterilir.

Bu geçişte mevcut kullanıcılara yeni bir süre başlangıcı verilmez. Kullanıcının seçtiği politika gereği eski yorumun yaşı esas alınır.

## Erişim denetimi

`topicDownloadAccessState()` mevcut üyelik ve yorum şartlarını korur; yorum şartlı modda ayrıca erişim hakkını değerlendirir.

Durum sırası:

1. Anonim kullanıcı için `auth_required`.
2. Yorum onayı bekleniyorsa `comment_pending`.
3. Geçerli hak varsa erişim açık.
4. Daha önce kazanılmış fakat süresi dolmuş hak varsa `comment_expired`.
5. Hiç hak veya uygun yorum yoksa `comment_required`.

`comment_expired` durumunda:

- Giriş adımı tamamlanmış görünür.
- Yorum adımı yeniden aktif görünür.
- İlerleme `1 / 3` mantığıyla hesaplanır.
- Kullanıcı yeni bir uygun yorum göndermeden eski yorum erişimi tekrar açamaz.

Süreli aktif hakta başarı alanında hesaplanan bitiş tarihi gösterilir. Sunucu render'ı ve AJAX durum güncellemesi aynı metni kullanır.

## Yorum yaşam döngüsü

### Yorum oluşturma

- `submitted` şartında uygun yorum oluşturulduğunda hak oluşturulur.
- `approved` şartında yorum beklemede kalır; henüz hak oluşturulmaz.

### Yorum onaylama

- Yorum daha önce onaylı değilse ve `approved` şartı kullanılıyorsa yeni hak oluşturulur.
- Aynı yorumun tekrar onaylanması yeni süre başlatmaz.
- Bildirim yalnızca gerçek bir durum geçişinde gönderilir.

### Yorum reddetme

- Yorumla ilişkili aktif hak her zaman iptal edilir.
- `download_access_relock_on_comment_delete` ayarı reddetme davranışını değiştirmez.

### Yorum silme

- Ayar açıksa yorumla ilişkili aktif hak `comment_deleted` nedeniyle iptal edilir.
- Ayar kapalıysa hak mevcut bitiş zamanına kadar veya kalıcı olarak devam eder.

Hem admin yorum silme akışı hem kullanıcı tarafındaki yorum silme API'si aynı erişim yaşam döngüsü yardımcısını çağırır.

### Yorum geri yükleme

- Silme nedeniyle iptal edilmiş hak yeniden değerlendirilir.
- Kalıcı hak tekrar etkinleştirilebilir.
- Süreli hakta ilk `granted_at` korunur; süre sıfırlanmaz.
- Bitiş zamanı geçmişse hak açılmaz ve kullanıcıdan yeni yorum istenir.
- Reddetme nedeniyle iptal edilen hak yalnızca yorum tekrar onaylanırsa açılabilir.

## Onay bildirimi

Mevcut Notifications modülündeki `comment_approved` olayı ve `NotificationDispatchService` kullanılacak. API veya admin uç noktasından doğrudan bildirim SQL'i yazılmayacak.

Yorum onayı indirme erişimini gerçekten açıyorsa bildirim içeriği:

- Başlık: `İndirme erişiminiz açıldı`
- Mesaj: `“{{topic_title}}” konusundaki yorumunuz onaylandı. İndirme bağlantıları artık kullanıma hazır.`
- Bağlantı: İlgili konu sayfası.
- Tür: `success`.

İndirme erişimiyle ilişkili olmayan onaylarda mevcut genel `comment_approved` şablonu kullanılabilir. Kullanıcının bildirim grubu, olay ve tür tercihleri korunur. Aynı yorum onayı için tekrar bildirim gönderilmesi dedupe anahtarıyla engellenir.

## Ayar önbelleği

Mevcut `invalidateAdminSettingsCache()` davranışı korunur:

- İstek içi statik cache geçersizleştirme bayrağı.
- APCu `admin_settings_v1` temizliği.
- `storage/cache/admin_settings_compiled.php` dosyasının silinmesi.

Yeni ayarlar `saveAdminSettings()` üzerinden kaydedilecek ve aynı invalidation zincirine dahil olacak. Doğrudan ayar tablosu güncelleyen yeni bir yol oluşturulmayacak.

Doğrulama, ayar kaydından hemen sonraki ayrı HTTP isteğinin yeni değeri okuduğunu kanıtlayacak. Beş dakikalık dosya cache süresinin dolması beklenmeyecek.

## Hata yönetimi

- Erişim hakkı tablosu okunamazsa güvenli davranış kilidi açık bırakmak değil, erişimi kapalı kabul etmektir.
- Hak oluşturma veya iptal işlemi başarısız olursa yorum işlemi geri alınmayacak; hata loglanacak ve sonraki erişim denetiminde güvenli yeniden uzlaştırma yapılacak.
- Bildirim hatası yorum onayını başarısız kılmayacak; hata `appLogException()` veya `error_log()` ile kaydedilecek.
- Geçersiz süre değeri/birimi admin ayarı normalizasyonunda güvenli varsayılana döner.
- Süre hesapları veritabanında UTC/standart uygulama zamanıyla tutulur; kullanıcıya mevcut uygulama saat diliminde gösterilir.

## Şema ve uyumluluk

- Yeni tablo için sürümlü migration eklenecek.
- Kurulum şeması yeni tabloyla güncellenecek.
- Uygulamanın runtime schema politikasına uygun, yalnızca mevcut migration/şema altyapısını kullanan bir ensure yolu sağlanacak.
- Eski ayarlarda yeni anahtarlar bulunmadığında varsayılan kalıcı erişim davranışı mevcut sistemi değiştirmeyecek.

## Test ve doğrulama

### Birim/yardımcı testleri

- Dakika, saat ve gün süre hesapları.
- Kalıcı hakta `expires_at = NULL`.
- Süreli hakta bitiş zamanı hesabı.
- Geçersiz süre biriminde güvenli varsayılan.
- `{{expires_at}}` şablon doğrulaması.

### Erişim senaryoları

- Yeni yorum, `submitted` şartında erişimi açar.
- Bekleyen yorum, `approved` şartında erişimi açmaz.
- Onaylanan yorum erişimi açar ve bildirim üretir.
- Aynı yorumu tekrar onaylamak süreyi yenilemez ve ikinci bildirim üretmez.
- Süresi dolan hak `comment_expired` durumuna geçer.
- Süresi dolduktan sonra yeni yorum yeni hak oluşturur.
- Silme sonrası kilitleme açık/kapalı davranışları.
- Reddetmenin erişimi her zaman iptal etmesi.
- Geri yüklemenin süreyi sıfırlamaması.
- Eski yorum tarihinin süre dışında olması halinde anında yeni yorum istenmesi.

### Önbellek senaryosu

- Admin ayarı kaydedilir.
- Ayrı bir HTTP isteğiyle konu erişim durumu alınır.
- Yeni değer dosya/APCu TTL beklenmeden görünür.

### Tarayıcı doğrulaması

- Admin alanlarının koşullu görünürlüğü.
- Aktif erişimde bitiş tarihi metni.
- Süresi dolan erişimde yeni başlık/açıklama ve `1 / 3` ilerleme.
- Yorum onayı sonrası bildirim çanı ve bildirim sayfasında tek kayıt.
- Yorum silme/reddetme sonrası AJAX durum güncellemesi.
- Tarayıcı konsolunda hata bulunmaması.

## Kapsam dışı

- Konu bazında farklı erişim süreleri.
- Kullanıcı bazında manuel süre uzatma.
- E-posta kanalını zorunlu hale getirme.
- Süre dolmadan otomatik hatırlatma bildirimi.
- Eski yorumları toplu olarak yeni tarihle yenileme.
