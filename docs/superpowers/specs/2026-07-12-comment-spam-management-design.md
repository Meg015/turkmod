# Yorum Spam Yönetimi Tasarımı

## Amaç

Admin Paneli > Gelişmiş Ayarlar > Yorum Sistemi altında ayrı bir **Spam Yönetimi** sekmesi oluşturmak ve yorum gönderimindeki spam kontrollerini yönetilebilir hale getirmek. Sistem yalnızca tekrar yorum ve bağlantı sayısını değil; noktalama işaretlerinden oluşan içerikleri, anlamsız kısa ifadeleri, tekrarlanan karakterleri ve aşırı büyük harf kullanımını da denetlemelidir.

Spam ile ilişkili mevcut ayarlar yeni sekmeye taşınacak ve önceki sekmelerde tekrar gösterilmeyecektir.

## Mevcut Durum

- `admin/settings.php` içindeki Yorum Sistemi alanında Genel, Limitler & Kısıtlamalar, Özellikler ve Moderasyon & Şikayet sekmeleri bulunuyor.
- `comment_spam_detection`, `comment_word_filter` ve `comment_auto_ban_words` ayarları Moderasyon & Şikayet sekmesinde gösteriliyor.
- `api/comments.php` içindeki mevcut spam kontrolü şu kurallarla sınırlı:
  - Aynı kullanıcının aynı içeriği son beş dakika içinde yeniden göndermesi.
  - Üçten fazla bağlantı.
  - En az on Latin harfi bulunan içeriğin yüzde yetmişten fazlasının büyük harf olması.
- Spam tespit edilirse yorum her durumda kaydedilmeden `429` cevabıyla reddediliyor.
- Noktalama-only yorumlar ve `vv`, `sa`, `as` gibi anlamsız ifadeler için ayrı denetim bulunmuyor.

## Yönetim Arayüzü

Yorum Sistemi alt sekmelerine `Spam Yönetimi` eklenecek. Sekme açıklaması, yorum kalitesi kontrollerini ve spam tespit edildiğinde uygulanacak davranışı yönettiğini açıkça belirtecek.

### Taşınacak mevcut ayarlar

Aşağıdaki ayarlar Moderasyon & Şikayet sekmesinden kaldırılıp Spam Yönetimi sekmesinde tek kez gösterilecek:

- `comment_spam_detection`
- `comment_word_filter`
- `comment_auto_ban_words`

Moderasyon & Şikayet sekmesinde yalnızca şikayet sistemi ve şikayet sayısına bağlı otomatik gizleme gibi şikayet odaklı ayarlar kalacak.

### Yeni spam ayarları

| Ayar | Tür | Varsayılan | Davranış |
|---|---|---:|---|
| `comment_spam_action` | select | `reject` | Spam tespit edildiğinde kaydetmeden reddetme, onaya gönderme veya reddedilmiş olarak kaydetme işlemini seçer. |
| `comment_spam_reject_message` | string | `Yorumunuz spam veya anlamsız içerik olarak algılandı. Lütfen daha açıklayıcı bir yorum yazın.` | Kullanıcıya gösterilecek güvenli ve genel hata mesajıdır. |
| `comment_spam_punctuation_only_enabled` | bool | `1` | Yalnızca noktalama, sembol, boşluk veya emoji içeren yorumları spam sayar. |
| `comment_spam_min_meaningful_chars` | number | `2` | Yorumda bulunması gereken minimum Unicode harf veya rakam sayısını belirler. `0` kontrolü kapatır. |
| `comment_spam_meaningless_enabled` | bool | `1` | Anlamsız ifade listesi kontrolünü açar. |
| `comment_spam_meaningless_phrases` | text | Satır bazlı `vv`, `sa`, `as` | Normalize edilmiş yorum listedeki ifadelerden yalnızca biriyle eşleşirse spam sayar. |
| `comment_spam_repeated_chars_enabled` | bool | `1` | Uzun karakter tekrarlarını denetler. |
| `comment_spam_repeated_chars_limit` | number | `5` | Aynı harf, rakam veya noktalama işaretinin art arda kaç kez kullanılabileceğini belirler. `0` kontrolü kapatır. |
| `comment_spam_duplicate_window_minutes` | number | `5` | Aynı kullanıcının aynı normalize edilmiş yorumu yeniden göndermesinin denetleneceği süreyi belirler. `0` kontrolü kapatır. |
| `comment_spam_max_links` | number | `3` | Bir yorumda izin verilen maksimum HTTP/HTTPS bağlantı sayısını belirler. `0` bağlantıya izin vermez, `-1` yalnızca bağlantı kontrolünü kapatır. |
| `comment_spam_caps_enabled` | bool | `1` | Aşırı büyük harf kontrolünü açar. |
| `comment_spam_caps_min_letters` | number | `10` | Büyük harf oranı uygulanmadan önce gereken minimum harf sayısıdır. |
| `comment_spam_caps_percent` | number | `70` | Büyük harf oranının spam kabul edileceği yüzde eşiğidir. |

`comment_spam_action` seçenekleri:

- `reject`: Yorumu veritabanına kaydetmeden reddeder.
- `pending`: Yorumu `pending` durumuyla kaydeder ve yönetici incelemesine gönderir.
- `store_rejected`: Yorumu `rejected` durumuyla kaydeder ve Yorum Yönetimi ekranında görünür kılar.

## Spam Değerlendiricisi

Mevcut boolean `detectSpam()` yaklaşımı, yapılandırılmış sonuç döndüren merkezi bir değerlendiriciye dönüştürülecek. Değerlendirici ham yorum, ayarlar, veritabanı bağlantısı ve kullanıcı kimliğini alacak; aşağıdaki yapıya denk bir sonuç üretecek:

```php
[
    'is_spam' => true,
    'reasons' => ['punctuation_only', 'meaningless_phrase'],
]
```

Kontroller birbirinden bağımsız küçük yardımcılarla yürütülecek. Sonuç kullanıcıya kural ayrıntılarını açıklamayacak; ayrıntılı nedenler yalnızca sunucu tarafındaki faaliyet/güvenlik kaydında kullanılacak. Bu, spam kurallarının kötüye kullanılmasını zorlaştırırken yöneticinin teşhis yapabilmesini sağlar.

### Normalizasyon

- Baştaki ve sondaki boşluklar kaldırılır.
- Karşılaştırma için metin Unicode uyumlu biçimde küçük harfe çevrilir.
- Birden fazla boşluk tek boşluk haline getirilir.
- Anlamsız ifade eşleşmesinde çevreleyen noktalama işaretleri yok sayılır; ancak normal bir cümle içinde geçen kısa heceler tek başına eşleşmiş sayılmaz.
- Harf/rakam denetimi Unicode sınıflarıyla yapılır; Türkçe karakterler anlamlı karakter kabul edilir.

Bu kurallar sayesinde `...`, `---`, `,,,`, yalnızca emoji içeren içerikler ve `vv` gibi listedeki tek başına ifadeler engellenirken, listedeki kısa bir parçayı normal bir kelime içinde barındıran geçerli yorumlar yanlışlıkla engellenmez.

### Kontrol sırası

1. Mevcut boşluk, minimum uzunluk ve maksimum uzunluk doğrulamaları.
2. Mevcut yasaklı kelime/kelime filtresi işlemleri.
3. Yapılandırılmış spam değerlendirmesi ve uygulanacak durumun belirlenmesi.
4. Mevcut yorum gönderim oran sınırı kontrolü.
5. `reject` davranışında hatanın döndürülmesi; diğer davranışlarda belirlenen durumla kontrollü kayıt akışı.

Kelime filtresi sansürleme seçeneği kullanılıyorsa spam değerlendirmesi sansürlenmiş son metin üzerinde çalışır.

## Spam Davranışları ve Yan Etkiler

### Kaydetmeden reddet

- Yorum eklenmez.
- Yapılandırılmış bir istemci hatası ve ayarlanabilir genel mesaj döndürülür.
- HTTP cevabı spam tespitini ifade eden `422` olur; oran sınırı için kullanılan `429` ile karıştırılmaz.
- Bildirim, mention, puan, yorum sayacı ve indirme erişim hakkı üretilmez.

### Yönetici onayına gönder

- Yorum `pending` durumuyla normal yorum tablosuna eklenir.
- Genel yorum onayı ayarı kapalı olsa bile bu yorum onaya düşer.
- Yorum herkese açık listede görünmez.
- Onaylanmadan bildirim, etkinlik puanı, genel yorum sayacı veya yoruma bağlı indirme erişimi oluşturmaz.
- Başarılı cevap, yorumun inceleme beklediğini belirtir.

### Reddedilmiş/spam olarak kaydet

- Yorum `rejected` durumuyla kaydedilir ve Yorum Yönetimi ekranındaki reddedilmiş yorumlar filtresinde görünür.
- Bildirim, mention bildirimi, etkinlik puanı, genel yorum sayacı ve indirme erişimi oluşturmaz.
- İstemciye yorumun kabul edilmediğini belirten hata cevabı döndürülür.
- Tespit nedenleri yorum kimliğiyle birlikte faaliyet/güvenlik kaydına yazılır.

Spam nedeniyle `pending` veya `rejected` kaydedilen yorumların nedenlerinin kaydı mevcut faaliyet günlüğünün metadata desteği üzerinden yapılacak; yalnızca bu özellik için yeni bir veritabanı tablosu zorunlu tutulmayacak.

## Misafir ve Yönetici Davranışı

- Spam kontrolleri yalnızca giriş yapmış kullanıcılara değil, izin verilmiş misafir yorumlarına da uygulanır.
- Yinelenen yorum kontrolü giriş yapmış kullanıcılar için kullanıcı kimliğiyle, misafirler için güvenli IP tabanlı oran anahtarıyla çalışır. Misafir tekrar kontrolü mevcut yorum tablosunda IP tutulmadığı için yalnızca mevcut oran sınırı altyapısının sağlayabildiği süreli anahtar üzerinden uygulanır.
- Yönetici oran sınırı muafiyeti spam kalite kontrollerini otomatik olarak devre dışı bırakmaz. Spam sistemi açıksa yöneticilerin yorumları da içerik kurallarından geçer.

## Ayar Doğrulaması

- Sayısal eşikler güvenli alt ve üst sınırlara çekilir; yalnızca `comment_spam_max_links` için `-1` özel kapatma değeri kabul edilir.
- Büyük harf yüzdesi `1-100` arasında tutulur.
- Büyük harf minimum harf sayısı ve tekrar karakter limiti negatif olamaz.
- Anlamsız ifade listesi satır bazında ayrıştırılır, boş ve yinelenen değerler temizlenir.
- Tanınmayan `comment_spam_action` değeri güvenli varsayılan olan `reject` davranışına düşer.
- Spam sistemi kapalı olduğunda yeni içerik kontrolleri uygulanmaz; bağımsız kelime filtresi mevcut davranışını sürdürür.

## İstemci Davranışı

Mevcut yorum JavaScript akışı korunacak. API tarafından dönen genel mesaj form üzerinde gösterilecek. `pending` sonucu başarılı gönderim olarak ele alınacak ve inceleme mesajı gösterilecek; `reject` ve `store_rejected` sonuçlarında form içeriği korunarak kullanıcıya yorumunu düzeltme imkânı verilecek.

## Geriye Uyumluluk

- Mevcut ayar anahtarları silinmeyecek; yalnızca yönetim ekranındaki sekmeleri değişecek.
- Yeni ayarların veritabanında karşılığı yoksa tanım varsayılanları kullanılacak.
- Mevcut `comment_spam_detection=0` kurulumu yeni bütün spam kontrollerini kapalı tutacak.
- Kelime filtresi ve yasaklı kelime davranışının mevcut değerleri korunacak.
- Yeni bir spam tablosu veya yorum durum değeri eklenmeyecek; mevcut `pending` ve `rejected` durumları kullanılacak.

## Test ve Doğrulama

Otomatik smoke/regresyon testi aşağıdaki örnekleri kapsayacak:

- `...`, `---`, `,,,` ve yalnızca emoji: noktalama/sembol-only spam.
- `vv`, `sa`, `as` ve çevresinde boşluk/noktalama bulunan varyasyonları: anlamsız ifade spam'i.
- Normal bir Türkçe cümle: kabul edilmeli.
- Türkçe karakter içeren kısa ama eşikleri karşılayan anlamlı yorum: kabul edilmeli.
- `aaaaaa`, `!!!!!!` ve benzeri tekrarlar: ayarlanan eşikte spam.
- İzin verilenden fazla bağlantı: spam.
- Büyük harf oranı eşik üstü ve eşik altı örnekleri.
- Aynı normalize edilmiş yorumun tekrar gönderimi ve süre penceresi dışındaki tekrar.
- Spam sistemi kapalıyken yeni kuralların uygulanmaması.
- `reject`, `pending` ve `store_rejected` işlemlerinin doğru HTTP/API sonucu ve veritabanı durumu.
- Spam yorumlarda yorum sayacı, bildirim, mention, puan ve indirme erişimi yan etkilerinin oluşmaması.
- Ayar ekranında taşınan alanların yalnızca Spam Yönetimi sekmesinde bir kez görünmesi.

Doğrulama ayrıca değiştirilen PHP dosyalarında `php -l`, ilgili ayar ekranının DOM kontrolü ve gerçek yorum API'sine kontrollü isteklerle yapılacak. Veritabanı yazma testleri işlem içinde çalıştırılıp geri alınacak veya test kayıtları hedefli biçimde temizlenecek.

## Kapsam Dışı

- Makine öğrenmesi tabanlı spam sınıflandırması.
- Harici spam servisleri.
- CAPTCHA sistemi.
- Kullanıcıya otomatik ban veya hesap cezası verilmesi.
- Ayrı bir spam yorum tablosu veya genel amaçlı kural oluşturucu arayüzü.
