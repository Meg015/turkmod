# TürkMod inceleme ve doğrulama raporu

Tarih: 1 Kasım 2026. Çalışma dizini: `C:\xampp\htdocs\turkmod`.

## Sonuç

Doğrulanan uygulama, güvenlik, önbellek, yönlendirme ve SEO hataları yerel projede düzeltildi. İkinci inceleme dahil 452 PHP dosyası sözdizimi kontrolünden geçti. 720 PHP regresyon kontrolü/senaryosu ve 24 JavaScript testi başarılı; derleme başarılı. İlk HTTP taramasında 36, ikinci taramanın son sonuçlarında 28 istek doğrulandı; PHP hata çıktısı veya 5xx yanıt kalmadı.

**Değişiklikler turkmod.net sunucusuna dağıtılmadı; commit veya push yapılmadı.** Search Console'daki geçmiş sorunlar bu yerel düzeltmeler nedeniyle çözülmüş kabul edilmedi.

## Uygulanan düzeltmeler

| Alan | Bulgu ve düzeltme |
| --- | --- |
| İstek girdileri | Dizi biçimindeki sorgular PHP tür hatası üretebiliyordu. Uygulamanın kullandığı skaler sorgu biçimi ve sayfa numarası başlangıçta doğrulanıyor; geçersiz istek 400, no-store ve noindex alıyor. |
| Rotalar | Query parametrelerinin URL'deki konu/kategori/profil hedefini değiştirmesi engellendi. Fazla konu/profil segmentleri 404 oluyor. Dizin geçişi, ters eğik çizgi ve kontrol karakterleri reddediliyor. |
| Dahili dosyalar | Geliştirme, bağımlılık ve dahili kaynak yollarının HTTP erişimi Apache ve PHP geliştirme yönlendiricisinde kısıtlandı. CSS, JavaScript ve medya yolları korunuyor. |
| Yayın erişimi | Taslak veya silinmiş konuya doğrudan ID üzerinden erişim kapatıldı. Veritabanı hataları bulunamayan/boş içerik diye gösterilmiyor. |
| Sayfalama | Ana sayfa, kategori ve herkese açık profilde toplamı aşan sayfa numarası gerçek 404 üretiyor. Canonical sorgusundaki çift page parametresi ve başka sayfaya sızan sayfa numarası düzeltildi. |
| Canonical ve bağlantılar | Üretilen konu/kategori/statik bağlantıları ve canonical adresleri slash yönlendirmesinin kuralını kullanıyor. Alt dizin kökü ve nokta içeren konu slug'ları kontrol edildi. Konu kartları ve konu bilgi paneli üst kategoriyi içeren doğru bağlantıyı üretiyor. |
| 404 sayfası | Yanlış SEO sayfa anahtarı düzeltildi. Yanıta noindex/nofollow ve no-store eklendi; HTML robots etiketi de aynı sonucu veriyor. |
| Site haritaları | Boş alt haritalar indekse eklenmiyor. Taslaklar dışarıda tutuluyor. Giriş gerektiren hesap/mesaj/ban itirazı ve işlem sayfaları haritadan çıkarılıyor; SEO ayarı bunları indekslenebilir yapamıyor. Robots ile engellenen aynı siteye ait upload görselleri görsel haritasına eklenmiyor. |
| XML ve yapılandırılmış veri | Hatalı sitemap XML'i açık hata üretiyor. JSON-LD çıktıları script etiketi dışına çıkışı engelleyen JSON bayraklarıyla kodlanıyor. Önbellek imzasında farklı kodlamaya otomatik geçiş kaldırıldı. |
| HTML güvenliği | HTMLPurifier bulunmadığında kullanılan eksik regex/raw HTML yolu kaldırıldı. Editör ve gösterim mevcut DOM izin listesinde birleşti; olay öznitelikleri ve zararlı URL'ler temizleniyor. |
| Önbellek | Geçersiz backend reddediliyor. Redis/veritabanı arızasında dosya önbelleğine geçilmiyor. Redis SCAN, prefix sınırı, etiket temizliği, anahtar çakışmaları, null/false değerleri ve bağlantı/komut hataları düzeltildi. |
| Tema ve varlıklar | Eksik/geçersiz temanın başka kurulu temayla değiştirilmesi kaldırıldı. Eksik manifest, asset veya derleme çıktısı sessizce atlanmıyor. Tema hatasında public yanıt 503/noindex; yönetim panelinde geçerli tema seçilebiliyor. |
| Hata ve sağlık yanıtları | Yakalanmamış hatalarda 500, no-store ve noindex üretiliyor. Sağlık kontrolü seçili önbelleğe gerçekten yazıp okuyor ve kuyruk tablosunu denetliyor; arızada 503 veriyor. |
| JavaScript | WebSocket kopunca HTTP polling'e geçiş ve BroadcastChannel yerine storage event taşıması kaldırıldı. Bekleyen bağlantı sırasında paralel socket açılması ve tarayıcı geri dönüşünde bağlantının kapalı kalması düzeltildi. |
| Görünüm ve içerik toplama | Eksik ana sayfa H1 başlığı eklendi. Görsel hatasında alternatif resme geçiş, Quill hatasında düz metne dönüş ve scraper seçicisi başarısız olduğunda sayfadaki rastgele indirme bağlantılarını toplama kaldırıldı. Otomatik demo kategori Seeder modülü kaldırıldı. |
| Statik dosya sürümü | Her istekte değişen zaman damgası kaldırıldı; dosya sürümü mevcut filemtime politikasıyla kararlı kalıyor. |

Sayfalama canonical adresinin kendi sayfasını göstermesi Google'ın [sayfalama yönergesi](https://developers.google.com/search/docs/specialty/ecommerce/pagination-and-incremental-page-loading) ile uyumlu. Noindex HTTP başlığı ve HTML meta etiketi desteklenen yöntemlerdir; robots engeli bunların yerine kullanılmaz. [Google noindex belgesi](https://developers.google.com/search/docs/crawling-indexing/block-indexing).

## İkinci incelemede düzeltilen ek hatalar

| Alan | Bulgu ve düzeltme |
| --- | --- |
| Yorum erişimi | Olmayan konu için yorum API'si 200 veriyordu. Yorum listesi ve geçmişi, ekleme, düzenleme, silme, yanıt, reaksiyon ve şikâyet işlemleri yayımlanmış/silinmemiş konuya bağlı. Reaksiyon, yanıt ve şikâyette yorumun da onaylanmış olması gerekiyor. Bekleyen kendi yorumunu düzenleme yetkisi korunuyor. |
| İstek gövdesi | Bozuk JSON'un form verisine dönüşmesi kaldırıldı. Nesne dışındaki JSON, dizi/nesne alanları, bozuk UTF-8, yanlış kimlik türleri ve bilinmeyen eylemler 400 alıyor. |
| Yorum düzenleme | Eklemedeki uzunluk sınırlarının düzenlemede atlanması düzeltildi. Düzenlemede kullanıcı kısıtı ve spam filtresi uygulanıyor; spam düzenlemesi 422 ile reddediliyor. |
| Şikâyetle gizleme | Çözülmüş şikâyetler gizleme eşiğine sayılmıyor. Aynı yorum için şikâyetler InnoDB satır kilidiyle sıralanıyor. Kayıt ve gizleme aynı transaction içinde; görünür yorum sayacı bir kez azaltılıyor, indirme hakkı/etkinlik puanı geri alma çağrılıyor. Önbellek commit sonrasında temizleniyor. |
| CSRF | Doğrulayıcı yüklenmediğinde handler'a devam etme kaldırıldı. Ortak yardımcılar tek CSRF uygulamasını doğrudan yüklüyor. Multipart alanlar Request nesnesine taşındı; geçerli upload formunun token'ı artık kaybolmuyor. Dizi token 5xx üretmiyor; URL query token'ı doğrulama kaynağı olarak kullanılmıyor. |
| Apache durum kodu | Gerçek Apache isteğinde CSRF reddinin gövdesi doğruyken HTTP durumu 500 oluyordu. Ortak Response sınıfı 419 için açık durum satırı gönderiyor; aynı istek artık 419. |
| Hız sınırı | Kontrol veya kayıt fonksiyonları eksikken devam etme kaldırıldı. Alt dizindeki API reddi de JSON ve doğru Retry-After alıyor. |
| İndirme | Kontrol fonksiyonu yoksa kilidi açık varsayan iki yol kaldırıldı. İndirme hakkını geri alma SQL hatası sıradan false sonucu olarak gizlenmiyor; işlem geri alınabiliyor. |
| Bildirim kimlikleri | Dizi kimliğin bütün bildirimleri okundu işaretlemesi engellendi. İç içe/geçersiz silme kimlikleri tamsayıya çevrilip başka bildirime uygulanmıyor. |
| Diğer | Giriş dönüş adresindeki slash/büyük harf/kodlama farkının kimlik doğrulama sayfasına döngü oluşturması engellendi. Görüntüleme API'si taslak veya olmayan konuyu artırmıyor ve başarı bildirmiyor. Yorum araması/reaksiyon sorgusu hataları başarılı boş veri olarak saklanmıyor. |

Yeni `security-flow-regression.php` 100 kontrol içeriyor. CSRF ve hız sınırı bağımlılıklarının eksik olması, geçerli/geçersiz token türleri, üç gövde biçimi, yayın durumu, uzunluk, giriş dönüşleri ve şikâyet eşiği denetlendi. Gizleme/sayaç/rollback gerçek MySQL'de yalnızca bağlantıya özel geçici InnoDB tablolarıyla da kontrol edildi; gerçek içerik tablolarına test kaydı eklenmedi. Yetki/puan geri alma çağrıları izole testlerde izlendi. Ayrı enjekte edilmiş PDO hatası indirme hakkı iptalindeki SQL başarısızlığının yükseltildiğini doğruluyor.

İkinci HTTP taramasında geçerli multipart upload isteği girişe 302, dizi token'lı istek 419 verdi. Bozuk JSON/dizi alanlar 400; oturumsuz düzenleme 401; olmayan konu/indirme/görüntüleme 404; geçerli yorum ve indirme erişim sorguları 200. Kanıtlar: [28 HTTP kontrolü](C:/Users/Mehmet/.codex/visualizations/2026/11/01/01a196ac-9664-70f0-be88-32f414923b38/http-audit-followup.json), [yenilenen medya karşılaştırması](C:/Users/Mehmet/.codex/visualizations/2026/11/01/01a196ac-9664-70f0-be88-32f414923b38/media-verification-followup.json).

## Search Console: turkmod.net

İncelenen mülk: `sc-domain:turkmod.net`. Sayfa indeksleme raporunun veri tarihi **23 Ekim 2026**.

| Gösterge | Görülen değer |
| --- | ---: |
| İndekslenen URL | 7.569 |
| İndekslenmeyen URL | 59.209 |
| 404 | 20.341 |
| Robots engeli | 13.250 |
| Noindex | 12.559 |
| Yönlendirmeli sayfa | 10.990 |
| 403 | 303 |
| Soft 404 | 38 |
| Yönlendirme hatası | 15 |
| Taranmış, indekslenmemiş | 284 |
| Uygun canonical içeren alternatif | 7 |
| Google farklı canonical seçmiş | 1 |

Tablo görülen başlıca nedenleri içerir. İndekslenmeyen bütün URL'lerin indekslenmesi gerekmez; kaldırılmış sayfalardaki 404 ve amaçlı noindex/robots kuralları beklenen durumlar olabilir. [Google sayfa indeksleme raporu açıklaması](https://support.google.com/webmasters/answer/7440203).

- Manuel işlemler ve güvenlik sorunları raporlarında **sorun algılanmadı**.
- Mobil ve masaüstü Core Web Vitals raporlarında 1.622 iyi URL ve sıfır sorun görüldü.
- HTTPS raporunda 1.710 HTTPS URL, sıfır HTTPS olmayan URL görüldü.
- Breadcrumb: 35 geçerli, sıfır geçersiz. Tartışma forumu: 1.712 geçerli, sıfır geçersiz.
- Sitemap 20 Ağustos'ta gönderilmiş, son okuma 30 Ekim. Ana harita ve **18 alt haritanın tamamı Başarılı**.
- Canlı haritalar HTTP üzerinden de denetlendi: tümü 200 ve XML; aynı haritada tekrar eden URL veya query içeren URL bulunmadı.

### Canlı örneklerin güncel yanıtları

`/indir?id=3703&confirm=1` şu zinciri verdi: 301 ile `/indir/`, 302 ile ilgili konu, 301 ile konunun slash içeren adresi, ardından 200. Yerel düzeltme indirme işlemine noindex/no-store ekliyor ve üretilen bağlantıları slash kuralıyla eşleştiriyor.

Rapordaki bozuk eski forum/BBCode örneği `.47/Bölüm 2: [URLhttps://…` bugün bir 301 sonrasında 404'e ulaştı. Bu örnekte güncel bir döngü görülmedi. Eski rapordaki 15 yönlendirme hatasının tamamının güncel olarak çözüldüğü sonucu çıkarılmadı.

Search Console'da sitemap yeniden gönderilmedi ve “Düzeltmeyi doğrula” başlatılmadı. Bunlar canlı kod yayımlandıktan sonra anlamlı olacaktır.

## Site haritası doğrulaması

| Ortam | Alt harita | Sayfa | Kategori | Konu | Görsel içeren konu |
| --- | ---: | ---: | ---: | ---: | ---: |
| Canlı | 18 | 2 | 34 | 7.670 | 7.643 |
| Yerel, düzeltmeler sonrası | 8 | 3 | 34 | 4.073 | 1 |

Yerel robots ayarı `/uploads/` taramasını engelliyor; bu yüzden upload görselleri yerel görsel haritasından çıkarılıyor. Kalan bir konuda iki dış kaynak görseli var. Canlı robots.txt dosyasında upload engeli görülmedi. Ortamların içerik sayıları da farklı.

Son yerel taramada sekiz alt harita XML olarak açıldı; tekrar ve query URL sayısı sıfır. Haritaların ilk/son URL'lerinden alınan **15 örnek hedef 200 ve indekslenebilir** yanıt verdi. Giriş gerektiren ban itirazı sayfasının haritaya girmesi bu kontrolde tespit edilip düzeltildi.

## Test sonuçları

Ortam: PHP 8.2.12, yerel MySQL, Node 24.16.0.

| Kontrol | Sonuç |
| --- | --- |
| `php scripts/php-lint.php` | 452 dosya başarılı; sonraki düzenlemeler ayrıca lint edildi |
| `php tests/production-log-regression.php` | 43 kontrol |
| `php tests/media-webp-regression.php` | 18 senaryo |
| `php tests/rate-limiter-mysql-regression.php` | Altı saat dilimi birleşiminde 60 kontrol |
| `php tests/notification-email-regression.php` | 391 kontrol; yerel SMTP alıcısında 57 ileti |
| `php tests/project-audit-regression.php` | 83 kontrol |
| `php tests/security-flow-regression.php` | 100 kontrol; SQLite ve geçici MySQL/InnoDB tabloları |
| `php -n tests/redis-cache-regression.php` | İzole Redis taklidiyle 25 kontrol |
| Dört `.test.mjs` dosyası, `node --test` | 24 test başarılı |
| `npm run build` | JavaScript ve CSS başarılı |
| `composer validate --no-check-publish` | Geçerli |
| `composer audit --locked --format=json` | Bildirilen açık ve terk edilmiş paket yok |
| `npm audit --omit=optional --json` | Bildirilen açık yok |
| `git diff --check` | Başarılı |
| HTTP taramaları | İlk 36 ve ikinci 28 kontrolün son sonuçlarında PHP hata çıktısı/5xx yok |

Tarayıcıda ana sayfa, konu, kategori, ikinci kategori sayfası, arama ve giriş formu açıldı. İncelenen ana/konu/kategori sayfalarında kırık resim ve tarayıcı konsolunda hata/uyarı görülmedi. H1, canonical, sayfalama ve konu JSON-LD çıktısı kontrol edildi.

## Dosya temizliği ve medya bütünlüğü

11 kalıntı dosyası ve üç boş dizin kaldırıldı:

- İki eski Playwright `.yml` sayfa dökümü.
- `output/playwright/notification-browser-check.js` tek seferlik kontrol kalıntısı.
- Composer incelemesinin dört geçici çıktı/kilit dosyası ve boş `storage/audit-tmp` dizini.
- Süresi dolmuş dört normal uygulama önbellek dosyası. Sonraki HTTP kontrollerinde gereken önbellek kayıtları uygulama tarafından yeniden üretildi.
- Otomatik demo Seeder kaldırıldıktan sonra boş kalan iki dizin.

Ayrıca kaldırılan Seeder kaynak dosyasıyla toplam **12 dosya / 217.150 bayt** kaldırıldı. Kalıntı dosyalarının toplamı 213.882 bayt.

Medya envanteri karşılaştırması:

| Ölçüm | Önce | Sonra |
| --- | ---: | ---: |
| Medya dosyası | 7.893 | 7.893 |
| Toplam bayt | 739.413.600 | 739.413.600 |
| Kayıp/değişmiş dosya | — | 0 / 0 |

Her medya dosyasının SHA-256 özeti başlangıçla aynı. Uploadlar, ekran görüntüleri, fontlar ve `storage/cache/scraper-images` içeriği temizliğe alınmadı. Composer/Node bağımlılıkları, migration'lar ve kullanılan kaynak/test dosyaları korundu.

Kanıtlar: [medya doğrulaması](C:/Users/Mehmet/.codex/visualizations/2026/11/01/01a196ac-9664-70f0-be88-32f414923b38/media-verification.json), [temizlik listesi](C:/Users/Mehmet/.codex/visualizations/2026/11/01/01a196ac-9664-70f0-be88-32f414923b38/cleanup.json), [son HTTP taraması](C:/Users/Mehmet/.codex/visualizations/2026/11/01/01a196ac-9664-70f0-be88-32f414923b38/http-audit-final.json), [son yerel sitemap taraması](C:/Users/Mehmet/.codex/visualizations/2026/11/01/01a196ac-9664-70f0-be88-32f414923b38/sitemap-audit-final.json).

## Yayım öncesi kontrol ve doğrulama sınırları

- `CORE_CACHE_BACKEND` için geçerli değerler `file`, `database`, `redis`. Geçersiz değer ve eski `db` kısaltması reddedilir. Redis seçiliyse native Redis uzantısı gerekir; bu yerel PHP CLI'da bulunmadığı için gerçek Redis sunucusu entegrasyonu test edilmedi. Yerel seçili dosya önbelleğinin sağlık yazma/okuma kontrolü geçti.
- Aktif tema ayarı kurulu ve geçerli bir tema kimliği göstermeli; bu projedeki tema `turkmod`. Eksik `default` temasına dayanan bir canlı ayar varsa yayım öncesinde düzeltilmeli.
- Oturum açılmış yönetim paneli işlemleri tarayıcıda gönderilmedi. İlgili mevcut PHP/JavaScript regresyonları çalıştırıldı.
- Kuyruk sağlık kontrolü tablo erişimini ölçer; worker'ın zamanında çalıştığını veya e-postanın alıcının kutusuna ulaştığını ölçmez.
- `.htaccess` üretim için OPcache zaman damgası denetimini kapatıyor. Yayım sırasında PHP servisinin OPcache'i yenilenmeli.
- Canlıya yayımın ardından aynı sitemap/HTTP kontrolleri canlıda tekrarlanmalı, Search Console'da ilgili doğrulamalar başlatılmalı. Rapor sonuçları belirtilen yerel ortam ve senaryolar için geçerlidir.
