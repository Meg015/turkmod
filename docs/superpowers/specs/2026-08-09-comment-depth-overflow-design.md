# Yorum Maksimum Derinlik Taşması Tasarımı

## Amaç

Yorum yanıtı, ayarlanan maksimum derinliğe ulaştığında reddedilmemelidir. Kullanıcının alıntıladığı yorum doğrudan `parent_id` olarak korunmalı; yeni yorum mevcut yorum ağacında alıntılanan yorumun altında, diğer yanıtlarla aynı girinti ve hizalama kurallarıyla gösterilmelidir. Bu akışta maksimum derinlik hatası toast bildirimi oluşmamalıdır. Geçerli diğer yorum hataları aynı şekilde gösterilmeye devam eder.

## Mevcut akış

- `assets/js/topic-comments.js`, yanıt formundan seçilen yorumun kimliğini `parent_id` olarak API'ye gönderir.
- `api/comments.php`, yanıtlanacak yorumun varlığını ve konuya ait olduğunu doğrular; ayrıca maksimum derinlikte isteği hata ile durdurur.
- API yanıtları `parent_id` ilişkisine göre özyinelemeli `replies` ağacı oluşturur.
- İstemci `renderComment()` ile bu ağacı özyinelemeli render eder ve her `replies` kapsayıcısında mevcut CSS girintisini uygular.

## Tasarım

### Sunucu davranışı

Yanıt ekleme sırasında maksimum derinlik reddi kaldırılacaktır. Var olan şu kontroller korunacaktır:

- Yanıt yorumlarının etkin olup olmadığı.
- Ebeveyn yorumun varlığı ve silinmemiş olması.
- Ebeveyn yorumun aynı konuya ait olması.
- İçerik, spam, oran sınırlaması, yetki ve CSRF doğrulamaları.

`parent_id` değiştirilmeyecek veya daha üst bir yoruma taşınmayacaktır. Böylece veritabanındaki ilişki, kullanıcının gerçekten alıntıladığı yorumla aynı kalır.

### İstemci ve görünüm

İstemci tarafında özel bir retry, hata mesajı filtreleme veya alternatif parent seçimi eklenmeyecektir. API başarılı döndüğünde mevcut başarı/pending akışı çalışacak ve yorumlar yeniden yüklenecektir. Mevcut özyinelemeli render ve CSS, doğrudan `parent_id` ilişkisini kullandığı için yeni yorum otomatik olarak alıntılanan yorumun altında ve hizalı görünecektir.

Maksimum derinlik ayarı artık yanıt eklemeyi engelleyen bir limit olarak kullanılmayacaktır. Bu değişiklik, istenen doğrudan-altına-ekleme davranışının doğal sonucudur; ayarın kaldırılması veya yönetim arayüzünün yeniden adlandırılması bu dar kapsamın dışındadır.

## Hata ve veri akışı

```text
Yanıtla → parent_id = alıntılanan yorum
        → API ebeveyn/konu/diğer kontrolleri
        → INSERT parent_id'yi aynen korur
        → Başarılı yanıt
        → yorum ağacı yeniden yüklenir
        → alıntılanan yorumun altında, mevcut girintiyle render
```

Maksimum derinlik durumunda artık 400 hata yanıtı üretilmeyeceği için istemcinin hata toast'ı tetiklenmez. Ağ, yetki, spam veya içerik kaynaklı gerçek hatalar değişmeden hata toast'ı göstermeye devam eder.

## Doğrulama ölçütleri

1. Maksimum derinlikteki bir yoruma yanıt gönderildiğinde istek başarılı olur ve maksimum derinlik hata toast'ı görünmez.
2. Oluşturulan kaydın `parent_id` değeri, yanıt formunda alıntılanan yorumun kimliğine eşittir.
3. Yenileme veya polling sonrasında yeni yorum alıntılanan yorumun `replies` listesinde görünür.
4. En az birden fazla derinlikte yanıtlar mevcut girinti/hizalama ile görünmeye devam eder.
5. Geçersiz ebeveyn, farklı konu, yanıtların kapalı olması ve diğer mevcut doğrulamalar hâlâ hata döndürür.
6. PHP sözdizimi ve ilgili JavaScript sözdizimi doğrulanır; mümkünse tarayıcı akışıyla manuel kontrol yapılır.

## Kapsam dışı

- Veritabanı şeması veya migration değişikliği.
- Yorum ağacının düzleştirilmesi ya da parent ilişkisinin değiştirilmesi.
- Yönetim ayarlarının, etiketlerinin veya maksimum derinlik alanının kaldırılması.
- Toast bileşeninin genel davranışının değiştirilmesi.
