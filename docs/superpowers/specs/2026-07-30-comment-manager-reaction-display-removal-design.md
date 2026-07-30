# Yorum Yönetimi Tepki Bilgisi Kaldırma Tasarımı

## Amaç

Admin panelindeki Yorum Yönetimi yorum kartlarında gösterilen kalp ikonu ve tepki sayısını kaldırmak.

## Kapsam

- `admin/comments-manager.php` içindeki kalp ikonlu tepki bilgi öğesi kaldırılacak.
- Tarih ve diğer yorum bilgileri korunacak.
- Tepki veritabanı alanları, sorgular, tepki sistemi, stiller ve diğer ekranlar değiştirilmeyecek.

## Uygulama

Yorum kartının `ui-comment-manager-comment-info` alanındaki `bi-heart-fill` ikonunu ve `reaction_count` çıktısını içeren `span` bloğu silinecek.

## Doğrulama

- Dosya PHP sözdizimi kontrolünden geçirilecek.
- İlgili kalp ikonlu bloğun artık dosyada bulunmadığı doğrulanacak.
