<?php
declare(strict_types=1);

$testDirectory = getenv('TURKMOD_UPLOAD_TEST_DIRECTORY');
if (PHP_SAPI !== 'cli-server' || !$testDirectory || !is_dir($testDirectory)) {
    http_response_code(404);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'GET') { echo 'ready'; exit; }
require_once dirname(__DIR__, 2) . '/includes/UploadSecurity.php';
require_once dirname(__DIR__, 2) . '/includes/src/Engine/Users/Support/profile-helpers.php';

class Logger {
    public static function getInstance(): self { return new self(); }
    public function alert(string $message, array $context): void {}
    public function security(string $message, array $context): void {}
    public function info(string $message, array $context): void {}
}
function appLogException(Throwable $exception, array $context = []): void {}
function eventsRecordActivity(PDO $pdo, int $userId, string $activityType, string $subjectType, int $subjectId, array $metadata = []): array { return []; }

header('Content-Type: application/json');
$scenario = in_array($_GET['scenario'] ?? '', ['success', 'failure', 'invalid', 'generic'], true) ? $_GET['scenario'] : 'invalid';
$directory = $testDirectory . '/' . $scenario;
mkdir($directory . '/uploads/profil', 0755, true);
$old = $directory . '/uploads/profil/old-avatar.png';
file_put_contents($old, 'previous-avatar');
if ($scenario === 'generic') {
    $result = UploadSecurity::getInstance()->saveFile($_FILES['avatar'], $directory . '/saved/avatar.png');
    $path = $result['path'] ?? '';
    echo json_encode(['success' => $result['success'], 'clean' => $path !== '' && !str_contains(file_get_contents($path), 'payload-marker')]);
    exit;
}
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->sqliteCreateFunction('NOW', static fn(): string => date('Y-m-d H:i:s'));
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, avatar TEXT, updated_at TEXT)');
$pdo->exec("INSERT INTO users (id, username, avatar) VALUES (1, 'test', 'uploads/profil/old-avatar.png')");
if ($scenario === 'failure') {
    $pdo->exec("CREATE TRIGGER reject_avatar BEFORE UPDATE ON users BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
}
$result = profileUploadAvatar($pdo, 1, $_FILES['avatar'], $directory . '/uploads');
$avatar = $pdo->query('SELECT avatar FROM users WHERE id = 1')->fetchColumn();
$stored = $directory . '/' . $avatar;
$files = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->isFile()) { $files[] = $file->getPathname(); }
}
echo json_encode(['error' => $result, 'old_exists' => is_file($old), 'avatar' => $avatar,
    'stored_exists' => is_file($stored), 'stored_clean' => is_file($stored) && !str_contains(file_get_contents($stored), 'payload-marker'),
    'file_count' => count($files)]);
