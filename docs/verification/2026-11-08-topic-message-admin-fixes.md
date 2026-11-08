# 8 Kasım 2026 — konu, mesajlaşma ve admin düzeltmeleri

İstenen beş akış düzeltildi. Veritabanı şeması değişmedi. Tema JavaScript üretim paketi yeniden derlendi.

## Sonuçlar

| Kontrol | Sonuç |
| --- | --- |
| `npm run build` | JS/CSS build başarılı |
| `node --test tests/*.test.mjs` | 32 test geçti |
| `php tests/topic-message-admin-regression.php` | 73 kontrol geçti |
| `php tests/notification-email-regression.php` | 391 kontrol, loopback SMTP ile 57 ileti geçti |
| `php tests/security-flow-regression.php` | 100 kontrol geçti |
| `php tests/project-audit-regression.php` | 83 kontrol geçti |
| `php scripts/php-lint.php` | Çalışma alanındaki 468 PHP dosyası geçti |
| Takip incelemesindeki PHP sözdizimi kontrolü | Değişen/eklenen 15 PHP dosyası yeniden geçti |
| `git diff --check` | Temiz |
| Playwright gerçek JavaScript/form kontrolleri | 48 kontrol geçti (31 temel + 17 takip); JavaScript hatası yok |

PHP akış testi şemayı ayrı, rastgele adlı geçici bir MySQL veritabanına kopyalar ve sonunda yalnız bu veritabanını kaldırır. WebSocket yayınları bellekte yakalanır. E-posta testi bağlantıya özel geçici tablolar ve loopback SMTP kullanır. Gerçek kullanıcılara test mesajı veya e-posta gönderilmedi.

## Doğrulanan davranışlar

- Olmayan konu ve genel sayfa HTTP 404/noindex ile görünür boş durum gösteriyor. Konu sayfasının yanlışlıkla normal konu şablonuna veya yan sütunlu düzene girmesi engellendi. Ana sayfa ve kategori bağlantıları mevcut. 1440 px masaüstü ve 390 px mobil açık/koyu temada görünüm kontrol edildi; yatay taşma yok.
- Revizyon popupında site içi bildirim, özel mesaj ve e-posta bağımsız seçiliyor. Başlangıçta yalnız site içi bildirim seçili. Boş not gönderilemiyor; kanalsız kayıt mümkün. Sekiz kanal kombinasyonu, yinelenen revizyon, kapalı e-posta kanalı, alıcı tercihi, geçersiz e-posta ve kendine özel mesaj hataları doğrulandı. Not ve düzenleme bağlantısı iletiliyor; e-posta teslimatı mevcut kuyruğa bırakılıyor. Başarısız kanal admin uyarısında belirtiliyor. Özel mesaj seçimi ayrıca seçilmemiş çan/e-posta kanallarını tetiklemiyor.
- Görünen sohbette çan olayı ve özel mesaj toastları bastırılıyor. Sohbet okunduğunda ilgili çan kayıtları da okunuyor; aynı sohbete ait kayıtlar mesaj ekranındaki çan listesinden çıkarılıyor. Başka sohbetler ve site bildirimleri korunuyor. Başkasının sohbetiyle sayaç filtrelemek mümkün değil; henüz alınmayan eşzamanlı mesaj erken okunmuyor.
- Mesaj API'si ilk 300 yerine son 300 mesajı kronolojik sırada getiriyor; önceki mesajlar geçmişte erişilebilir. 350 mesajlık sohbet ve sonradan gelen mesaj doğrulandı. WebSocket olmadan 2,5 saniyelik HTTP yedeği, gelen kutusu yenilemesi, geç yüklenen script başlangıcı, bağlantının geri gelmesi ve 10 saniyelik takılan isteğin ardından toparlanma tarayıcıda kontrol edildi. GET istekleri önbelleksiz ve oturum kilidi serbest bırakılarak çalışıyor.
- Eski iki otomatik taslak yolu kaldırıldı. Yeni formda Quill, açıklama, indirme linkleri, diğer alanlar ve dosya seçimleri temiz başlıyor. Mevcut formun adımları arasında yeni açıklama/link korunuyor. CSRF ve gönderim tokenleri korunuyor. Hem tema hem bağımsız form JavaScript'i gerçek Quill ile kontrol edildi.
- Admin uyarısı boş mesajla popup açmıyor. Başarılı işlemler ek Tamam popupı yerine toast kullanıyor. Ortak popup giriş alanını yalnız prompt için açıyor; hata metinleri ve gerekli metin girişleri çalışıyor. Ban/kısıtlama, yorum, kategori, sıralama ve bildirim yönetiminin ortak alert kullanımları tarandı.

## İkinci inceleme

Kullanıcının ek hata denetimi isteğinde aşağıdaki eksikler giderildi:

- Geciken gönderim cevabının, kullanıcı bu sırada bir sonraki mesajı yazmışsa yeni taslağı silmesi tarayıcıda yeniden üretildi. Gönderim tamamlandığında yalnız gönderilmiş metin temizleniyor; bekleyen ikinci gönderim engelleniyor.
- Mesaj ve gelen kutusu yenileme cevapları yerel değişiklik sürümüyle denetleniyor. Eski cevap silinen mesajı/sohbeti geri getirmiyor. Yetkili güncel sohbet listesinde bulunmayan satırlar kaldırılıyor.
- Geçmişten yüklenen, son 300 mesajın dışındaki bir mesajın düzenleme/silme sonucu doğrudan ilgili modele uygulanıyor. API normalleştirilmiş mesaj yamasını döndürüyor. Aynı işlem WebSocket olaylarında da eski mesajlara uygulanıyor. Düzenlemede 4000 karakter sınırı ve boş metin kontrolü sunucuda korunuyor.
- Geçmiş isteğine zaman aşımı ve hata yakalama eklendi. Hata mevcut konuşmayı silmiyor, yakalanmamış Promise hatası oluşturmuyor ve aynı geçmiş tekrar istenebiliyor. Bulunamayan aktif sohbet mesaj listesine yönlendiriliyor.
- Tarayıcı geri tuşunda BFCache kullanılmadığında form alanlarının yeniden doldurulması da yeniden üretildi. Her iki yükleme yolu, bu gezinmeyi tanıyıp alanları sıfırlayarak güncel sayfayı yükler; önceki başlık/açıklama/link geri dönmez.
- Yeni admin promptu önceki bekleyen promptu iptal ediyor; olay dinleyicileri ve giriş kısıtları sonraki pencereye taşınmıyor. Önceki kapanış zamanlayıcısı yeniden açılan modalı kapatmıyor. İçteki uyarı kapatıldığında açık ana modalın kaydırma kilidi korunuyor.

`tests/fixtures/workflow-followup-ui.js` bu durumları 17 gerçek tarayıcı kontrolüyle doğrular. `tests/topic-message-admin-regression.php` düzenleme/silme doğrulamalarıyla 73 kontrole çıkarıldı. Temel 31 tarayıcı senaryosu, 32 JavaScript testi, 100 güvenlik kontrolü ve 83 proje denetimi tekrar geçti. JS/CSS üretim build'i yeniden alındı.

## Tarayıcı kontrollerini tekrarlama

Yerel Apache/MySQL çalışırken:

```powershell
php tests/fixtures/workflow-ui-fixtures.php
npx --no-install @playwright/cli -s=turkmod-fixes open http://localhost/turkmod/ --headed
npx --no-install @playwright/cli -s=turkmod-fixes run-code --filename=tests/fixtures/workflow-ui-checks.js
npx --no-install @playwright/cli -s=turkmod-fixes run-code --filename=tests/fixtures/workflow-followup-ui.js
```

Sahte form ve mesaj API cevapları tarayıcıya özel route/JavaScript üzerinden sağlanır. 404 kontrolleri gerçek yerel PHP yönlendirmesine gider.

Görseller ve çalışma çıktısı:

- `output/playwright/missing-topic-desktop.png`
- `output/playwright/missing-topic-mobile-light.png`
- `output/playwright/missing-topic-mobile-dark.png`
- `output/playwright/revision-modal.png`
- `output/playwright/workflow-ui-results.log`
- `output/playwright/workflow-followup-after.log`
