# Alıntılı yanıt gecikmesi düzeltmesi

**Amaç:** Kaydedilmiş yanıtı ikinci liste isteğini ve tüm yorumların giriş animasyonunu beklemeden göstermek.

**Bulgular:** `submitInlineReply()` başarılı POST sonrasında `loadComments(1)` çağırıyor. Liste baştan çiziliyor ve her yoruma 0,4 saniyelik `animate-in` animasyonu ekleniyor. POST cevabında yeni yorum zaten mevcut.

**Tasarım:** Sunucunun doğruladığı yorumu mevcut yorum ağacına ve ilgili yanıt kapsayıcısına ekle. Alıntı ve kullanıcı grubu bilgilerini POST cevabında tamamla. Sayfalama, diğer yorumların DOM düğümleri, açık metinler ve daraltılmış dallar korunsun; yeni yanıtın bulunduğu dal açılsın. Eski poll cevapları yeni yanıtı silemesin. Onay bekleyen veya reddedilen yorumlar eklenmesin. Eksik cevap/ebeveyn durumunda mevcut liste yenilemesi yedek yol olarak kalsın.

İyimser biçimde kayıttan önce yorum göstermek, moderasyon ve hata durumları nedeniyle seçilmedi. Tüm listeyi yalnızca POST cevabıyla yeniden çizmek de mevcut sayfa ve form durumlarını gereksiz yere değiştirecekti.

## Uygulama

- [x] Gerçek JavaScript ile yalıtılmış tarayıcı sayfasında ikinci GET ve animasyon beklemesini yeniden üret.
- [x] Yalnızca ilgili yanıtı ekle; alıntı, grup, sayaç, sayfalama ve poll yarışını ele al.
- [x] İç içe yanıt, mobil daraltma, ikinci sayfa, çift gönderim, onay, hata ve eksik cevap senaryolarını doğrula.
- [x] Mevcut yorum regresyonlarını, PHP/JavaScript sözdizimini ve diff biçimini denetle.

Doğrulamalar taklit API cevapları ve yalıtılmış veritabanı kullanır; gerçek kullanıcılara test yanıtı/bildirimi gönderilmez.

Sonuçlar: `docs/verification/2026-11-01-comment-reply-latency.md`.

## Takip denetimi

- [x] Poll cevabına yeni üst yorum eklendiğinde açık alıntı taslağının silinmesini tarayıcıda yeniden üret ve düzelt. Form açıkken poll cevabı model/sayaç/DOM'a uygulanmasın; form kapandıktan sonraki poll güncellemeyi alsın.
- [x] Ana formdan gönderilen yorumun açık alıntının `parent_id` değerini yanlışlıkla almasını yeniden üret ve düzelt. Ebeveyn kimliğini yalnız ilgili alıntı formunun POST'u taşısın.
- [x] Sıralama ile kaldırılan formun açık işaretini ve düzenlemeden yanıta geçişte eski düzenleme görünümünü temizle.
- [x] Genişletilmiş 16 tarayıcı senaryosu, 16 JavaScript testi, 11 yalıtılmış cevap kontrolü ve 100 güvenlik kontrolünü çalıştır.
