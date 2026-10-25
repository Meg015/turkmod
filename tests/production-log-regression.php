<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/includes/UploadSecurity.php';

// Capture security notices without writing into application logs.
class Logger {
    public static array $notices = [];
    public static function getInstance(): self { return new self(); }
    public function alert(string $message, array $context): void { self::$notices[] = $message; }
    public function security(string $message, array $context): void { self::$notices[] = $message; }
}
function regressionAssert(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
function regressionReject(callable $action, string $message): Throwable {
    try { $action(); } catch (Throwable $e) { return $e; }
    throw new RuntimeException($message);
}
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) { return false; }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$directory = str_replace('\\', '/', dirname(__DIR__)) . '/storage/log-regression-' . bin2hex(random_bytes(8));
mkdir($directory, 0755);
$checks = 0;
try {
    $pdo = new class extends PDO {
        public function __construct() {
            parent::__construct('sqlite::memory:');
            // Some Windows PDO SQLite builds truncate integer callback results to 32 bits.
            $this->sqliteCreateFunction('UNIX_TIMESTAMP', static fn(?string $value = null): float => (float) ($value === null ? time() : strtotime($value)));
        }
        public function prepare(string $query, array $options = []): PDOStatement|false {
            if (str_contains($query, 'INFORMATION_SCHEMA')) { throw new PDOException('Metadata access denied'); }
            return parent::prepare($query, $options);
        }
    };
    $pdo->exec('CREATE TABLE request_rate_limits (scope TEXT, rate_key TEXT, attempt_count INTEGER,
        first_attempt_at TEXT, last_attempt_at TEXT, expires_at TEXT, created_at TEXT, updated_at TEXT)');
    $limiter = new App\Core\Security\DatabaseRateLimiter($pdo);
    regressionAssert(!$limiter->tooManyAttempts('new', 2, 60), 'Fresh client was blocked');
    $pdo->exec("INSERT INTO request_rate_limits (scope,rate_key,attempt_count,expires_at)
        VALUES ('default','blocked',3,'2099-01-01 00:00:00')");
    regressionAssert($limiter->tooManyAttempts('blocked', 2, 60), 'Limit was bypassed');
    $limiter->clear('blocked');
    regressionAssert(!$limiter->tooManyAttempts('blocked', 2, 60), 'Clearing limit failed');
    $checks += 3;
    foreach (['missing_limits', 'incomplete_limits'] as $table) {
        if ($table === 'incomplete_limits') { $pdo->exec('CREATE TABLE incomplete_limits (scope TEXT)'); }
        $error = regressionReject(fn() => new App\Core\Security\DatabaseRateLimiter($pdo, 'default', $table), 'Invalid schema was accepted');
        regressionAssert($error->getPrevious() instanceof PDOException, 'Schema failure lost its PDO cause');
        $checks++;
    }
    regressionAssert(regressionReject(fn() => new App\Core\Security\DatabaseRateLimiter($pdo, 'default', 'bad;table'), 'Unsafe table was accepted') instanceof InvalidArgumentException, 'Unsafe table exception changed');
    $checks++;

    // Execute the real pagination closure without bootstrapping/authenticating a whole page.
    $source = str_replace("\r\n", "\n", file_get_contents(dirname(__DIR__) . '/includes/src/Engine/Users/Http/profile-page-content.php'));
    $start = strpos($source, '$renderProfilePagination = static function');
    $end = strpos($source, "\n};", $start) + 3;
    $closure = substr($source, $start, $end - $start);
    foreach (['/profil', '/turkmod/profil'] as $profilePageBaseUrl) {
        $baseUri = '';
        $profileTotals = ['topics' => 40, 'activity' => 30];
        $profilePages = ['topics' => 2, 'activity' => 1];
        $profilePerPage = 10;
        $profilePageParams = ['topics' => 'topics_page', 'activity' => 'activity_page'];
        $safeTopicStatusFilter = 'pending';
        $safeActivityFilter = 'comment';
        eval($closure);
        $topics = $renderProfilePagination('topics');
        $activity = $renderProfilePagination('activity');
        regressionAssert(str_contains($topics, $profilePageBaseUrl . '?tab=topics&amp;topics_page=3&amp;topic_status=pending'), 'Topic paging lost canonical path/filter');
        regressionAssert(str_contains($activity, $profilePageBaseUrl . '?tab=activity&amp;activity_page=2&amp;activity_type=comment'), 'Activity paging lost canonical path/filter');
        $checks += 2;
    }

    $security = UploadSecurity::getInstance();
    $upload = static fn(string $path, string $name): array => ['name' => $name, 'tmp_name' => $path,
        'error' => UPLOAD_ERR_OK, 'size' => filesize($path)];
    foreach (['jpg', 'png', 'gif', 'webp'] as $extension) {
        $path = $directory . '/safe.' . $extension;
        $image = imagecreatetruecolor(32, 24);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 20, 100, 200, 70));
        regressionAssert(mediaSaveImage($image, $path, $extension, []), 'Fixture encoding failed');
        unset($image);
        file_put_contents($path, "\0<?x\0base64_decode\0eval(\0system(\0", FILE_APPEND);
        regressionAssert($security->validateUpload($upload($path, 'avatar.' . $extension))['valid'], 'Benign binary bytes triggered a PHP alert: ' . $extension);
        mediaSanitizeImage($path);
        $info = getimagesize($path);
        regressionAssert([$info[0], $info[1]] === [32, 24] && !str_contains(file_get_contents($path), 'base64_decode'), 'Image sanitization did not preserve pixels/remove appended data');
        if (in_array($extension, ['png', 'webp'], true)) {
            $decoded = mediaCreateImageFromFile($path, $extension);
            regressionAssert(imagecolorsforindex($decoded, imagecolorat($decoded, 0, 0))['alpha'] > 0, 'Alpha channel was lost');
        }
        foreach (['<?php echo 1;', '<?= 1 ?>'] as $payload) {
            file_put_contents($path, $payload, FILE_APPEND);
            regressionAssert(!$security->validateUpload($upload($path, 'avatar.' . $extension))['valid'], 'Explicit PHP upload was accepted');
            mediaSanitizeImage($path);
            regressionAssert(!str_contains(file_get_contents($path), $payload), 'Round-trip retained executable payload');
            $checks++;
        }
        $checks += 2;
    }
    // Preserve two GIF frames, delays and looping while removing metadata/trailing content.
    $image = imagecreatetruecolor(4, 4);
    ob_start();
    imagegif($image);
    $singleGif = ob_get_clean();
    $headerLength = 13 + ((ord($singleGif[10]) & 0x80) ? 3 * (1 << ((ord($singleGif[10]) & 7) + 1)) : 0);
    $frame = substr($singleGif, $headerLength, -1);
    $loop = "\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x00";
    $control = "\x21\xF9\x04\x00\x0A\x00\x00\x00";
    $expectedGif = substr($singleGif, 0, $headerLength) . $loop . $control . $frame . $control . $frame . "\x3B";
    $animated = $directory . '/animated.gif';
    $withMetadata = substr($singleGif, 0, $headerLength) . "\x21\xFE\x07comment\x00" . substr($expectedGif, $headerLength) . 'payload-marker';
    file_put_contents($animated, $withMetadata);
    regressionAssert($security->validateUpload($upload($animated, 'avatar.gif'))['valid'], 'Animated GIF was rejected');
    mediaSanitizeImage($animated);
    regressionAssert(file_get_contents($animated) === $expectedGif, 'GIF animation/delays/loop were lost or metadata survived');
    foreach ([substr($expectedGif, 0, -1), substr($expectedGif, 0, $headerLength) . "\x21\xF9\xFF"] as $brokenGif) {
        regressionReject(fn() => mediaSanitizeGifData($brokenGif, 4, 4), 'Truncated GIF was accepted');
    }
    $headerGif = hex2bin('47494638396101000100800000000000ffffff');
    $blackFrame = hex2bin('2c0000000001000100000202440100');
    $whiteFrame = hex2bin('2c00000000010001000002024c0100');
    $distinctFrames = $headerGif . $loop . $control . $blackFrame . $control . $whiteFrame . "\x3B";
    regressionAssert(mediaSanitizeGifData($distinctFrames, 1, 1) === $distinctFrames, 'Distinct GIF frames were altered');
    $brokenFrame = $headerGif . $blackFrame . hex2bin('2c0000000001000100000200') . "\x3B";
    file_put_contents($animated, $brokenFrame);
    regressionReject(fn() => mediaSanitizeImage($animated), 'Corrupt later GIF frame was accepted');
    regressionAssert(file_get_contents($animated) === $brokenFrame, 'GIF failure modified the original');
    $checks += 6;
    foreach ([
        $headerGif . hex2bin('21f904100a000000') . $blackFrame . "\x3B", // Reserved disposal mode.
        $headerGif . hex2bin('21f904e00a000000') . $blackFrame . "\x3B", // Reserved GCE bits.
        $headerGif . hex2bin('21f904010a00ff00') . $blackFrame . "\x3B", // Transparency outside palette.
        $headerGif . hex2bin('2c0000000001000100180202440100') . "\x3B", // Reserved descriptor bits.
    ] as $malformedControl) {
        regressionReject(fn() => mediaSanitizeGifData($malformedControl, 1, 1), 'Malformed GIF control was accepted');
        $checks++;
    }

    $invalid = $directory . '/invalid.png';
    file_put_contents($invalid, 'not a PNG');
    regressionAssert(!$security->validateUpload($upload($invalid, 'avatar.png'))['valid'], 'Non-image was accepted');
    $before = file_get_contents($invalid);
    regressionReject(fn() => mediaSanitizeImage($invalid), 'Invalid image sanitization succeeded');
    regressionAssert(file_get_contents($invalid) === $before, 'Failed processing modified original');
    $header = "\x89PNG\r\n\x1a\n";
    $chunk = 'IHDR' . pack('NNCCCCC', 50000, 50000, 8, 2, 0, 0, 0);
    file_put_contents($invalid, $header . pack('N', 13) . $chunk . pack('N', crc32($chunk)));
    regressionAssert(!$security->validateUpload($upload($invalid, 'avatar.png'))['valid'], 'Huge image dimensions were accepted');
    $checks += 3;

    $endpoint = new App\Engine\Seo\Http\CspReportEndpoint($directory);
    $extensionReport = ['body' => ['sourceFile' => 'chrome-extension://example/script.js', 'effectiveDirective' => 'script-src-elem', 'blockedURL' => 'inline', 'disposition' => 'enforce']];
    $realReport = ['body' => ['sourceFile' => 'https://turkmod.net/assets/app.js', 'effectiveDirective' => 'script-src-elem', 'blockedURL' => 'inline', 'disposition' => 'enforce']];
    $request = static fn(array $reports): App\Core\Http\Request => new App\Core\Http\Request('POST', '/api/csp-report.php', [], ['Content-Type' => 'application/reports+json'], json_encode($reports));
    regressionAssert($endpoint->handle($request([$extensionReport]))->getStatusCode() === 204 && !file_exists($directory . '/csp-reports.log'), 'Extension-only batch was logged');
    $endpoint->handle($request([$extensionReport, $realReport]));
    $lines = file($directory . '/csp-reports.log');
    $record = json_decode(substr($lines[0], 20), true);
    regressionAssert($record['report'] === [$realReport], 'Mixed CSP batch retained noise or lost real violation');
    $unknown = ['body' => ['sourceFile' => 'blob', 'effectiveDirective' => 'script-src-elem', 'disposition' => 'enforce']];
    $endpoint->handle($request([$unknown]));
    regressionAssert(count(file($directory . '/csp-reports.log')) === 2, 'Unknown-origin CSP violation was suppressed');
    $checks += 3;

    require_once dirname(__DIR__) . '/includes/ErrorHandler.php';
    restore_error_handler();
    restore_exception_handler();
    $cause = new PDOException('Permission denied');
    $cause->errorInfo = ['HY000', 1142, 'SELECT denied'];
    $wrapped = new RuntimeException('Schema readiness failed', 0, $cause);
    $formatter = new ReflectionMethod(ErrorHandler::class, 'exceptionTrace');
    $trace = $formatter->invoke(ErrorHandler::getInstance(), $wrapped);
    regressionAssert(str_contains($trace, 'Permission denied') && str_contains($trace, 'HY000') && str_contains($trace, '1142'), 'Private log lost underlying PDO diagnostics');
    $checks++;

    echo 'PASS: ' . $checks . ' production PHP regression checks on PHP ' . PHP_VERSION . PHP_EOL;
} finally {
    foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $file) {
        if ($file->isFile()) { unlink($file->getPathname()); }
    }
    rmdir($directory);
}
