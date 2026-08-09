# Gerçek Zamanlı Presence Koordinasyonu Uygulama Planı

**Tarih:** 2026-08-09

**Tasarım:** `docs/superpowers/specs/2026-08-09-realtime-presence-coordination-design.md`

## Uygulama İlkeleri

- Mevcut `users.last_activity_at` alanı HTTP yedeği olarak kullanılacak; yeni migration eklenmeyecek.
- Presence ikincil özelliktir. Presence veritabanı, WebSocket veya istemci hatası profil, yorum, mesaj ve bildirim akışlarını durdurmayacak.
- Çalışma ağacındaki mesaj konuşması temizleme ve diğer kullanıcı değişiklikleri korunacak; hiçbir dosya geri alınmayacak veya topluca yeniden biçimlendirilmeyecek.
- Test edilebilir durum hesabı küçük sınıflarda tutulacak; Ratchet ve DOM katmanı yalnızca adaptör olacak.
- Her aşamada önce odaklı doğrulama eklenecek, sonra uygulama yazılacak.
- Derlenmiş varlıklar yalnızca kaynak dosyalar tamamlandıktan sonra üretilecek.

## 1. Ortak Hesap Görünürlüğü ve Toplu Presence Modeli

**Dosyalar**

- Yeni: `includes/src/Engine/UserActivity/UserPresenceLookup.php`
- Değiştir: `includes/src/Engine/UserActivity/UserPresence.php`
- Değiştir: `includes/src/Engine/UserActivity/Support/helpers.php`
- Değiştir: `scripts/verify-user-presence.php`

**İşler**

1. Doğrulamaya aktif, yasaklı, pasif, silinmiş ve bulunamayan hesap örnekleri ekle; gizli hesapların `visible: false` üretmesini bekle.
2. `UserPresenceLookup` içinde pozitif ve benzersiz kullanıcı kimliği normalizasyonunu tek yerde uygula.
3. Tek toplu sorguyla `id`, `status`, `is_banned`, `deleted_at` ve `last_activity_at` alanlarını oku; N+1 sorgu üretme.
4. Görünürlük kuralını `status = active`, `is_banned != 1` ve `deleted_at IS NULL` olarak merkezileştir.
5. Görünür kullanıcı için sınırlı public model üret: `visible`, `is_online`, `status_label`, yuvarlatılmış `relative_label` ve durum sınıfı.
6. Gizli, bulunamayan ve geçersiz kimlikleri aynı `visible: false` biçimine dönüştür; hesap durumunu ayırt ettirecek ayrıntı verme.
7. WebSocket kesin durumunun toplu modele `onlineOverrides` girdisiyle uygulanabilmesini sağla; HTTP çağrısı bu girdiyi vermediğinde mevcut 300 saniyelik kuralı kullan.
8. Eski tarihlerin public göreli etiketinde kesin saat göstermemesini sağla. `exact_label` sunucu içi uyumluluk için kalabilse de public JSON ve presence tooltip’ine taşınmamalı.

**Doğrulama**

- `php scripts/verify-user-presence.php`
- SQLite belleğinde tek sorgu, uygunluk filtresi, 299/300 saniye sınırı ve ham tarih sızıntısı kontrolü.

## 2. Salt Okunur Toplu HTTP Endpoint’i

**Dosyalar**

- Yeni: `api/user-presence.php`
- Yeni: `scripts/verify-realtime-presence.php`
- Kullan: `includes/src/Core/Cache/Cache.php`
- Kullan: `includes/RateLimitHelpers.php`

**İşler**

1. Yalnızca `GET` kabul eden `api/user-presence.php` endpoint’ini ekle.
2. Virgülle ayrılmış `ids` girdisini en fazla 100 pozitif benzersiz kimlikle sınırla; boş ve bozuk isteği doğrulama hatasıyla reddet.
3. Oturum açmış kullanıcı için kullanıcı kimliği, anonim ziyaretçi için IP tabanlı hız sınırı uygula. Başarılı isteği de rate-limit sayacına yaz.
4. Sıralanmış kimlik kümesine göre kısa ömürlü önbellek anahtarı üret; proje `Cache` servisiyle yaklaşık 10 saniyelik cevap önbelleği kullan.
5. Cevabı `source: http`, `observed_at`, ve kimliğe göre anahtarlanmış `users` sözlüğüyle döndür.
6. Ham `last_activity_at`, `exact_label`, yasak nedeni, hesap durumu veya varlık ayrıntısı döndürme.
7. Veritabanı/önbellek hatasında mevcut API hata standardını kullan; hassas ayrıntıyı yalnızca uygulama loguna gönder.
8. Aynı tarayıcıdaki koordinatör 100’den büyük birleşimi 100’lük ayrı istekler halinde çalıştırabilecek; endpoint tek istekte sınırı aşmayı kabul etmeyecek.

**Doğrulama**

- `php scripts/verify-realtime-presence.php`
- `php -l api/user-presence.php`
- 100/101 kimlik sınırı, yinelenen/negatif kimlik, anonim cevap ve görünmez hesap sözleşmesi.

## 3. Test Edilebilir Bağlantı ve Abonelik Kaydı

**Dosyalar**

- Yeni: `includes/src/Core/Realtime/PresenceSubscriptionRegistry.php`
- Değiştir: `scripts/verify-realtime-presence.php`

**İşler**

1. Ratchet nesnelerinden bağımsız bir kayıt sınıfı oluştur; anahtar olarak bağlantı/resource kimliği kullan.
2. Bağlantı ekleme işleminin kullanıcı için ilk bağlantı olup olmadığını döndürmesini sağla.
3. Bağlantı çıkarma işleminin kullanıcı için son bağlantı olup olmadığını döndürmesini sağla ve bütün aboneliklerini temizle.
4. `presence_subscribe` ve `presence_unsubscribe` farklarını bağlantı başına tut; tek mesajda en fazla 100 kimlik, bağlantı toplamında güvenli üst sınır uygula.
5. İzlenen kullanıcı kimliğinden abone bağlantı kimliklerine ters indeks oluştur; değişiklik olayında bütün bağlantıları tarama.
6. Tekrarlanan subscribe/unsubscribe mesajlarını idempotent yap.

**Doğrulama**

- İlk/ikinci bağlantı, son bağlantı, bağlantı temizliği, yinelenen abonelik, 100 kimlik mesaj sınırı ve ters indeks yönlendirmesi.
- Testler gerçek ağ veya 30 saniyelik bekleme kullanmamalı.

## 4. WebSocket Sunucusunda Kesin Presence

**Dosyalar**

- Değiştir: `scripts/websocket-server.php`
- Değiştir: `includes/src/Core/Realtime/WebSocketConfig.php` yalnızca gerekli sınırlar merkezi ayara alınırsa
- Değiştir: `scripts/verify-realtime-presence.php`

**İşler**

1. Sunucu başlangıcında benzersiz `server_instance_id` ve monoton olay sıra sayacı oluştur.
2. Başarılı mevcut PHP oturumu doğrulamasından sonra bağlantıyı `PresenceSubscriptionRegistry` içine kaydet.
3. İlk bağlantıda bekleyen çevrimdışı React timer’ını iptal et ve hesap uygunluğu doğrulanabiliyorsa `presence_changed` çevrimiçi olayını yalnızca ilgili abonelere gönder.
4. Presence veritabanı kontrolü başarısızsa mevcut mesaj/bildirim WebSocket bağlantısını reddetme; presence olayını atla ve istemcinin HTTP yedeğine geçmesine izin ver.
5. `onMessage` içinde boyutu sınırlı JSON ayrıştır; yalnızca bilinen `presence_subscribe`, `presence_unsubscribe` ve gerekirse `presence_sync` türlerini kabul et.
6. Subscribe sonrasında `UserPresenceLookup` ile ilk snapshot’ı oluştur; sunucuda bağlantısı bulunan uygun kullanıcılar için kesin çevrimiçi override uygula.
7. Son bağlantı kapandığında 30 saniyelik React timer başlat; kullanıcı yeniden bağlanırsa iptal et, süre dolarsa tek çevrimdışı olayı üret.
8. Olaylara `server_instance_id`, monoton `sequence` ve `observed_at` ekle.
9. Boş `SplObjectStorage`, abonelik ve timer kayıtlarını temizle; `onError` ve `onClose` tekrar çağrılarında güvenli davran.
10. Sunucu yeniden başlatıldığında istemcinin yeni nesil snapshot’ı yetkili kabul edebilmesi için protokol sürümünü açıkça gönder.

**Doğrulama**

- `php -l scripts/websocket-server.php`
- Kaynak seviyesi protokol doğrulaması ve saf kayıt sınıfı testleri.
- Yerel sunucuda iki oturumla ilk bağlantı, çoklu bağlantı ve 30 saniyelik son bağlantı senaryosu.

## 5. Hesap Durumu Değişikliklerini Anında Gizleme

**Dosyalar**

- Yeni: `includes/src/Engine/UserActivity/UserPresenceInvalidator.php`
- Değiştir: `includes/src/Core/Realtime/WebSocketBroadcaster.php`
- Değiştir: `scripts/websocket-server.php`
- Değiştir: `includes/src/Engine/Users/BanService.php`
- Değiştir: `includes/src/Engine/Users/Support/users-helpers.php`
- Değiştir: `admin/api/user-status-change.php`
- Değiştir: `admin/user-edit.php`
- Gerektiği yerde değiştir: `includes/src/Engine/AdminAudit/Support/helpers.php`

**İşler**

1. `UserPresenceInvalidator` ile ilgili kullanıcıya ait presence cache etiketlerini sil ve WebSocket iç API’sine `presence_invalidate` komutu gönder.
2. `WebSocketBroadcaster` mevcut kullanıcıya payload gönderme davranışını bozmadan yeni iç komut biçimini desteklesin.
3. WebSocket sunucusu invalidation aldığında hesabı tekrar sorgulasın; görünmezse abonelere `visible: false` yayımlasın ve o hesaba ait açık bağlantıları kapatsın.
4. Ban/unban, activate/deactivate, silme, toplu kullanıcı işlemi, kullanıcı düzenleme ve audit geri alma yollarında başarılı veritabanı değişikliğinden sonra invalidator çağır.
5. Bildirim yayınlama başarısızlığını yönetim işleminin başarısızlığına dönüştürme; hata loglansın ve beş dakikalık uzlaştırma güvenlik ağı olarak kalsın.
6. Değişiklik transaction içindeyse invalidation yalnızca commit sonrasında çalışsın.

**Doğrulama**

- Ban/pasifleştirme/silme sonrası `visible: false` üretimi.
- Unban/aktifleştirme sonrası ilk yeni snapshot’ta presence bileşeninin geri gelebilmesi.
- WebSocket servisi kapalıyken yönetim işlemlerinin yine başarılı olması.

## 6. Çoklu Sekmede Tek Ağ Sahibi

**Dosyalar**

- Değiştir: `assets/js/public-topbar-realtime.js`
- Yeni: `scripts/verify-public-presence.js`

**İşler**

1. Mevcut sekme kimliği, `BroadcastChannel`, `localStorage` fallback’i, heartbeat ve 45 saniyelik peer TTL altyapısını koru.
2. Canlı sekmeler arasından deterministik ağ lideri seç; sekmeler aynı peer listesinden aynı lideri hesaplasın.
3. Yalnızca lider WebSocket açsın. Liderlik kaybedildiğinde bağlantıyı kapat; yeni lider abonelik birleşimini yeniden kursun. 30 saniyelik sunucu toleransı bu devirde çevrimdışı titreşimini önlesin.
4. Her sekmenin yerel `watchPresence(userIds, callback)` aboneliklerini tut; sekme durum mesajına benzersiz izlenen kullanıcı kümesini ekle.
5. Lider bütün canlı sekmelerin birleşimini hesaplasın ve sunucuya yalnızca eklenen/çıkarılan kimlik farklarını 100’lük mesajlar halinde göndersin.
6. WebSocket snapshot ve değişiklik olaylarını sekmeler arası kanalda dağıt; lider olmayan sekmeler de mesaj/bildirim ve presence olaylarını mevcut `subscribe` API’sinden alabilsin.
7. Eski `window.publicTopbarRealtime.subscribe` sözleşmesini koru; mesaj ve bildirim menülerinde gerileme oluşturma.
8. `watchPresence` temizleme işlevi son aboneliği kaldırdığında ilgili kimliği birleşimden düşürsün.
9. `BroadcastChannel` yoksa storage event’i, her ikisi yoksa sekme başına güvenli ağ hattı fallback’i kullan.
10. Oturum açmamış ziyaretçide WebSocket açma; yalnızca sekmeler arası HTTP presence koordinasyonunu çalıştır.

**Doğrulama**

- Node doğrulamasında iki/üç sahte sekme, deterministik lider, lider devri, peer TTL ve abonelik birleşimi.
- WebSocket olayının lider olmayan sekmedeki mevcut subscriber’a ulaşması.
- Eski mesaj bildirimi sahipliği ve tekrar engellemesinin korunması.

## 7. HTTP Fallback ve Kademeli Geri Çekilme

**Dosyalar**

- Değiştir: `assets/js/public-topbar-realtime.js`
- Değiştir: `scripts/verify-public-presence.js`

**İşler**

1. Presence API URL’sini `meta[name="app-base-uri"]` üzerinden güvenli biçimde oluştur.
2. Yalnızca lider sekme ve görünür belge HTTP presence isteği çalıştırsın.
3. Normal başarılı aralığı 60 saniye yap; ardışık hatalarda 120 saniye ve en fazla 300 saniyeye çık.
4. İlk başarılı cevapta hata sayacını ve aralığı 60 saniyeye sıfırla.
5. WebSocket sağlıklıyken yoğun polling’i durdur; beş dakikalık görünürlük/tutarlılık uzlaştırması bırak.
6. WebSocket kopunca son bilinen durumu koru ve HTTP fallback’i gecikmeden başlat.
7. Kimlik birleşimi 100’den büyükse grupları sırayla çalıştır; bir grubun hatası başarılı grupları silmesin.
8. Visibility dönüşünde gecikmiş yenilemeyi hemen çalıştır; gizli belgede timer’ı yeniden planlayıp ağ çağrısı yapma.
9. Her HTTP isteğine yerel sıra numarası ver; daha yeni istekten sonra tamamlanan eski cevabı uygulama.

**Doğrulama**

- Sahte saat/fetch ile 60 → 120 → 300, 300’de tavan ve başarıda 60’a dönüş.
- WebSocket bağlı/bağlantısız ve belge görünür/gizli kombinasyonları.
- Başarısız isteğin bütün noktaları kırmızıya çevirmediği kontrolü.

## 8. Ortak DOM ve Mobil Tooltip Bileşeni

**Dosyalar**

- Değiştir: `assets/js/ui-foundation.js`
- Değiştir: `assets/js/public-topbar-realtime.js`
- Değiştir: `assets/css/theme.css`
- Değiştir: `assets/css/pro-comments.css`
- Değiştir: `assets/css/messages-page.css`

**İşler**

1. Ortak veri sözleşmesini uygula: presence kökü, `data-presence-user-id`, durum noktası ve isteğe bağlı göreli metin hedefi.
2. Tek DOM adaptörüyle `is-online`, `is-offline`, erişilebilir ad, tooltip metni ve profil göreli metnini güncelle.
3. `visible: false` durumunda bütün presence kökünü `hidden` yap; boşluk tutucu bırakma. Sonraki görünür snapshot için düğümü DOM’da güvenli biçimde koru.
4. İlk taramada ve dinamik yorum/mesaj render’ından sonra kullanıcı kimliklerini otomatik izle; kaldırılan düğümlerin watcher kaydını temizle.
5. Nokta tıklama/dokunmasında `preventDefault` ve `stopPropagation` uygula; çevresindeki avatar/profil bağlantısının diğer alanlarını değiştirme.
6. Mobil/işaretçi dokunmasında `.is-tooltip-open` durumunu aç; 2,5 saniyelik tek timer ve dışarı pointer dokunuşuyla kapat.
7. Aynı anda yalnızca bir tooltip açık tut; Escape ve focus kaybında güvenli kapatma ekle.
8. Masaüstü hover/focus davranışını ve `prefers-reduced-motion` kuralını koru.

**Doğrulama**

- Nokta dokunmasının profil navigasyonunu engellemesi, avatarın kalanının engellememesi.
- 2,5 saniye, dış dokunma, Escape ve tek açık tooltip davranışı.
- Gizli presence kökünün yer kaplamaması ve yeniden görünür olabilmesi.

## 9. Profil, Yorum ve Mesaj Yüzeylerini Ortak Sözleşmeye Taşıma

**Dosyalar**

- Değiştir: `themes/turkmod/profile-sidebar.tpl`
- Değiştir: `includes/partials/profile-sidebar.php`
- Değiştir: `includes/src/Engine/Users/ProfilePresentation.php`
- Değiştir: `includes/PublicThemeRenderer.php`
- Değiştir: `api/comments.php`
- Değiştir: `themes/turkmod/comment-item.tpl`
- Değiştir: `assets/js/topic-comments.js`
- Değiştir: `includes/src/Modules/Messages/Services/MessageService.php`
- Değiştir: `includes/src/Modules/Messages/Http/messages-page-content.php`
- Değiştir: `assets/js/messages-page.js`

**İşler**

1. Profil sunumuna `presence_visible` ve hedef kullanıcı kimliğini ekle; tema ve PHP fallback satırına ortak data niteliklerini yerleştir.
2. Profilde çevrimiçiyse “Çevrimiçi”, değilse “Son çevrimiçi: …” göster; gizliyse “Üyelik Süresi” altında presence satırını bütünüyle sakla.
3. Yorum kök ve yanıt sorgularına uygunluk alanlarını aynı JOIN içinde ekle; public JSON’a yalnızca `presence_visible`, `is_online` ve etiketleri koy.
4. Tema yorum şablonu ve JavaScript fallback renderer’ı aynı `data-presence-user-id` işaretlemesini üretsin.
5. Mesaj servisi konuşma listesi ve açık thread modeline `with_user_presence_visible` eklesin; ham tarih cevaptan çıkarılmış kalmaya devam etsin.
6. Mesaj listesi ve başlığındaki düğümleri ortak DOM sözleşmesine geçir.
7. `messages-page.js` içindeki bağımsız 60 saniyelik `action=list` presence interval’ını kaldır; konuşma içeriği polling’ini mesaj teslimi için koru.
8. Mevcut `new_message` akışını ve mesaj thread temizleme değişikliklerini bozmadan yalnızca presence güncellemesini ortak koordinatöre bırak.
9. Dinamik yorum veya konuşma listesi yeniden render edildiğinde önbellekteki WebSocket durumu sunucu-render edilmiş yaklaşık değerin üzerine hemen uygulansın.

**Doğrulama**

- Profil, kök yorum, iç içe yanıt, mesaj listesi ve mesaj başlığında aynı kullanıcı için tek olayla tutarlı güncelleme.
- Gizli kullanıcıda nokta, tooltip ve son görülme metninin hiçbir yüzeyde bulunmaması.
- Mesaj sayfasında presence için ayrı 60 saniyelik liste isteğinin kalmaması.

## 10. Script Yükleme, Tema ve Derlenmiş Varlıklar

**Dosyalar**

- Değiştir: `includes/public-header.php`
- Değiştir: `themes/turkmod/modules/header.tpl`
- Gerekirse değiştir: `includes/PublicThemeRenderer.php`
- Üret: `assets/dist/public.min.js`
- Üret: `assets/dist/theme.min.css`
- Üret: `themes/turkmod/js/bundle.min.js`
- Üret: `themes/turkmod/css/bundle.css`
- Üret: `themes/turkmod/css/bundle.min.css`

**İşler**

1. `public-topbar-realtime.js` dosyasını oturum açmış ve anonim public sayfalarda tam bir kez yükle.
2. Oturum açmış sayfalarda mevcut topbar kullanıcı kimliği/WebSocket URL yapılandırmasını koru; anonim sayfada kimlik bulunmaması normal kabul edilsin.
3. Script sırasını `ui-foundation.js` ortak tooltip davranışı realtime DOM güncellemesinden önce hazır olacak biçimde doğrula.
4. Yeni CSS durumlarını root varlıklara ve aktif Turkmod temasına aynı davranışla taşı.
5. Kaynaklar tamamlandıktan sonra `npm run build` çalıştır; yalnızca üreticinin beklenen çıktılarındaki farkları koru.

**Doğrulama**

- Aynı script’in iki kez yüklenmediği ve iki koordinatör başlatmadığı kontrolü.
- Anonim profil/konu sayfasında konsol hatası olmadan HTTP fallback.
- Oturumlu sayfada topbar bildirim ve mesaj menülerinin çalışmaya devam etmesi.

## 11. Otomatik Doğrulama Paketi

**Komutlar**

1. `php scripts/verify-user-presence.php`
2. `php scripts/verify-realtime-presence.php`
3. `node scripts/verify-public-presence.js`
4. `node --check assets/js/public-topbar-realtime.js`
5. `node --check assets/js/ui-foundation.js`
6. `node --check assets/js/topic-comments.js`
7. `node --check assets/js/messages-page.js`
8. `composer lint`
9. `npm run build`
10. `composer guard:migration:workspace`

**Başarı koşulları**

- PHP ve JavaScript kontrolleri hatasız.
- Presence doğrulamaları sabit saatle deterministik.
- Migration guard yeni şema ihtiyacı bildirmiyor.
- Build sonrasında kaynak ve dağıtım varlıkları uyumlu.
- Mevcut mesaj thread temizleme doğrulaması uygulanmış şema koşulunda ayrıca yeniden çalıştırılıyor; presence değişikliği bu özelliği bozmuyor.

## 12. Gerçek Tarayıcı ve Canlıya Geçiş Kontrolü

**Tarayıcı senaryoları**

1. Aynı kullanıcıyla iki sekme aç; ağ panelinde tek WebSocket ve tek presence fallback sahibi olduğunu doğrula.
2. Lider sekmeyi kapat; diğer sekmenin bağlantıyı devraldığını ve noktanın kırmızıya düşmediğini doğrula.
3. İki ayrı oturum aç; ilk bağlantıda bütün yüzeylerin yeşile, son bağlantıdan 30 saniye sonra kırmızıya geçtiğini doğrula.
4. Son bağlantıdan sonraki 30 saniye içinde yeniden bağlan; çevrimdışı olayının hiç görünmediğini doğrula.
5. WebSocket servisini durdur; HTTP fallback’in 60/120/300 politikasını ve son bilinen durum korumasını doğrula.
6. Kullanıcıyı pasifleştir, yasakla ve sil; açık sayfalardaki presence köklerinin anında tamamen gizlendiğini doğrula.
7. Mobil emülasyonda noktaya ve avatarın diğer bölümlerine ayrı ayrı dokun; tooltip süresi ve navigasyonu doğrula.
8. Profil, yorumlar, mesaj listesi ve mesaj başlığını açık/koyu tema ile kontrol et.

**Dağıtım sırası**

1. `last_activity_at` güvence migration’ının canlıda uygulanmış olduğunu migration sistemiyle doğrula.
2. Presence lookup, HTTP endpoint’i ve WebSocket’in geriye uyumlu sunucu kodunu dağıt.
3. İstemci ve tema varlıklarını dağıt.
4. WebSocket servisini kontrollü yeniden başlat.
5. Sağlık kontrolü ve iki oturumlu kısa smoke test yap.
6. Loglarda WebSocket JSON reddi, veritabanı hatası ve HTTP 429 oranını izle.

**Geri alma**

- İstemci varlıkları önceki sürüme dönerse yeni sunucu mesajları kullanılmadan kalır.
- Yeni istemci eski WebSocket sunucusuna denk gelirse HTTP fallback çalışır.
- Presence hataları ana sayfaları durdurmadığından acil durumda yalnızca realtime istemci yüklemesi kaldırılarak mevcut beş dakikalık sunucu-render edilmiş durum korunabilir.

## Tamamlanma Ölçütleri

- Tasarımdaki 2, 3, 4, 8, 9 ve 10 numaralı ileri seviye maddeler uygulanmış ve doğrulanmış.
- Normal koşulda aynı tarayıcı için tek presence ağ sahibi var.
- WebSocket değişiklikleri bütün mevcut yüzeylere anında ulaşıyor.
- 30 saniyelik geçiş toleransı kısa kopmalardaki titreşimi önlüyor.
- HTTP hataları 60/120/300 saniye politikasını izliyor.
- Pasif, yasaklı ve silinmiş hesaplar tüm presence yüzeylerinde tamamen gizli.
- Mobil tooltip 2,5 saniye ve dış dokunma davranışına uyuyor.
- Ham aktivite zamanı public JSON veya tooltip’te açığa çıkmıyor.
- Migration guard temiz ve canlıya geçiş sırası belgelenmiş.
- İlgisiz çalışma ağacı değişiklikleri korunmuş.
