# Sidebar “Yeni Konu” Menü Öğesini Kaldırma Tasarımı

## Amaç

Admin panelinin sol sidebar menüsündeki “Yeni Konu” bağlantısını kaldırmak.

## Kapsam

- `admin/sidebar.php` içindeki “Yeni Konu” menü bağlantısı kaldırılacak.
- `admin/create.php` sayfası korunacak.
- Konu listesi gibi diğer ekranlardaki “Yeni Konu” butonları korunacak.
- Menü sıralaması, stiller ve diğer sidebar bağlantıları değiştirilmeyecek.

## Uygulama Yaklaşımı

Bağlantıyı CSS ile gizlemek veya yalnızca etiket metnini silmek yerine, ilgili bağlantı HTML'i sidebar şablonundan tamamen kaldırılacak. Böylece erişilemeyen ya da boş görünen bir menü öğesi kalmayacak.

## Doğrulama

- Kaynak taramasında `admin/sidebar.php` içinde sidebar bağlantısının artık bulunmadığı doğrulanacak.
- PHP sözdizimi kontrolü çalıştırılacak.
- `admin/create.php` ve sayfa içi “Yeni Konu” aksiyonlarının değiştirilmediği diff üzerinden doğrulanacak.
