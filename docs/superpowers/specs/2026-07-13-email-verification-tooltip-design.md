# E-posta Doğrulama Tooltip Tasarımı

## Amaç

Admin panelindeki `E-posta Doğrulama Sistemi` ve `Giriş İçin Doğrulama Zorunlu` ayarlarının ne yaptığını, birbirleriyle ilişkisini ve kapalı olduklarında oluşan davranışı açık biçimde anlatmak.

## Kapsam

Yalnızca `admin/helpers.php` içindeki iki ayarın tooltip metinleri değiştirilecek. Ayar anahtarları, varsayılan değerler, kayıt biçimi ve çalışma davranışı değiştirilmeyecek.

## Metinler

### E-posta Doğrulama Sistemi

> Yeni kayıt olan kullanıcılara e-posta doğrulama bağlantısı gönderir. Kullanıcı bağlantıya tıkladığında e-posta adresi doğrulanmış sayılır. Bu ayar kapalıysa doğrulama e-postası gönderilmez.

### Giriş İçin Doğrulama Zorunlu

> E-posta adresini doğrulamayan kullanıcıların giriş yapmasını engeller. Bu özelliğin çalışması için E-posta Doğrulama Sistemi de açık olmalıdır. Kapalıysa kullanıcılar e-posta adresini doğrulamadan giriş yapabilir.

## Doğrulama

- İki ayarın da `tooltip` tanımı bulunduğu kontrol edilecek.
- Admin ayar tanımlarının PHP söz dizimi doğrulanacak.
- Ayar adlarının ve mevcut davranış anahtarlarının değişmediği kontrol edilecek.
