# Presence Single Tooltip Design

## Amaç

Profil, konu yorumları, mesaj listesi ve aktif mesaj başlığındaki çevrimiçi/çevrimdışı noktalarında aynı anda görünen tarayıcı `title` balonu ile özel tasarımlı tooltip çakışmasını kaldırmak. Her presence noktası masaüstü, klavye ve mobil dokunmada yalnızca bir tooltip gösterecektir.

## Kapsam

- Tema ve fallback profil presence işaretleri.
- Dinamik oluşturulan yorum presence işaretleri.
- Mesaj listesi ve aktif konuşma başlığındaki presence işaretleri.
- Bütün yüzeyleri güncelleyen ortak `publicPresenceUI` davranışı.
- Kaynak ve derlenmiş asset doğrulamaları.

Presence renkleri, beş dakikalık çevrimiçi eşiği, WebSocket/HTTP koordinasyonu ve tooltip'in mevcut görsel tasarımı değişmeyecektir.

## Seçilen Yaklaşım

Tek yetkili tooltip kaynağı özel UI tooltip'i olacaktır. Presence durumu:

- Görsel metin için `data-presence-tooltip`,
- Ekran okuyucu ve erişilebilir ad için `aria-label`,
- Durum sınıfı için `is-online` veya `is-offline`

alanlarında tutulacaktır. Presence işaretlerinde native tarayıcı balonu oluşturan `title` kullanılmayacaktır.

Yalnızca şablonlardan `title` kaldırmak yeterli değildir; ortak UI kodu her presence güncellemesinde yeniden `title` eklemektedir. Bu nedenle ortak uygulama fonksiyonu `title` yazmayı bırakacak ve eski ya da dışarıdan eklenmiş bir `title` değerini güvenli şekilde kaldıracaktır. Böylece ilk sunucu render'ı ile dinamik yorum/mesaj güncellemeleri aynı sözleşmeye uyar.

## Davranış

- Fareyle üzerine gelince yalnızca özel tooltip görünür.
- Presence noktasında `cursor: default` kullanılır; soru işareti veya el imleci gösterilmez.
- Klavyeyle odaklanınca aynı özel tooltip görünür.
- Enter, Space veya mobil dokunmada tooltip açılır ve 2,5 saniye sonra kapanır.
- Noktaya dokunmak/tıklamak çevresindeki profil veya konuşma bağlantısına yönlendirme yapmaz.
- Escape ve dışarı dokunma mevcut kapatma davranışını korur.
- Online/offline değişiminde `aria-label` ve özel tooltip metni birlikte güncellenir.

## Hata Dayanımı

Ortak UI kodu bir presence kökünde uygun nokta bulamazsa mevcut sessiz geri dönüşü korur. `title` temizleme işlemi standart DOM `removeAttribute` üzerinden yapılır ve presence güncellemesini engellemez. CSS veya JavaScript yüklenemediğinde `aria-label` erişilebilir adı korur; native tooltip'e geri dönüş yapılmaz.

## Doğrulama

- Kaynak taraması presence noktalarında `title` üretilmediğini doğrular.
- Kaynak ve computed-style kontrolleri profil, yorum ve mesaj presence noktalarında `cursor: default` kullanıldığını doğrular.
- Ortak UI testi presence uygulamasının `title` kaldırdığını doğrular.
- Profil, yorum ve mesaj DOM'larında `data-presence-tooltip` ve `aria-label` korunur.
- Gerçek tarayıcıda yorum noktası hover/tıklama akışında yalnızca özel tooltip açılır.
- Mobil viewport'ta dokunma, yönlendirmeyi engelleme ve 2,5 saniyelik kapanma korunur.
- Build, PHP lint, odaklı presence testleri ve `git diff --check` geçer.

## Tamamlanma Ölçütleri

- Hiçbir presence işaretinde native `title` bulunmaz.
- Hiçbir presence işareti soru işareti veya el imleci göstermez.
- Profil, yorumlar ve mesajların tamamında tek tooltip sözleşmesi kullanılır.
- Erişilebilirlik ve mobil davranışta gerileme oluşmaz.
- Derlenmiş assetler kaynak değişikliklerle birlikte yenilenir.
