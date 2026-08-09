# Gerçek Zamanlı Presence Koordinasyonu Tasarımı

**Tarih:** 2026-08-09
**Durum:** Onaylandı

## Amaç

Mevcut beş dakikalık son etkinlik tabanlı çevrimiçi durumunu, WebSocket bağlıyken anlık ve sekmeler arasında koordineli çalışan bir yapıya yükseltmek. Profil, konu yorumları ve özel mesaj alanları aynı durum kaynağını, aynı gizlilik kurallarını ve aynı erişilebilir arayüz davranışını kullanacaktır.

Bu tasarım aşağıdaki ihtiyaçları birlikte karşılar:

- Aynı tarayıcıdaki sekmelerin ayrı ayrı presence sorgusu ve WebSocket bağlantısı açmaması.
- HTTP yedeğinin art arda başarısızlıklarda kademeli olarak seyrekleşmesi.
- Kısa bağlantı kopmalarında çevrimiçi/çevrimdışı göstergesinin yanıp sönmemesi.
- Yasaklanmış, pasif veya silinmiş hesaplarda presence bilgisinin tamamen gizlenmesi.
- Mobil dokunmada durum açıklamasının bağlantıdan bağımsız açılması.
- Mevcut presence yüzeylerinin WebSocket olaylarıyla anında güncellenmesi.

## Kapsam

Presence aşağıdaki mevcut yüzeylerde ortaklaştırılır:

- Kullanıcı profili
- Konu ana yorumları ve iç içe yanıtlar
- Özel mesaj konuşma listesi
- Açık özel mesaj konuşmasının başlığı

Redis kurulumu, birden çok WebSocket sunucusuna yatay ölçekleme, kullanıcıya özel presence gizlilik tercihi ve yeni bir kullanıcıdan kullanıcıya engelleme sistemi bu çalışmanın kapsamında değildir. Hesap görünürlüğü mevcut `status`, `is_banned` ve `deleted_at` alanlarıyla belirlenir.

## Seçilen Yaklaşım

Mevcut WebSocket ve sekme koordinasyonu altyapısını genişleten hibrit yaklaşım kullanılacaktır:

- WebSocket bağlıyken kesin bağlantı durumu önceliklidir.
- WebSocket kullanılamadığında `users.last_activity_at` ve mevcut beş dakikalık eşik HTTP yedeğinin kaynağıdır.
- Aynı tarayıcıdaki sekmeler lider seçimi ve `BroadcastChannel`/`localStorage` haberleşmesiyle tek bağlantı ve tek sorgu hattını paylaşır.
- Sunucu yalnızca istemcinin abone olduğu kullanıcıların değişikliklerini gönderir.

Bu yaklaşım gerçek zamanlı davranışı sağlarken mevcut tek WebSocket sürecine uyar ve Redis gibi yeni bir canlı ortam bağımlılığı oluşturmaz.

## Mimari

### Ortak istemci koordinatörü

`assets/js/public-topbar-realtime.js` mevcut bildirim koordinasyonunun yanında presence görevlerini de üstlenir. Her sekme benzersiz sekme kimliğini ve ihtiyaç duyduğu kullanıcı kimliklerini koordinatöre bildirir. Seçilmiş lider sekme:

- Tek WebSocket bağlantısını açar.
- Tüm canlı sekmelerin presence aboneliklerinin birleşimini sunucuya iletir.
- Gerekirse toplu HTTP yedeğini çalıştırır.
- Gelen özet ve değişiklikleri diğer sekmelere dağıtır.

Lider dışındaki sekmeler ağ isteği açmaz; paylaşılan olayları kendi DOM bileşenlerine uygular. Mevcut sekme heartbeat'i ve 45 saniyelik sekme yaşam süresi korunur. Lider kapanır veya süresi dolarsa kalan sekmelerden biri görevi devralır, bağlantıyı açar ve birleşik aboneliği yeniden kurar.

Bu WebSocket koordinasyonu yalnızca kimliği doğrulanmış ziyaretçiler için çalışır. Oturum açmamış public profil ve konu ziyaretçileri anonim WebSocket bağlantısı açmaz; salt okunur, hız sınırlı toplu HTTP yedeğiyle yaklaşık durumu alır.

Koordinatör aşağıdaki kavramsal arabirimi sağlar:

- `watchPresence(userIds, callback)`: Bir yüzeyin kullanıcı kümesine abone olması.
- Dönen temizleme işlevi: Yüzey kaldırıldığında aboneliği sonlandırması.
- Son durum önbelleği: Yeni eklenen yüzeye eldeki güncel değeri hemen vermesi.

İç API adı uygulama sırasında mevcut kod adlandırmasına uydurulabilir; tek sahiplik ve abonelik davranışı değişmez.

### WebSocket presence yöneticisi

`scripts/websocket-server.php` içindeki mevcut kullanıcı-bağlantı kaydı korunur ve aşağıdaki sorumluluklarla genişletilir:

- Doğrulanmış kullanıcının ilk bağlantısında çevrimiçi geçişi üretmek.
- Son bağlantı kapandığında 30 saniyelik çevrimdışı zamanlayıcısı başlatmak.
- Aynı kullanıcı yeniden bağlanırsa bekleyen zamanlayıcıyı iptal etmek.
- Bağlantı başına presence abonelik kümesini tutmak.
- İlk abonelikte toplu özet, sonrasında yalnızca ilgili değişiklikleri göndermek.
- Olayları yalnızca o kullanıcıya abone bağlantılara yönlendirmek.

Sunucudaki kesin presence bellekte tutulur. Bu, mevcut tek WebSocket süreçli dağıtım modeliyle uyumludur. WebSocket süreci yeniden başlarsa istemciler bağlantının koptuğunu algılar, HTTP yedeğine geçer ve yeniden bağlandıklarında abonelik özetini tekrar alır.

### Ortak sunucu görünürlük kuralı

Presence üretiminden önce tek bir ortak sunucu katmanı hesap uygunluğunu denetler. Bir hesap ancak aşağıdaki koşulların tamamında görünür presence verisi üretebilir:

- Aktif durumda olması
- Yasaklanmamış olması
- Silinmemiş olması

Aynı kural WebSocket özeti, WebSocket değişikliği, HTTP toplu endpoint'i ve ilk sunucu render'ında kullanılır. Geçersiz veya gizli kimlikler ayırt edilebilir hesap ayrıntısı sızdırmadan `visible: false` sonucu verir. İstemci bu sonucu aldığında nokta, tooltip ve son çevrimiçi satırını tamamen kaldırır.

Bir hesap açık sayfa sırasında pasif, yasaklı veya silinmiş hale getirilirse hesap yönetimi akışı presence görünürlük geçersizleştirme olayı yayımlar. Bunun kaçırılması halinde seyrek tutarlılık kontrolü aynı bilgiyi en geç sonraki uzlaştırmada düzeltir.

### Toplu HTTP yedeği

Aynı kökenli ve salt okunur toplu presence endpoint'i eklenir veya uygun mevcut endpoint genişletilir. Public profilde zaten sunulan sınırlı presence bilgisini anonim ziyaretçilere de verebilir; oturumdan bağımsız olarak hız sınırı, hesap görünürlük filtresi ve proje güvenlik kuralları uygulanır. İstekler:

- Yalnızca pozitif sayısal ve benzersiz kullanıcı kimliklerini kabul eder.
- İstek başına en fazla 100 kimlik taşır.
- Daha büyük birleşimleri 100'lük gruplar halinde işler.
- Ham `last_activity_at` veya kesin bağlantı zamanını döndürmez.

Görünür hesap cevabı en az `visible`, `is_online`, durum etiketi, yuvarlatılmış göreli son görülme ve olay sürümünü içerir. Gizli ve geçersiz kimlikler aynı sınırlı cevabı üretir.

## Veri Akışı

### İlk yükleme ve abonelik

1. Profil, yorum veya mesaj yüzeyi DOM'da bulunan kullanıcı kimliklerini koordinatöre kaydeder.
2. Her sekme kendi abonelik kümesini sekmeler arası kanalda duyurur.
3. Lider sekme canlı sekmelerin birleşimini hesaplar ve fark tabanlı abonelik güncellemesini WebSocket sunucusuna gönderir.
4. Sunucu kimlikleri doğrular, hesap görünürlük filtresini uygular ve başlangıç özetini döndürür.
5. Lider özeti diğer sekmelere dağıtır; her yüzey yalnızca izlediği kullanıcıların DOM'unu günceller.

### Çevrimiçi geçişi

1. Kullanıcının ilk doğrulanmış WebSocket bağlantısı açılır.
2. Varsa bekleyen çevrimdışı zamanlayıcısı iptal edilir.
3. Sunucu yeni bir monoton olay sürümü üretir.
4. Kullanıcıya abone uygun bağlantılara `presence_changed` olayı gönderilir.
5. İstemciler daha eski sürümleri yok sayar ve görünür yüzeyleri anında yeşile geçirir.

### Çevrimdışı geçişi ve tolerans

1. Kullanıcının son WebSocket bağlantısı kapanır.
2. Sunucu 30 saniyelik çevrimdışı zamanlayıcısı başlatır.
3. Bu sürede geçerli bağlantı açılırsa zamanlayıcı iptal edilir ve çevrimdışı olayı gönderilmez.
4. Süre sonunda hâlâ bağlantı yoksa yeni sürümlü çevrimdışı olayı yayımlanır.

Bu tolerans sayfa yenileme, cihazın kısa süreli ağ değişimi ve lider sekme devri sırasında kırmızı/yeşil titreşimi önler.

### Abonelik daraltma

Sekme kapanması, sayfa içeriğinin değişmesi veya bir bileşenin kaldırılması kendi aboneliğini düşürür. Lider yeni birleşimi hesaplayıp farkı sunucuya gönderir. Süresi dolmuş sekmeler 45 saniyelik mevcut peer TTL sonrasında birleşimden çıkarılır.

## Bağlantı ve Geri Çekilme Politikası

WebSocket bağlıyken düzenli yoğun presence sorgusu yapılmaz. Beş dakikada bir seyrek HTTP uzlaştırması, hesap görünürlüğü ve olası kaçırılmış değişiklikler için kullanılabilir.

WebSocket kullanılamadığında lider sekme toplu HTTP yedeğini şu düzenle çalıştırır:

- Başarılı durumda: 60 saniye
- İlk ardışık hatadan sonra: 120 saniye
- Sonraki ardışık hatalarda: en fazla 300 saniye
- İlk başarılı yanıtta: tekrar 60 saniye

Sayfa görünür değilken gereksiz sorgu yapılmaz. Görünür hale gelince gecikmiş sorgu hemen çalışır. Başarısız sorgu mevcut durumu aniden çevrimdışı yapmaz; son bilinen değer korunur ve durum ancak yetkili bir WebSocket/HTTP cevabıyla değiştirilir.

WebSocket'in kendi yeniden bağlanma gecikmesi mevcut sınırlı üstel geri çekilmeyi kullanır. Başarılı bağlantıdan sonra gecikme sıfırlanır ve tam abonelik yeniden gönderilir.

## Durum Önceliği ve Sürümleme

- Sağlıklı WebSocket özeti veya olayı kesin kaynak olarak HTTP tahmininden üstündür.
- WebSocket yokken HTTP, mevcut beş dakikalık `last_activity_at` eşiğiyle yaklaşık durum üretir.
- Her WebSocket bağlantı nesli benzersiz sunucu örneği/bağlantı kimliği taşır; o nesil içindeki olaylar monoton sıra numarası alır.
- Her HTTP cevabı sunucunun gözlem zamanını taşır; istemci istek sırasını da yerel olarak izler.
- Aynı bağlantı neslindeki daha düşük sıralı olaylar ve daha yeni HTTP isteğinden önce başlamış gecikmiş cevaplar uygulanmaz.
- Yeniden bağlantıdan alınan ilk tam özet yeni nesil için yetkilidir; önceki bağlantıdan kanalda kalmış olaylar uygulanmaz.
- Kaynak değişiminde ilk yetkili özet önceki kaynağın durumunu kontrollü biçimde değiştirir.

Sürüm bilgisi kullanıcıya kesin bağlantı zamanı göstermek için kullanılmaz; yalnızca sıralama ve bayat olay engelleme içindir.

## Arayüz Davranışı

### Ortak durum bileşeni

Tüm yüzeyler ortak işaretleme, sınıf adları ve davranış kurallarını kullanır:

- Çevrimiçi: yeşil nokta ve “Çevrimiçi” metni
- Çevrimdışı: kırmızı nokta ve “Çevrimdışı” metni
- Gizli: nokta, tooltip, son çevrimiçi metni ve yer tutucu yok

Durum yalnızca renkle anlatılmaz. Nokta odaklanabilir bir açıklamaya, uygun erişilebilir ada ve klavye davranışına sahiptir. `prefers-reduced-motion` etkinse hareketli vurgu kullanılmaz.

### Masaüstü ve klavye

Fareyle noktanın üzerine gelindiğinde veya klavyeyle odaklanıldığında “Çevrimiçi” ya da “Çevrimdışı” tooltip'i açılır. Tooltip, çevredeki profil bağlantısının kullanımını engellemez.

### Mobil dokunma

- Yalnızca durum noktasına dokunmak profil bağlantısına gitmez.
- Dokunma tooltip'i açar.
- Tooltip 2,5 saniye sonra otomatik kapanır.
- Sayfanın başka bir yerine dokunmak tooltip'i hemen kapatır.
- Avatarın ve kullanıcı kartının diğer alanları normal profil bağlantısı davranışını korur.
- Aynı anda yalnızca bir presence tooltip'i açık kalır.

### Profil metni

Profilde “Üyelik Süresi” satırının altında:

- Kullanıcı bağlıysa “Çevrimiçi” gösterilir.
- Kullanıcı bağlı değilse “Son çevrimiçi: …” şeklinde yuvarlatılmış göreli değer gösterilir.
- Hesap presence için uygun değilse satır bütünüyle kaldırılır.

Kesin bağlantı saati ve ham aktivite zamanı istemciye açılmaz.

## Güvenlik

- WebSocket kullanıcısı PHP oturumu ve mevcut cookie doğrulamasıyla belirlenir; sorgu parametresindeki kimliğe tek başına güvenilmez.
- İstemci kendi çevrimiçi durumunu veya başka kullanıcının durumunu yazamaz.
- Mesaj türü, JSON yapısı, kimlik sayısı ve kimlik formatı sunucuda doğrulanır.
- Bağlantı başına abonelik kümesi ve mesaj sıklığı sınırlandırılır.
- HTTP endpoint'i proje hız sınırlaması ve kısa süreli sunucu önbelleği kullanır.
- SQL işlemleri parametreli ve toplu yürütülür; kullanıcı başına N+1 sorgu üretilmez.
- Gizli, geçersiz ve var olmayan kimlikler dışarıdan ayırt edilemeyecek sınırlı presence cevabı alır.
- Beklenmeyen payload'lar güvenli biçimde reddedilir ve hassas veri içermeden loglanır.

## Hata Davranışı

- Presence hatası profil, yorum veya mesaj işlevlerini bozmaz.
- WebSocket kopması tüm kullanıcıları çevrimdışı yapmaz.
- HTTP hatası mevcut noktaları silmez veya kırmızıya çevirmez.
- Geçersiz tek bir kullanıcı kimliği bütün toplu cevabı bozmaz.
- Sekmeler arası kanal kullanılamazsa mevcut `localStorage` yedeği kullanılır.
- Her iki koordinasyon yöntemi de kullanılamazsa sekme kendi bağlantısını güvenli geri çekilmeyle çalıştırabilir; işlev korunur, yalnızca tek bağlantı optimizasyonu kaybedilir.
- WebSocket süreci yeniden başladığında istemci abonelikleri otomatik yeniden kurar.

## Test Stratejisi

### Sunucu testleri

- İlk bağlantının anında çevrimiçi olay üretmesi.
- Aynı kullanıcının ikinci bağlantısının yinelenen geçiş üretmemesi.
- Son bağlantı kapandığında 30 saniyeden önce çevrimdışı olmaması.
- 30 saniye içinde yeniden bağlantının zamanlayıcıyı iptal etmesi.
- Süre sonunda tek çevrimdışı olay üretilmesi.
- Olayların yalnızca ilgili abonelere ulaşması.
- 100 kimlik sınırı, bozuk JSON, yinelenen ve negatif kimliklerin güvenli işlenmesi.
- Aktif olmayan, yasaklı ve silinmiş hesapların özette ve olayda gizlenmesi.
- HTTP yedeğinde 299 saniyenin çevrimiçi, 300 saniyenin çevrimdışı sayılması.

### İstemci testleri

- Birden çok sekmede yalnızca bir lider ağ hattı bulunması.
- Sekmelerin kullanıcı kümelerinin doğru birleştirilmesi ve daraltılması.
- Lider kapanınca diğer sekmenin görevi ve abonelikleri devralması.
- 60 → 120 → 300 saniyelik geri çekilme ve başarıda sıfırlama.
- Görünmeyen sayfada sorgunun durması, dönüşte hemen yenilenmesi.
- Eski sürümlü WebSocket ve HTTP cevaplarının yok sayılması.
- WebSocket hatasında son bilinen durumun korunması ve HTTP'ye geçiş.
- Gizli hesap sonucunda bütün presence öğelerinin DOM'dan kaldırılması.

### Arayüz ve tarayıcı testleri

- Profil, konu yorumları, iç içe yanıtlar, mesaj listesi ve mesaj başlığının aynı olaya birlikte tepki vermesi.
- Masaüstünde hover/focus tooltip'i ve klavye erişilebilirliği.
- Mobilde noktaya dokunmanın yönlendirmeyi engellemesi.
- Mobil tooltip'in 2,5 saniye sonra ve dış dokunmada kapanması.
- Avatarın diğer bölümlerinin profile yönlendirmeyi sürdürmesi.
- Dar ekran, açık/koyu tema ve azaltılmış hareket tercihinin görsel kontrolü.

### Canlıya geçiş doğrulaması

- Değişen PHP dosyalarında sözdizimi kontrolü.
- JavaScript sözdizimi ve proje varlık derlemesi.
- Mevcut migration koruma/şema doğrulamaları.
- WebSocket kapalı, yeniden başlıyor ve erişilebilir senaryoları.
- Gerçek tarayıcıda iki sekme ve iki ayrı oturumla uçtan uca presence geçişi.

## Dağıtım ve Geri Alma

Bu tasarım yeni zorunlu veritabanı alanı veya Redis servisi eklemez. Mevcut `last_activity_at` migration'ı HTTP yedeğini destekler.

Dağıtım sırası:

1. Sunucu tarafı görünürlük ve toplu HTTP desteği.
2. Geriye uyumlu WebSocket sunucu mesajları.
3. İstemci koordinatörü ve ortak UI adaptörleri.
4. Derlenmiş varlıklar.
5. WebSocket servisinin kontrollü yeniden başlatılması.

Yeni istemci WebSocket mesajlarını alamazsa HTTP yedeğiyle çalışmaya devam eder. Geri alma sırasında istemci varlıkları önceki sürüme döndürülebilir; sunucudaki yeni mesaj türleri kullanılmadan etkisiz kalır. Presence ikincil özellik olduğundan hiçbir hata ana profil, yorum veya mesaj akışını durdurmamalıdır.

## Kabul Ölçütleri

- Aynı tarayıcıdaki açık sekmeler normal koşullarda tek presence WebSocket/HTTP hattını paylaşır.
- WebSocket bağlıyken durum değişikliği sayfa yenilenmeden bütün mevcut yüzeylere ulaşır.
- Son bağlantı kapandıktan sonraki 30 saniye içinde çevrimdışı titreşimi oluşmaz.
- WebSocket kesintisinde sorgu sıklığı 60, 120 ve en fazla 300 saniyeye geri çekilir; başarıda normale döner.
- Yasaklı, pasif ve silinmiş hesaplarda presence arayüzü tamamen görünmezdir.
- Mobil noktaya dokunma yalnızca 2,5 saniyelik durum tooltip'ini açar; profil bağlantısının geri kalanı çalışır.
- Gecikmiş olaylar yeni durumu geriye çeviremez.
- WebSocket veya presence endpoint'i arızası ana kullanıcı akışlarını bozmaz.
