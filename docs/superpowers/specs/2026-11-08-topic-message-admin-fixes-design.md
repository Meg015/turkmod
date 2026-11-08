# Konu, mesajlaşma ve admin akışları

Kapsam kullanıcının bildirdiği beş sorunun mevcut PHP/JavaScript ve TürkMod teması içinde giderilmesidir.

- Bulunamayan, silinen veya yayından kaldırılan konular HTTP 404 ve noindex ile ortalanmış, tema renklerine uyumlu bir boş durum gösterir. Ana sayfa ve kategori keşfi bağlantıları bulunur; genel 404 de aynı görünümü kullanır.
- Revizyon notunda e-posta, özel mesaj ve site içi bildirim bağımsız seçilir. Başlangıçta yalnız site içi bildirim seçilidir. Seçilmeyen kanal çalışmaz. Not ve konu düzenleme bağlantısı seçilen kanallara aktarılır. Teslimat hataları moderasyonun kaydedilmediği izlenimini yaratmadan açıkça bildirilir.
- Görünen sohbetin mesajları okunur; aynı sohbete ait çan kayıtları da okunmuş olur. Mesaj ekranında özel mesaj toastları gösterilmez. Diğer bildirim türleri ve başka sohbetlerin okunmamış sayıları korunur.
- Mesaj ekranı WebSocket olaylarına ek olarak önbelleksiz, süre sınırlı HTTP yenilemesi yapar; sekmeye dönüş ve bağlantının geri gelmesi hemen yeniler. Uzun sohbetlerde en yeni mesajlar gösterilir, eski mesajlar geçmişten yüklenebilir.
- Yeni mod formu her yeni açılışta boş başlar. İki eski otomatik taslak yolu kaldırılır, Quill ve indirme alanları birlikte sıfırlanır. Aynı formdaki adımlar arasında kullanıcının o an yazdığı bilgiler korunur.
- Admin ortak uyarısı boş içerikle popup açmaz, giriş alanı yalnız prompt için görünür. Başarılı işlemler ek Tamam popupı istemez; mevcut toast/yenileme akışı kullanılır. Hata ve gerekli bilgi girişleri görünür kalır.

Yaklaşım: ekranlarda ayrı yamalar yerine mevcut ortak 404, bildirim, mesaj ve admin yardımcılarını düzeltmek. Yeni bağımlılık veya veritabanı şeması gerekmiyor. Genel bildirimleri kapatmak diğer olayları kaybettireceği için seçilmedi; yalnız WebSocket kullanmak bağlantı arızasını çözmediği için HTTP yedeği korunuyor.

Doğrulama: yalıtılmış veritabanı ile kanal matrisi, uzun sohbet ve okundu eşitlemesi; gerçek JavaScript ile bildirim/popup davranışı; masaüstü ve mobil 404/form görsel kontrolü; PHP lint, JavaScript testleri ve üretim build. Gerçek kullanıcılara test mesajı veya e-posta gönderilmez.
