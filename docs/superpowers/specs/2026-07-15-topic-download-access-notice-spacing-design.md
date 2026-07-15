# Topic Download Access Notice Spacing Revision

## Amaç

Konu indirme alanındaki `.topic-dl-access-notice` bileşeninin mevcut görsel karakterini ve davranışını koruyarak içeriğin sıkışık görünmesini gidermek.

## Kapsam

- Yalnızca `assets/css/ui-foundation.css` içindeki erişim bildirimi kuralları düzenlenecek.
- HTML, PHP şablonları, JavaScript davranışı ve erişim kilidi akışı değiştirilmeyecek.
- Normal, başarı, bekleme, süresi dolmuş ve kompakt durumlar korunacak.
- Masaüstü ve mobil ölçüler birlikte ele alınacak.

## Tasarım

- Dış kutunun padding değeri ve ikon ile içerik arasındaki boşluk ölçülü biçimde artırılacak.
- Başlık, açıklama, süre/ilerleme bilgisi ve adımlar arasındaki dikey ritim belirginleştirilecek.
- İkon kutusu mevcut biçimini koruyacak; yalnızca içerikle daha dengeli hizalanacak.
- Adım satırı üst içerikten daha net ayrılacak ve adım kartlarının iç boşluğu artırılacak.
- Küçük ekranlarda bileşen iki sütunlu yapısını koruyacak; adım kartları okunabilir kalacak ve yatay taşma oluşturmayacak.

## Başarı Ölçütleri

- Bileşenin iç elementleri birbirine yapışık görünmemeli.
- Başlık ve açıklama tek bir bilgi grubu gibi okunmalı; durum/ilerleme ve adımlar ayrı seviyeler oluşturmalı.
- İki ve üç adımlı varyantlarda eşit dağılım korunmalı.
- Başarı durumundaki kompakt görünüm gereksiz yüksekliğe dönüşmemeli.
- CSS derlemesi hatasız tamamlanmalı.

## Doğrulama

- CSS üretim komutu çalıştırılacak.
- Kaynak ve üretilmiş CSS içinde ilgili seçicilerin bulunduğu kontrol edilecek.
- Mümkün olan yerel konu sayfasında masaüstü ve dar ekran görünümü incelenecek.
