<?php
/**
 * Security Hardening for Events Module
 */

/**
 * Validate and sanitize user input
 */
function eventsValidateInput($input, $type = 'string', $maxLength = 255) {
    if ($type === 'string') {
        $input = trim((string)$input);
        if (strlen($input) > $maxLength) {
            return false;
        }
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }

    if ($type === 'email') {
        $input = trim((string)$input);
        if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        return $input;
    }

    if ($type === 'integer') {
        $input = (int)$input;
        return $input;
    }

    if ($type === 'float') {
        $input = (float)$input;
        return $input;
    }

    if ($type === 'url') {
        $input = trim((string)$input);
        if (!filter_var($input, FILTER_VALIDATE_URL)) {
            return false;
        }
        return $input;
    }

    return false;
}

/**
 * Prevent SQL injection with prepared statements
 */
function eventsPrepareQuery($pdo, $query, $params = []) {
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return false;
    }
}

/**
 * Prevent XSS attacks
 */
function eventsEscapeOutput($output) {
    return htmlspecialchars((string)$output, ENT_QUOTES, 'UTF-8');
}

/**
 * Validate CSRF token
 */
function eventsValidateCSRFToken($token) {
    if (!isset($_SESSION['_csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['_csrf_token'], (string)$token);
}

/**
 * Generate CSRF token
 */
function eventsGenerateCSRFToken() {
    if (!isset($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Rate limiting
 */
function eventsCheckRateLimit($userId, $action, $limit = 10, $window = 60) {
    $key = "events_ratelimit_{$userId}_{$action}";
    $count = apcu_fetch($key);

    if ($count === false) {
        apcu_store($key, 1, $window);
        return true;
    }

    if ($count >= $limit) {
        return false;
    }

    apcu_inc($key);
    return true;
}

/**
 * Validate user authorization
 */
function eventsCheckUserAuthorization($userId, $resourceId, $resourceType = 'task') {
    global $pdo;

    if (!$pdo instanceof PDO) return false;

    try {
        switch ($resourceType) {
            case 'task':
                $stmt = $pdo->prepare("
                    SELECT id FROM events_task_progress
                    WHERE user_id = ? AND task_id = ?
                ");
                break;

            case 'reward':
                $stmt = $pdo->prepare("
                    SELECT id FROM events_rewards
                    WHERE user_id = ? AND id = ?
                ");
                break;

            case 'raffle':
                $stmt = $pdo->prepare("
                    SELECT id FROM events_raffle_entries
                    WHERE user_id = ? AND raffle_id = ?
                ");
                break;

            default:
                return false;
        }

        $stmt->execute([$userId, $resourceId]);
        return $stmt->fetch() !== false;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Log security events
 */
function eventsLogSecurityEvent($userId, $eventType, $details = []) {
    global $pdo;

    if (!$pdo instanceof PDO) return false;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO events_security_log (user_id, event_type, details, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        return $stmt->execute([
            $userId,
            $eventType,
            json_encode($details),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Detect suspicious activity
 */
function eventsDetectSuspiciousActivity($userId) {
    global $pdo;

    if (!$pdo instanceof PDO) return false;

    try {
        // Check for multiple failed attempts
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM events_security_log
            WHERE user_id = ?
            AND event_type = 'failed_authorization'
            AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");

        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ((int)($result['count'] ?? 0) > 5) {
            return true; // Suspicious activity detected
        }

        return false;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Sanitize file upload
 */
function eventsSanitizeFileUpload($file, $allowedTypes = ['image/jpeg', 'image/png', 'image/webp']) {
    if (!isset($file['tmp_name']) || !isset($file['type'])) {
        return false;
    }

    // Check MIME type
    if (!in_array($file['type'], $allowedTypes)) {
        return false;
    }

    // Check file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return false;
    }

    // Verify file is actually an image
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        return false;
    }

    return true;
}

/**
 * Resolve encryption key for sensitive event data.
 */
function eventsResolveEncryptionKey($key = null) {
    if (is_string($key) && $key !== '') {
        return $key;
    }

    $envKey = getenv('ENCRYPTION_KEY');
    if (is_string($envKey) && $envKey !== '') {
        return $envKey;
    }

    throw new RuntimeException('Events encryption key is not configured.');
}

/**
 * Encrypt sensitive data
 */
function eventsEncryptData($data, $key = null) {
    $key = eventsResolveEncryptionKey($key);

    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);

    if ($encrypted === false) {
        throw new RuntimeException('Events encryption failed.');
    }

    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt sensitive data
 */
function eventsDecryptData($data, $key = null) {
    $key = eventsResolveEncryptionKey($key);

    $data = base64_decode((string)$data, true);
    if ($data === false) {
        throw new RuntimeException('Events encrypted payload is invalid.');
    }

    $iv = substr($data, 0, openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = substr($data, openssl_cipher_iv_length('aes-256-cbc'));

    return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
}
?>
