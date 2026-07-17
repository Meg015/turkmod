<?php

declare(strict_types=1);

namespace App\Modules\BanAppeals\Services;

use PDO;
use Throwable;

final class BanAppealService
{
    public function __construct(
        private ?BanAppealSchemaService $schema = null,
        private ?BanAppealNotificationService $notifications = null,
    ) {
        $this->schema ??= new BanAppealSchemaService();
        $this->notifications ??= new BanAppealNotificationService();
    }

    public function ensureSchema(?PDO $pdo): void
    {
        if ($pdo) {
            $this->schema->ensureSchema($pdo);
        }
    }

    public function ensureMessagesTable(?PDO $pdo): void
    {
        if ($pdo) {
            $this->schema->ensureMessages($pdo);
        }
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'open' => 'Açık',
            'reviewing' => 'İnceleniyor',
            'accepted' => 'Kabul Edildi',
            'rejected' => 'Reddedildi',
            default => 'Bilinmiyor',
        };
    }

    public function submitForUser(PDO $pdo, int $userId, string $message): string
    {
        $message = trim($message);
        if (mb_strlen($message) < 10) {
            return 'Itiraz mesaji en az 10 karakter olmalidir.';
        }
        if (mb_strlen($message) > 3000) {
            return 'Itiraz mesaji en fazla 3000 karakter olabilir.';
        }

        $this->schema->ensureSchema($pdo);
        $rateLimit = $this->checkUserSubmitRateLimit($pdo, $userId);
        if ($rateLimit['error'] !== '') {
            return $rateLimit['error'];
        }

        $activeAppealId = $this->activeId($pdo, $userId);
        if ($activeAppealId) {
            $error = $this->addMessage($pdo, $activeAppealId, $userId, 'user', $message);
            if ($error !== '') {
                return $error;
            }

            $nowSql = $this->schema->nowSql($pdo);
            $pdo->prepare("UPDATE ban_appeals SET updated_at = {$nowSql} WHERE id = :id")->execute(['id' => $activeAppealId]);
            if (function_exists('userActivityLog')) {
                userActivityLog($pdo, $userId, 'ban_appeal_message_added', 'appeal', 'ban_appeal', $activeAppealId, 'Ban itirazi mesaji eklendi', [], $userId);
            }
            $this->notifications->dispatchAdminSubmission($pdo, $userId, $activeAppealId, $message, true);
            $this->hitUserSubmitRateLimit($rateLimit['key'], $rateLimit['window']);

            return '';
        }

        $error = $this->create($pdo, $userId, $message);
        if ($error === '') {
            $this->hitUserSubmitRateLimit($rateLimit['key'], $rateLimit['window']);
        }

        return $error;
    }

    public function addMessage(PDO $pdo, int $appealId, ?int $senderUserId, string $senderType, string $message): string
    {
        $message = trim($message);
        if ($appealId <= 0) {
            return 'Gecersiz itiraz.';
        }
        if (!in_array($senderType, ['user', 'admin', 'system'], true)) {
            $senderType = 'user';
        }
        if (mb_strlen($message) < 2) {
            return 'Mesaj en az 2 karakter olmalidir.';
        }
        if (mb_strlen($message) > 3000) {
            return 'Mesaj en fazla 3000 karakter olabilir.';
        }

        $this->schema->ensureMessages($pdo);
        $nowSql = $this->schema->nowSql($pdo);
        $pdo->prepare("INSERT INTO ban_appeal_messages (appeal_id, sender_user_id, sender_type, message, created_at)
            VALUES (:appeal_id, :sender_user_id, :sender_type, :message, {$nowSql})")
            ->execute([
                'appeal_id' => $appealId,
                'sender_user_id' => $senderUserId && $senderUserId > 0 ? $senderUserId : null,
                'sender_type' => $senderType,
                'message' => $message,
            ]);

        return '';
    }

    /** @return list<array<string,mixed>> */
    public function messages(PDO $pdo, int $appealId): array
    {
        try {
            $this->schema->ensureMessages($pdo);
            $stmt = $pdo->prepare('SELECT m.*, u.username AS sender_name, u.email AS sender_email
                FROM ban_appeal_messages m
                LEFT JOIN users u ON u.id = m.sender_user_id
                WHERE m.appeal_id = ?
                ORDER BY m.created_at ASC, m.id ASC');
            $stmt->execute([$appealId]);

            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($messages as $index => $message) {
                $messages[$index]['sender_role'] = (string) ($message['sender_type'] ?? 'user');
            }

            return $messages;
        } catch (Throwable $e) {
            error_log('Ban appeal messages lookup failed: ' . $e->getMessage());
            return [];
        }
    }

    public function create(PDO $pdo, int $userId, string $message): string
    {
        if ($userId <= 0) {
            return 'Gecersiz kullanici.';
        }
        if (mb_strlen($message) < 10) {
            return 'İtiraz metni en az 10 karakter olmalıdır.';
        }
        if (mb_strlen($message) > 3000) {
            return 'İtiraz metni en fazla 3000 karakter olmalıdır.';
        }

        $this->schema->ensureSchema($pdo);
        $nowSql = $this->schema->nowSql($pdo);
        $pdo->prepare("INSERT INTO ban_appeals (user_id, message, status, created_at) VALUES (:user_id, :message, 'open', {$nowSql})")
            ->execute(['user_id' => $userId, 'message' => $message]);
        $appealId = (int) $pdo->lastInsertId();
        if ($appealId > 0) {
            $this->addMessage($pdo, $appealId, $userId, 'user', $message);
            if (function_exists('userActivityLog')) {
                userActivityLog($pdo, $userId, 'ban_appeal_created', 'appeal', 'ban_appeal', $appealId, 'Ban itirazi olusturuldu', [], $userId);
            }
            $this->notifications->dispatchAdminSubmission($pdo, $userId, $appealId, $message, false);
        }

        return '';
    }

    /** @return list<array<string,mixed>> */
    public function forUser(PDO $pdo, int $userId): array
    {
        $this->schema->ensureSchema($pdo);
        $stmt = $pdo->prepare('SELECT id, message, status, admin_note, created_at, reviewed_at, updated_at FROM ban_appeals WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 50');
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function activeId(PDO $pdo, int $userId): ?int
    {
        try {
            $this->schema->ensureSchema($pdo);
            $stmt = $pdo->prepare("SELECT id FROM ban_appeals WHERE user_id = :user_id AND status IN ('open', 'reviewing') ORDER BY created_at DESC LIMIT 1");
            $stmt->execute(['user_id' => $userId]);
            $id = (int) ($stmt->fetchColumn() ?: 0);

            return $id > 0 ? $id : null;
        } catch (Throwable $e) {
            error_log('Ban appeal active lookup failed: ' . $e->getMessage());
            return null;
        }
    }

    /** @return array{total:int,open:int,reviewing:int,accepted:int,rejected:int} */
    public function stats(PDO $pdo): array
    {
        $this->schema->ensureSchema($pdo);

        return [
            'total' => (int) $pdo->query('SELECT COUNT(*) FROM ban_appeals')->fetchColumn(),
            'open' => (int) $pdo->query("SELECT COUNT(*) FROM ban_appeals WHERE status = 'open'")->fetchColumn(),
            'reviewing' => (int) $pdo->query("SELECT COUNT(*) FROM ban_appeals WHERE status = 'reviewing'")->fetchColumn(),
            'accepted' => (int) $pdo->query("SELECT COUNT(*) FROM ban_appeals WHERE status = 'accepted'")->fetchColumn(),
            'rejected' => (int) $pdo->query("SELECT COUNT(*) FROM ban_appeals WHERE status = 'rejected'")->fetchColumn(),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function forAdmin(PDO $pdo, string $statusFilter = ''): array
    {
        $this->schema->ensureSchema($pdo);
        $where = [];
        $params = [];

        if ($statusFilter !== '' && in_array($statusFilter, ['open', 'reviewing', 'accepted', 'rejected'], true)) {
            $where[] = 'ba.status = :status';
            $params['status'] = $statusFilter;
        }

        $whereStr = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $pdo->prepare("SELECT ba.*, u.username AS user_name, u.email AS user_email,
                   u.status AS user_status, u.is_banned AS user_is_banned, u.ban_reason AS user_ban_reason,
                   reviewer.username AS reviewer_name
            FROM ban_appeals ba
            LEFT JOIN users u ON u.id = ba.user_id
            LEFT JOIN users reviewer ON reviewer.id = ba.reviewed_by
            {$whereStr}
            ORDER BY CASE ba.status WHEN 'open' THEN 1 WHEN 'reviewing' THEN 2 WHEN 'accepted' THEN 3 WHEN 'rejected' THEN 4 ELSE 5 END,
                     ba.created_at DESC
            LIMIT 100");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string,mixed>|null */
    public function forAdminById(PDO $pdo, int $appealId): ?array
    {
        if ($appealId <= 0) {
            return null;
        }

        $this->schema->ensureSchema($pdo);
        $stmt = $pdo->prepare("SELECT ba.*, u.username AS user_name, u.email AS user_email,
                   u.status AS user_status, u.is_banned AS user_is_banned, u.ban_reason AS user_ban_reason,
                   reviewer.username AS reviewer_name
            FROM ban_appeals ba
            LEFT JOIN users u ON u.id = ba.user_id
            LEFT JOIN users reviewer ON reviewer.id = ba.reviewed_by
            WHERE ba.id = :id
            LIMIT 1");
        $stmt->execute(['id' => $appealId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function update(PDO $pdo, int $appealId, string $status, string $adminNote, int $adminId): string
    {
        if (!in_array($status, ['open', 'reviewing', 'accepted', 'rejected'], true)) {
            return 'Geçersiz durum.';
        }

        $this->schema->ensureSchema($pdo);
        $appealStmt = $pdo->prepare('SELECT user_id, status FROM ban_appeals WHERE id = :id LIMIT 1');
        $appealStmt->execute(['id' => $appealId]);
        $appealRow = $appealStmt->fetch(PDO::FETCH_ASSOC);
        if (!$appealRow) {
            return 'Itiraz bulunamadi.';
        }

        $appealUserId = (int) $appealRow['user_id'];
        $oldStatus = (string) $appealRow['status'];
        $note = trim($adminNote);
        $nowSql = $this->schema->nowSql($pdo);
        $stmt = $pdo->prepare("UPDATE ban_appeals SET status = :status, admin_note = :note, reviewed_by = :reviewed_by, reviewed_at = {$nowSql}, updated_at = {$nowSql} WHERE id = :id");
        $stmt->execute([
            'status' => $status,
            'note' => $note !== '' ? $note : null,
            'reviewed_by' => $adminId > 0 ? $adminId : null,
            'id' => $appealId,
        ]);

        if ($note !== '') {
            $this->addMessage($pdo, $appealId, $adminId, 'admin', $note);
        }

        if ($status === 'accepted') {
            $this->unbanUser($pdo, $appealUserId);
        }

        if (function_exists('userActivityLog')) {
            userActivityLog($pdo, $appealUserId, 'ban_appeal_updated', 'appeal', 'ban_appeal', $appealId, 'Ban itirazi guncellendi', [
                'old_status' => $oldStatus,
                'new_status' => $status,
                'has_admin_note' => $note !== '',
            ], $adminId);
        }

        $statusLabel = $this->statusLabel($status);
        $noteLine = $note !== '' ? ' Not: ' . $note : '';
        $this->notifications->dispatchUpdate(
            $pdo,
            $appealUserId,
            $adminId,
            $appealId,
            'Ban itiraziniz "' . $statusLabel . '" durumuna alindi.' . $noteLine,
            $status
        );

        return '';
    }

    public function reply(PDO $pdo, int $appealId, int $adminId, string $message): string
    {
        $message = trim($message);
        if ($appealId <= 0) {
            return 'Itiraz bulunamadi.';
        }
        if ($adminId <= 0) {
            return 'Gecersiz yonetici.';
        }
        if (mb_strlen($message) < 2) {
            return 'Cevap en az 2 karakter olmalidir.';
        }
        if (mb_strlen($message) > 3000) {
            return 'Cevap en fazla 3000 karakter olabilir.';
        }

        $this->schema->ensureSchema($pdo);
        $appealStmt = $pdo->prepare('SELECT user_id, status FROM ban_appeals WHERE id = :id LIMIT 1');
        $appealStmt->execute(['id' => $appealId]);
        $appealRow = $appealStmt->fetch(PDO::FETCH_ASSOC);
        if (!$appealRow) {
            return 'Itiraz bulunamadi.';
        }

        $status = (string) ($appealRow['status'] ?? 'open');
        if (!in_array($status, ['open', 'reviewing'], true)) {
            return 'Kapanmis itiraza yeni cevap eklenemez.';
        }

        $error = $this->addMessage($pdo, $appealId, $adminId, 'admin', $message);
        if ($error !== '') {
            return $error;
        }

        $nowSql = $this->schema->nowSql($pdo);
        $nextStatus = $status === 'open' ? 'reviewing' : $status;
        $pdo->prepare("UPDATE ban_appeals SET status = :status, updated_at = {$nowSql} WHERE id = :id")
            ->execute([
                'status' => $nextStatus,
                'id' => $appealId,
            ]);

        $appealUserId = (int) ($appealRow['user_id'] ?? 0);
        if (function_exists('userActivityLog')) {
            userActivityLog($pdo, $appealUserId, 'ban_appeal_admin_reply_added', 'appeal', 'ban_appeal', $appealId, 'Ban itirazina yonetici cevabi eklendi', [
                'old_status' => $status,
                'new_status' => $nextStatus,
            ], $adminId);
        }

        $this->notifications->dispatchUpdate(
            $pdo,
            $appealUserId,
            $adminId,
            $appealId,
            'Ban itiraziniza yonetim cevabi eklendi.',
            $nextStatus
        );

        return '';
    }

    private function unbanUser(PDO $pdo, int $userId): void
    {
        if (function_exists('usersUnban')) {
            usersUnban($pdo, $userId);
            return;
        }

        \App\Engine\Users\BanService::unban($pdo, $userId);
    }

    /** @return array{key:string,window:int,error:string} */
    private function checkUserSubmitRateLimit(PDO $pdo, int $userId): array
    {
        $limit = $this->appealMessageLimit($pdo);
        $window = $this->appealMessageCooldownMinutes($pdo);
        $key = 'ban_appeal_message:' . $userId;
        if ($limit <= 0 || $window <= 0 || !function_exists('checkRateLimit')) {
            return ['key' => $key, 'window' => $window, 'error' => ''];
        }

        try {
            if (checkRateLimit($key, $limit, $window)) {
                return ['key' => $key, 'window' => $window, 'error' => ''];
            }

            $remainingSeconds = function_exists('getRateLimitRemainingSeconds')
                ? max(1, (int) getRateLimitRemainingSeconds($key, $window))
                : $window * 60;
            $remainingText = $remainingSeconds >= 60
                ? (int) ceil($remainingSeconds / 60) . ' dakika'
                : $remainingSeconds . ' saniye';

            return [
                'key' => $key,
                'window' => $window,
                'error' => 'Cok hizli mesaj gonderiyorsunuz. Lutfen ' . $remainingText . ' sonra tekrar deneyin.',
            ];
        } catch (Throwable $e) {
            error_log('Ban appeal rate limit check failed: ' . $e->getMessage());

            return ['key' => $key, 'window' => $window, 'error' => ''];
        }
    }

    private function hitUserSubmitRateLimit(string $key, int $window): void
    {
        if ($window <= 0 || $key === '' || !function_exists('incrementRateLimit')) {
            return;
        }

        try {
            incrementRateLimit($key, $window);
        } catch (Throwable $e) {
            error_log('Ban appeal rate limit hit failed: ' . $e->getMessage());
        }
    }

    private function appealMessageLimit(PDO $pdo): int
    {
        try {
            $settings = function_exists('getAdminSettings') ? getAdminSettings($pdo) : [];
            $value = (int) ($settings['ban_appeal_message_limit'] ?? 1);

            return max(0, min(100, $value));
        } catch (Throwable $e) {
            error_log('Ban appeal limit setting lookup failed: ' . $e->getMessage());

            return 1;
        }
    }

    private function appealMessageCooldownMinutes(PDO $pdo): int
    {
        try {
            $settings = function_exists('getAdminSettings') ? getAdminSettings($pdo) : [];
            $value = (int) ($settings['ban_appeal_message_cooldown_minutes'] ?? 5);

            return max(0, min(1440, $value));
        } catch (Throwable $e) {
            error_log('Ban appeal cooldown setting lookup failed: ' . $e->getMessage());

            return 5;
        }
    }
}
