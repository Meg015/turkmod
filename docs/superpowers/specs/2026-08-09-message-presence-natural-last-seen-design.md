# Mesajlarda Çevrimiçi Durumu ve Doğal Son Görülme Tasarımı

**Tarih:** 2026-08-09
**Durum:** Onaylandı

## Amaç

Mevcut beş dakikalık kullanıcı presence modelini özel mesajlar sayfasına taşımak ve profil sayfasındaki “Son Çevrimiçi” değerini takvim sınırlarını dikkate alan daha doğal Türkçe ifadelerle göstermek. Çözüm mevcut mesaj sorgularını ve yenileme akışlarını genişletecek; yeni bir presence endpoint'i, kullanıcı başına ek sorgu veya WebSocket protokolü eklemeyecek.

## Kapsam

- Özel mesajlar sayfasındaki sol konuşma listesinin avatarlarında çevrimiçi/çevrimdışı durum noktası göstermek.
- Açık konuşmanın üst başlığındaki kullanıcı avatarında aynı durum noktasını göstermek.
- Aktif konuşmanın durumunu mevcut konuşma yenilemesiyle, diğer konuşmaları dakikada bir toplu liste yenilemesiyle güncellemek.
- Profildeki “Son Çevrimiçi” metnini dakika, saat, dün, gün ve tam tarih basamaklarına ayırmak.
- Durum göstergelerini fare, klavye ve ekran okuyucu kullanımına uygun hale getirmek.
- Mevcut ortak presence hesabını tek doğruluk kaynağı olarak korumak.

Arama sonuçlarına durum noktası eklemek, yeni çevrimiçi kullanıcı listesi oluşturmak, anlık presence WebSocket olayı eklemek ve kullanıcıya presence gizlilik ayarı sunmak bu çalışmanın kapsamında değildir.

## Temel Presence Kuralı

- Kaynak alan mevcut `users.last_activity_at` değeridir.
- Son geçerli etkinliği içinde bulunulan andan 300 saniyeden daha yeni olan kullanıcı çevrimiçi kabul edilir.
- Tam 300 saniye ve daha eski etkinlik çevrimdışı kabul edilir.
- Boş, okunamayan veya gelecekte olan değer güvenli biçimde çevrimdışı kabul edilir.
- Sunucu tarafındaki ortak presence sunum modeli durum sınıfını, boolean çevrimiçi bilgisini ve kullanıcıya gösterilecek metni üretir.
- Mesaj servisi, profil sunumu ve yorumlar eşik veya etiket mantığını kendi içinde tekrar etmez.

## Doğal “Son Çevrimiçi” Metni

Profilde gösterilen değer, sunucunun `Europe/Istanbul` yerel takvimine göre aşağıdaki sırayla hesaplanır:

1. Kullanıcı çevrimiçiyse: `Şimdi çevrimiçi`.
2. Aynı takvim gününde ve bir saatten kısa süre önce etkinse: `12 dakika önce` biçimi.
3. Aynı takvim gününde ve en az bir saat önce etkinse: `3 saat önce` biçimi.
4. Bir önceki takvim gününde etkinse: `Dün 21:40` biçimi.
5. İki ile altı takvim günü önce etkinse: `3 gün önce` biçimi.
6. Yedi veya daha fazla takvim günü önce etkinse: `02.08.2026 21:40` biçiminde tam tarih ve saat.
7. Geçerli etkinlik kaydı yoksa: `Bilinmiyor`.

“Dün” ve gün sayısı geçen saniyeye göre değil, yerel takvim günü farkına göre belirlenir. Böylece gece yarısını geçen bir etkinlik aynı günün saat ifadesiyle karıştırılmaz. Geçerli bir tarih bulunduğunda tam tarih-saat mevcut açıklama/başlık metninde de korunur.

## Mesaj Servisi ve Sunum Verisi

`MessageService` içindeki tek konuşma ve konuşma listesi sorguları zaten karşı tarafın kullanıcı kaydına katılır. Bu iki sorguya `u.last_activity_at` aynı seçim içinde eklenir; kullanıcı başına yeni sorgu çalıştırılmaz.

Konuşma satırlarını istemciye hazırlayan ortak dekorasyon adımı, ortak presence modelini kullanarak en az şu alanları üretir:

- `with_user_is_online`: boolean çevrimiçi durumu.
- `with_user_presence_label`: `Çevrimiçi` veya `Çevrimdışı`.
- `with_user_presence_state_class`: görünümün kullanacağı sınırlı durum sınıfı.

Ham `last_activity_at` değeri mesaj API yanıtına taşınmaz. Böylece istemci tarih hesabı yapmaz ve bütün yüzeyler aynı sunucu kuralını kullanır.

## Mesajlar Sayfası Görsel Davranışı

Sol konuşma listesindeki ve aktif konuşma başlığındaki avatar, göreli konumlandırılmış küçük bir kapsayıcıya alınır. Durum noktası avatarın sağ alt köşesinde bulunur ve mevcut avatar ölçülerini veya satır hizasını değiştirmez.

- Çevrimiçi durum canlı yeşil, ince zemin halkalı ve hafif nabız animasyonludur.
- Çevrimdışı durum yumuşatılmış kırmızı ve hareketsizdir.
- Noktaların görsel dili profil ve yorumlardaki mevcut presence bileşeniyle tutarlı olur.
- Fareyle üzerine gelindiğinde ve klavyeyle odaklandığında `Çevrimiçi` veya `Çevrimdışı` bilgi balonu gösterilir.
- Nokta odaklanabilir ve erişilebilir adı bulunur; durum yalnızca renkle aktarılmaz.
- `prefers-reduced-motion: reduce` etkin olduğunda nabız animasyonu kaldırılır.
- Küçük ekranlarda bilgi balonu görüntü alanının dışına taşmayacak yönde hizalanır.

Arama sonuçları geçici keşif öğeleri olduğu ve tasarım kapsamı konuşmalar olduğu için arama sonucu avatarlarına nokta eklenmez.

## Yenileme ve Veri Akışı

1. Mesajlar sayfası açılırken `listThreads()` ve aktif konuşma için `threadForUser()` ortak presence alanlarını döndürür.
2. Sunucu tarafından oluşturulan ilk HTML, konuşma listesindeki ve aktif başlıktaki noktaları bu alanlarla çizer.
3. Aktif konuşma için çalışan mevcut yaklaşık 3,5 saniyelik `action=thread` yenilemesi, yeni mesajlarla birlikte aktif başlığın ve sol listedeki karşılık gelen satırın presence durumunu günceller.
4. Diğer konuşmaların noktaları mevcut `action=list` yanıtı kullanılarak 60 saniyede bir topluca güncellenir.
5. Toplu yenileme yalnızca noktanın sınıfını, metnini ve erişilebilirlik özniteliklerini değiştirir; konuşma listesini yeniden kurmaz ve kullanıcının kaydırma/odak durumunu bozmaz.
6. Belge gizliyken 60 saniyelik yenileme çalıştırılmaz. Sekme yeniden görünür olduğunda bir defalık anlık liste yenilemesi yapılır, ardından normal süre devam eder.

Bu akış yeni bir endpoint veya zamanlayıcı başına kullanıcı sorgusu oluşturmaz. Aktif konuşmanın hızlı güncellenmesi mevcut istekten yararlanırken, diğer konuşmaların doğruluğu düşük maliyetli bir dakikalık toplu yenilemeyle korunur.

## Hata ve Sınır Davranışı

- Eksik ya da tanınmayan presence alanı istemcide çevrimdışı kabul edilir.
- `action=thread` veya `action=list` isteği başarısız olursa mevcut nokta durumu korunur; gösterge geçici olarak kaybolmaz veya yanıp sönmez.
- Yenileme hatası mesaj okuma, gönderme ve konuşma seçme akışlarını etkilemez.
- Bir konuşma listeden kaldırılmışsa istemci o kullanıcı için DOM güncellemesi yapmadan devam eder.
- Sekme görünür olduğunda yapılan anlık yenileme başarısız olsa bile 60 saniyelik normal denemeler sürer.
- Profilde geçersiz veya bulunmayan son etkinlik `Bilinmiyor` olarak kalır; nokta çevrimdışı görünür.

## Performans, Güvenlik ve Bakım

- Presence verisi mevcut mesaj sorgularına eklenir; N+1 sorgu oluşmaz.
- Diğer konuşmalar tek `action=list` isteğiyle güncellenir ve yenileme süresi 60 saniyedir.
- Arka plan sekmesinde gereksiz ağ isteği yapılmaz.
- API yalnızca hesaplanmış durum alanlarını döndürür; ham etkinlik zamanı yayımlanmaz.
- Durum sınıfı istemcide yalnızca izin verilen çevrimiçi/çevrimdışı değerlerinden seçilir.
- Tooltip ve erişilebilirlik metinleri mevcut HTML kaçış kurallarından geçirilir.
- Tarih ve eşik mantığı ortak `UserPresence` biriminde kalır; mesaj görünümü yalnızca sunum yapar.

## Doğrulama

- Ortak presence hesabında 299 saniyenin çevrimiçi, 300 saniyenin çevrimdışı olduğu doğrulanır.
- Doğal zaman metni için aynı gün dakika, aynı gün saat, önceki takvim günü, iki-altı günlük aralık ve yedi günlük sınır sabit bir referans zamanla test edilir.
- Ay ve yıl geçişindeki “Dün” ile takvim günü farkı örnekleri test edilir.
- Boş, geçersiz ve gelecekteki tarihler güvenli çevrimdışı/`Bilinmiyor` sonucunu verir.
- Tek konuşma ve konuşma listesi yanıtlarının ortak presence alanlarını içerdiği doğrulanır.
- Mesaj sorgularında presence için kullanıcı başına ek sorgu bulunmadığı kaynak ve çalışma zamanı kontrolleriyle doğrulanır.
- Aktif konuşma yenilemesinin hem başlık hem ilgili sol liste noktasını güncellediği test edilir.
- Dakikalık liste yenilemesinin diğer noktaları güncellediği, gizli sekmede durduğu ve görünürlük dönüşünde hemen çalıştığı test edilir.
- Liste isteği hatasında önceki görsel durumun korunduğu doğrulanır.
- Fare, klavye odağı, erişilebilir ad ve azaltılmış hareket davranışı kontrol edilir.
- Masaüstü ve mobil yerleşim, giriş yapılmış kullanıcı oturumuyla gerçek tarayıcıda incelenir.
- Değişen PHP dosyaları sözdizimi kontrolünden, JavaScript dosyaları sözdizimi kontrolünden ve ilgili CSS paketleri proje derlemesinden geçirilir.
- Mevcut migration koruması ve hedefli presence doğrulama betiği çalıştırılır.

## Kabul Ölçütleri

- Mesajlar sayfasındaki her mevcut konuşma avatarında yeşil veya kırmızı durum noktası görünür.
- Aktif konuşma başlığındaki avatar aynı presence durumunu gösterir.
- Nokta fare ve klavye etkileşiminde metinsel durumu açıklar.
- Aktif konuşmanın durumu mevcut hızlı yenilemeyle, diğer konuşmalar en geç yaklaşık bir dakika içinde güncellenir.
- Gizli sekmede dakikalık durum isteği yapılmaz; sekmeye dönüldüğünde durum yenilenir.
- Profildeki “Son Çevrimiçi” değeri onaylanan takvim basamaklarına göre doğal Türkçe metin üretir.
- Eksik veya başarısız presence verisi ana mesajlaşma deneyimini bozmaz.
- Uygulama yeni endpoint, N+1 sorgu veya ham son etkinlik tarihi ifşası oluşturmaz.
