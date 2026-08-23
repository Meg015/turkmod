<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/autoloader.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\Socket\SocketServer;
use App\Core\DatabaseConnection;
use App\Core\Realtime\WebSocketConfig;
use App\Core\Realtime\PresenceSubscriptionRegistry;
use App\Engine\UserActivity\UserPresenceLookup;

$loop = Loop::get();
$isDevelopmentTls = strtolower(trim((string) (DatabaseConnection::getEnvConfig()['APP_ENV'] ?? 'production'))) !== 'production';
$socketAddress = WebSocketConfig::websocketBindHost() . ':' . WebSocketConfig::websocketPort();
$socketContext = [];

if ($isDevelopmentTls) {
    $socketAddress = 'tls://' . $socketAddress;
    $socketContext['tls'] = [
        'local_cert' => 'C:/xampp/apache/conf/ssl.crt/server.crt',
        'local_pk' => 'C:/xampp/apache/conf/ssl.key/server.key',
        'allow_self_signed' => true,
        'verify_peer' => false,
        'verify_peer_name' => false,
    ];
}

// This long-running CLI server reads PHP sessions manually; disable cookie/cache
// header behavior so session_id/session_start remain safe after stdout logging.
ini_set('session.use_cookies', '0');
ini_set('session.use_only_cookies', '0');
session_cache_limiter('');

class ChatServer implements MessageComponentInterface
{
    public \SplObjectStorage $clients;
    public array $userConnections = [];

    private PresenceSubscriptionRegistry $presenceRegistry;

    private UserPresenceLookup $presenceLookup;

    /** @var array<string,ConnectionInterface> */
    private array $connectionsById = [];

    /** @var array<int,object> */
    private array $offlineTimers = [];

    private string $serverInstanceId;

    private int $presenceSequence = 0;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->presenceRegistry = new PresenceSubscriptionRegistry();
        $this->presenceLookup = new UserPresenceLookup();
        $this->serverInstanceId = bin2hex(random_bytes(12));
    }

    private function cookieValue(ConnectionInterface $conn, string $cookieName): string
    {
        if ($cookieName === '') {
            return '';
        }

        $headers = $conn->httpRequest->getHeader('Cookie');
        if (!is_array($headers) || $headers === []) {
            return '';
        }

        $rawCookies = implode('; ', $headers);
        foreach (explode(';', $rawCookies) as $cookiePart) {
            $cookiePart = trim($cookiePart);
            if ($cookiePart === '' || !str_contains($cookiePart, '=')) {
                continue;
            }

            [$name, $value] = array_pad(explode('=', $cookiePart, 2), 2, '');
            if (trim($name) === $cookieName) {
                return rawurldecode($value);
            }
        }

        return '';
    }

    private function authenticateConnection(ConnectionInterface $conn, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $sessionCookieName = session_name();
        $sessionId = $this->cookieValue($conn, $sessionCookieName);
        if ($sessionId === '') {
            return false;
        }

        $previousSessionId = session_id();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $_SESSION = [];
        session_id($sessionId);
        $started = session_start();
        if (!$started) {
            $_SESSION = [];
            if ($previousSessionId !== '') {
                session_id($previousSessionId);
            }

            return false;
        }

        $sessionUserId = (int) ($_SESSION['_auth_user_id'] ?? 0);
        session_write_close();
        $_SESSION = [];

        if ($previousSessionId !== '') {
            session_id($previousSessionId);
        }

        return $sessionUserId > 0 && $sessionUserId === $userId;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        echo "Attempting connection ({$conn->resourceId})...\n";
        $querystring = $conn->httpRequest->getUri()->getQuery();
        parse_str($querystring, $query);

        $userId = (int)($query['user_id'] ?? 0);

        echo "Parsed user_id: {$userId}\n";

        // Authenticate via the user's PHP session cookie (HttpOnly, SameSite).
        // The CSRF token is NOT passed in the URL query string — it would leak
        // via server logs, Referer headers, and browser history.
        if ($this->authenticateConnection($conn, $userId) && $this->presenceActorAllowed($userId)) {
            $this->clients->attach($conn);
            $conn->userId = $userId;
            $connectionId = (string) $conn->resourceId;
            $this->connectionsById[$connectionId] = $conn;

            if (!isset($this->userConnections[$userId])) {
                $this->userConnections[$userId] = new \SplObjectStorage;
            }
            $this->userConnections[$userId]->attach($conn);
            $isFirstConnection = $this->presenceRegistry->attach($connectionId, $userId);

            if (isset($this->offlineTimers[$userId])) {
                Loop::cancelTimer($this->offlineTimers[$userId]);
                unset($this->offlineTimers[$userId]);
            }

            if ($isFirstConnection) {
                $this->broadcastPresenceChange($userId);
            }

            echo "New connection! ({$conn->resourceId}) for user {$userId}\n";
        } else {
            echo "Connection rejected! user_id: {$userId}\n";
            $conn->close();
        }
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $rawMessage = is_string($msg) ? $msg : (string) $msg;
        if (strlen($rawMessage) > 16384) {
            $this->sendPresenceError($from, 'payload_too_large');
            return;
        }

        $payload = json_decode($rawMessage, true);
        if (!is_array($payload)) {
            $this->sendPresenceError($from, 'invalid_json');
            return;
        }

        $type = trim((string) ($payload['type'] ?? ''));
        if (!in_array($type, ['presence_subscribe', 'presence_unsubscribe'], true)) {
            $this->sendPresenceError($from, 'unsupported_message');
            return;
        }

        $userIds = $payload['user_ids'] ?? null;
        if (!is_array($userIds)) {
            $this->sendPresenceError($from, 'invalid_user_ids');
            return;
        }

        try {
            $connectionId = (string) $from->resourceId;
            if ($type === 'presence_subscribe') {
                $addedIds = $this->presenceRegistry->subscribe($connectionId, $userIds);
                if ($addedIds !== []) {
                    $this->sendPresenceSnapshot($from, $addedIds);
                }
                return;
            }

            $this->presenceRegistry->unsubscribe($connectionId, $userIds);
        } catch (\InvalidArgumentException $error) {
            $this->sendPresenceError($from, 'invalid_subscription');
        } catch (\Throwable $error) {
            error_log('Presence WebSocket message failed: ' . $error->getMessage());
            $this->sendPresenceError($from, 'presence_unavailable');
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        if ($this->clients->contains($conn)) {
            $this->clients->detach($conn);
        }
        if (isset($conn->userId) && isset($this->userConnections[$conn->userId])) {
            $this->userConnections[$conn->userId]->detach($conn);
            if (count($this->userConnections[$conn->userId]) === 0) {
                unset($this->userConnections[$conn->userId]);
            }
        }

        $connectionId = (string) $conn->resourceId;
        unset($this->connectionsById[$connectionId]);
        $detached = $this->presenceRegistry->detach($connectionId);
        if (is_array($detached) && $detached['is_last_connection']) {
            $userId = (int) $detached['user_id'];
            if (isset($this->offlineTimers[$userId])) {
                Loop::cancelTimer($this->offlineTimers[$userId]);
            }
            $this->offlineTimers[$userId] = Loop::addTimer(30.0, function () use ($userId): void {
                unset($this->offlineTimers[$userId]);
                if (!$this->presenceRegistry->isUserOnline($userId)) {
                    $this->broadcastPresenceChange($userId);
                }
            });
        }
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    public function broadcastToUser(int $userId, string $message)
    {
        if (isset($this->userConnections[$userId])) {
            foreach ($this->userConnections[$userId] as $conn) {
                $conn->send($message);
            }
        }
    }

    public function invalidatePresence(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $state = $this->lookupPresence([$userId]);
        $userState = $state[$userId] ?? ['user_id' => $userId, 'visible' => false];
        $this->broadcastPresencePayload('presence_changed', [$userId => $userState], $userId);

        if (($userState['visible'] ?? false) === false && isset($this->userConnections[$userId])) {
            $connections = [];
            foreach ($this->userConnections[$userId] as $connection) {
                $connections[] = $connection;
            }
            foreach ($connections as $connection) {
                $connection->close();
            }
        }
    }

    private function presenceActorAllowed(int $userId): bool
    {
        try {
            $state = $this->lookupPresence([$userId]);
            if ($state === []) {
                return true;
            }

            return (bool) ($state[$userId]['visible'] ?? false);
        } catch (\Throwable $error) {
            error_log('Presence actor eligibility unavailable: ' . $error->getMessage());
            return true;
        }
    }

    /**
     * @param array<int,int> $userIds
     * @return array<int,array<string,mixed>>
     */
    private function lookupPresence(array $userIds): array
    {
        $pdo = DatabaseConnection::connection();
        if (!$pdo instanceof \PDO) {
            return [];
        }

        return $this->presenceLookup->lookup(
            $pdo,
            $userIds,
            $this->presenceRegistry->onlineOverrides($userIds),
        );
    }

    /** @param array<int,int> $userIds */
    private function sendPresenceSnapshot(ConnectionInterface $connection, array $userIds): void
    {
        try {
            $users = $this->lookupPresence($userIds);
            if ($users === []) {
                $this->sendPresenceError($connection, 'presence_unavailable');
                return;
            }
            $this->sendJson($connection, $this->presenceEnvelope('presence_snapshot', $users));
        } catch (\Throwable $error) {
            error_log('Presence snapshot failed: ' . $error->getMessage());
            $this->sendPresenceError($connection, 'presence_unavailable');
        }
    }

    private function broadcastPresenceChange(int $userId): void
    {
        try {
            $users = $this->lookupPresence([$userId]);
            if ($users === []) {
                return;
            }
            $this->broadcastPresencePayload('presence_changed', $users, $userId);
        } catch (\Throwable $error) {
            error_log('Presence change broadcast failed: ' . $error->getMessage());
        }
    }

    /**
     * @param array<int,array<string,mixed>> $users
     */
    private function broadcastPresencePayload(string $type, array $users, int $watchedUserId): void
    {
        $payload = $this->presenceEnvelope($type, $users);
        foreach ($this->presenceRegistry->subscriberConnectionIds($watchedUserId) as $connectionId) {
            if (isset($this->connectionsById[$connectionId])) {
                $this->sendJson($this->connectionsById[$connectionId], $payload);
            }
        }
    }

    /**
     * @param array<int,array<string,mixed>> $users
     * @return array<string,mixed>
     */
    private function presenceEnvelope(string $type, array $users): array
    {
        $this->presenceSequence++;

        return [
            'type' => $type,
            'protocol' => 1,
            'server_instance_id' => $this->serverInstanceId,
            'sequence' => $this->presenceSequence,
            'observed_at' => (int) floor(microtime(true) * 1000),
            'users' => $users,
        ];
    }

    private function sendPresenceError(ConnectionInterface $connection, string $code): void
    {
        $this->sendJson($connection, [
            'type' => 'presence_error',
            'protocol' => 1,
            'code' => $code,
        ]);
    }

    /** @param array<string,mixed> $payload */
    private function sendJson(ConnectionInterface $connection, array $payload): void
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return;
        }

        try {
            $connection->send($encoded);
        } catch (\Throwable $error) {
            error_log('WebSocket send failed: ' . $error->getMessage());
        }
    }
}

$chat = new ChatServer();

// Setup WebSocket Server
$webSock = new SocketServer($socketAddress, $socketContext, $loop);
$server = new IoServer(
    new HttpServer(
        new WsServer(
            $chat
        )
    ),
    $webSock,
    $loop
);

// Setup Internal API Server for broadcasts from PHP
$internalApiSocket = new SocketServer(
    WebSocketConfig::broadcastBindHost() . ':' . WebSocketConfig::broadcastPort(),
    [],
    $loop
);
$internalApiSocket->on('connection', function (\React\Socket\ConnectionInterface $connection) use ($chat) {
    $buffer = '';
    $maxBytes = WebSocketConfig::broadcastMaxBytes();
    $timeoutTimer = Loop::addTimer(WebSocketConfig::broadcastTimeoutSeconds(), static function () use ($connection): void {
        $connection->end();
    });
    $connection->on('data', function ($data) use ($connection, $chat, &$buffer, $maxBytes, $timeoutTimer) {
        if (strlen($data) > $maxBytes - strlen($buffer)) {
            Loop::cancelTimer($timeoutTimer);
            $connection->write("HTTP/1.1 413 Payload Too Large\r\nContent-Length: 17\r\n\r\nPayload Too Large");
            $connection->end();

            return;
        }
        $buffer .= $data;
        $parts = explode("\r\n\r\n", $buffer, 2);
        if (count($parts) !== 2) {
            return;
        }

        [$headers, $body] = $parts;
        if (preg_match('/^Content-Length:\s*(\d+)\s*$/im', $headers, $matches) !== 1) {
            Loop::cancelTimer($timeoutTimer);
            $connection->write("HTTP/1.1 411 Length Required\r\nContent-Length: 15\r\n\r\nLength Required");
            $connection->end();

            return;
        }

        $contentLength = (int) $matches[1];
        if ($contentLength > $maxBytes) {
            Loop::cancelTimer($timeoutTimer);
            $connection->write("HTTP/1.1 413 Payload Too Large\r\nContent-Length: 17\r\n\r\nPayload Too Large");
            $connection->end();

            return;
        }
        if (strlen($body) < $contentLength) {
            return;
        }

        Loop::cancelTimer($timeoutTimer);
        $decoded = json_decode(substr($body, 0, $contentLength), true);
        if (
            is_array($decoded)
            && ($decoded['action'] ?? '') === 'presence_invalidate'
            && (int) ($decoded['user_id'] ?? 0) > 0
        ) {
            $chat->invalidatePresence((int) $decoded['user_id']);
            $connection->write("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nOK");
            $connection->end();

            return;
        }
        if (is_array($decoded) && isset($decoded['user_id'], $decoded['payload'])) {
            $payload = json_encode($decoded['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $userIds = is_array($decoded['user_id']) ? $decoded['user_id'] : [$decoded['user_id']];
            if ($payload !== false) {
                foreach ($userIds as $uid) {
                    $chat->broadcastToUser((int) $uid, $payload);
                }
                $connection->write("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nOK");
                $connection->end();

                return;
            }
        }

        $connection->write("HTTP/1.1 400 Bad Request\r\nContent-Length: 11\r\n\r\nBad Request");
        $connection->end();
    });
    $connection->on('close', static function () use ($timeoutTimer): void {
        Loop::cancelTimer($timeoutTimer);
    });
});

echo "WebSocket server running on " . $socketAddress . "\n";
echo "Internal API server running on " . WebSocketConfig::broadcastBindHost() . ':' . WebSocketConfig::broadcastPort() . "\n";

$loop->run();
