# Şikayetler ve Raporlar düzenlemesi

**Amaç:** Rapor yönetimini daha kompakt göstermek ve iki rapor türünde de seçilen kayıtları topluca silebilmek.

**Tasarım:** Mevcut panel renklerini ve yazı tipini koru. Başlık, sekmeler ve durum özetlerini küçült. Arama, durum, neden ve tarih filtrelerini tek duyarlı ızgarada hizala. Toplu işlem alanını sürekli görünür tut; seçim yokken uygulama düğmesini devre dışı bırak. Küçük ekranda seçim kutularını koru. Açıklama ve yönetici notunu geniş ekranda yan yana göster.

**Davranış:** Toplu işlem menüsü durum güncellemelerini ve “Seçilenleri sil” işlemini içerir. Yalnızca geçerli, benzersiz, pozitif kimlikler ve açıkça belirtilen rapor türü işleme alınır. Silme sırasında yönetici notu kullanılmaz. Silme ve bağlı işlem geçmişi aynı veritabanı işlemi içinde tamamlanır; hata tüm seçili silmeleri geri alır. CSRF, yetki denetimi ve filtreye dönüş korunur.

## Uygulama

- [x] Rapor servislerine doğrulanmış, atomik seçili silme ekle; yalıtılmış veritabanında sınır ve geri alma testlerini çalıştır.
- [x] Sayfanın POST işleyicisine ve toplu işlem menüsüne silmeyi bağla; seçim ve işlem durumlarını JavaScript ile eşleştir.
- [x] Başlık, sekmeler, özet, filtre, toplu işlem ve rapor kartlarını sıkılaştır; mobil ve koyu tema desteğini koru.
- [x] PHP/JavaScript kontrolleri, derleme ve tarayıcıda masaüstü/mobil doğrulamasını tamamla. Gerçek raporları silme; medya dosyalarını değiştirme.

## Doğrulama ve bulunan ek sorun

- Yalıtılmış SQLite testinde seçili silme, kapsam ayrımı, kimlik doğrulama, tekil silme, hata halinde geri alma, iç içe işlemler ve şema onarımı: 57 kontrol geçti.
- Seçim sayacı, tümünü seçme, kısmi seçim, boş seçim engeli, silme/durum geçişi, notun korunması, tekrar gönderim engeli ve tarayıcı geri dönüşü: 4 JavaScript testi geçti.
- Masaüstü ve 390 piksel mobil görünüm; açık/koyu tema, rapor detay penceresi ve işlem geçmişi, üç sekme ve arama/durum/neden/tarih birleşik filtresi tarayıcıda doğrulandı.
- Kullanıcı Şikayetleri sekmesi yerelde eksik `user_reports` tablosu nedeniyle hata veriyordu. `2026_11_01_0001_restore_user_report_tables` migrationı mevcut kurulum servisini kullanarak eksik tabloları oluşturur. Yerelde uygulandı; mevcut konu raporlarının içerik özeti ve kullanıcı raporu geçmişi değişmedi.
- Gerçek raporlarda silme veya durum güncellemesi yapılmadı. Medya dosyaları değiştirilmedi. Canlı ortama aktarım yapılmadı; canlı ortamda yeni migration veritabanı senkronizasyonuyla uygulanmalıdır.

## Takip denetimi

Durum değişmemesine rağmen geçmiş kaydı oluşması ve geçmiş yazma hatasında rapor durumunun kısmen güncellenmesi yalıtılmış veritabanında yeniden üretildi. Tekil ve toplu durum işlemleri ortak `ReportStatusService` üzerinden aynı transaction içinde güncellenir; boş toplu not mevcut notları korur, tekil düzenleme formu notu açıkça silebilir. Sayaç hatalarını sıfır kayıt gibi gösteren hata yutma kaldırılır. Tekil POST kimlikleri de toplu işlemdeki pozitif tamsayı doğrulamasını kullanır. Doğrulama: değişikliksiz işlem, not koruma/temizleme, hata geri alma, kayıt kapsamı, iç içe transaction ve mevcut silme testleri.

Sonuç: durum akışı 73, seçili silme/şema onarımı 57, bildirim/e-posta 391 PHP kontrolü ve 4 JavaScript testi geçti. PHP sözdizimi, derleme ve diff biçim denetimi başarılı. Değişikliksiz kaydetme hata olarak gösterilmez; bulunamayan kayıt ayrıca belirtilir. Eksik bildirim bağımlılığı artık sessizce atlanmaz. Gerçek raporlarda güncelleme/silme veya gerçek bildirim gönderimi yapılmadı.
