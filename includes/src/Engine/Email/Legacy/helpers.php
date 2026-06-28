<?php

declare(strict_types=1);

/**
 * Email Module — SMTP/mail() tabanlı e-posta gönderimi.
 * Admin ayarlarından SMTP bilgilerini okur.
 */

/**
 * Send an email using configured driver (smtp, sendmail, or php mail()).
 *
 * @param string $to      Recipient email
 * @param string $subject Email subject
 * @param string $body    HTML body
 * @param array  $options Optional: from_name, from_address, reply_to, headers
 * @return bool
 */
function appSendMail(string $to, string $subject, string $body, array $options = []): bool
{
    global $pdo;

    $settings = function_exists('getAdminSettings') ? getAdminSettings($pdo) : [];

    $driver      = $settings['mail_driver'] ?? 'mail';
    $fromName    = $options['from_name'] ?? $settings['mail_from_name'] ?? 'İçerik Topic';
    $fromAddress = $options['from_address'] ?? $settings['mail_from_address'] ?? 'noreply@localhost';
    $smtpHost    = $settings['smtp_host'] ?? 'localhost';
    $smtpPort    = (int)($settings['smtp_port'] ?? 587);
    $smtpUser    = $settings['smtp_username'] ?? '';
    $smtpPass    = $settings['smtp_password'] ?? '';
    $smtpEnc     = $settings['smtp_encryption'] ?? 'tls';

    // Validate recipient
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        appLogException(new \RuntimeException("Invalid email recipient: {$to}"), ['fn' => 'appSendMail']);
        return false;
    }

    // Build headers
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$fromName} <{$fromAddress}>\r\n";
    $headers .= "Reply-To: " . ($options['reply_to'] ?? $fromAddress) . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    if ($driver === 'smtp' && $smtpHost !== '' && $smtpUser !== '') {
        return appSendMailSmtp($to, $subject, $body, [
            'host' => $smtpHost,
            'port' => $smtpPort,
            'username' => $smtpUser,
            'password' => $smtpPass,
            'encryption' => $smtpEnc,
            'from_name' => $fromName,
            'from_address' => $fromAddress,
        ]);
    }

    // Fallback: PHP mail()
    try {
        return @mail($to, $subject, $body, $headers);
    } catch (Throwable $e) {
        appLogException($e, ['fn' => 'appSendMail', 'driver' => 'mail', 'to' => $to]);
        return false;
    }
}

/**
 * Send email via SMTP using fsockopen (no external dependency).
 */
function appSendMailSmtp(string $to, string $subject, string $body, array $config): bool
{
    $host = $config['host'];
    $port = $config['port'];
    $user = $config['username'];
    $pass = $config['password'];
    $enc  = $config['encryption'] ?? 'tls';
    $fromName = $config['from_name'] ?? '';
    $fromAddr = $config['from_address'] ?? $user;

    try {
        $prefix = ($enc === 'ssl') ? 'ssl://' : '';
        $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);

        if (!$socket) {
            appLogException(new \RuntimeException("SMTP connection failed: {$errstr} ({$errno})"), ['fn' => 'appSendMailSmtp']);
            return false;
        }

        $response = fgets($socket, 512);

        // EHLO
        fwrite($socket, "EHLO localhost\r\n");
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }

        // STARTTLS if needed
        if ($enc === 'tls') {
            fwrite($socket, "STARTTLS\r\n");
            fgets($socket, 512);
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            fwrite($socket, "EHLO localhost\r\n");
            $response = '';
            while ($line = fgets($socket, 512)) {
                $response .= $line;
                if (substr($line, 3, 1) === ' ') break;
            }
        }

        // AUTH LOGIN
        if ($user !== '') {
            fwrite($socket, "AUTH LOGIN\r\n");
            fgets($socket, 512);
            fwrite($socket, base64_encode($user) . "\r\n");
            fgets($socket, 512);
            fwrite($socket, base64_encode($pass) . "\r\n");
            $authResponse = fgets($socket, 512);
            if (strpos($authResponse, '235') === false) {
                fwrite($socket, "QUIT\r\n");
                fclose($socket);
                appLogException(new \RuntimeException("SMTP auth failed"), ['fn' => 'appSendMailSmtp']);
                return false;
            }
        }

        // MAIL FROM
        fwrite($socket, "MAIL FROM:<{$fromAddr}>\r\n");
        fgets($socket, 512);

        // RCPT TO
        fwrite($socket, "RCPT TO:<{$to}>\r\n");
        fgets($socket, 512);

        // DATA
        fwrite($socket, "DATA\r\n");
        fgets($socket, 512);

        // Message
        $message = "From: {$fromName} <{$fromAddr}>\r\n";
        $message .= "To: {$to}\r\n";
        $message .= "Subject: {$subject}\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "\r\n";
        $message .= $body . "\r\n.\r\n";

        fwrite($socket, $message);
        $dataResponse = fgets($socket, 512);

        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        return strpos($dataResponse, '250') !== false;
    } catch (Throwable $e) {
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
