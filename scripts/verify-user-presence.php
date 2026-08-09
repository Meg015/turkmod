<?php

declare(strict_types=1);

use App\Engine\UserActivity\UserPresence;
use App\Engine\UserActivity\UserPresenceLookup;
use App\Engine\Users\ProfilePresentation;
use App\Modules\Messages\Services\MessageService;

require_once dirname(__DIR__) . '/includes/autoloader.php';
require_once dirname(__DIR__) . '/includes/src/Engine/UserActivity/Support/helpers.php';

date_default_timezone_set('Europe/Istanbul');

function presenceAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$now = strtotime('2026-08-09 12:00:00');
$timezone = new DateTimeZone('Europe/Istanbul');
$presence = new UserPresence(static fn (): int => $now, $timezone);
$dateForAge = static fn (int $seconds): string => date('Y-m-d H:i:s', $now - $seconds);

$online = $presence->describe($dateForAge(299));
presenceAssert($online['is_online'] === true, '299 saniyelik etkinlik çevrimiçi olmalı.');
presenceAssert($online['relative_label'] === 'Şimdi çevrimiçi', 'Çevrimiçi profil etiketi hatalı.');

$boundary = $presence->describe($dateForAge(300));
presenceAssert($boundary['is_online'] === false, '300 saniyelik etkinlik çevrimdışı olmalı.');
presenceAssert($boundary['relative_label'] === '5 dakika önce', 'Sınırdaki göreli etiket hatalı.');
presenceAssert($boundary['exact_label'] === '09.08.2026 11:55', 'Tam tarih etiketi hatalı.');

$publicBoundary = $presence->describePublic('2026-07-20 11:15:00');
presenceAssert($publicBoundary['relative_label'] === '20.07.2026', 'Public presence kesin saati göreli etiketten çıkarmalı.');
presenceAssert($publicBoundary['exact_label'] === '', 'Public presence kesin tarih alanını açmamalı.');
presenceAssert($publicBoundary['title_label'] === 'Çevrimdışı', 'Public tooltip yalnızca durum etiketini taşımalı.');

$hour = $presence->describe($dateForAge(3 * 3600));
presenceAssert($hour['relative_label'] === '3 saat önce', 'Saatlik göreli etiket hatalı.');

$calendarCases = [
    ['2026-08-09 11:48:00', '12 dakika önce', 'Aynı gün dakika etiketi hatalı.'],
    ['2026-08-09 09:00:00', '3 saat önce', 'Aynı gün saat etiketi hatalı.'],
    ['2026-08-08 21:40:00', 'Dün 21:40', 'Önceki takvim günü etiketi hatalı.'],
    ['2026-08-07 23:55:00', '2 gün önce', 'İki günlük takvim etiketi hatalı.'],
    ['2026-08-03 00:05:00', '6 gün önce', 'Altı günlük takvim etiketi hatalı.'],
    ['2026-08-02 12:00:00', '02.08.2026 12:00', 'Yedi günlük tam tarih sınırı hatalı.'],
];
foreach ($calendarCases as [$activityAt, $expectedLabel, $failureMessage]) {
    presenceAssert($presence->describe($activityAt)['relative_label'] === $expectedLabel, $failureMessage);
}

$newYearNow = strtotime('2026-01-01 00:03:00');
$newYearPresence = new UserPresence(static fn (): int => $newYearNow, $timezone);
presenceAssert(
    $newYearPresence->describe('2025-12-31 23:57:00')['relative_label'] === 'Dün 23:57',
    'Yıl geçişindeki Dün etiketi hatalı.'
);

$newMonthNow = strtotime('2026-03-01 00:03:00');
$newMonthPresence = new UserPresence(static fn (): int => $newMonthNow, $timezone);
presenceAssert(
    $newMonthPresence->describe('2026-02-28 23:57:00')['relative_label'] === 'Dün 23:57',
    'Ay geçişindeki Dün etiketi hatalı.'
);

foreach ([null, '', '0000-00-00 00:00:00', 'geçersiz-tarih', date('Y-m-d H:i:s', $now + 1)] as $invalidValue) {
    $invalid = $presence->describe($invalidValue);
    presenceAssert($invalid['is_online'] === false, 'Geçersiz etkinlik çevrimdışı olmalı.');
    presenceAssert($invalid['has_activity'] === false, 'Geçersiz etkinlik kaydı var sayılmamalı.');
    presenceAssert($invalid['relative_label'] === 'Bilinmiyor', 'Geçersiz etkinlik etiketi Bilinmiyor olmalı.');
}

$lookupPdo = new PDO('sqlite::memory:');
$lookupPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$lookupPdo->exec('CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    status TEXT,
    is_banned INTEGER DEFAULT 0,
    deleted_at TEXT NULL,
    last_activity_at TEXT NULL
)');
$lookupInsert = $lookupPdo->prepare('INSERT INTO users (id, status, is_banned, deleted_at, last_activity_at) VALUES (?, ?, ?, ?, ?)');
$lookupInsert->execute([1, 'active', 0, null, $dateForAge(299)]);
$lookupInsert->execute([2, 'inactive', 0, null, $dateForAge(20)]);
$lookupInsert->execute([3, 'active', 1, null, $dateForAge(20)]);
$lookupInsert->execute([4, 'active', 0, '2026-08-09 11:00:00', $dateForAge(20)]);
$lookupInsert->execute([5, 'active', 0, null, '2026-07-20 11:15:00']);

$lookup = new UserPresenceLookup($presence);
$lookupResult = $lookup->lookup($lookupPdo, [5, 4, 3, 2, 1, 999, 1]);
presenceAssert(array_keys($lookupResult) === [1, 2, 3, 4, 5, 999], 'Presence kimlikleri benzersiz ve sıralı olmalı.');
presenceAssert(($lookupResult[1]['visible'] ?? false) === true, 'Aktif hesap presence için görünür olmalı.');
presenceAssert(($lookupResult[1]['is_online'] ?? false) === true, 'Aktif yakın tarihli hesap çevrimiçi olmalı.');
presenceAssert(($lookupResult[2]['visible'] ?? true) === false, 'Pasif hesap presence için tamamen gizli olmalı.');
presenceAssert(($lookupResult[3]['visible'] ?? true) === false, 'Yasaklı hesap presence için tamamen gizli olmalı.');
presenceAssert(($lookupResult[4]['visible'] ?? true) === false, 'Silinmiş hesap presence için tamamen gizli olmalı.');
presenceAssert(($lookupResult[999]['visible'] ?? true) === false, 'Bulunamayan hesap gizli hesapla aynı sonucu vermeli.');
presenceAssert(($lookupResult[5]['relative_label'] ?? '') === '20.07.2026', 'Eski public presence etiketi kesin saat açmamalı.');
presenceAssert(!str_contains(json_encode($lookupResult, JSON_UNESCAPED_UNICODE) ?: '', 'last_activity_at'), 'Toplu presence ham aktivite tarihi açmamalı.');

$overrideResult = $lookup->lookup($lookupPdo, [1], [1 => false]);
presenceAssert(($overrideResult[1]['is_online'] ?? true) === false, 'WebSocket kesin offline override değeri uygulanmalı.');
presenceAssert(($overrideResult[1]['status_label'] ?? '') === 'Çevrimdışı', 'Override etiketi kesin durumla uyumlu olmalı.');

$tooManyIdsRejected = false;
try {
    $lookup->normalizeIds(range(1, UserPresenceLookup::MAX_IDS_PER_BATCH + 1));
} catch (InvalidArgumentException) {
    $tooManyIdsRejected = true;
}
presenceAssert($tooManyIdsRejected, '100 kullanıcı kimliği sınırı uygulanmalı.');

$_SESSION = [];
$writes = 0;
$writer = static function (?PDO $pdo, int $userId) use (&$writes): void {
    presenceAssert($pdo === null, 'Doğrulama yazıcısı veritabanı kullanmamalı.');
    presenceAssert($userId === 42, 'Presence yazıcısına yanlış kullanıcı gönderildi.');
    $writes++;
};

presenceAssert(userPresenceTouchAuthenticated(null, 42, 1000, $writer), 'İlk presence yazımı çalışmalı.');
presenceAssert(!userPresenceTouchAuthenticated(null, 42, 1059, $writer), '60 saniye dolmadan yazım tekrarlanmamalı.');
presenceAssert(userPresenceTouchAuthenticated(null, 42, 1060, $writer), '60. saniyede yazım yenilenmeli.');
presenceAssert($writes === 2, 'Presence throttle beklenen yazım sayısını üretmedi.');

$failedWriter = static function (): void {
    throw new RuntimeException('Beklenen doğrulama hatası');
};
presenceAssert(!userPresenceTouchAuthenticated(null, 42, 1120, $failedWriter), 'Başarısız yazım false dönmeli.');
presenceAssert((int) ($_SESSION['_auth_last_presence_write'] ?? 0) === 1060, 'Başarısız yazım throttle zamanını değiştirmemeli.');
presenceAssert(userPresenceTouchAuthenticated(null, 42, 1121, $writer), 'Başarısız yazımdan sonra tekrar denenebilmeli.');

$profile = new ProfilePresentation(timeResolver: static fn (): int => $now, presence: $presence);
$profileData = $profile->sidebarData([
    'id' => 42,
    'username' => 'PresenceTest',
    'last_activity_at' => $dateForAge(299),
], [
    'base_uri' => '',
    'created_at' => '2025-08-09 12:00:00',
]);
presenceAssert(($profileData['is_online'] ?? false) === true, 'Profil sunumu ortak çevrimiçi durumunu kullanmalı.');
presenceAssert(($profileData['presence_relative_label'] ?? '') === 'Şimdi çevrimiçi', 'Profil göreli presence etiketi hatalı.');

$messageService = new MessageService(presence: $presence);
$decorateThread = new ReflectionMethod($messageService, 'decorateThreadRow');
$decoratedThread = $decorateThread->invoke($messageService, [
    'thread_id' => 7,
    'with_user_id' => 84,
    'with_user_name' => 'MesajTest',
    'with_user_last_activity_at' => $dateForAge(299),
], 42, '');
presenceAssert(($decoratedThread['with_user_is_online'] ?? false) === true, 'Mesaj konuşması ortak çevrimiçi durumunu kullanmalı.');
presenceAssert(($decoratedThread['with_user_presence_label'] ?? '') === 'Çevrimiçi', 'Mesaj konuşması presence etiketi hatalı.');
presenceAssert(($decoratedThread['with_user_presence_visible'] ?? false) === true, 'Aktif mesaj kullanıcısının presence durumu görünür olmalı.');
presenceAssert(!array_key_exists('with_user_last_activity_at', $decoratedThread), 'Mesaj konuşması ham son etkinlik tarihini açığa çıkarmamalı.');

$projectRoot = dirname(__DIR__);
$commentApi = (string) file_get_contents($projectRoot . '/api/comments.php');
$commentTemplate = (string) file_get_contents($projectRoot . '/themes/turkmod/comment-item.tpl');
$commentScript = (string) file_get_contents($projectRoot . '/assets/js/topic-comments.js');
$themeHeader = (string) file_get_contents($projectRoot . '/themes/turkmod/modules/header.tpl');
$themeProfileSidebar = (string) file_get_contents($projectRoot . '/themes/turkmod/profile-sidebar.tpl');
$fallbackProfileSidebar = (string) file_get_contents($projectRoot . '/includes/partials/profile-sidebar.php');
$publicThemeRenderer = (string) file_get_contents($projectRoot . '/includes/PublicThemeRenderer.php');
$messageServiceSource = (string) file_get_contents($projectRoot . '/includes/src/Modules/Messages/Services/MessageService.php');
$messagePage = (string) file_get_contents($projectRoot . '/includes/src/Modules/Messages/Http/messages-page-content.php');
$messageScript = (string) file_get_contents($projectRoot . '/assets/js/messages-page.js');
$presenceMigration = (string) file_get_contents($projectRoot . '/database/migrations/2026_08_09_0024_ensure_users_last_activity_at.php');

presenceAssert(substr_count($commentApi, 'u.last_activity_at') >= 3, 'Ana yorum, yanıt ve yeni yorum sorguları presence tarihini seçmeli.');
presenceAssert(str_contains($commentApi, "'presence_label'"), 'Yorum API presence etiketini döndürmeli.');
presenceAssert(str_contains($commentApi, "'presence_visible'"), 'Yorum API hesap uygunluk sonucunu döndürmeli.');
presenceAssert(str_contains($commentTemplate, '[[presence_html]]'), 'Yorum şablonu presence işaretini içermeli.');
presenceAssert(str_contains($publicThemeRenderer, "assets/js/topic-comments.js"), 'Aktif tema konu sayfası yorum istemcisini yüklemeli.');
presenceAssert(substr_count($messageServiceSource, 'u.last_activity_at AS with_user_last_activity_at') === 2, 'Mesaj liste ve konuşma sorguları presence tarihini aynı sorguda seçmeli.');
presenceAssert(str_contains($messageServiceSource, "'with_user_presence_label'"), 'Mesaj servisi presence etiketini döndürmeli.');
presenceAssert(substr_count($messagePage, 'data-messages-thread-presence') === 1, 'Mesaj listesi ortak presence işaretini içermeli.');
presenceAssert(substr_count($messagePage, 'data-messages-active-presence') === 1, 'Aktif mesaj başlığı presence işaretini içermeli.');
presenceAssert(!str_contains($messageScript, 'setInterval(refreshThreadPresence, 60000)'), 'Mesaj sayfası bağımsız presence polling çalıştırmamalı.');
presenceAssert(str_contains($messageScript, 'getPresenceState'), 'Mesaj sayfası ortak gerçek zamanlı presence önbelleğini kullanmalı.');
presenceAssert(str_contains($messageScript, 'updateThreadPresence(data.thread)'), 'Aktif konuşma yenilemesi presence durumunu güncellemeli.');
presenceAssert(str_contains($presenceMigration, 'ADD COLUMN last_activity_at'), 'Presence migration eksik canlı sütununu oluşturabilmeli.');
presenceAssert(str_contains($presenceMigration, 'ADD INDEX idx_last_activity'), 'Presence migration eksik canlı indeksini oluşturabilmeli.');

presenceAssert(str_contains($commentScript, 'data-user-presence-dot'), 'Comment presence dot must use shared mobile tooltip behavior.');
presenceAssert(str_contains($publicThemeRenderer, "asset_url('assets/js/public-topbar-realtime.js'"), 'Presence client must be loaded with a cachebuster.');
presenceAssert(!str_contains($themeHeader, 'public-topbar-realtime.js'), 'Theme header must not load an unversioned presence client.');

$themeTenurePosition = strpos($themeProfileSidebar, 'profile-sidebar-meta-item--tenure');
$themePresencePosition = strpos($themeProfileSidebar, 'profile-sidebar-meta-item--presence');
presenceAssert($themeTenurePosition !== false && $themePresencePosition !== false && $themePresencePosition > $themeTenurePosition, 'Tema profil presence satırı üyelik süresinden sonra gelmeli.');
presenceAssert(str_contains($themeProfileSidebar, 'data-presence-user-id'), 'Tema profil presence satırı ortak kullanıcı kimliğini taşımalı.');

$fallbackTenurePosition = strpos($fallbackProfileSidebar, 'profile-sidebar-meta-item--tenure');
$fallbackPresencePosition = strpos($fallbackProfileSidebar, 'profile-sidebar-meta-item--presence');
presenceAssert($fallbackTenurePosition !== false && $fallbackPresencePosition !== false && $fallbackPresencePosition > $fallbackTenurePosition, 'Fallback profil presence satırı üyelik süresinden sonra gelmeli.');
presenceAssert(str_contains($fallbackProfileSidebar, 'data-user-presence-dot'), 'Fallback profil presence noktası ortak mobil tooltip davranışını kullanmalı.');

fwrite(STDOUT, "User presence verification passed.\n");
