# Alıntılı yanıt gecikmesi — 1 Kasım 2026

## Bulgu ve düzeltme

Başarılı yanıt POST'undan sonra `loadComments(1)` ile ikinci istek yapılıyor, tüm yorumlar yeniden çiziliyor ve 0,4 saniyelik `animate-in` animasyonu başlatılıyordu. POST cevabındaki yeni yorum kullanılmıyordu.

Onaylanmış yanıt artık mevcut yorum ağacına ve kendi ebeveyninin yanıt kapsayıcısına doğrudan ekleniyor. Yeni yanıt giriş animasyonunu beklemiyor. POST cevabı alıntı yazarını, Unicode metinden 80 karakterlik önizlemeyi ve kullanıcı grubunu içeriyor. Mevcut sayfalar, diğer yorum düğümleri ve taslaklar korunuyor. Onay bekleyen/hatalı yorumlar eklenmiyor; eksik cevapta eski liste yükleme yolu kullanılıyor.

## Doğrulama

- Gerçek tema şablonu, CSS ve `topic-comments.js`, Chrome'da taklit API cevaplarıyla çalıştırıldı. Önce: ilk liste isteği + POST + ikinci liste isteği; ikinci GET çözülmeden yanıt görünmüyordu. Sonra: ilk liste isteği + POST; ikinci GET yok ve yanıt görünür.
- 12 tarayıcı senaryosu geçti: doğrudan ekleme; alıntı/grup/Markdown/HTML kaçışları; iç içe yanıt ve derinlik; yerleşik şablon; ikinci sayfadan yanıtlama ve sıradaki sayfa; 390 px mobilde yalnız ilgili dalı açma; onay; doğrulama/ağ hatası ve çift gönderim; eksik POST verisi; eski poll cevabı; POST'tan önce yanıtı gören poll; başka açık taslak; dört ek sıralama seçimi.
- Deneme ölçümünde sunucu cevabının çözülmesi ile DOM eklemesi arası yaklaşık 2,4 ms. Bu, taklit API kullanılan yerel arayüz ölçümüdür; gerçek sunucu/ağ süresini ölçmez.
- Gerçek POST sorgusu ve `formatComment()` yalıtılmış SQLite veritabanında çalıştırıldı: 11 kontrol geçti. İç içe ve ziyaretçi alıntısı, Unicode önizleme, grup/avatar, düzenleme/silme yetkileri, profil ve ilk reaksiyon durumu denetlendi.
- `node --test tests/production-log-regression.test.mjs`: 16 test geçti.
- `php tests/security-flow-regression.php`: 100 kontrol geçti.
- PHP/JavaScript sözdizimi ve ilgili diff biçimi denetimi geçti.

Geçici test araçları doğrulama sırasında Git tarafından yok sayılan `output/playwright/comment-reply-*` dosyalarında çalıştırıldı; son dosya temizliğinde kaldırıldı. Gerçek yorum veya test bildirimi oluşturulmadı. Konu sayfasının güncel `assets/js/topic-comments.js?v=...` dosyasını yüklediği doğrulandı; bu dosya ayrı sunulduğundan paket derlemesi gerekmiyor.

## Takip denetimi

İki ek hata tarayıcıda yeniden üretildi: Poll cevabında farklı bir üst yorum gelmesi açık alıntı taslağını siliyordu. Ayrıca paylaşılan `replyTo` durumu nedeniyle ana formdan gönderilen yorum, açık alıntının ebeveyn kimliğini alabiliyordu. Düzeltme öncesi ölçüm: `draftPreserved=false`, `mainCommentParentId=1`; düzeltme sonrası aynı akışta `draftPreserved=true`, `mainCommentParentId=null`.

Form açıkken poll cevapları artık yorum ağacını, sayacı veya DOM'u değiştirmiyor. Form kapandıktan sonraki poll normal güncellemeyi uyguluyor. Ana formun ortak ebeveyn durumu kaldırıldı; alıntı POST'u kendi ebeveyn kimliğini koruyor. Sıralama sonrası tam çizim, artık bulunmayan formun açık işaretini sıfırlıyor. Düzenleme formundan yanıta geçiş de eski yorumdaki düzenleme görünümünü temizliyor.

Genişletilmiş **16 tarayıcı senaryosu** geçti. **16 JavaScript testi**, **11 yalıtılmış cevap kontrolü** ve **100 güvenlik kontrolü** tekrar geçti. JavaScript sözdizimi ve ilgili diff denetimi başarılı. Gerçek yorum veya bildirim oluşturulmadı.

## Geçici dosya temizliği

Kullanıcının isteğiyle 33 geçici dosya (320.716 bayt) ve boş `output/playwright/admin` klasörü kaldırıldı: 8 eski kaynak kopyası/test betiği, 21 tarayıcı sayfa dökümü ve 4 tarayıcı konsol günlüğü. Temizlenen alanlardaki 17 ekran görüntüsü korundu. Uygulama kaynakları, kalıcı testler, bağımlılıklar, yüklenen dosyalar ve görsel önbelleği temizliğe alınmadı.
