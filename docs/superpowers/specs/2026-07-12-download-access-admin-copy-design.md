# İndirme Erişimi Admin Metin Yönetimi Tasarımı

## Amaç

Başarı alanındaki “İndirmeye Başla” eylemini tamamen kaldırmak ve yorum şartı görünümündeki başlık, açıklama ve ilerleme cümlesini admin panelinden yönetilebilir hale getirmek.

## Kaldırılacak eylem

- Başarı bildirimindeki “İndirmeye Başla” butonu tema ve legacy çıktısından kaldırılacak.
- Butona ait JavaScript olayları ve CSS kuralları kaldırılacak.
- `download_access_start_button_enabled` ve `download_access_start_button_label` admin ayarları katalogdan ve İndirme Yöneticisi grubundan kaldırılacak.
- Kilit canlı açıldığında ilk kartın otomatik vurgulanması korunacak.

## Yönetilebilir metinler

### Yorum başlığı

Yeni `download_access_comment_title` ayarı eklenecek.

- Varsayılan: `Yorum gerekli`
- Konu ilk render'ında ve canlı erişim güncellemesinde aynı değer kullanılacak.

### Yorum açıklaması

Mevcut `download_access_comment_message` ayarı kullanılmaya devam edecek.

- Varsayılan mevcut davranışla aynı kalacak: `Önce bir yorum gönderin; kilit otomatik açılır.`
- Yeni, yinelenen bir ayar oluşturulmayacak.

### İlerleme şablonu

Yeni `download_access_progress_template` ayarı eklenecek.

- Varsayılan: `{{completed}} adımdan {{total}} adımı tamamlandı`
- `{{completed}}` tamamlanan adım sayısıyla değiştirilecek.
- `{{total}}` toplam adım sayısıyla değiştirilecek.
- Admin metni boşsa veya iki gerekli değişkenden biri yoksa güvenli varsayılan şablon kullanılacak.
- Sunucu render'ı ve JavaScript canlı güncellemesi aynı normalize edilmiş şablonu kullanacak.

## Bileşen zinciri

- `admin/helpers.php`: yeni ayar tanımları ve kaldırılan buton ayarları.
- `admin/settings.php`: İndirme Erişim Kilidi alan listesi.
- `PublicThemeRenderer.php`: runtime ayar aktarımı, başlık ve ilerleme metni oluşturma.
- Legacy topic controller: aynı ayar ve şablon davranışı.
- `topic-downloads.tpl`: butonsuz semantik çıktı.
- `topic-downloads.js`: admin başlığı ve ilerleme şablonuyla canlı güncelleme.
- `ui-foundation.css`: kullanılmayan buton/eylem stillerinin temizlenmesi.

## Doğrulama

- Başarı alanında buton veya artık eylem kapsayıcısı bulunmaması.
- Admin panelinde kaldırılan iki buton ayarının bulunmaması.
- Yeni yorum başlığı ve ilerleme şablonu ayarlarının görünmesi.
- Yorum gerekli sayfada admin başlığı, açıklaması ve hesaplanmış ilerleme metninin doğru render edilmesi.
- API sonrası canlı güncellemede aynı şablonun kullanılması.
- Geçersiz şablonda güvenli varsayılana dönülmesi.
- PHP/JavaScript lint, tarayıcı konsolu ve mevcut başarı/kilit regresyon kontrolleri.

