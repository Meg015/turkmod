# Yorum Derinlik Taşması Hizalama Tasarımı

## Amaç

Maksimum yanıt derinliği aşıldığında yorum doğrudan alıntılanan yorumun `parent_id` ilişkisini korumalı, ancak görsel olarak bir seviye daha sağa kaymamalıdır. Taşan yorum, maksimum seviyedeki alıntılanan yorumla aynı sol hizadan, onun altında dikey olarak gösterilmelidir. Alıntı etiketi ve yorumun gerçek parent ilişkisi korunacaktır.

## Mevcut durum

- `api/comments.php` artık maksimum derinlikte yanıtı reddetmiyor; `parent_id` doğrudan alıntılanan yoruma kaydediliyor.
- API, yorumları `parent_id` üzerinden özyinelemeli `replies` ağacına dönüştürüyor.
- `assets/js/topic-comments.js`, her `replies` katmanına CSS ile sağ girinti veren kapsayıcılar oluşturuyor.
- `comment_max_depth` ayarı şu an yorum istemcisinin veri özniteliklerine aktarılmadığı için yalnızca sunucu ayarı olarak mevcut.

## Tasarım

### Derinlik bilgisinin istemciye aktarılması

Mevcut ayar, hem şablon tabanlı hem de PHP ile doğrudan oluşturulan yorum bölümüne `data-comment-max-depth` olarak aktarılacaktır. Varsayılan değer `3` olacaktır. `0` değeri, mevcut sunucu davranışındaki gibi görsel kırılmayı devre dışı bırakıp sınırsız iç içe görünüm anlamına gelecektir.

### Render derinliği

`renderComment()` fonksiyonuna sıfır tabanlı derinlik parametresi eklenecektir:

- Kök yorum derinliği `0` olacaktır.
- Bir yanıt, parent yorumun derinliğine `1` eklenerek render edilecektir.
- Mevcut ayarın seviye anlamı korunacaktır: `comment_max_depth = 3` için derinlik `0`, `1` ve `2` normal girintili; derinlik `3` ve sonrası taşmış kabul edilecektir.

Bir yorumun altındaki `replies` kapsayıcısı, bir sonraki derinlik maksimum değere ulaşıyor veya aşıyorsa `ui-comment-replies--depth-overflow` sınıfını alacaktır. Böylece taşma, yorumun hemen altındaki ilk seviyede başlar ve daha derin yanıtlar da aynı yatay hizada kalır.

### Görsel davranış

Taşmış kapsayıcı için yalnızca yorum ağacının girintisini oluşturan özellikler sıfırlanacaktır:

- `margin-left: 0`
- `padding-left: 0`
- `border-left: none`

Kapsayıcı içindeki `gap` ve üst boşluk korunacaktır. Sonuç olarak taşan yorum, maksimum seviyedeki parent yorumun bulunduğu yatay başlangıçtan devam eder; ardışık taşmış yanıtlar alt alta görünür. Mobil ve masaüstünde aynı kural geçerli olacaktır.

## Veri akışı

```text
comment_max_depth ayarı
        ├─ data-comment-max-depth
        └─ renderComment(comment, depth)
              ├─ normal replies wrapper → mevcut girinti
              └─ depth overflow wrapper → parent ile aynı sol hizası
```

Veritabanı ve API parent ilişkisi değişmeyecektir. `parent_author` ve `parent_body_preview` alanları aynı kaldığından alıntı etiketi görünmeye devam edecektir. Bu nedenle görsel düzleştirme, yanıtın hangi yoruma verildiği bilgisini kaybetmeyecektir.

## Doğrulama ölçütleri

1. `comment_max_depth = 3` iken ilk üç görsel seviye mevcut girintiyle görünür.
2. Dördüncü seviyedeki yanıt, üçüncü seviyedeki alıntılanan yorumla aynı sol hizadan, altına eklenir.
3. Beşinci ve sonraki seviyeler yeni bir sağ girinti oluşturmaz; aynı hizada alt alta devam eder.
4. Taşan yorumun alıntı etiketi ve `parent_id` ilişkisi korunur.
5. `comment_max_depth = 0` iken taşma sınıfı eklenmez ve mevcut sınırsız girinti korunur.
6. PHP ve JavaScript sözdizimi kontrolleri başarılı olur; CSS kuralı mevcut tema override’larından sonra da uygulanır.

## Kapsam dışı

- Yanıtın başka bir parent’a taşınması veya veritabanı ilişkisinin değiştirilmesi.
- Maksimum derinlikte yanıt oluşturmayı yeniden engellemek.
- Yorum ağacının sıralama, pagination veya polling davranışını değiştirmek.
- Toast bileşeninin genel davranışını değiştirmek.
