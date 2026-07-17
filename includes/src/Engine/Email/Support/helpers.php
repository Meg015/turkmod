<?php

declare(strict_types=1);

/**
 * Email Module — SMTP/mail() tabanlı e-posta gönderimi.
 * Admin ayarlarından SMTP bilgilerini okur.
 */

if (!function_exists('appSetLastMailResult')) {
    function appSetLastMailResult(array $result): void
    {
        $GLOBALS['app_last_mail_result'] = array_merge([
            'ok' => false,
            'driver' => '',
            'transport' => '',
            'to' => '',
            'subject' => '',
            'from_name' => '',
            'from_address' => '',
            'reply_to' => '',
            'error' => '',
            'response' => '',
            'smtp_code' => null,
            'smtp_response' => '',
            'provider_message_id' => '',
            'exception_class' => '',
            'exception_file' => '',
            'exception_line' => null,
        ], $result);
    }
}

if (!function_exists('appLastMailResult')) {
    function appLastMailResult(): array
    {
        $result = $GLOBALS['app_last_mail_result'] ?? [];
        return is_array($result) ? $result : [];
    }
}

if (!function_exists('appNormalizeMailHeaderValue')) {
    function appNormalizeMailHeaderValue(string $value): string
    {
        return trim(str_replace(["\r", "\n"], '', $value));
    }
}

if (!function_exists('appEncodeMailHeaderValue')) {
    function appEncodeMailHeaderValue(string $value): string
    {
        $value = appNormalizeMailHeaderValue($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_encode_mimeheader') && preg_match('/[^\x20-\x7E]/', $value) === 1) {
            return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
        }

        return $value;
    }
}

if (!function_exists('appNormalizeMailAddress')) {
    function appNormalizeMailAddress(string $value, string $fallback = ''): string
    {
        $value = appNormalizeMailHeaderValue($value);
        if ($value !== '') {
            return $value;
        }

        return appNormalizeMailHeaderValue($fallback);
    }
}

if (!function_exists('appMailAddressDomain')) {
    function appMailAddressDomain(string $address): string
    {
        $address = appNormalizeMailHeaderValue($address);
        if ($address === '' || filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            return '';
        }

        $atPos = strrpos($address, '@');
        if ($atPos === false) {
            return '';
        }

        $domain = strtolower(trim(substr($address, $atPos + 1)));
        $domain = trim($domain, "[]");

        return $domain;
    }
}

if (!function_exists('appMailAddressLooksPlaceholder')) {
    function appMailAddressLooksPlaceholder(string $address): bool
    {
        $domain = appMailAddressDomain($address);
        if ($domain === '') {
            return true;
        }

        $reservedDomains = ['localhost', 'localhost.localdomain', 'localdomain'];
        if (in_array($domain, $reservedDomains, true)) {
            return true;
        }

        return str_ends_with($domain, '.test')
            || str_ends_with($domain, '.invalid')
            || str_ends_with($domain, '.localhost')
            || str_ends_with($domain, '.example');
    }
}

if (!function_exists('appResolveMailAddress')) {
    /**
     * Resolve a usable mail address, preferring a real mailbox over placeholders.
     *
     * @param array<int,string> $preferredFallbacks
     */
    function appResolveMailAddress(string $value, string $fallback = '', array $preferredFallbacks = []): string
    {
        $candidate = appNormalizeMailAddress($value, $fallback);
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL) !== false && !appMailAddressLooksPlaceholder($candidate)) {
            return $candidate;
        }

        foreach ($preferredFallbacks as $preferred) {
            $preferred = appNormalizeMailAddress((string) $preferred);
            if ($preferred !== '' && filter_var($preferred, FILTER_VALIDATE_EMAIL) !== false && !appMailAddressLooksPlaceholder($preferred)) {
                return $preferred;
            }
        }

        return $candidate !== '' ? $candidate : appNormalizeMailAddress($fallback);
    }
}

if (!function_exists('appMailMessageIdDomain')) {
    function appMailMessageIdDomain(string $fromAddress, string $smtpHost = ''): string
    {
        $domain = appMailAddressDomain($fromAddress);
        if ($domain !== '') {
            return $domain;
        }

        $candidate = trim(str_replace(["\r", "\n"], '', $smtpHost));
        $candidate = preg_replace('~^(?:ssl|tls)://~i', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/:\d+$/', '', $candidate) ?? $candidate;
        $candidate = trim($candidate, "[]");
        if ($candidate !== '' && preg_match('/^[a-z0-9.-]+$/i', $candidate) === 1) {
            return strtolower($candidate);
        }

        $host = gethostname();
        if (is_string($host) && $host !== '' && preg_match('/^[a-z0-9.-]+$/i', $host) === 1) {
            return strtolower($host);
        }

        return 'localhost';
    }
}

if (!function_exists('appGenerateMailMessageId')) {
    function appGenerateMailMessageId(string $fromAddress, string $smtpHost = ''): string
    {
        try {
            $random = bin2hex(random_bytes(12));
        } catch (Throwable $e) {
            $random = str_replace('.', '', uniqid('', true));
        }

        $domain = appMailMessageIdDomain($fromAddress, $smtpHost);

        return '<' . $random . '.' . dechex((int) floor(microtime(true) * 1000000)) . '@' . $domain . '>';
    }
}

if (!function_exists('appBuildMailBody')) {
    function appBuildMailBody(string $body): string
    {
        $body = preg_replace('/\r\n|\r|\n/', "\r\n", $body) ?? $body;

        if (function_exists('quoted_printable_encode')) {
            $body = quoted_printable_encode($body);
        }

        return preg_replace('/(?m)^\./', '..', $body) ?? $body;
    }
}

if (!function_exists('emailLogsTableExists')) {
    function emailLogsTableExists(PDO $pdo): bool
    {
        try {
            $stmt = $pdo->prepare('
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
            ');
            $stmt->execute(['email_logs']);

            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('emailLogsEnsureSchema')) {
    function emailLogsEnsureSchema(?PDO $pdo): void
    {
        if (!$pdo) {
            return;
        }
        if (emailLogsTableExists($pdo)) {
            return;
        }
        throw new RuntimeException('Missing email_logs; run Admin Panel > Database Synchronization.');
    }
}

if (!function_exists('emailLogsSanitizeValue')) {
    function emailLogsSanitizeValue($value, string $key = '')
    {
        $sensitivePattern = '/(?:password|secret|token|authorization|cookie)/i';

        if (is_array($value)) {
            if ($key !== '' && preg_match($sensitivePattern, $key) === 1) {
                return '[masked]';
            }

            $sanitized = [];
            foreach ($value as $childKey => $childValue) {
                $sanitized[$childKey] = emailLogsSanitizeValue($childValue, (string) $childKey);
            }

            return $sanitized;
        }

        if (is_object($value)) {
            if ($key !== '' && preg_match($sensitivePattern, $key) === 1) {
                return '[masked]';
            }

            if ($value instanceof \JsonSerializable) {
                return emailLogsSanitizeValue($value->jsonSerialize(), $key);
            }

            return emailLogsSanitizeValue((array) $value, $key);
        }

        if (is_resource($value)) {
            return '[resource]';
        }

        if ($key !== '' && preg_match($sensitivePattern, $key) === 1) {
            return '[masked]';
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        return trim((string) $value);
    }
}

if (!function_exists('emailLogsSanitizeContext')) {
    function emailLogsSanitizeContext(array $context): array
    {
        $sanitized = [];
        foreach ($context as $key => $value) {
            $sanitized[$key] = emailLogsSanitizeValue($value, (string) $key);
        }

        return $sanitized;
    }
}

if (!function_exists('emailLogsStatusMeta')) {
    function emailLogsStatusMeta(?string $status): array
    {
        return match (strtolower(trim((string) $status))) {
            'sent' => ['label' => 'Gönderildi', 'class' => 'success', 'icon' => 'bi-check2-circle'],
            'failed' => ['label' => 'Hatalı', 'class' => 'danger', 'icon' => 'bi-exclamation-octagon'],
            'queued' => ['label' => 'Kuyrukta', 'class' => 'warning', 'icon' => 'bi-hourglass-split'],
            'processing' => ['label' => 'İşleniyor', 'class' => 'info', 'icon' => 'bi-arrow-repeat'],
            'skipped' => ['label' => 'Atlandı', 'class' => 'secondary', 'icon' => 'bi-dash-circle'],
            default => ['label' => 'Bilinmiyor', 'class' => 'secondary', 'icon' => 'bi-envelope'],
        };
    }
}

if (!function_exists('emailLogsSourceLabel')) {
    function emailLogsSourceLabel(string $source): string
    {
        $normalized = strtolower(trim($source));
        return match ($normalized) {
            'auth' => 'Kimlik Doğrulama',
            'contact' => 'İletişim',
            'events' => 'Etkinlikler',
            'notifications' => 'Bildirimler',
            'settings' => 'Ayarlar',
            'system' => 'Sistem',
            'manual' => 'Manuel',
            'cron' => 'Cron',
            default => $source !== '' ? ucwords(str_replace(['-', '_'], ' ', $source)) : 'Sistem',
        };
    }
}

if (!function_exists('emailLogsStatusLabel')) {
    function emailLogsStatusLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            'sent' => 'Gönderildi',
            'failed' => 'Hatalı',
            'queued' => 'Kuyrukta',
            'processing' => 'İşleniyor',
            'skipped' => 'Atlandı',
            default => $status !== '' ? ucwords(str_replace(['-', '_'], ' ', $status)) : 'Bilinmiyor',
        };
    }
}

if (!function_exists('emailLogsContextLabel')) {
    function emailLogsContextLabel(string $key): string
    {
        static $map = [
            'status' => 'Durum',
            'source' => 'Kaynak',
            'source_key' => 'Kaynak anahtarı',
            'driver' => 'Sürücü',
            'transport' => 'Aktarım',
            'recipient_email' => 'Alıcı e-postası',
            'recipient_name' => 'Alıcı adı',
            'subject' => 'Konu',
            'notification_id' => 'Bildirim ID',
            'queue_id' => 'Kuyruk ID',
            'user_id' => 'Kullanıcı ID',
            'attempt_no' => 'Deneme',
            'max_attempts' => 'Maks. deneme',
            'provider_message_id' => 'Sağlayıcı ID',
            'provider_response' => 'Sağlayıcı yanıtı',
            'smtp_code' => 'SMTP kodu',
            'smtp_response' => 'SMTP yanıtı',
            'error_message' => 'Hata mesajı',
            'exception_class' => 'İstisna sınıfı',
            'exception_file' => 'İstisna dosyası',
            'exception_line' => 'İstisna satırı',
            'from_name' => 'Gönderen adı',
            'from_address' => 'Gönderen e-postası',
            'reply_to' => 'Yanıt adresi',
            'smtp_host' => 'SMTP sunucusu',
            'smtp_port' => 'SMTP portu',
            'smtp_encryption' => 'SMTP şifreleme',
            'subject_length' => 'Konu uzunluğu',
            'body_length' => 'Gövde uzunluğu',
            'template_key' => 'Şablon',
            'exception' => 'İstisna',
            'result' => 'Sonuç',
        ];

        return $map[$key] ?? ucwords(str_replace(['-', '_'], ' ', $key));
    }
}

if (!function_exists('emailLogsContextValueText')) {
    function emailLogsContextValueText($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Evet' : 'Hayır';
        }

        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return is_string($encoded) && $encoded !== '' ? $encoded : '';
        }

        if (is_object($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return is_string($encoded) && $encoded !== '' ? $encoded : '[nesne]';
        }

        $text = trim((string) $value);
        return $text !== '' ? $text : '';
    }
}

/**
 * @return array{where:string,params:array<string,string>}
 */
if (!function_exists('emailLogsBuildWhere')) {
    function emailLogsBuildWhere(
        string $search = '',
        string $status = '',
        string $source = '',
        string $driver = '',
        string $dateFrom = '',
        string $dateTo = '',
        string $prefix = 'e.'
    ): array {
        $where = ['1=1'];
        $params = [];

        $statusCol = $prefix . 'status';
        $sourceCol = $prefix . 'source';
        $sourceKeyCol = $prefix . 'source_key';
        $driverCol = $prefix . 'driver';
        $transportCol = $prefix . 'transport';
        $recipientEmailCol = $prefix . 'recipient_email';
        $recipientNameCol = $prefix . 'recipient_name';
        $subjectCol = $prefix . 'subject';
        $errorMessageCol = $prefix . 'error_message';
        $providerResponseCol = $prefix . 'provider_response';
        $smtpResponseCol = $prefix . 'smtp_response';
        $contextCol = $prefix . 'context_json';
        $createdCol = $prefix . 'created_at';
        $queueIdCol = $prefix . 'queue_id';
        $notificationIdCol = $prefix . 'notification_id';
        $userIdCol = $prefix . 'user_id';
        $providerMessageIdCol = $prefix . 'provider_message_id';

        if ($search !== '') {
            $where[] = "(
                {$recipientEmailCol} LIKE :search
                OR {$recipientNameCol} LIKE :search
                OR {$subjectCol} LIKE :search
                OR {$statusCol} LIKE :search
                OR {$sourceCol} LIKE :search
                OR {$sourceKeyCol} LIKE :search
                OR {$driverCol} LIKE :search
                OR {$transportCol} LIKE :search
                OR {$errorMessageCol} LIKE :search
                OR {$providerResponseCol} LIKE :search
                OR {$smtpResponseCol} LIKE :search
                OR {$providerMessageIdCol} LIKE :search
                OR CAST({$queueIdCol} AS CHAR) LIKE :search
                OR CAST({$notificationIdCol} AS CHAR) LIKE :search
                OR CAST({$userIdCol} AS CHAR) LIKE :search
                OR CAST({$contextCol} AS CHAR) LIKE :search
            )";
            $params['search'] = '%' . $search . '%';
        }
        if ($status !== '') {
            $where[] = "{$statusCol} = :status";
            $params['status'] = $status;
        }
        if ($source !== '') {
            $where[] = "{$sourceCol} = :source";
            $params['source'] = $source;
        }
        if ($driver !== '') {
            $where[] = "{$driverCol} = :driver";
            $params['driver'] = $driver;
        }
        if ($dateFrom !== '') {
            $where[] = "{$createdCol} >= :date_from";
            $params['date_from'] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '') {
            $where[] = "{$createdCol} < :date_to";
            $params['date_to'] = date('Y-m-d H:i:s', strtotime($dateTo . ' +1 day'));
        }

        return ['where' => implode(' AND ', $where), 'params' => $params];
    }
}

if (!function_exists('emailLogsGetList')) {
    function emailLogsGetList(
        PDO $pdo,
        string $search = '',
        string $status = '',
        string $source = '',
        string $driver = '',
        int $page = 1,
        int $perPage = 10,
        string $dateFrom = '',
        string $dateTo = ''
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(10, $perPage));

        $filter = emailLogsBuildWhere($search, $status, $source, $driver, $dateFrom, $dateTo, 'e.');
        $offset = ($page - 1) * $perPage;

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM email_logs e WHERE {$filter['where']}");
        $countStmt->execute($filter['params']);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT e.* FROM email_logs e WHERE {$filter['where']} ORDER BY e.created_at DESC, e.id DESC LIMIT :limit OFFSET :offset");
        foreach ($filter['params'] as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }
}

if (!function_exists('emailLogsClearAll')) {
    function emailLogsClearAll(PDO $pdo): int
    {
        if (!emailLogsTableExists($pdo)) {
            return 0;
        }

        $count = (int) $pdo->query("SELECT COUNT(*) FROM email_logs")->fetchColumn();
        if ($count <= 0) {
            return 0;
        }

        try {
            $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable $e) {
            $driver = '';
        }

        try {
            if ($driver === 'sqlite') {
                $pdo->exec("DELETE FROM email_logs");
                $pdo->exec("DELETE FROM sqlite_sequence WHERE name = 'email_logs'");
            } else {
                $pdo->exec("TRUNCATE TABLE email_logs");
            }
        } catch (Throwable $e) {
            $pdo->exec("DELETE FROM email_logs");
            if ($driver === 'sqlite') {
                try {
                    $pdo->exec("DELETE FROM sqlite_sequence WHERE name = 'email_logs'");
                } catch (Throwable $ignored) {
                }
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                try {
                    $pdo->exec("ALTER TABLE email_logs AUTO_INCREMENT = 1");
                } catch (Throwable $ignored) {
                }
            }
        }

        return $count;
    }
}

if (!function_exists('emailLogsGetStats')) {
    function emailLogsGetStats(PDO $pdo): array
    {
        return [
            'total' => (int) $pdo->query("SELECT COUNT(*) FROM email_logs")->fetchColumn(),
            'total_24h' => (int) $pdo->query("SELECT COUNT(*) FROM email_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetchColumn(),
            'total_7d' => (int) $pdo->query("SELECT COUNT(*) FROM email_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
            'sent_24h' => (int) $pdo->query("SELECT COUNT(*) FROM email_logs WHERE status = 'sent' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetchColumn(),
            'failed_24h' => (int) $pdo->query("SELECT COUNT(*) FROM email_logs WHERE status = 'failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetchColumn(),
            'sources' => (int) $pdo->query("SELECT COUNT(DISTINCT source) FROM email_logs WHERE source IS NOT NULL AND source <> ''")->fetchColumn(),
        ];
    }
}

if (!function_exists('emailLogsGetStatuses')) {
    function emailLogsGetStatuses(PDO $pdo): array
    {
        try {
            $stmt = $pdo->query("SELECT DISTINCT status FROM email_logs WHERE status IS NOT NULL AND status <> '' ORDER BY status");
            return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('emailLogsGetSources')) {
    function emailLogsGetSources(PDO $pdo): array
    {
        try {
            $stmt = $pdo->query("SELECT DISTINCT source FROM email_logs WHERE source IS NOT NULL AND source <> '' ORDER BY source");
            return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('emailLogsGetDrivers')) {
    function emailLogsGetDrivers(PDO $pdo): array
    {
        try {
            $stmt = $pdo->query("SELECT DISTINCT driver FROM email_logs WHERE driver IS NOT NULL AND driver <> '' ORDER BY driver");
            return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('emailLogsFormatContext')) {
    function emailLogsFormatContext(?string $contextJson): string
    {
        $raw = trim((string) $contextJson);
        if ($raw === '') {
            return 'Ek detay yok';
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || $decoded === []) {
            return strlen($raw) > 320 ? substr($raw, 0, 317) . '...' : $raw;
        }

        $parts = [];
        foreach (['source', 'source_key', 'status', 'driver', 'transport', 'queue_id', 'notification_id', 'user_id', 'attempt_no', 'max_attempts'] as $key) {
            if (!array_key_exists($key, $decoded) || $decoded[$key] === null || $decoded[$key] === '') {
                continue;
            }
            $valueText = emailLogsContextValueText($decoded[$key]);
            if ($valueText === '') {
                continue;
            }

            if ($key === 'status') {
                $valueText = emailLogsStatusLabel($valueText);
            }

            $parts[] = emailLogsContextLabel($key) . ': ' . $valueText;
        }

        if ($parts === []) {
            foreach ($decoded as $key => $value) {
                if ($value === null || $value === '' || is_array($value) || is_object($value)) {
                    continue;
                }
                $parts[] = emailLogsContextLabel((string) $key) . ': ' . (string) $value;
                if (count($parts) >= 6) {
                    break;
                }
            }
        }

        return $parts !== [] ? implode(' | ', $parts) : 'Ek detay yok';
    }
}

if (!function_exists('appEmailLog')) {
    function appEmailLog(?PDO $pdo, array $context = []): ?int
    {
        if (!$pdo) {
            return null;
        }

        if (function_exists('emailLogsEnsureSchema')) {
            emailLogsEnsureSchema($pdo);
        }
        if (function_exists('emailLogsTableExists') && !emailLogsTableExists($pdo)) {
            return null;
        }

        $status = strtolower(trim((string) ($context['status'] ?? 'sent')));
        if (!in_array($status, ['sent', 'failed', 'queued', 'processing', 'skipped'], true)) {
            $status = 'sent';
        }

        $source = strtolower(trim((string) ($context['source'] ?? 'system')));
        if ($source === '') {
            $source = 'system';
        }

        $sourceKey = trim((string) ($context['source_key'] ?? 'direct'));
        if ($sourceKey === '') {
            $sourceKey = 'direct';
        }

        $recipientEmail = trim((string) ($context['recipient_email'] ?? ''));
        if ($recipientEmail === '') {
            return null;
        }

        $subject = trim((string) ($context['subject'] ?? ''));
        if ($subject === '') {
            $subject = '(Başlıksız)';
        }

        $recipientName = trim((string) ($context['recipient_name'] ?? ''));
        $driver = trim((string) ($context['driver'] ?? ''));
        $transport = trim((string) ($context['transport'] ?? ''));
        $providerMessageId = trim((string) ($context['provider_message_id'] ?? ''));
        $providerResponse = trim((string) ($context['provider_response'] ?? ''));
        $smtpResponse = trim((string) ($context['smtp_response'] ?? ''));
        $errorMessage = trim((string) ($context['error_message'] ?? ''));
        $exceptionClass = trim((string) ($context['exception_class'] ?? ''));
        $exceptionFile = trim((string) ($context['exception_file'] ?? ''));
        $exceptionLine = isset($context['exception_line']) && $context['exception_line'] !== '' ? (int) $context['exception_line'] : null;
        $smtpCode = isset($context['smtp_code']) && $context['smtp_code'] !== '' ? (int) $context['smtp_code'] : null;
        $notificationId = isset($context['notification_id']) && $context['notification_id'] !== '' ? (int) $context['notification_id'] : null;
        $queueId = isset($context['queue_id']) && $context['queue_id'] !== '' ? (int) $context['queue_id'] : null;
        $userId = isset($context['user_id']) && $context['user_id'] !== '' ? (int) $context['user_id'] : null;
        $attemptNo = isset($context['attempt_no']) && $context['attempt_no'] !== '' ? (int) $context['attempt_no'] : null;
        $maxAttempts = isset($context['max_attempts']) && $context['max_attempts'] !== '' ? (int) $context['max_attempts'] : null;
        $sanitizedContext = emailLogsSanitizeContext($context);
        $contextJson = $sanitizedContext !== []
            ? json_encode($sanitizedContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;
        if (!is_string($contextJson) || $contextJson === '') {
            $contextJson = null;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO email_logs
                    (status, source, source_key, recipient_email, recipient_name, subject, driver, transport, notification_id, queue_id, user_id, attempt_no, max_attempts, provider_message_id, provider_response, smtp_code, smtp_response, error_message, exception_class, exception_file, exception_line, context_json)
                VALUES
                    (:status, :source, :source_key, :recipient_email, :recipient_name, :subject, :driver, :transport, :notification_id, :queue_id, :user_id, :attempt_no, :max_attempts, :provider_message_id, :provider_response, :smtp_code, :smtp_response, :error_message, :exception_class, :exception_file, :exception_line, :context_json)
            ");
            $stmt->execute([
                'status' => $status,
                'source' => $source,
                'source_key' => mb_substr($sourceKey, 0, 100),
                'recipient_email' => mb_substr($recipientEmail, 0, 255),
                'recipient_name' => $recipientName !== '' ? mb_substr($recipientName, 0, 255) : null,
                'subject' => mb_substr($subject, 0, 255),
                'driver' => $driver !== '' ? mb_substr($driver, 0, 20) : null,
                'transport' => $transport !== '' ? mb_substr($transport, 0, 20) : null,
                'notification_id' => $notificationId,
                'queue_id' => $queueId,
                'user_id' => $userId,
                'attempt_no' => $attemptNo,
                'max_attempts' => $maxAttempts,
                'provider_message_id' => $providerMessageId !== '' ? mb_substr($providerMessageId, 0, 255) : null,
                'provider_response' => $providerResponse !== '' ? $providerResponse : null,
                'smtp_code' => $smtpCode,
                'smtp_response' => $smtpResponse !== '' ? $smtpResponse : null,
                'error_message' => $errorMessage !== '' ? $errorMessage : null,
                'exception_class' => $exceptionClass !== '' ? mb_substr($exceptionClass, 0, 255) : null,
                'exception_file' => $exceptionFile !== '' ? mb_substr($exceptionFile, 0, 500) : null,
                'exception_line' => $exceptionLine,
                'context_json' => $contextJson,
            ]);

            return (int) $pdo->lastInsertId();
        } catch (Throwable $e) {
            error_log('Email log insert failed: ' . $e->getMessage());
            return null;
        }
    }
}

/**
 * Send an email using configured driver (smtp, sendmail, or php mail()).
 *
 * @param string $to      Recipient email
 * @param string $subject Email subject
 * @param string $body    HTML body
 * @param array  $options Optional: from_name, from_address, reply_to, headers, settings
 * @return bool
 */
function appSendMail(string $to, string $subject, string $body, array $options = []): bool
{
    global $pdo;

    $liveSettings = function_exists('getAdminSettings') ? getAdminSettings($pdo) : [];
    $overrideSettings = isset($options['settings']) && is_array($options['settings']) ? $options['settings'] : [];
    $settings = is_array($liveSettings) ? array_replace($liveSettings, $overrideSettings) : $overrideSettings;

    $driver = strtolower(trim((string) ($settings['mail_driver'] ?? 'mail')));
    $fromName = appNormalizeMailHeaderValue((string) ($options['from_name'] ?? $settings['mail_from_name'] ?? 'İçerik Topic'));
    if ($fromName === '') {
        $fromName = appNormalizeMailHeaderValue((string) ($settings['site_name'] ?? 'İçerik Topic'));
    }
    $smtpHost = appNormalizeMailHeaderValue((string) ($settings['smtp_host'] ?? 'localhost'));
    $smtpPort = (int) ($settings['smtp_port'] ?? 587);
    $smtpUser = appNormalizeMailHeaderValue((string) ($settings['smtp_username'] ?? ''));
    $smtpPass = (string) ($settings['smtp_password'] ?? '');
    $smtpEnc = strtolower(appNormalizeMailHeaderValue((string) ($settings['smtp_encryption'] ?? 'tls')));
    $fromAddress = appResolveMailAddress(
        (string) ($options['from_address'] ?? $settings['mail_from_address'] ?? ''),
        '',
        [$smtpUser]
    );
    $replyTo = appResolveMailAddress(
        (string) ($options['reply_to'] ?? $fromAddress),
        $fromAddress,
        [$smtpUser]
    );
    $encodedSubject = appEncodeMailHeaderValue($subject);
    $encodedFromName = appEncodeMailHeaderValue($fromName);
    $emailLogContext = isset($options['email_log']) && is_array($options['email_log']) ? $options['email_log'] : [];
    $emailLogContext = array_merge([
        'source' => 'system',
        'source_key' => 'direct',
    ], $emailLogContext);
    $subjectLength = function_exists('mb_strlen') ? mb_strlen($subject, 'UTF-8') : strlen($subject);
    $bodyLength = function_exists('mb_strlen') ? mb_strlen($body, 'UTF-8') : strlen($body);
    $writeEmailLog = static function (bool $ok) use ($pdo, $emailLogContext, $to, $subject, $driver, $smtpHost, $smtpPort, $smtpEnc, $fromName, $fromAddress, $replyTo, $bodyLength, $subjectLength): void {
        if (!function_exists('appEmailLog')) {
            return;
        }

        $mailResult = function_exists('appLastMailResult') ? appLastMailResult() : [];
        appEmailLog($pdo, array_merge($emailLogContext, [
            'status' => $ok ? 'sent' : 'failed',
            'recipient_email' => $to,
            'recipient_name' => trim((string) ($emailLogContext['recipient_name'] ?? '')),
            'subject' => $subject,
            'driver' => (string) ($mailResult['driver'] ?? $driver),
            'transport' => (string) ($mailResult['transport'] ?? ($driver === 'smtp' ? 'smtp' : $driver)),
            'provider_message_id' => (string) ($mailResult['provider_message_id'] ?? ''),
            'provider_response' => (string) ($mailResult['response'] ?? ''),
            'smtp_code' => $mailResult['smtp_code'] ?? null,
            'smtp_response' => (string) ($mailResult['smtp_response'] ?? ''),
            'error_message' => (string) ($mailResult['error'] ?? ''),
            'exception_class' => (string) ($mailResult['exception_class'] ?? ''),
            'exception_file' => (string) ($mailResult['exception_file'] ?? ''),
            'exception_line' => $mailResult['exception_line'] ?? null,
            'body_length' => $bodyLength,
            'subject_length' => $subjectLength,
            'from_name' => $fromName,
            'from_address' => $fromAddress,
            'reply_to' => $replyTo,
            'smtp_host' => $smtpHost,
            'smtp_port' => $smtpPort,
            'smtp_encryption' => $smtpEnc,
        ]));
    };

    // Validate recipient
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        appSetLastMailResult([
            'ok' => false,
            'driver' => $driver,
            'transport' => 'none',
            'to' => $to,
            'subject' => $subject,
            'from_name' => $fromName,
            'from_address' => $fromAddress,
            'reply_to' => $replyTo,
            'error' => "Invalid email recipient: {$to}",
        ]);
        $writeEmailLog(false);
        appLogException(new \RuntimeException("Invalid email recipient: {$to}"), ['fn' => 'appSendMail']);
        return false;
    }

    // Build headers
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: quoted-printable',
        'Date: ' . date(DATE_RFC2822),
        'Message-ID: ' . appGenerateMailMessageId($fromAddress, $smtpHost),
        'From: ' . ($encodedFromName !== '' ? $encodedFromName . ' ' : '') . '<' . $fromAddress . '>',
    ];
    if ($replyTo !== '') {
        $headers[] = 'Reply-To: ' . $replyTo;
    }
    $headers[] = 'X-Mailer: PHP/' . phpversion();

    $extraHeaders = $options['headers'] ?? [];
    if (is_string($extraHeaders)) {
        $extraHeaders = preg_split('/\r\n|\r|\n/', $extraHeaders) ?: [];
    }
    if (is_array($extraHeaders)) {
        foreach ($extraHeaders as $extraHeader) {
            $extraHeader = appNormalizeMailHeaderValue((string) $extraHeader);
            if ($extraHeader !== '') {
                $headers[] = $extraHeader;
            }
        }
    }

    if ($driver === 'smtp') {
        if ($smtpHost === '') {
            appSetLastMailResult([
                'ok' => false,
                'driver' => $driver,
                'transport' => 'smtp',
                'to' => $to,
                'subject' => $subject,
                'from_name' => $fromName,
                'from_address' => $fromAddress,
                'reply_to' => $replyTo,
                'error' => 'SMTP sunucusu tanimlanmadi, SMTP ile gonderim baslatilamadi.',
            ]);
            $writeEmailLog(false);

            return false;
        }

        $ok = appSendMailSmtp($to, $subject, $body, [
            'host' => $smtpHost,
            'port' => $smtpPort,
            'username' => $smtpUser,
            'password' => $smtpPass,
            'encryption' => $smtpEnc,
            'from_name' => $fromName,
            'from_address' => $fromAddress,
            'reply_to' => $replyTo,
            'driver' => $driver,
            'to' => $to,
            'subject' => $subject,
        ]);
        $writeEmailLog($ok);
        return $ok;
    }

    // Sendmail and mail share the PHP mail() fallback; keep the transport label distinct for diagnostics.
    $transport = $driver === 'sendmail' ? 'sendmail' : 'mail';
    $mailBody = appBuildMailBody($body);

    // Fallback: PHP mail()
    try {
        $mailError = null;
        set_error_handler(static function (int $severity, string $message) use (&$mailError): bool {
            $mailError = $message;
            return true;
        });

        try {
            $mailParams = '';
            if ($fromAddress !== '' && filter_var($fromAddress, FILTER_VALIDATE_EMAIL) !== false) {
                $mailParams = '-f' . $fromAddress;
            }
            $mailResult = $mailParams !== ''
                ? mail($to, $encodedSubject, $mailBody, implode("\r\n", $headers), $mailParams)
                : mail($to, $encodedSubject, $mailBody, implode("\r\n", $headers));
        } finally {
            restore_error_handler();
        }

        if ($mailResult) {
            appSetLastMailResult([
                'ok' => true,
                'driver' => $driver,
                'transport' => $transport,
                'to' => $to,
                'subject' => $subject,
                'from_name' => $fromName,
                'from_address' => $fromAddress,
                'reply_to' => $replyTo,
            ]);
            $writeEmailLog(true);
            return true;
        }

        $lastError = $mailError ?? '';
        if ($lastError === '') {
            $error = error_get_last();
            if (is_array($error)) {
                $lastError = (string) ($error['message'] ?? '');
            }
        }
        if ($lastError === '') {
            $lastError = 'PHP mail() returned false.';
        }

        appSetLastMailResult([
            'ok' => false,
            'driver' => $driver,
            'transport' => $transport,
            'to' => $to,
            'subject' => $subject,
            'from_name' => $fromName,
            'from_address' => $fromAddress,
            'reply_to' => $replyTo,
            'error' => $lastError,
        ]);
        $writeEmailLog(false);

        return false;
    } catch (Throwable $e) {
        appSetLastMailResult([
            'ok' => false,
            'driver' => $driver,
            'transport' => $transport,
            'to' => $to,
            'subject' => $subject,
            'from_name' => $fromName,
            'from_address' => $fromAddress,
            'reply_to' => $replyTo,
            'error' => $e->getMessage(),
            'exception_class' => get_class($e),
            'exception_file' => $e->getFile(),
            'exception_line' => $e->getLine(),
        ]);
        $writeEmailLog(false);
        appLogException($e, ['fn' => 'appSendMail', 'driver' => 'mail', 'to' => $to]);
        return false;
    }
}

/**
 * Send email via SMTP using fsockopen (no external dependency).
 */
function appSendMailSmtp(string $to, string $subject, string $body, array $config): bool
{
    $host = (string) ($config['host'] ?? '');
    $port = (int) ($config['port'] ?? 587);
    $user = (string) ($config['username'] ?? '');
    $pass = (string) ($config['password'] ?? '');
    $enc  = strtolower((string) ($config['encryption'] ?? 'tls'));
    $fromName = appNormalizeMailHeaderValue((string) ($config['from_name'] ?? ''));
    $fromAddr = appNormalizeMailAddress((string) ($config['from_address'] ?? $user), $user);
    $replyTo = appNormalizeMailAddress((string) ($config['reply_to'] ?? $fromAddr), $fromAddr);
    $driver = (string) ($config['driver'] ?? 'smtp');
    $encodedSubject = appEncodeMailHeaderValue($subject);
    $encodedFromName = appEncodeMailHeaderValue($fromName);
    $ehloHost = appNormalizeMailHeaderValue((string) (gethostname() ?: 'localhost'));
    if ($ehloHost === '' || preg_match('/^[a-z0-9.-]+$/i', $ehloHost) !== 1) {
        $ehloHost = 'localhost';
    }

    $fail = static function (string $error, array $extra = []) use ($driver, $to, $subject, $fromName, $fromAddr, $replyTo): bool {
        appSetLastMailResult(array_merge([
            'ok' => false,
            'driver' => $driver,
            'transport' => 'smtp',
            'to' => $to,
            'subject' => $subject,
            'from_name' => $fromName,
            'from_address' => $fromAddr,
            'reply_to' => $replyTo,
            'error' => $error,
        ], $extra));

        return false;
    };

    $readResponse = static function ($socket): string {
        $response = '';
        while (($line = fgets($socket, 512)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || substr($line, 3, 1) === ' ') {
                break;
            }
        }

        return $response;
    };

    $expectResponse = static function ($socket, array $codes, string $step, array $extra = []) use ($readResponse, $fail): bool {
        $response = $readResponse($socket);
        $trimmed = trim($response);
        $code = preg_match('/^(\d{3})/', $trimmed, $matches) === 1 ? (int) $matches[1] : 0;
        if (!in_array($code, $codes, true)) {
            return $fail($step . ' failed' . ($trimmed !== '' ? ': ' . $trimmed : '.'), array_merge([
                'smtp_code' => $code,
                'smtp_response' => $trimmed,
            ], $extra));
        }

        return true;
    };

    try {
        $prefix = ($enc === 'ssl') ? 'ssl://' : '';
        $socketWarning = '';
        set_error_handler(static function (int $severity, string $message) use (&$socketWarning): bool {
            $socketWarning = trim($message);
            return true;
        });
        try {
            $socket = fsockopen($prefix . $host, $port, $errno, $errstr, 10);
        } finally {
            restore_error_handler();
        }

        if (!$socket) {
            $connectionError = trim($errstr) !== '' ? trim($errstr) : $socketWarning;
            if ($connectionError === '') {
                $connectionError = 'Unknown connection error';
            }
            return $fail("SMTP connection failed: {$connectionError} ({$errno})", [
                'smtp_errno' => $errno,
                'smtp_error' => $connectionError,
            ]);
        }
        stream_set_timeout($socket, 15);
        $mailBody = appBuildMailBody($body);

        if (!$expectResponse($socket, [220], 'SMTP greeting')) {
            fclose($socket);
            return false;
        }

        // EHLO
        fwrite($socket, "EHLO {$ehloHost}\r\n");
        if (!$expectResponse($socket, [250], 'EHLO')) {
            fclose($socket);
            return false;
        }

        // STARTTLS if needed
        if ($enc === 'tls') {
            fwrite($socket, "STARTTLS\r\n");
            if (!$expectResponse($socket, [220], 'STARTTLS')) {
                fclose($socket);
                return false;
            }

            $tlsWarning = '';
            set_error_handler(static function (int $severity, string $message) use (&$tlsWarning): bool {
                $tlsWarning = trim($message);
                return true;
            });
            try {
                $tlsEnabled = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            } finally {
                restore_error_handler();
            }
            if ($tlsEnabled !== true) {
                fclose($socket);
                return $fail('SMTP TLS negotiation failed' . ($tlsWarning !== '' ? ': ' . $tlsWarning : '.'));
            }

            fwrite($socket, "EHLO {$ehloHost}\r\n");
            if (!$expectResponse($socket, [250], 'EHLO after STARTTLS')) {
                fclose($socket);
                return false;
            }
        }

        // AUTH LOGIN
        if ($user !== '') {
            fwrite($socket, "AUTH LOGIN\r\n");
            if (!$expectResponse($socket, [334], 'SMTP AUTH LOGIN')) {
                fclose($socket);
                return false;
            }
            fwrite($socket, base64_encode($user) . "\r\n");
            if (!$expectResponse($socket, [334], 'SMTP username challenge')) {
                fclose($socket);
                return false;
            }
            fwrite($socket, base64_encode($pass) . "\r\n");
            if (!$expectResponse($socket, [235], 'SMTP authentication')) {
                fwrite($socket, "QUIT\r\n");
                fclose($socket);
                return false;
            }
        }

        // MAIL FROM
        fwrite($socket, "MAIL FROM:<{$fromAddr}>\r\n");
        if (!$expectResponse($socket, [250], 'MAIL FROM')) {
            fwrite($socket, "QUIT\r\n");
            fclose($socket);
            return false;
        }

        // RCPT TO
        fwrite($socket, "RCPT TO:<{$to}>\r\n");
        if (!$expectResponse($socket, [250, 251], 'RCPT TO')) {
            fwrite($socket, "QUIT\r\n");
            fclose($socket);
            return false;
        }

        // DATA
        fwrite($socket, "DATA\r\n");
        if (!$expectResponse($socket, [354], 'DATA')) {
            fwrite($socket, "QUIT\r\n");
            fclose($socket);
            return false;
        }

        // Message
        $message = "From: " . ($encodedFromName !== '' ? $encodedFromName . ' ' : '') . "<{$fromAddr}>\r\n";
        $message .= "To: {$to}\r\n";
        $message .= "Reply-To: {$replyTo}\r\n";
        $message .= 'Date: ' . date(DATE_RFC2822) . "\r\n";
        $message .= 'Message-ID: ' . appGenerateMailMessageId($fromAddr, $host) . "\r\n";
        $message .= "Subject: {$encodedSubject}\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: quoted-printable\r\n";
        $message .= "\r\n";
        $smtpBody = preg_replace('/(?m)^\./', '..', $mailBody) ?? $mailBody;
        $message .= $smtpBody . "\r\n.\r\n";

        fwrite($socket, $message);
        $dataResponse = $readResponse($socket);
        $dataResponseTrimmed = trim($dataResponse);
        $dataResponseCode = preg_match('/^(\d{3})/', $dataResponseTrimmed, $matches) === 1 ? (int) $matches[1] : 0;

        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        if ($dataResponseCode !== 250) {
            return $fail('SMTP message delivery failed' . ($dataResponseTrimmed !== '' ? ': ' . $dataResponseTrimmed : '.'), [
                'smtp_code' => $dataResponseCode,
                'smtp_response' => $dataResponseTrimmed,
            ]);
        }

        appSetLastMailResult([
            'ok' => true,
            'driver' => $driver,
            'transport' => 'smtp',
            'to' => $to,
            'subject' => $subject,
            'from_name' => $fromName,
            'from_address' => $fromAddr,
            'reply_to' => $replyTo,
            'response' => $dataResponseTrimmed,
        ]);

        return true;
    } catch (Throwable $e) {
        appSetLastMailResult([
            'ok' => false,
            'driver' => $driver,
            'transport' => 'smtp',
            'to' => $to,
            'subject' => $subject,
            'from_name' => $fromName,
            'from_address' => $fromAddr,
            'reply_to' => $replyTo,
            'error' => $e->getMessage(),
            'exception_class' => get_class($e),
            'exception_file' => $e->getFile(),
            'exception_line' => $e->getLine(),
        ]);
        appLogException($e, ['fn' => 'appSendMailSmtp', 'to' => $to]);
        return false;
    }
}

/**
 * Send password reset email.
 */
function accountEmailService(?PDO $pdo = null): \App\Engine\Email\AccountEmailService
{
    $connection = $pdo ?? ($GLOBALS['pdo'] ?? null);
    return new \App\Engine\Email\AccountEmailService($connection instanceof PDO ? $connection : null);
}

function sendPasswordResetEmail(string $to, string $userName, string $resetUrl, ?int $expiresMinutes = null): bool
{
    $expiresMinutes = $expiresMinutes !== null
        ? max(15, min(1440, $expiresMinutes))
        : (function_exists('getAdminSettings') ? max(15, min(1440, (int) (getAdminSettings(($GLOBALS['pdo'] ?? null) instanceof PDO ? $GLOBALS['pdo'] : null)['password_reset_token_ttl_minutes'] ?? 60))) : 60);
    return accountEmailService()->send('password_reset_request', $to, [
        'username' => $userName,
        'action_url' => $resetUrl,
        'expires_minutes' => (string) $expiresMinutes,
    ]);
}

/**
 * Send welcome/registration email.
 */
function sendWelcomeEmail(string $to, string $userName, string $loginUrl): bool
{
    return accountEmailService()->send('welcome', $to, ['username' => $userName, 'login_url' => $loginUrl]);
}
