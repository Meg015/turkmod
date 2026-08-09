<?php

declare(strict_types=1);

use App\Engine\UserActivity\UserPresence;
use App\Engine\UserActivity\UserPresenceLookup;
use App\Core\Realtime\PresenceSubscriptionRegistry;

require_once dirname(__DIR__) . '/includes/autoloader.php';

date_default_timezone_set('Europe/Istanbul');

function realtimePresenceAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$now = strtotime('2026-08-09 12:00:00');
$presence = new UserPresence(static fn (): int => $now, new DateTimeZone('Europe/Istanbul'));
$lookup = new UserPresenceLookup($presence);
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    status TEXT,
    is_banned INTEGER DEFAULT 0,
    deleted_at TEXT NULL,
    last_activity_at TEXT NULL
)');
$insert = $pdo->prepare('INSERT INTO users (id, status, is_banned, deleted_at, last_activity_at) VALUES (?, ?, ?, ?, ?)');
$insert->execute([10, 'active', 0, null, '2026-08-09 11:59:00']);
$insert->execute([11, 'inactive', 0, null, '2026-08-09 11:59:00']);

$httpModel = $lookup->lookup($pdo, [10, 11, 12]);
realtimePresenceAssert(($httpModel[10]['is_online'] ?? false) === true, 'HTTP modeli beş dakikalık fallback durumunu kullanmalı.');
realtimePresenceAssert(($httpModel[11]['visible'] ?? true) === false, 'Pasif hesap HTTP modelinde gizli olmalı.');
realtimePresenceAssert(($httpModel[12]['visible'] ?? true) === false, 'Bulunamayan hesap HTTP modelinde gizli olmalı.');

$socketModel = $lookup->lookup($pdo, [10], [10 => false]);
realtimePresenceAssert(($socketModel[10]['is_online'] ?? true) === false, 'WebSocket kesin durumu HTTP tahminini geçersiz kılmalı.');

$registry = new PresenceSubscriptionRegistry();
realtimePresenceAssert($registry->attach('conn-a', 10) === true, 'İlk kullanıcı bağlantısı ilk geçişi üretmeli.');
realtimePresenceAssert($registry->attach('conn-b', 10) === false, 'İkinci kullanıcı bağlantısı yeni geçiş üretmemeli.');
realtimePresenceAssert($registry->attach('conn-c', 20) === true, 'Başka kullanıcının ilk bağlantısı bağımsız olmalı.');
realtimePresenceAssert($registry->subscribe('conn-a', [20, 30, 30]) === [20, 30], 'Abonelikler benzersiz ve sıralı eklenmeli.');
realtimePresenceAssert($registry->subscribe('conn-a', [30]) === [], 'Tekrarlanan abonelik idempotent olmalı.');
realtimePresenceAssert($registry->subscribe('conn-b', [20]) === [20], 'Aynı kullanıcıyı farklı bağlantı izleyebilmeli.');
realtimePresenceAssert($registry->subscriberConnectionIds(20) === ['conn-a', 'conn-b'], 'Ters abonelik indeksi ilgili bağlantıları döndürmeli.');
realtimePresenceAssert($registry->unsubscribe('conn-a', [20, 99]) === [20], 'Yalnızca var olan abonelik kaldırılmalı.');
realtimePresenceAssert($registry->subscriberConnectionIds(20) === ['conn-b'], 'Abonelik kaldırma ters indeksi temizlemeli.');
realtimePresenceAssert(($registry->detach('conn-a')['is_last_connection'] ?? true) === false, 'İlk bağlantı kapanınca kullanıcı çevrimdışı olmamalı.');
$lastDetach = $registry->detach('conn-b');
realtimePresenceAssert(($lastDetach['is_last_connection'] ?? false) === true, 'Son bağlantı kapanınca çevrimdışı toleransı başlayabilmeli.');
realtimePresenceAssert($registry->subscriberConnectionIds(20) === [], 'Bağlantı kapanınca abonelikleri temizlenmeli.');
realtimePresenceAssert($registry->onlineOverrides([10, 20]) === [10 => false, 20 => true], 'Kesin bağlantı override haritası doğru olmalı.');

$messageLimitRejected = false;
try {
    $registry->subscribe('conn-c', range(1, PresenceSubscriptionRegistry::MAX_IDS_PER_MESSAGE + 1));
} catch (InvalidArgumentException) {
    $messageLimitRejected = true;
}
realtimePresenceAssert($messageLimitRejected, 'WebSocket mesajı 100 kimlikten fazlasını reddetmeli.');

$projectRoot = dirname(__DIR__);
$endpoint = (string) file_get_contents($projectRoot . '/api/user-presence.php');
$webSocketServer = (string) file_get_contents($projectRoot . '/scripts/websocket-server.php');
$broadcaster = (string) file_get_contents($projectRoot . '/includes/src/Core/Realtime/WebSocketBroadcaster.php');
realtimePresenceAssert(str_contains($endpoint, 'MAX_IDS_PER_BATCH'), 'Presence endpoint 100 kimlik sınırını kullanmalı.');
realtimePresenceAssert(str_contains($endpoint, "'source' => 'http'"), 'Presence endpoint kaynak bilgisini döndürmeli.');
realtimePresenceAssert(str_contains($endpoint, "'observed_at'"), 'Presence endpoint gözlem sırası bilgisini döndürmeli.');
realtimePresenceAssert(str_contains($endpoint, "'user-presence:'"), 'Presence cache girdileri kullanıcı etiketi taşımalı.');
realtimePresenceAssert(!str_contains($endpoint, "'last_activity_at'"), 'Presence endpoint ham aktivite alanı yayımlamamalı.');
realtimePresenceAssert(str_contains($webSocketServer, "Loop::addTimer(30.0"), 'Son WebSocket bağlantısı 30 saniyelik tolerans kullanmalı.');
realtimePresenceAssert(str_contains($webSocketServer, "'presence_subscribe'"), 'WebSocket sunucusu presence aboneliğini desteklemeli.');
realtimePresenceAssert(str_contains($webSocketServer, "'server_instance_id'"), 'WebSocket presence olayları sunucu nesli taşımalı.');
realtimePresenceAssert(str_contains($webSocketServer, "'presence_invalidate'"), 'WebSocket iç API presence invalidation desteklemeli.');
realtimePresenceAssert(str_contains($broadcaster, 'invalidatePresence'), 'Broadcaster hesap görünürlüğü invalidation komutu yayımlamalı.');

fwrite(STDOUT, "Realtime presence verification passed.\n");
