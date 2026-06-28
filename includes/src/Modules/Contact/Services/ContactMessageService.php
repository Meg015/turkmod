<?php

declare(strict_types=1);

namespace App\Modules\Contact\Services;

use PDO;
use Throwable;

final class ContactMessageService
{
    /** @var array<int,bool> */
    private array $replyAuditColumnCache = [];

    public function __construct(
        private ?ContactSchemaService $schema = null,
        private ?ContactCategoryService $categories = null,
        private ?ContactMailService $mail = null,
    ) {
        $this->schema ??= new ContactSchemaService();
        $this->categories ??= new ContactCategoryService($this->schema);
        $this->mail ??= new ContactMailService();
    }

    /**
     * @return array<string,array{label:string,class:string,icon:string}>
     */
    public function statusLabels(): array
    {
        return [
            'new' => ['label' => 'Yeni', 'class' => 'danger', 'icon' => 'bi-inbox'],
            'replied' => ['label' => 'Yanitlandi', 'class' => 'warning', 'icon' => 'bi-reply'],
            'resolved' => ['label' => 'Cozuldu', 'class' => 'success', 'icon' => 'bi-check2-circle'],
        ];
    }

    /**
     * @return array<string,array{label:string,class:string,icon:string}>
     */
    public function emailStatusLabels(): array
    {
        return [
            'pending' => ['label' => 'Bekliyor', 'class' => 'warning', 'icon' => 'bi-hourglass-split'],
            'sent' => ['label' => 'Gonderildi', 'class' => 'success', 'icon' => 'bi-send-check'],
            'failed' => ['label' => 'Basarisiz', 'class' => 'danger', 'icon' => 'bi-exclamation-octagon'],
        ];
    }

    /**
     * @return array{total:int,new:int,replied:int,resolved:int,unseen:int}
     */
    public function stats(PDO $pdo): array
    {
        $this->schema->ensureMessagesTable($pdo);

        $stats = [
            'total' => 0,
            'new' => 0,
            'replied' => 0,
            'resolved' => 0,
            'unseen' => 0,
        ];

        try {
            $stats['total'] = (int) $pdo->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn();
            foreach (['new', 'replied', 'resolved'] as $status) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM contact_messages WHERE status = ?');
                $stmt->execute([$status]);
                $stats[$status] = (int) $stmt->fetchColumn();
            }
            $stats['unseen'] = (int) $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE seen_at IS NULL AND status = 'new'")->fetchColumn();
        } catch (Throwable $exception) {
            error_log('Contact stats failed: ' . $exception->getMessage());
        }

        return $stats;
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function count(PDO $pdo, array $filters = []): int
    {
        $this->schema->ensureMessagesTable($pdo);

        $status = trim((string) ($filters['status'] ?? ''));
        $categoryId = (int) ($filters['category_id'] ?? 0);
        $query = trim((string) ($filters['q'] ?? ''));

        $where = [];
        $params = [];

        if ($status !== '' && isset($this->statusLabels()[$status])) {
            $where[] = 'm.status = :status';
            $params['status'] = $status;
        }

        if ($categoryId > 0) {
            $where[] = 'm.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        if ($query !== '') {
            $where[] = '(m.sender_name LIKE :query OR m.sender_email LIKE :query OR m.subject LIKE :query OR m.message LIKE :query OR m.category_name_snapshot LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        try {
            $sql = "
                SELECT COUNT(*)
                FROM contact_messages m
                LEFT JOIN contact_categories c ON c.id = m.category_id
                {$whereSql}
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        } catch (Throwable $exception) {
            error_log('Contact message count failed: ' . $exception->getMessage());

            return 0;
        }
    }

    /**
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    public function list(PDO $pdo, array $filters = []): array
    {
        $this->schema->ensureMessagesTable($pdo);
        $hasReplyAuditColumn = $this->supportsReplyAuditColumn($pdo);

        $status = trim((string) ($filters['status'] ?? ''));
        $categoryId = (int) ($filters['category_id'] ?? 0);
        $query = trim((string) ($filters['q'] ?? ''));
        $limit = max(1, min(100, (int) ($filters['limit'] ?? 50)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $where = [];
        $params = [];

        if ($status !== '' && isset($this->statusLabels()[$status])) {
            $where[] = 'm.status = :status';
            $params['status'] = $status;
        }

        if ($categoryId > 0) {
            $where[] = 'm.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        if ($query !== '') {
            $where[] = '(m.sender_name LIKE :query OR m.sender_email LIKE :query OR m.subject LIKE :query OR m.message LIKE :query OR m.category_name_snapshot LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        try {
            $replyAdminSelect = $hasReplyAuditColumn
                ? 'ra.name AS reply_admin_name'
                : 'NULL AS reply_admin_name';
            $replyAdminJoin = $hasReplyAuditColumn
                ? 'LEFT JOIN users ra ON ra.id = m.admin_reply_admin_id'
                : '';

            $sql = "
                SELECT m.*,
                       c.name AS category_name,
                       c.icon AS category_icon,
                       u.name AS member_name,
                       u.email AS member_email,
                       {$replyAdminSelect}
                FROM contact_messages m
                LEFT JOIN contact_categories c ON c.id = m.category_id
                LEFT JOIN users u ON u.id = m.user_id
                {$replyAdminJoin}
                {$whereSql}
                ORDER BY CASE m.status WHEN 'new' THEN 1 WHEN 'replied' THEN 2 WHEN 'resolved' THEN 3 ELSE 4 END,
                         m.created_at DESC,
                         m.id DESC
                LIMIT {$limit} OFFSET {$offset}
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return array_map([$this, 'decorateRow'], $rows);
        } catch (Throwable $exception) {
            error_log('Contact message list failed: ' . $exception->getMessage());

            return [];
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public function find(PDO $pdo, int $messageId): ?array
    {
        if ($messageId <= 0) {
            return null;
        }

        $this->schema->ensureMessagesTable($pdo);
        $hasReplyAuditColumn = $this->supportsReplyAuditColumn($pdo);

        try {
            $replyAdminSelect = $hasReplyAuditColumn
                ? 'ra.name AS reply_admin_name'
                : 'NULL AS reply_admin_name';
            $replyAdminJoin = $hasReplyAuditColumn
                ? 'LEFT JOIN users ra ON ra.id = m.admin_reply_admin_id'
                : '';

            $stmt = $pdo->prepare("
                SELECT m.*,
                       c.name AS category_name,
                       c.icon AS category_icon,
                       u.name AS member_name,
                       u.email AS member_email,
                       {$replyAdminSelect}
                FROM contact_messages m
                LEFT JOIN contact_categories c ON c.id = m.category_id
                LEFT JOIN users u ON u.id = m.user_id
                {$replyAdminJoin}
                WHERE m.id = ?
                LIMIT 1
            ");
            $stmt->execute([$messageId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $this->decorateRow($row) : null;
        } catch (Throwable $exception) {
            return null;
        }
    }

    public function markSeen(PDO $pdo, int $messageId): bool
    {
        if ($messageId <= 0) {
            return false;
        }

        $message = $this->find($pdo, $messageId);
        if (!$message) {
            return false;
        }

        if (!empty($message['seen_at'])) {
            return true;
        }

        try {
            $stmt = $pdo->prepare('UPDATE contact_messages SET seen_at = ' . $this->schema->nowSql($pdo) . ', updated_at = ' . $this->schema->nowSql($pdo) . ' WHERE id = ?');
            $stmt->execute([$messageId]);

            return $stmt->rowCount() > 0;
        } catch (Throwable $exception) {
            return false;
        }
    }

    /**
     * @param array<string,mixed> $input
     * @return array{success:bool,message:string,id:int,mail_sent:bool,mail_error:string,status:string}
     */
    public function submit(PDO $pdo, array $input, ?int $userId = null): array
    {
        $this->schema->ensureSchema($pdo);

        $honeypot = trim((string) ($input['company'] ?? $input['website'] ?? ''));
        if ($honeypot !== '') {
            return [
                'success' => false,
                'message' => 'Spam dogrulamasi basarisiz.',
                'id' => 0,
                'mail_sent' => false,
                'mail_error' => '',
                'status' => 'new',
            ];
        }

        $categoryId = (int) ($input['category_id'] ?? 0);
        $subject = trim((string) ($input['subject'] ?? ''));
        $messageText = trim((string) ($input['message'] ?? ''));
        $name = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));

        if ($categoryId <= 0) {
            return ['success' => false, 'message' => 'Kategori seciniz.', 'id' => 0, 'mail_sent' => false, 'mail_error' => '', 'status' => 'new'];
        }

        $category = $this->categories->find($pdo, $categoryId);
        if (!$category || (int) ($category['is_active'] ?? 0) !== 1) {
            return ['success' => false, 'message' => 'Secilen kategori aktif degil.', 'id' => 0, 'mail_sent' => false, 'mail_error' => '', 'status' => 'new'];
        }

        $isMember = $userId !== null && $userId > 0;
        $memberName = '';
        $memberEmail = '';
        if ($isMember) {
            $member = function_exists('usersGetById') ? usersGetById($pdo, $userId) : null;
            if (!is_array($member)) {
                return ['success' => false, 'message' => 'Uye bilgileri bulunamadi.', 'id' => 0, 'mail_sent' => false, 'mail_error' => '', 'status' => 'new'];
            }

            $memberName = trim((string) ($member['name'] ?? ''));
            $memberEmail = trim((string) ($member['email'] ?? ''));
            $name = $memberName;
            $email = $memberEmail;
        }

        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 160) {
            return ['success' => false, 'message' => 'Ad alanini kontrol edin.', 'id' => 0, 'mail_sent' => false, 'mail_error' => '', 'status' => 'new'];
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            return ['success' => false, 'message' => 'Gecerli bir e-posta adresi girin.', 'id' => 0, 'mail_sent' => false, 'mail_error' => '', 'status' => 'new'];
        }

        if ($subject === '' || mb_strlen($subject) < 3 || mb_strlen($subject) > 190) {
            return ['success' => false, 'message' => 'Konu alanini kontrol edin.', 'id' => 0, 'mail_sent' => false, 'mail_error' => '', 'status' => 'new'];
        }

        if ($messageText === '' || mb_strlen($messageText) < 10 || mb_strlen($messageText) > 5000) {
            return ['success' => false, 'message' => 'Mesaj alanini kontrol edin.', 'id' => 0, 'mail_sent' => false, 'mail_error' => '', 'status' => 'new'];
        }

        $nowSql = $this->schema->nowSql($pdo);
        $ip = trim((string) ($input['submitted_ip'] ?? ''));
        $userAgent = trim((string) ($input['submitted_user_agent'] ?? ''));

        try {
            $sql = 'INSERT INTO contact_messages
                (category_id, category_name_snapshot, category_icon_snapshot, user_id, is_member, sender_name, sender_email, subject, message, status, seen_at, admin_reply_body, admin_reply_sent_at, admin_reply_email_status, admin_reply_email_error, submitted_ip, submitted_user_agent, created_at, updated_at)
                VALUES
                (:category_id, :category_name_snapshot, :category_icon_snapshot, :user_id, :is_member, :sender_name, :sender_email, :subject, :message, \'new\', NULL, NULL, NULL, \'pending\', NULL, :submitted_ip, :submitted_user_agent, ' . $nowSql . ', ' . $nowSql . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'category_id' => $categoryId,
                'category_name_snapshot' => (string) ($category['name'] ?? ''),
                'category_icon_snapshot' => trim((string) ($category['icon'] ?? 'bi-envelope')) ?: 'bi-envelope',
                'user_id' => $isMember ? $userId : null,
                'is_member' => $isMember ? 1 : 0,
                'sender_name' => $name,
                'sender_email' => $email,
                'subject' => $subject,
                'message' => $messageText,
                'submitted_ip' => $ip !== '' ? $ip : null,
                'submitted_user_agent' => $userAgent !== '' ? $userAgent : null,
            ]);

            $messageId = (int) $pdo->lastInsertId();
            $stored = $this->find($pdo, $messageId) ?? [];
            $mailResult = $this->mail->sendAdminNotification($pdo, $stored !== [] ? $stored : [
                'id' => $messageId,
                'sender_name' => $name,
                'sender_email' => $email,
                'subject' => $subject,
                'message' => $messageText,
                'category_name_snapshot' => (string) ($category['name'] ?? ''),
            ], $category);

            $mailSuccess = (bool) ($mailResult['success'] ?? false);
            $mailError = (string) ($mailResult['error'] ?? '');
            $responseMessage = 'Mesajiniz alindi.';
            if (!$mailSuccess) {
                $responseMessage = 'Mesajiniz alindi ama e-posta iletimi tamamlanamadi.';
            } elseif ($mailError !== '') {
                $responseMessage = 'Mesajiniz alindi. Yonetici bildiriminde kismi iletim sorunu olustu.';
            }

            return [
                'success' => true,
                'message' => $responseMessage,
                'id' => $messageId,
                'mail_sent' => $mailSuccess,
                'mail_error' => $mailError,
                'status' => 'new',
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'message' => 'Mesaj kaydedilemedi.',
                'id' => 0,
                'mail_sent' => false,
                'mail_error' => '',
                'status' => 'new',
            ];
        }
    }

    /**
     * @return array{success:bool,message:string,mail_sent:bool,mail_error:string,status:string}
     */
    public function reply(PDO $pdo, int $messageId, string $replyBody, int $adminId = 0, string $adminName = 'Yonetim'): array
    {
        $this->schema->ensureMessagesTable($pdo);

        return $this->replyWithAudit($pdo, $messageId, trim($replyBody), $adminId, $adminName);
    }

    /**
     * @return array{success:bool,message:string,mail_sent:bool,mail_error:string,status:string}
     */
    private function replyWithAudit(PDO $pdo, int $messageId, string $replyBody, int $adminId = 0, string $adminName = 'Yonetim'): array
    {
        if ($messageId <= 0) {
            return ['success' => false, 'message' => 'Mesaj bulunamadi.', 'mail_sent' => false, 'mail_error' => '', 'status' => 'new'];
        }
        if ($replyBody === '' || mb_strlen($replyBody) < 3 || mb_strlen($replyBody) > 5000) {
            return ['success' => false, 'message' => 'Yanit metni gerekli.', 'mail_sent' => false, 'mail_error' => '', 'status' => 'new'];
        }

        $message = $this->find($pdo, $messageId);
        if (!$message) {
            return ['success' => false, 'message' => 'Mesaj bulunamadi.', 'mail_sent' => false, 'mail_error' => '', 'status' => 'new'];
        }

        $status = (string) ($message['status'] ?? 'new');
        $nextStatus = $status === 'resolved' ? 'resolved' : 'replied';
        $existingReplyBody = trim((string) ($message['admin_reply_body'] ?? ''));
        $existingEmailStatus = $this->normalizeEmailStatus((string) ($message['admin_reply_email_status'] ?? 'pending'));
        if ($existingReplyBody !== '' && $existingReplyBody === $replyBody && in_array($existingEmailStatus, ['sent', 'pending'], true)) {
            $alreadySent = $existingEmailStatus === 'sent';

            return [
                'success' => true,
                'message' => $alreadySent
                    ? 'Ayni yanit daha once gonderildi.'
                    : 'Yanit zaten kayitli. E-posta teslim durumu beklemede, tekrar gonderim yapilmadi.',
                'mail_sent' => $alreadySent,
                'mail_error' => $alreadySent ? '' : 'pending',
                'status' => $status,
            ];
        }

        $category = $this->categories->find($pdo, (int) ($message['category_id'] ?? 0)) ?? [
            'name' => (string) ($message['category_name_snapshot'] ?? 'Iletisim'),
            'icon' => (string) ($message['category_icon_snapshot'] ?? 'bi-envelope'),
        ];

        $adminReplyAdminId = $adminId > 0 ? $adminId : null;
        $hasReplyAuditColumn = $this->supportsReplyAuditColumn($pdo);
        $nowSql = $this->schema->nowSql($pdo);
        $ownsTransaction = false;

        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $ownsTransaction = true;
            }

            $setParts = [
                'status = :status',
                'admin_reply_body = :reply_body',
                'admin_reply_email_status = :email_status',
                'admin_reply_email_error = NULL',
                'updated_at = ' . $nowSql,
            ];
            $params = [
                'status' => $nextStatus,
                'reply_body' => $replyBody,
                'email_status' => 'pending',
                'id' => $messageId,
            ];
            if ($hasReplyAuditColumn) {
                $setParts[] = 'admin_reply_admin_id = :admin_reply_admin_id';
                $params['admin_reply_admin_id'] = $adminReplyAdminId;
            }

            $stmt = $pdo->prepare('UPDATE contact_messages SET ' . implode(', ', $setParts) . ' WHERE id = :id');
            $stmt->execute($params);

            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return [
                'success' => false,
                'message' => 'Yanit kaydedilemedi.',
                'mail_sent' => false,
                'mail_error' => '',
                'status' => $status,
            ];
        }

        $messageForMail = $this->find($pdo, $messageId) ?? array_merge($message, [
            'admin_reply_body' => $replyBody,
            'admin_reply_admin_id' => $adminReplyAdminId,
            'status' => $nextStatus,
        ]);
        $mailResult = $this->mail->sendReply($pdo, $messageForMail, $category, $replyBody, $adminName);
        $mailSuccess = (bool) ($mailResult['success'] ?? false);
        $mailError = $mailSuccess ? '' : (string) ($mailResult['error'] ?? 'Yanit e-postasi gonderilemedi.');

        try {
            $setParts = [
                'admin_reply_sent_at = ' . ($mailSuccess ? $nowSql : 'NULL'),
                'admin_reply_email_status = :email_status',
                'admin_reply_email_error = :email_error',
                'updated_at = ' . $nowSql,
            ];
            $params = [
                'admin_reply_admin_id' => $adminReplyAdminId,
                'email_status' => $mailSuccess ? 'sent' : 'failed',
                'email_error' => $mailSuccess ? null : $mailError,
                'id' => $messageId,
            ];
            if ($hasReplyAuditColumn) {
                array_splice($setParts, 1, 0, ['admin_reply_admin_id = :admin_reply_admin_id']);
            } else {
                unset($params['admin_reply_admin_id']);
            }

            $stmt = $pdo->prepare('UPDATE contact_messages SET ' . implode(', ', $setParts) . ' WHERE id = :id');
            $stmt->execute($params);

            return [
                'success' => true,
                'message' => $mailSuccess
                    ? 'Yanit kaydedildi ve e-posta gonderildi.'
                    : 'Yanit kaydedildi ama e-posta gonderilemedi.',
                'mail_sent' => $mailSuccess,
                'mail_error' => $mailError,
                'status' => $nextStatus,
            ];
        } catch (Throwable $exception) {
            return [
                'success' => true,
                'message' => $mailSuccess
                    ? 'Yanit e-posta olarak gonderildi. Durum guncellemesi beklemede.'
                    : 'Yanit kaydedildi ancak e-posta sonucu kayda islenemedi.',
                'mail_sent' => $mailSuccess,
                'mail_error' => $mailError !== '' ? $mailError : 'Durum guncellemesi beklemede.',
                'status' => $nextStatus,
            ];
        }
    }

    public function resolve(PDO $pdo, int $messageId): bool
    {
        if ($messageId <= 0) {
            return false;
        }

        $this->schema->ensureMessagesTable($pdo);

        try {
            $stmt = $pdo->prepare('UPDATE contact_messages SET status = \'resolved\', updated_at = ' . $this->schema->nowSql($pdo) . ' WHERE id = ?');
            $stmt->execute([$messageId]);

            return $stmt->rowCount() > 0;
        } catch (Throwable $exception) {
            return false;
        }
    }

    public function delete(PDO $pdo, int $messageId): bool
    {
        if ($messageId <= 0) {
            return false;
        }

        $this->schema->ensureMessagesTable($pdo);

        try {
            $stmt = $pdo->prepare('DELETE FROM contact_messages WHERE id = ?');
            $stmt->execute([$messageId]);

            return $stmt->rowCount() > 0;
        } catch (Throwable $exception) {
            return false;
        }
    }

    public function openCount(PDO $pdo): int
    {
        $this->schema->ensureMessagesTable($pdo);

        try {
            return (int) $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn();
        } catch (Throwable $exception) {
            return 0;
        }
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function decorateRow(array $row): array
    {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['category_id'] = isset($row['category_id']) ? (int) $row['category_id'] : null;
        $row['user_id'] = isset($row['user_id']) ? (int) $row['user_id'] : null;
        $row['admin_reply_admin_id'] = isset($row['admin_reply_admin_id']) ? (int) $row['admin_reply_admin_id'] : null;
        $row['is_member'] = (int) ($row['is_member'] ?? 0) === 1;
        $row['status'] = $this->normalizeStatus((string) ($row['status'] ?? 'new'));
        $row['admin_reply_email_status'] = $this->normalizeEmailStatus((string) ($row['admin_reply_email_status'] ?? 'pending'));
        $row['category_name_display'] = (string) ($row['category_name'] ?? $row['category_name_snapshot'] ?? '');
        $row['category_icon_display'] = (string) ($row['category_icon'] ?? $row['category_icon_snapshot'] ?? 'bi-envelope');
        $row['sender_name_display'] = trim((string) ($row['sender_name'] ?? '')) !== ''
            ? (string) $row['sender_name']
            : (string) ($row['member_name'] ?? 'Misafir');
        $row['sender_email_display'] = trim((string) ($row['sender_email'] ?? '')) !== ''
            ? (string) $row['sender_email']
            : (string) ($row['member_email'] ?? '');
        $row['reply_admin_name_display'] = trim((string) ($row['reply_admin_name'] ?? ''));

        return $row;
    }

    private function normalizeStatus(string $status): string
    {
        return isset($this->statusLabels()[$status]) ? $status : 'new';
    }

    private function normalizeEmailStatus(string $status): string
    {
        return isset($this->emailStatusLabels()[$status]) ? $status : 'pending';
    }

    private function supportsReplyAuditColumn(PDO $pdo): bool
    {
        $key = spl_object_id($pdo);
        if (!array_key_exists($key, $this->replyAuditColumnCache)) {
            $this->replyAuditColumnCache[$key] = $this->schema->columnExists($pdo, 'contact_messages', 'admin_reply_admin_id');
        }

        return $this->replyAuditColumnCache[$key];
    }
}

