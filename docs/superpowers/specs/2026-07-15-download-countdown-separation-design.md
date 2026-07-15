# İndirme Geri Sayımlarını Ayırma Tasarımı

## Amaç

Konu içindeki indirme kartı geri sayımı ile dış bağlantı yönlendirme sayfasındaki geri sayımı admin panelinden birbirinden bağımsız yönetmek.

## Yönetim Paneli

`Admin Paneli > Gelişmiş Ayarlar > İndirme Yöneticisi > Mevcut Ayarlar` bölümünde:

- Mevcut alan `Konu İçi Geri Sayım Süresi (sn)` olarak netleştirilir ve konu içindeki indirme kartı geri sayımını yönetmeye devam eder.
- Mevcut alanın hemen altına `Yönlendirme Sayfası Geri Sayım Süresi (sn)` alanı eklenir.
- Yeni alanın varsayılan değeri `5` saniyedir.
- Her iki alanda da `0`, ilgili akışın beklemeden devam etmesi anlamına gelir.
- Her iki alan en fazla `300` saniye kabul eder; admin kayıt katmanı sınır dışı değerleri güvenli aralığa çeker.

## Ayar ve Veri Akışı

Yeni ayar anahtarı `download_redirect_countdown_seconds` olacaktır.

- `download_countdown_seconds` yalnızca konu detayındaki indirme kartlarına aktarılır.
- `download_redirect_countdown_seconds` yalnızca yönlendirme sayfasındaki sayaç, buton geri sayımı ve otomatik yönlendirme zamanlamasına aktarılır.
- Yeni ayar veritabanında henüz bulunmuyorsa yönlendirme sayfası `5` saniye varsayılanını kullanır; eski ayarın değerini devralmaz.
- Ayarlar mevcut genel admin ayarı kaydetme mekanizmasıyla saklanır; ayrı tablo veya şema değişikliği gerekmez.

## Çalışma Davranışı

Yönlendirme sayfası kapalıysa yeni sayaç ayarı kullanılmaz ve mevcut doğrudan yönlendirme davranışı korunur. Otomatik yönlendirme kapalıysa sayaç alanı gösterilmez ve manuel devam butonunun mevcut davranışı korunur; süre yalnızca bu sayfaya ait yeni ayardan okunur.

Negatif veya geçersiz değerler çalışma katmanında en az `0` olacak şekilde güvenli hale getirilir. Admin alanı mevcut sayı alanı doğrulama ve kayıt kurallarını kullanarak değeri `0-300` aralığında tutar.

## Doğrulama

- Admin panelinde yeni alanın mevcut geri sayım alanının hemen altında görünmesi ve kaydedilebilmesi.
- Konu sayacı ile yönlendirme sayacı farklı değerlere ayarlandığında her sayfanın kendi değerini kullanması.
- Her iki ayarın ayrı ayrı `0` değerinde beklemesiz çalışması.
- Yeni ayar kaydı bulunmadığında yönlendirme sayfasının `5` saniye kullanması.
- Yönlendirme sayfası veya otomatik yönlendirme kapalıyken mevcut davranışların bozulmaması.

## Kapsam Dışı

Sayaç metinleri, indirme erişim kilitleri, indirme sayacı istatistikleri ve yönlendirme sayfasının görsel tasarımı değiştirilmeyecektir.
