# TürkMod proje incelemesi ve düzeltme planı

## Amaç ve sınırlar

PHP uygulamasını, API uçlarını, yönetim panelini, tema derlemesini ve SEO çıktısını incele; doğrulanan sorunları düzelt. Başarısız bağımlılıklar için alternatif backend veya uydurma veri sunma. Medya dosyalarını değiştirme veya silme. Üretim ve yerel ortam sonuçlarını ayrı kaydet.

## Uygulama yaklaşımı

Mevcut mimari içinde hedefli düzeltmeler yapılır. Yapılandırılmış backend hataları açıkça yükseltilir; bulunamayan içerik 404, sistem arızası 5xx üretir. Kalıcı canonical adresleri ve site haritaları gerçek yayın durumuyla tutarlı olmalıdır. Geçersiz eski bağlantılar için yeni uyumluluk katmanı eklenmez.

## İş adımları

- [x] Git durumunu, proje kurallarını, bağımlılıkları ve mevcut testleri incele.
- [x] Başlangıç PHP sözdizimi, Composer doğrulaması, mevcut PHP/JavaScript regresyonları ve derlemeyi çalıştır.
- [x] Search Console: dizin nedenleri, örnek URL'ler, site haritaları, HTTPS ve yapılandırılmış veriyi incele; canlı yanıtlarla karşılaştır.
- [x] Rotalar, hatalı girdiler, erişim kontrolleri, önbellek ve hata yönetimindeki doğrulanmış hataları düzelt.
- [x] Alternatif backend, demo içerik ve eski ayar okuma yollarını kaldır; gereken bağımlılıkları doğrudan kullan.
- [x] Canonical, robots, sayfalama, HTTP durumları ve site haritalarını doğrula ve gereken düzeltmeleri yap.
- [x] Etkilenen davranışlar için regresyon kontrolleri ekle; mevcut testleri ve tema derlemesini çalıştır.
- [x] Dosya envanteri ve referansları incele; yalnızca doğrulanmış artık dosyaları kaldır. Medya envanterinin aynı kaldığını doğrula.
- [x] Bulguları, değişiklikleri, test sonuçlarını ve canlıya uygulanması gereken işleri doğrulama raporuna yaz.

## Doğrulama odağı

1. Veritabanı veya seçilmiş önbellek backend'i arızalandığında başarılı/boş içerik olarak gizlenmemesi.
2. Olmayan veya sınır dışı sayfaların indekslenebilir 200 üretmemesi.
3. Alt dizin, HTTPS proxy ve slash kurallarının yönlendirme döngüsü üretmemesi.
4. Site haritalarının yalnızca erişilebilir, indekslenebilir canonical URL'ler içermesi.
5. HTTP üzerinden geliştirme dosyaları ve dahili PHP kaynaklarına erişimin engellenmesi; mevcut herkese açık CSS/JS dosyalarının çalışması.

## Başlangıç sonuçları

- 448 PHP dosyası sözdizimi kontrolünden geçti; Composer yapılandırması geçerli.
- Mevcut PHP testleri: 43 + 18 + 60 + 391 kontrol geçti.
- Mevcut JavaScript testleri: 20 test geçti.
- `npm run build` başarılı.
- Search Console son veri tarihi: 23 Ekim 2026. 7.569 indekslenmiş, 59.209 indekslenmemiş URL. 404: 20.341; soft 404: 38; yönlendirme hatası: 15.

## Tamamlanan doğrulama

- İlk tur: 450 PHP lint; 619 PHP regresyon kontrolü/senaryosu; 24 JavaScript testi; derleme ve bağımlılık incelemeleri başarılı.
- 36 yerel HTTP kontrolünde PHP hata çıktısı/5xx yok. Sekiz yerel alt sitemap XML olarak geçerli; 15 örnek hedef 200 ve indekslenebilir.
- 7.893 medya dosyasının boyutu ve SHA-256 özeti aynı; kayıp veya değişmiş dosya yok.
- 11 kalıntı dosyası, kaldırılan Seeder kaynak dosyası ve üç boş dizin temizlendi.
- Ayrıntılı sonuçlar `docs/verification/2026-11-01-project-audit.md` raporunda. Canlıya dağıtım ve Search Console yeniden doğrulaması yapılmadı.
- İkinci tur planı `2026-11-01-project-audit-followup.md` içinde tamamlandı. Güncel toplam: 452 PHP lint, 720 PHP kontrolü/senaryosu ve 24 JavaScript testi; ek 28 HTTP kontrolü.
