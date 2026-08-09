# Kullanıcı Çevrimiçi Durumu Tasarımı

**Tarih:** 2026-08-09
**Durum:** Onaylandı

## Amaç

Kullanıcının yakın zamanda etkin olup olmadığını profil sayfasında ve konu yorumlarında tutarlı, erişilebilir ve düşük maliyetli biçimde göstermek. Son etkinliği son 5 dakika içinde olan kullanıcı çevrimiçi, diğer kullanıcılar çevrimdışı kabul edilir.

## Kapsam

- Giriş yapmış kullanıcının son etkinlik zamanını normal web ve API isteklerinde takip etmek.
- Public kullanıcı profilinde, “Üyelik Süresi” satırının hemen altında “Son Çevrimiçi” bilgisini göstermek.
- Konu yorumlarındaki kullanıcı avatarlarında çevrimiçi/çevrimdışı durum noktası göstermek.
- Ana yorumlar ile iç içe yanıtların aynı durum bileşenini kullanmasını sağlamak.
- Durum göstergelerini renk dışında metin, klavye odağı ve erişilebilir adla desteklemek.

Bu çalışma WebSocket tabanlı gerçek zamanlı presence, ayrı heartbeat endpoint’i, çevrimiçi kullanıcı listesi ve kullanıcıya özel görünürlük ayarı içermez.

## Presence Kuralı

- Kaynak alan mevcut `users.last_activity_at` sütunudur.
- Son etkinlik zamanı sunucu saatine göre değerlendirilir.
- `last_activity_at`, içinde bulunulan zamandan 300 saniyeden daha az önceyse kullanıcı çevrimiçidir.
- Tam 300 saniye ve daha eski etkinlik çevrimdışı kabul edilir.
- Boş, okunamayan veya geleceğe taşmış geçersiz değer güvenli biçimde çevrimdışı kabul edilir.
- Profil metni çevrimiçi kullanıcı için “Şimdi çevrimiçi”, çevrimdışı kullanıcı için mevcut tarih yardımcılarıyla “12 dakika önce”, “3 saat önce” benzeri göreli değer üretir.
- Tam tarih ve saat, gösterge üzerindeki açıklama/başlık metninde bulunur.

## Etkinlik Takibi

Global oturum başlangıç akışında, kimliği doğrulanmış kullanıcı için `last_activity_at` güncellenir. Yazma işlemi oturumdaki son veritabanı güncelleme zamanı kullanılarak kullanıcı başına en fazla 60 saniyede bir yapılır.

Güncelleme parametreli ve yalnızca geçerli, aktif oturumun kullanıcı kimliğine yönelik olur. Veritabanı güncellemesi başarısız olursa sayfa veya API isteği kesilmez; hata mevcut uygulama kayıt altyapısına gönderilir. Böylece presence ikincil bir özellik olarak ana kullanıcı akışlarını bozmaz.

## Ortak Sunum Modeli

Presence hesabı tek bir ortak yardımcı katmanda tutulur. Bu katman en az aşağıdaki değerleri üretir:

- `is_online`: beş dakikalık kurala göre boolean durum.
- `status_label`: “Çevrimiçi” veya “Çevrimdışı”.
- `relative_label`: “Şimdi çevrimiçi” ya da son etkinliğin göreli zamanı.
- `exact_label`: geçerli tarih varsa tam tarih-saat açıklaması.

Profil ve yorum akışları aynı hesaplama kuralını kullanır. Eşik, etiketler veya tarih davranışı görünüm dosyalarında tekrar tanımlanmaz.

## Profil Deneyimi

Public profil verisi hazırlanırken kullanıcının `last_activity_at` değeri ortak profil bağlamına eklenir. Profil yan çubuğunda “Üyelik Süresi” satırının hemen ardından yeni bir “Son Çevrimiçi” satırı yer alır.

Satırda:

- Duruma göre yeşil veya kırmızı küçük işaret,
- “Son Çevrimiçi” etiketi,
- Çevrimiçiyse “Şimdi çevrimiçi”, değilse göreli son etkinlik değeri,
- Geçerli tarih varsa tam tarih-saat açıklaması bulunur.

Son etkinlik kaydı yoksa değer “Bilinmiyor” olarak gösterilir ve durum işareti çevrimdışı görünür. Yerleşim mevcut profil meta satırlarının ikon, boşluk ve tipografi düzenini korur.

## Yorum Deneyimi

Yorum API’sindeki ana yorum ve yanıt sorgularına `u.last_activity_at` aynı sorgu içinde eklenir. Kullanıcı başına ek sorgu çalıştırılmaz. Biçimlendirilmiş yorum yanıtı, istemcinin yalnızca görünüm için ihtiyaç duyduğu `is_online` ve `presence_label` alanlarını içerir; ham son etkinlik tarihi dışarı açılmaz.

Durum göstergesi avatarın sağ alt köşesinde yaklaşık 10 piksel çapında konumlanır:

- Çevrimiçi gösterge canlı yeşildir, kart zeminine uyumlu ince bir dış halkası ve hafif nabız efekti vardır.
- Çevrimdışı gösterge yumuşatılmış kırmızıdır ve animasyonsuzdur.
- Fareyle üzerine gelindiğinde veya klavyeyle odaklandığında koyu bir bilgi balonunda “Çevrimiçi” ya da “Çevrimdışı” görünür.
- Gösterge odaklanabilir, erişilebilir adı bulunur ve durum yalnızca renkle aktarılmaz.
- `prefers-reduced-motion: reduce` etkinse nabız animasyonu uygulanmaz.

Tema istemci şablonu ve JavaScript yedek HTML üretimi aynı işaretlemeyi kullanır. Böylece şablon bulunamadığında da görünüm ve erişilebilirlik korunur. Ana yorumlar ile yanıtlar aynı render fonksiyonundan geçer.

## Veri Akışı

1. Kimliği doğrulanmış bir istek global başlangıç akışına ulaşır.
2. Oturumdaki son presence yazımının üzerinden en az 60 saniye geçtiyse `users.last_activity_at` sunucu zamanıyla güncellenir.
3. Profil sayfası kullanıcı kaydındaki son etkinliği ortak presence sunum modeline dönüştürür.
4. Yorum API’si yorum sahibiyle birlikte son etkinliği seçer, ortak kuralla boolean durum üretir ve sınırlı presence alanlarını JSON’a ekler.
5. Profil ve yorum görünümü durum sınıfını, metnini, tooltip’ini ve erişilebilir etiketini üretir.

## Hata ve Sınır Davranışı

- Presence güncellemesinin başarısız olması HTTP yanıtını değiştirmez.
- Kullanıcı kaydı veya tarih bulunamazsa gösterge çevrimdışı olur; profil metni “Bilinmiyor” gösterir.
- Silinmiş ya da anonim yorum sahibinde gösterge çevrimdışı kabul edilir.
- İstemci bilinmeyen/eksik presence verisini çevrimdışı varsayar.
- Durum sonraki normal yorum yenilemesinde güncellenebilir; bu tasarım ayrıca heartbeat veya anlık push bağlantısı açmaz.

## Performans ve Güvenlik

- Etkinlik yazımı en fazla dakikada bir yapılarak yoğun gezinmede yazma yükü sınırlandırılır.
- Yorumlarda presence mevcut toplu sorgulara katılır; N+1 sorgu oluşturulmaz.
- Kullanıcı kimliği yalnızca doğrulanmış oturumdan alınır ve sorgular parametrelidir.
- JSON yanıtı ham etkinlik zamanını yayımlamaz.
- HTML öznitelikleri ve metinleri mevcut kaçış yardımcılarıyla güvenli biçimde üretilir.

## Doğrulama

- Ortak presence hesabı için 299 saniye çevrimiçi, 300 saniye çevrimdışı sınırı doğrulanır.
- Boş, geçersiz ve gelecekteki tarihler çevrimdışı sonucunu vermelidir.
- Etkinlik yazımının aynı oturumda 60 saniye dolmadan tekrarlanmadığı doğrulanır.
- Public profilde yeni satırın “Üyelik Süresi” altında olduğu ve çevrimiçi/çevrimdışı metinlerinin doğru üretildiği kontrol edilir.
- Yorum API’sinde ana yorumlar ve yanıtlar için presence alanları doğrulanır.
- Yorum şablonu ve JavaScript yedek renderer’ında nokta, tooltip, klavye odağı ve erişilebilir ad kontrol edilir.
- Masaüstü, mobil ve iç içe yanıt yerleşimleri görsel olarak incelenir.
- Azaltılmış hareket tercihinde nabız animasyonunun kapandığı doğrulanır.
- Değişen PHP dosyaları sözdizimi kontrolünden, JavaScript/CSS varlıkları proje derlemesinden geçirilir.

## Kabul Ölçütleri

- Son etkinliği 5 dakikadan yeni olan kullanıcı tüm ilgili yerlerde çevrimiçi görünür.
- Profilde “Son Çevrimiçi” satırı “Üyelik Süresi” satırının hemen altındadır.
- Çevrimdışı profilde göreli son etkinlik, açıklamada ise tam tarih-saat gösterilir.
- Tüm konu yorumlarında ve yanıtlarında yeşil/kırmızı durum noktası görünür.
- Nokta fare ve klavye etkileşiminde metinsel durumu açıklar.
- Presence takibi ana istekleri bozmaz ve kullanıcı başına dakikada birden fazla veritabanı yazımı üretmez.
- Yorum listeleme sorguları kullanıcı başına ek sorgu çalıştırmaz.
