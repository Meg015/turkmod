# Yorum Yönetimi Kullanıcı Geçmişi Modalı Tasarımı

## Amaç

Admin Yorum Yönetimi ekranındaki yorum kartlarından, yorum sahibinin kapsamlı profil ve moderasyon geçmişine hızlı erişim sağlamak. Yönetici aynı modal üzerinden gerekli temel moderasyon işlemlerini gerçekleştirebilmelidir.

## Kapsam

Modal aşağıdaki bölümleri içerir:

- Kullanıcı profil özeti
- Hesap, grup ve ban durumu
- Kayıt ve son giriş bilgileri
- Temel içerik ve moderasyon sayaçları
- Son yorumlar
- Son konular
- Kullanıcı hakkındaki raporlar
- Admin notları
- Ceza ve kısıtlama geçmişi
- Son kullanıcı hareketleri
- Banla veya banı kaldır
- Kısıtlama ekle
- Admin notu ekle

Her geçmiş listesi en fazla son 20 kaydı gösterir. Tam geçmiş için ilgili kullanıcı yönetimi görünümüne bağlantı sunulur. Modal içinde geçmiş sayfalaması yapılmaz.

## Mimari

Mevcut `admin/api/user-details.php` kullanıcı detay API'si genişletilir. Yeni, yorum yönetimine özel ikinci bir kullanıcı API'si oluşturulmaz.

Yorum kartındaki kullanıcı tetikleyicisi kullanıcı kimliğini taşır. Tetikleyiciye basıldığında Yorum Yönetimi JavaScript'i API'den kullanıcı verisini alır ve ortak modal gövdesini doldurur.

API yanıtı şu mantıksal bölümleri taşır:

- `user`: profil ve hesap bilgileri
- `stats`: yorum, konu, rapor ve moderasyon sayaçları
- `activity`: son kullanıcı hareketleri
- `comments`: son yorumlar
- `topics`: son konular
- `reports`: kullanıcı hakkındaki son raporlar
- `notes`: son admin notları
- `restrictions`: ceza ve kısıtlama geçmişi
- `permissions`: modalda hangi hassas alanların ve işlemlerin gösterilebileceği
- `links`: tam kullanıcı geçmişi ve profil bağlantıları

Veriler modal ilk açıldığında tek istekte alınır. Modal yeniden açıldığında veya başarılı bir moderasyon işlemi tamamlandığında veri yeniden yüklenir; uzun süre yaşayan istemci cache'i kullanılmaz.

## Arayüz

Modal geniş masaüstü görünümüne ve mobilde tam ekrana yakın duyarlı düzene sahip olur.

### Üst Bölüm

- Avatar
- Kullanıcı adı
- Kullanıcı grubu
- Hesap durumu
- Ban veya aktif kısıtlama rozeti
- Kayıt tarihi
- Son giriş
- Yorum, konu ve rapor gibi temel sayaç kartları

### Sekmeler

1. Özet
2. Yorumlar
3. Konular
4. Raporlar
5. Admin Notları
6. Ceza Geçmişi

Özet sekmesi profil bilgileri, sayaçlar ve son kullanıcı hareketlerini gösterir. Diğer sekmeler kendi kayıtlarını ters kronolojik sırada listeler. Boş listeler açıklayıcı boş durum bileşeni gösterir.

### Alt Eylem Çubuğu

Yöneticinin izinlerine göre aşağıdaki işlemler gösterilir:

- Banla
- Banı kaldır
- Kısıtlama ekle
- Admin notu ekle
- Kullanıcı yönetiminde tam detayı aç

Ban, ban kaldırma ve kısıtlama işlemleri Yorum Yönetimi ekranındaki mevcut CSRF korumalı moderasyon formlarını ve onay modallarını yeniden kullanır. Yeni paralel bir moderasyon iş akışı oluşturulmaz.

Admin notu ekleme mevcut kullanıcı yönetimi mantığıyla aynı yetki ve audit kurallarını kullanır. Başarılı işlemin ardından modal verisi yeniden yüklenir.

## Yetkilendirme ve Güvenlik

- Modal yalnızca mevcut kullanıcı detayı görüntüleme yetkisine sahip yöneticiler için açılır.
- E-posta, IP veya benzeri hassas alanlar yalnızca API'nin izin verdiği durumda gösterilir.
- Moderasyon düğmeleri yalnızca ilgili işlem yetkisi varsa sunulur.
- API tarafı istemciden gelen kullanıcı kimliğine güvenmez; kullanıcıyı veritabanından yükler ve her istekte yetki kontrolü yapar.
- Yazma işlemleri CSRF doğrulaması, audit kaydı ve mevcut rate limit kurallarını korur.
- Kullanıcısı silinmiş veya anonim yorumlarda detay tetikleyicisi gösterilmez.
- Çıktılar bağlama uygun biçimde escape edilir; HTML içeren kullanıcı verisine izin verilmez.

## Hata Yönetimi

- İlk yüklemede modal iskeleti ve yükleniyor durumu gösterilir.
- Kullanıcı bulunamazsa açıklayıcı boş durum ve kapatma seçeneği sunulur.
- Yetki reddinde hassas veri gösterilmez ve genel bir erişim hatası sunulur.
- API veya ağ hatasında modal açık kalır; tekrar deneme düğmesi ve toast mesajı gösterilir.
- Moderasyon işlemi başarısız olursa mevcut modal durumu korunur ve sunucunun güvenli hata mesajı gösterilir.
- Başarılı moderasyon işleminden sonra kullanıcı durumu ve geçmiş verisi yeniden alınır.

## Performans

- Her geçmiş koleksiyonu SQL tarafında 20 kayıtla sınırlandırılır.
- Sayaçlar toplu veya bağımsız aggregate sorgularla alınır; kayıt başına sorgu yapılmaz.
- Yalnızca modal açıldığında istek gönderilir; yorum listesi yüklenirken bütün kullanıcı geçmişleri önceden yüklenmez.
- API yanıtı sadece arayüzün kullandığı alanları içerir.

## Testler

### PHP ve API

- Yetkili yönetici kullanıcı detayını alabilir.
- Yetkisiz kullanıcı 403 yanıtı alır.
- Bulunmayan kullanıcı 404 yanıtı alır.
- Hassas alanlar izin olmadan dönmez.
- Geçmiş koleksiyonları doğru veri şeklini kullanır.
- Her koleksiyon en fazla 20 kayıt döndürür.
- Eksik opsiyonel tablolar güvenli boş koleksiyon üretir.
- Moderasyon izinleri API yanıtına doğru yansır.

### JavaScript ve Arayüz

- Kullanıcı tetikleyicisi modalı açar ve doğru kullanıcıyı yükler.
- Modal kapatma, Escape ve backdrop davranışları mevcut admin modal standardıyla uyumludur.
- Sekmeler klavye ve fareyle çalışır.
- Boş, yükleniyor ve hata durumları doğru gösterilir.
- Modal yeniden açıldığında veri yenilenir.
- Moderasyon sonrası kullanıcı detayları yeniden yüklenir.
- Anonim veya silinmiş kullanıcı yorumlarında tetikleyici bulunmaz.

## Kapsam Dışı

- Modal içinde sınırsız geçmiş veya sayfalama
- Yeni moderasyon türleri
- Yeni kullanıcı yetki modeli
- Mevcut Kullanıcı Yönetimi detay ekranının kaldırılması
- Kullanıcı geçmişi verilerinin istemcide kalıcı cache'lenmesi
