# Topic Download Access Notice Polish

## Amaç

Konu indirme bölümündeki `.topic-dl-access-notice` bileşeninin mevcut görsel yapısını ve davranışını koruyarak hizalama, boşluk, okunabilirlik ve mobil görünüm sorunlarını düzeltmek.

## Kapsam

- Mevcut HTML, metinler, durum sınıfları ve JavaScript davranışı değişmeyecek.
- Ana ikon daha dengeli bir görsel alana yerleştirilecek.
- Başlık, açıklama, süre ve ilerleme bilgilerinin dikey ritmi sıkılaştırılacak.
- Adım kutuları masaüstünde eşit ve okunaklı, dar ekranda taşmadan kompakt görünecek.
- Bekleyen, tamamlanan, başarılı ve süresi dolmuş durumların mevcut renk anlamları korunacak.
- Açık ve koyu tema değişkenleri kullanılmaya devam edilecek.

## Uygulama

Değişiklikler `assets/css/ui-foundation.css` içindeki mevcut bileşen kurallarıyla sınırlı olacak. Üretilen public CSS paketi mevcut build komutuyla yenilenecek. Yeni JavaScript veya şablon yapısı eklenmeyecek.

## Doğrulama

- PHP/CSS build kontrolleri çalıştırılacak.
- Notice bileşeni masaüstü ve mobil genişliklerde kontrol edilecek.
- Başarılı, bekleyen ve kilitli durum sınıflarının görünümü korunacak.
- Metin veya adım sayısı değiştiğinde yatay taşma oluşmadığı doğrulanacak.
