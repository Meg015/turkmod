# Presence Single Tooltip Implementation Plan

1. `scripts/verify-public-presence.js` ve `scripts/verify-user-presence.php` içine presence noktalarının native `title` üretmediğini, ortak UI kodunun eski `title` değerini temizlediğini ve `aria-label`/`data-presence-tooltip` sözleşmesini koruduğunu doğrulayan regresyon kontrolleri ekle.
2. `assets/js/ui-foundation.js` içindeki ortak presence uygulayıcısının `title` yazmasını kaldır ve mevcut `title` özelliğini her durum güncellemesinde temizle.
3. `assets/js/topic-comments.js`, `themes/turkmod/profile-sidebar.tpl` ve `includes/partials/profile-sidebar.php` içindeki başlangıç presence işaretlerinden native `title` niteliğini kaldır; erişilebilir ad ve özel tooltip verisini koru.
4. Mesaj listesi ve aktif konuşma işaretlerinin aynı ortak uygulayıcıdan geçtiğini ve native `title` taşımadığını kaynak/DOM testleriyle doğrula.
5. Profil, yorum ve mesaj presence CSS kurallarında `cursor: help` değerini `cursor: default` ile değiştir; diğer yardım ikonlarının cursor davranışına dokunma.
6. `npm run build` ile derlenmiş assetleri yenile; PHP lint, presence doğrulama betikleri, JavaScript syntax kontrolü ve `git diff --check` çalıştır.
7. Gerçek tarayıcıda yorum, profil ve mümkünse mesaj presence noktalarını denetle: `title` yok, computed cursor `default`, tek özel tooltip var, tıklama yönlendirmiyor ve 2,5 saniye sonra kapanıyor. Mobil viewport testinden sonra viewport'u sıfırla.
