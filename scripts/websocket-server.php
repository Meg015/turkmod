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
use App\Core\Realtime\WebSocketConfig;

$loop = Loop::get();

// This long-running CLI server reads PHP sessions manually; disable cookie/cache
// header behavior so session_id/session_start remain safe after stdout logging.
ini_set('session.use_cookies', '0');
ini_set('session.use_only_cookies', '0');
session_cache_limiter('');

class ChatServer implements MessageComponentInterface
{
    public \SplObjectStorage $clients;
    public array $userConnections = [];

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
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
        if ($this->authenticateConnection($conn, $userId)) {
            $this->clients->attach($conn);
            $conn->userId = $userId;

            if (!isset($this->userConnections[$userId])) {
                $this->userConnections[$userId] = new \SplObjectStorage;
            }
            $this->userConnections[$userId]->attach($conn);

            echo "New connection! ({$conn->resourceId}) for user {$userId}\n";
        } else {
            echo "Connection rejected! user_id: {$userId}\n";
            $conn->close();
        }
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        // Currently, all messages from clients are handled via HTTP API.
        // We only use this WebSocket server for pushing notifications to clients.
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        if (isset($conn->userId) && isset($this->userConnections[$conn->userId])) {
            $this->userConnections[$conn->userId]->detach($conn);
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
}

$chat = new ChatServer();

// Setup WebSocket Server
$webSock = new SocketServer(
    WebSocketConfig::websocketBindHost() . ':' . WebSocketConfig::websocketPort(),
    [],
    $loop
);
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

echo "WebSocket server running on " . WebSocketConfig::websocketBindHost() . ':' . WebSocketConfig::websocketPort() . "\n";
echo "Internal API server running on " . WebSocketConfig::broadcastBindHost() . ':' . WebSocketConfig::broadcastPort() . "\n";

$loop->run();
