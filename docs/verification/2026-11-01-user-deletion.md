# Kalıcı kullanıcı silme doğrulaması — 1 Kasım 2026

## Sonuç

- `php tests/user-deletion-regression.php`: **91 kontrol, 0 hata**.
- `php tests/user-deletion-mysql-regression.php`: **26 kontrol, 0 hata**.
- Değişen PHP kaynakları `php -l`, kullanıcı yönetimi JavaScript'i `node --check` kontrolünden geçti.
- İlgili değişiklikler `git diff --check` kontrolünden geçti.

## Veri kapsamı

İçerik devri ve kalıcı silme; yorum ekleri ve düzenleme geçmişinin korunması; diğer kullanıcıların yanıtları; konu, yorum ve reaksiyon sayaçları; FK olmayan grup/itiraz referansları; bekleyen e-postalar; özel mesaj işaretçileri; bot içe aktarma durumu; izin, onay ve sistem hesabı kontrolleri doğrulandı. Hatalar, günlük yazma hatası, storage tarafından yok sayılan silme ve iç içe transaction geri alma kontrol edildi.

Dosya testleri yalnızca testin oluşturduğu dosyaları kullandı. Paylaşılan görseller, küçük görseller, eski Windows yolları, URL önekleri ve uploads dışındaki dosyalar korundu. Dosya temizliği açık transaction sırasında reddedildi.

MySQL testi mevcut veritabanının yalnızca tablo yapısını rastgele adlandırılmış, geçici bir test veritabanına kopyaladı. Test kullanıcıları bu veritabanında oluşturuldu. Geçici veritabanı sonunda kaldırıldı; kaynak kullanıcı sayısının değişmediği denetlendi. Gerçek hesap silinmedi.

## Takip incelemesindeki düzeltmeler

- Eski toplu silme yolunun izin ve içerik seçimi akışını atlaması engellendi. Yalnızca düzenleme yetkisi olan hesabın iki silme yolundan da kullanıcı silemediği MySQL üzerinde doğrulandı.
- Sistem hesabının doğrudan düzenleme ve kullanıcı ayrıntısı sorgularından döndürülmesi engellendi.
- Hesap silinince kaldırılan etiketlemeler için kalan yorumların `mention_count` sayacı yenileniyor; koruma ve içerikleri silme modları doğrulandı.
- Konu, yorum, sabit sayfa ve revizyon içeriklerindeki ortak görseller dosya temizliğinden korunuyor. Sabit sayfanın OG görseli ve sorgu/fragment içeren göreli yollar da denetlendi.
- Sistem hesabı oluşturulurken benzersiz e-posta üzerinden mevcut kaydın güvenli biçimde yeniden kullanılması sağlandı. Tekrarlı kullanım MySQL üzerinde doğrulandı; eşzamanlı yük testi yapılmadı.

## Tarayıcı

Gerçek `user-delete-modal.php`, `users-tab.js`, `admin-ui.js` ve CSS kaynakları izole bir sayfada, taklit API yanıtlarıyla Chrome'da çalıştırıldı. Gerçek admin oturumunda silme yapılmadı.

- Koruma varsayılan; içerik silme izni yoksa diğer seçenek devre dışı.
- Yanlış kullanıcı adı istek göndermiyor.
- Çift gönderim tek istek oluşturuyor; ortak AJAX yardımcısı `Siliniyor...` durumunu gösteriyor.
- Modal yeniden açılınca güncel CSRF korunuyor.
- Hata sonrası form açık ve dolu kalıyor; başarı sonrası bildirim ve yenileme çalışıyor.
- 1280×720 ve 390×844 görünüm incelendi. Mobil purge durumunda footer alt sınırı 827 px, viewport 844 px; gövde kaydırılıyor, yatay taşma yok.

Görsel kayıtlar yerel, Git tarafından yok sayılan `output/playwright/user-delete-desktop.png`, `user-delete-mobile.png` ve `user-delete-mobile-purge.png` dosyalarında.

## Başarı bildirimi ve kompakt pencere düzeltmesi

Silme sonrası başarı popupı kaldırıldı. AJAX başarısı sonucu sunucu flash kaydına yazar; liste yenilendiğinde mevcut sayfa içi bildirim sonucu bir kez gösterir. Sıfır konu/yorum sayıları mesajdan çıkarıldı. Dosya temizleme sorunu uyarı olarak gösterilir; normal POST yanıtı da aynı tonu kullanır.

Gerçek `delete_user` action gövdesi, modal, CSS ve JavaScript kaynakları izole bir PHP fixture'ında Chrome ile çalıştırıldı. Kullanıcı silme servisi taklit edildi; veritabanına bağlanılmadı. 54 arayüz kontrolü geçti: boş içerik, yalnız konu, yalnız yorum, dosya temizleme uyarısı, ikinci popupın açılmaması, tek istek, bildirimin tek kullanımı, yanlış ad doğrulaması ve hata sonrası formun korunması.

480 px genişliğindeki pencere 1280×720 masaüstünde yaklaşık 515 px yüksekliğinde. 390×844, 320×568, koyu tema ve içerik silme yetkisi olmayan kullanıcı görünümü incelendi. 96 px admin üst çubuğu payıyla düğmeler ekran içinde kaldı; küçük ekranda gövde kaydırıldı, yatay taşma görülmedi. Kullanıcı adının harf biçimi korundu.

`admin/users.php` ve modal PHP sözdizimi, `users-tab.js` JavaScript sözdizimi ve ilgili diff kontrolleri geçti. Yerel görseller `output/playwright/user-delete-compact-{desktop,mobile-purge,mobile-small,dark,restricted}.png` dosyalarında. Geçici fixture ve tarayıcı kontrol betiği doğrulama sonrası kaldırıldı.

## Ortak footer ile son inceleme

Ortak `admin/footer.php`, sayfada zaten gösterilen `$successMsg`, `$warningMsg` ve `$errorMsg` değişkenlerini ayrıca toast verisine kopyalıyordu. Sayfa içi bildirimler gösterildikten sonra bu değişkenler tüketilerek tekrar önlendi; bağımsız bilgi toastları çalışmaya devam ediyor.

Gerçek sayfanın flash okuma ve bildirim şablonu ile gerçek footer izole PHP çıktısında birlikte çalıştırıldı. Düzeltmeden önce başarı, uyarı ve hata için üç tekrar doğrulandı; düzeltmeden sonra üç ton, bilgi toastının korunması ve sonraki istekte tüketim için **9 kontrol, 0 hata** elde edildi. Geçici doğrulama betiği kaldırıldı. SQLite 91 ve geçici MySQL 26 veri kontrolü yeniden geçti; gerçek hesaplara dokunulmadı. PHP/JS sözdizimi ve ilgili diff kontrolleri de geçti.
