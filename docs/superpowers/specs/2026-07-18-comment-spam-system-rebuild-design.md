# Yorum Spam Sistemi Sade Yenileme Tasarımı

## Amaç

Admin Paneli > Gelişmiş Ayarlar > Yorum Sistemi > Spam Yönetimi alanını baştan sadeleştirmek. Mevcut karmaşık yorum spam kontrolleri kaldırılacak ve sistem dört anlaşılır kural, tek davranış seçimi ve mevcut muafiyetler üzerine yeniden kurulacak.

Yeni sistemin hedefi şudur:

- Adminin kolay anlayacağı az sayıda ayar sunmak.
- Spam nedenini kullanıcıya toast mesajında açıkça göstermek.
- Spam yakalandığında adminin seçimine göre yorumu reddetmek veya onaya düşürmek.
- Eski rastgele metin skoru, link limiti, tekrar karakter ve tekrar yorum penceresi gibi davranışları tamamen devreden çıkarmak.

## Mevcut Durum Özeti

Mevcut kodda spam davranışı üç yere dağılmış durumda:

- `admin/settings.php` yorum ayarlarını ve Spam Yönetimi alt sekmesini gösteriyor.
- `admin/helpers.php` ayar tanımlarını ve kaydetme doğrulamalarını içeriyor.
- `api/comments.php` yorum gönderirken eski yasaklı kelime kontrolü, kelime filtresi ve merkezi spam değerlendirmesini birlikte çalıştırıyor.
- `includes/src/Engine/Comments/Support/helpers.php` içinde `commentSpamEvaluate()` merkezi spam kararını veriyor.

Mevcut sistemde kullanılmayan eski `detectSpam()` fonksiyonu da `api/comments.php` içinde duruyor. Yeni çalışmada bu teknik borç temizlenecek.

## Yeni Admin Ayarları

Spam Yönetimi sekmesinde yalnızca aşağıdaki ayarlar kalacak.

| Ayar | Önerilen anahtar | Tür | Varsayılan | Davranış |
| --- | --- | --- | --- | --- |
| Spam denetimi aktif | `comment_spam_detection` | bool | `1` | Kapalıysa tüm yeni spam kontrolleri çalışmaz. |
| Tek kelime filtresi | `comment_spam_exact_terms` | text | boş | Yorumun tamamı listedeki kelime veya ifadeyle eşleşirse spam sayılır. |
| Cümlede geçen kelime filtresi | `comment_spam_contains_terms` | text | boş | Listedeki kelime veya ifade yorum içinde geçerse spam sayılır. |
| Minimum harf/rakam sayısı | `comment_spam_min_alnum_count` | number | `2` | Yorumda bulunması gereken en az Unicode harf veya rakam sayısıdır. `0` kontrolü kapatır. |
| Büyük harf engelleme | `comment_spam_block_uppercase` | bool | `0` | Açıkken belirgin şekilde tamamı büyük harfle yazılmış yorumlar spam sayılır. |
| Spam'e takılan yorum ne yapılsın? | `comment_spam_violation_action` | select | `reject` | `reject` yorumu kaydetmeden reddeder, `pending` yorumu onaya düşürür. |
| Spam muaf kullanıcı adları | `comment_spam_exempt_usernames` | text | boş | Aynı kalır. Eşleşen kullanıcılar spam kontrollerinden muaftır. |
| Spam muaf gruplar | `comment_spam_exempt_groups` | text | boş | Aynı kalır. Eşleşen gruplar spam kontrollerinden muaftır. |

### Sıfır İle Kapatma

Kullanıcı isteğine göre sayısal kontrol olan `comment_spam_min_alnum_count` için `0` tamamen kapalı anlamına gelir.

Büyük harf engelleme yüzde veya sayı ayarı göstermeyecek. Admin sadece açar veya kapatır. Yanlış pozitifleri azaltmak için uygulama içinde kısa metinleri ve doğal kısaltmaları koruyan sabit güvenlik eşiği kullanılacak.

## Kaldırılacak Eski Ayarlar ve Davranışlar

Yeni sistemde aşağıdaki ayarlar ve kontroller kullanılmayacak:

- `banned_words`
- `comment_word_filter`
- `comment_auto_ban_words`
- `comment_spam_meaningless_enabled`
- `comment_spam_meaningless_phrases`
- `comment_spam_gibberish_enabled`
- `comment_spam_gibberish_max_length`
- `comment_spam_gibberish_score_threshold`
- `comment_spam_repeated_chars_enabled`
- `comment_spam_repeated_chars_limit`
- `comment_spam_duplicate_window_minutes`
- `comment_spam_max_links`
- `comment_spam_caps_enabled`
- `comment_spam_caps_min_letters`
- `comment_spam_caps_percent`
- `comment_spam_action` içindeki `store_rejected`
- `api/comments.php` içindeki kullanılmayan `detectSpam()`

Bu eski anahtarlar admin arayüzünde gösterilmeyecek ve yorum API'si tarafından spam kararında okunmayacak. Veritabanında eski satırlar kalsa bile yeni sistemin davranışını etkilemeyecek.

## Spam Değerlendirme Kuralları

Yeni merkezi değerlendirici ham yorum metnini, admin ayarlarını ve kullanıcı bağlamını alacak. Sonuç şu yapıda dönecek:

```php
[
    'is_spam' => true,
    'primary_reason' => 'contains_term',
    'matched_term' => 'reklam',
    'message' => 'Yorumunuz cümle içinde yasaklı kelime içeriyor: "reklam". Lütfen bu ifadeyi kaldırıp tekrar deneyin.',
    'reasons' => [
        [
            'code' => 'contains_term',
            'matched_term' => 'reklam',
        ],
    ],
]
```

### 1. Tek Kelime Filtresi

Bu filtre yorumun tamamını kontrol eder.

- Liste satır sonu, virgül veya noktalı virgülle ayrıştırılır.
- Boş ve tekrarlanan kayıtlar temizlenir.
- Karşılaştırma küçük/büyük harf duyarsız yapılır.
- Yorumun başındaki ve sonundaki boşluk veya noktalama temizlenir.
- Yorumun tamamı listedeki ifadeyle eşleşirse spam sayılır.

Örnek:

- Liste: `sa`
- Yorum: `sa`
- Sonuç: spam
- Yorum: `sa güzel olmuş`
- Sonuç: bu filtreye takılmaz

### 2. Cümlede Geçen Kelime Filtresi

Bu filtre yorumun içinde geçen yasaklı kelime veya ifadeleri kontrol eder.

- Liste satır sonu, virgül veya noktalı virgülle ayrıştırılır.
- Tek kelimelerde tam kelime sınırı aranır. `mod` kelimesi `model` içinde eşleşmez.
- Birden fazla kelimeli ifadelerde normalize edilmiş içerikte ifade geçiyorsa eşleşir.
- Eşleşen terim kullanıcı mesajında gösterilir.

Örnek:

- Liste: `reklam`
- Yorum: `buraya reklam bırakıyorum`
- Sonuç: spam, eşleşen terim `reklam`

### 3. Minimum Harf/Rakam Sayısı

Bu filtre yorumdaki Unicode harf ve rakamları sayar.

- Türkçe karakterler harf sayılır.
- Rakamlar anlamlı karakter sayılır.
- Noktalama, emoji, boşluk ve semboller sayılmaz.
- Ayar `0` ise kontrol kapalıdır.

Örnek:

- Ayar: `2`
- Yorum: `...`
- Sonuç: spam
- Yorum: `+1`
- Sonuç: iki anlamlı karakter olduğu için geçer

### 4. Büyük Harf Engelleme

Bu filtre yüzde ayarı kullanmaz. Admin yalnızca açar veya kapatır.

Uygulama içi davranış:

- Harf sayısı çok kısa olan yorumlar bu kontrolden muaf tutulur.
- Yorumdaki harflerin tamamı veya neredeyse tamamı büyük harfse spam sayılır.
- Rakam, noktalama ve emoji büyük harf kararını bozmaz.
- Küçük kısaltmaların yanlış engellenmemesi için kısa metinler korunur.

Örnek:

- `BU MOD COK GUZEL AMA HATA VAR` spam sayılır.
- `GTX 1660 ile denedim` spam sayılmaz.

## Sebep Önceliği ve Toast Mesajları

Bir yorum birden fazla kurala takılırsa kullanıcıya yalnızca en faydalı sebep gösterilecek. Tüm nedenler admin log metadata alanına yazılacak.

Öncelik sırası:

1. Cümlede geçen kelime filtresi
2. Tek kelime filtresi
3. Büyük harf engelleme
4. Minimum harf/rakam sayısı

Kullanıcı mesajları:

- Cümlede geçen kelime filtresi: `Yorumunuz cümle içinde yasaklı kelime içeriyor: "reklam". Lütfen bu ifadeyi kaldırıp tekrar deneyin.`
- Tek kelime filtresi: `Yorumunuz tek kelime filtresine takıldı: "sa". Lütfen daha açıklayıcı bir yorum yazın.`
- Büyük harf engelleme: `Yorumunuz büyük harf kullanımından dolayı spam filtresine takıldı. Lütfen tamamı büyük harf yazmadan tekrar deneyin.`
- Minimum harf/rakam sayısı: `Yorumunuz yeterli harf veya rakam içermiyor. Lütfen daha açıklayıcı bir yorum yazın.`

API hem `message` hem `error` alanlarında aynı kullanıcı dostu metni dönecek. Mevcut yorum JavaScript'i bu mesajı toast olarak gösterecek.

## Spam Eylemi

`comment_spam_violation_action` iki değer kabul eder:

- `reject`
- `pending`

Tanımsız veya eski bir değer gelirse güvenli varsayılan olarak `reject` kullanılacak.

### Reddet

- Yorum veritabanına eklenmez.
- API `422` döner.
- Kullanıcıya spam sebebini açıklayan toast gösterilir.
- Form içeriği korunur, kullanıcı düzeltip tekrar deneyebilir.
- Yorum sayacı, bildirim, mention, etkinlik puanı ve indirme erişimi oluşturulmaz.
- Admin loguna yorum metni uzunluğu, kullanıcı, konu ve spam nedenleri yazılır.

### Onaya Düşür

- Yorum `pending` durumuyla kaydedilir.
- Yorum herkese açık yorum listesinde görünmez.
- Kullanıcıya spam sebebini açıklayan ve yorumun onaya gönderildiğini belirten toast gösterilir.
- Bildirim, mention bildirimi, etkinlik puanı, yorum sayacı ve indirme erişimi onaydan önce oluşturulmaz.
- Admin yorum yönetimi ekranında yorum bekleyenler arasında görünür.

## Muafiyetler

Mevcut muafiyet davranışı korunacak:

- `comment_spam_exempt_usernames`
- `comment_spam_exempt_groups`

Muaf kullanıcılar yalnızca spam içerik kontrollerinden muaf olur. Normal yorum gönderim izinleri, uzunluk limitleri ve rate limit davranışı ayrı kalır.

Misafir yorumları için muafiyet uygulanmaz.

## Yorum Gönderim Akışı

Yeni akış:

1. Yorum sistemi açık mı kontrol edilir.
2. Kullanıcı veya misafir yorum izni kontrol edilir.
3. Kullanıcının yorum kısıtlaması var mı kontrol edilir.
4. Konu, boş yorum, minimum ve maksimum yorum uzunluğu doğrulanır.
5. Spam muafiyeti belirlenir.
6. Muaf değilse yeni spam değerlendirici çalışır.
7. Spam yoksa yorum mevcut normal akışla kaydedilir.
8. Spam varsa `comment_spam_violation_action` kararına göre reddedilir veya `pending` kaydedilir.
9. Kullanıcıya spam sebebi toast olarak döner.

## Admin Arayüzü

Spam Yönetimi alt sekmesinin yapısı sade olacak:

- Temel Koruma: spam denetimi aktif, spam eylemi
- Filtreler: tek kelime filtresi, cümlede geçen kelime filtresi, minimum harf/rakam sayısı, büyük harf engelleme
- Spam Muafiyetleri: kullanıcı adı ve grup listeleri

Eski Hızlı Filtreler ve Gelişmiş Eşikler grupları kaldırılacak.

## Geriye Uyumluluk

- Eski spam ayarları UI'dan kaldırılacak.
- Eski spam ayarları yorum API'sinde okunmayacak.
- `comment_spam_detection` anahtarı korunacak, çünkü genel spam aç/kapat davranışı için zaten doğru isimdir.
- Eski veritabanı ayar satırlarını silmek zorunlu değildir. İstenirse ayrı bir cleanup migration ile kaldırılabilir, ancak yeni sistemin doğru çalışması için şart değildir.
- Mevcut yorum tablosuna yeni kolon eklenmeyecek. Spam nedenleri mevcut aktivite/admin log metadata yapısıyla tutulacak.

## Test ve Doğrulama

Otomatik testler:

- Spam kapalıyken hiçbir yeni spam kuralı çalışmamalı.
- Tek kelime filtresi yalnızca yorumun tamamı eşleştiğinde çalışmalı.
- Cümlede geçen kelime filtresi tek kelimelerde kelime sınırına saygı göstermeli.
- Cümlede geçen kelime filtresi çok kelimeli ifadeleri yakalamalı.
- Minimum harf/rakam sayısı `0` iken kapalı olmalı.
- Minimum harf/rakam sayısı Türkçe harfleri ve rakamları anlamlı karakter kabul etmeli.
- Büyük harf engelleme açıkken uzun, tamamen büyük harf yorumları yakalamalı.
- Büyük harf engelleme kısa kısaltmaları yakalamamalı.
- Birden fazla spam sebebi varsa kullanıcı mesajı öncelik sırasına göre seçilmeli.
- `reject` eylemi yorum kaydetmeden `422` dönmeli.
- `pending` eylemi yorumu `pending` durumuyla kaydetmeli.
- Spam pending/reject akışlarında bildirim, mention, yorum sayacı, etkinlik puanı ve indirme erişimi oluşmamalı.
- Muaf kullanıcılar spam kontrollerinden geçmemeli.

Manuel doğrulama:

- Admin ayarında yalnızca yeni sade Spam Yönetimi alanları görünmeli.
- Eski spam alanları admin arayüzünde görünmemeli.
- Konu yorum formunda spam sebebi toast olarak görünmeli.
- Reddedilen spam yorumda form içeriği korunmalı.
- Onaya düşen spam yorum halka açık listede görünmemeli.

## Kapsam Dışı

- Makine öğrenmesi veya harici spam servisi.
- CAPTCHA.
- Otomatik kullanıcı banı.
- Ayrı spam yorum tablosu.
- Spam skor sistemi.
- Link sayısı, tekrar karakter, rastgele metin ve aynı yorum tekrar filtresinin yeni sürüme taşınması.
