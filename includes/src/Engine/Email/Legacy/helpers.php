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
    $fromAddress = appNormalizeMailAddress(
        (string) ($options['from_address'] ?? $settings['mail_from_address'] ?? 'noreply@localhost'),
        (string) ($settings['mail_from_address'] ?? 'noreply@localhost')
    );
    $replyTo = appNormalizeMailAddress(
        (string) ($options['reply_to'] ?? $fromAddress),
        $fromAddress
    );
    $smtpHost = appNormalizeMailHeaderValue((string) ($settings['smtp_host'] ?? 'localhost'));
    $smtpPort = (int) ($settings['smtp_port'] ?? 587);
    $smtpUser = appNormalizeMailHeaderValue((string) ($settings['smtp_username'] ?? ''));
    $smtpPass = (string) ($settings['smtp_password'] ?? '');
    $smtpEnc = strtolower(appNormalizeMailHeaderValue((string) ($settings['smtp_encryption'] ?? 'tls')));
    $encodedSubject = appEncodeMailHeaderValue($subject);
    $encodedFromName = appEncodeMailHeaderValue($fromName);

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
        appLogException(new \RuntimeException("Invalid email recipient: {$to}"), ['fn' => 'appSendMail']);
        return false;
    }

    // Build headers
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
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

            return false;
        }

        return appSendMailSmtp($to, $subject, $body, [
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
    }

    // Sendmail and mail share the PHP mail() fallback; keep the transport label distinct for diagnostics.
    $transport = $driver === 'sendmail' ? 'sendmail' : 'mail';

    // Fallback: PHP mail()
    try {
        $mailError = null;
        set_error_handler(static function (int $severity, string $message) use (&$mailError): bool {
            $mailError = $message;
            return true;
        });

        try {
            $mailResult = mail($to, $encodedSubject, $body, implode("\r\n", $headers));
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
        ]);
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
        $socket = fsockopen($prefix . $host, $port, $errno, $errstr, 10);

        if (!$socket) {
            return $fail("SMTP connection failed: {$errstr} ({$errno})", [
                'smtp_errno' => $errno,
                'smtp_error' => $errstr,
            ]);
        }

        if (!$expectResponse($socket, [220], 'SMTP greeting')) {
            fclose($socket);
            return false;
        }

        // EHLO
        fwrite($socket, "EHLO localhost\r\n");
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

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return $fail('SMTP TLS negotiation failed.');
            }

            fwrite($socket, "EHLO localhost\r\n");
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
        $message .= "Subject: {$encodedSubject}\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "\r\n";
        $message .= $body . "\r\n.\r\n";

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
        ]);
        appLogException($e, ['fn' => 'appSendMailSmtp', 'to' => $to]);
        return false;
    }
}

/**
 * Send password reset email.
 */
function sendPasswordResetEmail(string $to, string $userName, string $resetUrl): bool
{
    $subject = 'Şifre Sıfırlama Talebi';
    $body = <<<HTML
<!DOCTYPE html>
<html lang="tr">
<head><meta charset="UTF-8"></head>
<body style="font-family:Roboto,sans-serif;background:#f8fafc;padding:2rem;">
<div style="max-width:500px;margin:0 auto;background:#fff;border-radius:8px;padding:2rem;border:1px solid #e2e8f0;">
    <h2 style="color:#1e293b;margin-top:0;">Şifre Sıfırlama</h2>
    <p>Merhaba <strong>{$userName}</strong>,</p>
    <p>Hesabınız için bir şifre sıfırlama talebi aldık. Aşağıdaki bağlantıya tıklayarak yeni şifrenizi belirleyebilirsiniz:</p>
    <p style="text-align:center;margin:1.5rem 0;">
        <a href="{$resetUrl}" style="background:#f2a51a;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold;">Şifremi Sıfırla</a>
    </p>
    <p style="color:#64748b;font-size:0.9rem;">Bu bağlantı 1 saat geçerlidir. Eğer bu talebi siz yapmadıysanız, bu e-postayı görmezden gelebilirsiniz.</p>
    <hr style="border:none;border-top:1px solid #e2e8f0;margin:1.5rem 0;">
    <p style="color:#94a3b8;font-size:0.8rem;">Bu e-posta otomatik olarak gönderilmiştir.</p>
</div>
</body>
</html>
HTML;

    return appSendMail($to, $subject, $body);
}

/**
 * Send welcome/registration email.
 */
function sendWelcomeEmail(string $to, string $userName, string $loginUrl): bool
{
    $subject = 'Hoş Geldiniz!';
    $body = <<<HTML
<!DOCTYPE html>
<html lang="tr">
<head><meta charset="UTF-8"></head>
<body style="font-family:Roboto,sans-serif;background:#f8fafc;padding:2rem;">
<div style="max-width:500px;margin:0 auto;background:#fff;border-radius:8px;padding:2rem;border:1px solid #e2e8f0;">
    <h2 style="color:#1e293b;margin-top:0;">Hoş Geldiniz!</h2>
    <p>Merhaba <strong>{$userName}</strong>,</p>
    <p>Hesabınız başarıyla oluşturuldu. Artık giriş yaparak içeriklere erişebilir ve yorum yapabilirsiniz.</p>
    <p style="text-align:center;margin:1.5rem 0;">
        <a href="{$loginUrl}" style="background:#f2a51a;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold;">Giriş Yap</a>
    </p>
    <hr style="border:none;border-top:1px solid #e2e8f0;margin:1.5rem 0;">
    <p style="color:#94a3b8;font-size:0.8rem;">Bu e-posta otomatik olarak gönderilmiştir.</p>
</div>
</body>
</html>
HTML;

    return appSendMail($to, $subject, $body);
}
