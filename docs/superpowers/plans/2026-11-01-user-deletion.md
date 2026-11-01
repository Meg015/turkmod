# Kalıcı Kullanıcı Silme Implementation Plan

**Goal:** Admin Kullanıcı Yönetimi sayfasında içerikleri koruyarak veya silerek kalıcı hesap silme.

**Architecture:** `UserDeletionService` veritabanı transactionını ve ilişkileri yönetir. Kullanıcı yardımcı fonksiyonu commit sonrasındaki dosya/önbellek temizliğini yapar. Mevcut işlem menüsü ve ortak modal denetleyicisi kullanılır.

**Tech Stack:** PHP 8.1+, PDO MySQL/SQLite, vanilla JavaScript.

**Spec:** `docs/superpowers/specs/2026-11-01-user-deletion-design.md` (kullanıcı iki seçenekli akışı onayladı).

## Global Constraints

- Gerçek kullanıcı silinmez; doğrulama geçici veritabanlarında yapılır.
- `users.delete`; purge için ayrıca `topics.delete`, `comments.delete` gerekir.
- Varsayılan `preserve`; diğer seçenek `purge`.
- Hesap fiziksel olarak silinir; sistem hesabı kapalı ve listelerde gizlidir.
- Başarısız işlem kısmi veri kaybına yol açmaz.

## Review Focus

- Başka kullanıcının silinen yoruma yanıtı korunur.
- Koruma modunda yorum ekleri FK kaskadıyla kaybolmaz.
- İç içe transaction hatası çağıranın değişikliklerini kaybetmez.
- Olmayan modül tabloları ve FK olmayan referanslar doğru ele alınır.
- Başka kayıtta kullanılan veya uploads dışındaki dosyalar silinmez.

## Task 1: Veri servisi ve anlamlı regresyon testleri

**Files:** Create `includes/src/Engine/Users/UserDeletionService.php`, `includes/src/Engine/Users/UserDeletionFiles.php`, `tests/user-deletion-regression.php`, `tests/user-deletion-mysql-regression.php`; modify `includes/src/Engine/Users/Support/users-helpers.php`.

**Interfaces:** `UserDeletionService::delete(PDO $pdo, int $userId, int $actorId, string $mode, string $confirmation, string $reason): array` işlem sayıları ve silinebilir dosya yollarını döndürür. `usersDeletePermanently(...)` servis çağrısını ve commit sonrası temizliği bağlar.

- [x] SQLite fixture ile preserve/purge, yetki/kimlik doğrulama, sistem hesabı, FK ve sayaç testlerini yaz ve başarısız çalıştığını doğrula.
- [x] Servisi, transaction/savepoint ve açık referans temizliğini uygula.
- [x] Hata tetikleyicisiyle kısmi silmenin geri alındığını doğrula.
- [x] Yardımcı fonksiyona dosya/önbellek temizliğini ekle; gizli sistem hesabını listeden çıkar.

## Task 2: Admin işlem akışı

**Files:** Modify `admin/users.php`, `admin/users-tabs/users.php`, `admin/assets/users-tab.js`, `admin/assets/users-page.css`; create `admin/partials/user-delete-modal.php`.

**Interfaces:** POST `action=delete_user`, `user_id`, `content_mode`, `confirm_username`, `reason`, `confirm_delete=1`, `_token`. Yanıt mevcut JSON/flash sözleşmesini kullanır.

- [x] Ayrı silme yetkisini mevcut edit yetkisi kapısından bağımsız kontrol et.
- [x] Menüye yetkili hesaplar için silme ekle; kendi hesabında gizle.
- [x] Varsayılan preserve, purge etkisi, gerekçe, kullanıcı adı ve onay alanlarıyla modal ekle.
- [x] Ortak modal, AJAX, hata bildirimi ve çift gönderim engelini bağla.

## Task 3: Son doğrulama

- [x] `php tests/user-deletion-regression.php`: 91 kontrol geçti.
- [x] Değişen PHP dosyalarında `php -l`, JS dosyasında `node --check` çalıştır.
- [x] MySQL geçici fixture üzerinde gerçek FK davranışıyla iki modu ve rollback'i doğrula: 26 kontrol geçti.
- [x] Diff'i incele; ilgisiz çalışma alanı dosyalarının değişmediğini doğrula.
- [x] Gerçek modal/CSS/JS ile izole tarayıcı sayfasında varsayılan seçenek, yetki görünümü, güncel CSRF, doğrulama, çift gönderim, hata/başarı ve mobil kaydırma akışlarını doğrula.

## Task 4: Takip incelemesi

- [x] Eski toplu silme yolunu kapat; düzenleme yetkisiyle silme yapılamadığını doğrula.
- [x] Sistem hesabını doğrudan düzenleme ve kullanıcı ayrıntısı sorgularından çıkar.
- [x] Silinen etiketlemelerden sonra yorumların etiketleme sayaçlarını yenile.
- [x] İçeriklerde ve sabit sayfa OG alanında kullanılan ortak dosyaları koru; göreli URL sorgu ve fragment eklerini denetle.
- [x] Sistem hesabının ilk oluşturulmasında benzersiz e-posta çakışmasını ele al; mevcut hesabın yeniden kullanımını doğrula.

## Task 5: Kompakt silme penceresi ve sonuç bildirimi

**Files:** `admin/users.php`, `admin/assets/users-tab.js`, `admin/partials/user-delete-modal.php`, `admin/assets/users-page.css`.

**Interfaces:** AJAX silme başarısı mevcut JSON sözleşmesini kullanır; sonuç sonraki GET isteğinde bir defa tüketilen sunucu flash bildirimiyle gösterilir.

- [x] Başarı popupını kaldır; AJAX ve normal POST sonuçlarını sayfa içi başarı/uyarı bildirimiyle göster ve sıfır içerik sayılarını mesajdan çıkar.
- [x] Silme penceresini 480 px genişlik, sıkı seçenek grubu ve kompakt alanlarla düzenle.
- [x] İzole tarayıcı fixture'ında ikinci popupın açılmadığını, doğrulama/hata davranışını ve masaüstü/mobil ölçülerini kontrol et; PHP/JS sözdizimi ve diff kontrolünü çalıştır.

## Task 6: Ortak footer ile bildirim tekrarı

- [x] Sayfada gösterilen başarı, uyarı ve hata değişkenlerini tüket; ortak footer aynı mesajı toast olarak tekrar göstermesin.
- [x] Gerçek sayfa bildirim şablonu ve footer ile üç bildirim tonunu, bağımsız bilgi toastını ve sonraki istekte tüketimi doğrula: 9 kontrol geçti.
- [x] SQLite/MySQL veri silme testlerini yeniden çalıştır: 117 kontrol geçti. PHP/JS sözdizimi ve ilgili diff kontrolü temiz.
