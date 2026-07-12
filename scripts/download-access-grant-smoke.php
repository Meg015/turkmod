<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/init.php';

$topicId = (int) $pdo->query("SELECT t.id
    FROM topics t
    INNER JOIN topic_download_links dl ON dl.topic_id = t.id
    WHERE t.status = 'published' AND t.deleted_at IS NULL
    ORDER BY t.id DESC
    LIMIT 1")->fetchColumn();
$userStmt = $pdo->prepare("SELECT u.id
    FROM users u
    WHERE NOT EXISTS (
        SELECT 1 FROM comments c WHERE c.topic_id = :topic_id AND c.user_id = u.id
    )
    ORDER BY u.id ASC
    LIMIT 1");
$userStmt->execute(['topic_id' => $topicId]);
$userId = (int) $userStmt->fetchColumn();
$pair = ['topic_id' => $topicId, 'user_id' => $userId];
if ($topicId <= 0 || $userId <= 0) {
    throw new RuntimeException('Smoke testi için uygun kullanıcı/konu çifti bulunamadı.');
}

$settings = getAdminSettings($pdo);
$settings['download_access_mode'] = 'members_comment';
$settings['download_access_comment_requirement'] = 'approved';
$settings['download_access_grant_mode'] = 'timed';
$settings['download_access_grant_duration_value'] = '1';
$settings['download_access_grant_duration_unit'] = 'minutes';
$settings['download_access_relock_on_comment_delete'] = '1';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$pdo->beginTransaction();
try {
    $insert = $pdo->prepare("INSERT INTO comments (topic_id, user_id, parent_id, body, status, created_at, updated_at)
        VALUES (?, ?, NULL, ?, 'approved', ?, ?)");
    $oldTime = date('Y-m-d H:i:s', time() - 120);
    $insert->execute([(int) $pair['topic_id'], (int) $pair['user_id'], 'download-access-expired-smoke', $oldTime, $oldTime]);
    $expiredCommentId = (int) $pdo->lastInsertId();
    $expiredState = topicDownloadAccessState($pdo, $settings, (int) $pair['topic_id'], (int) $pair['user_id']);
    $assert(($expiredState['reason'] ?? '') === 'comment_expired', 'Süresi dolan hak comment_expired olmadı.');
    $assert((int) ($expiredState['grant_comment_id'] ?? 0) === $expiredCommentId, 'Eski yorum tarihiyle başlangıç hakkı uzlaştırılmadı.');

    $now = date('Y-m-d H:i:s');
    $insert->execute([(int) $pair['topic_id'], (int) $pair['user_id'], 'download-access-active-smoke', $now, $now]);
    $activeCommentId = (int) $pdo->lastInsertId();
    $activeComment = [
        'id' => $activeCommentId,
        'topic_id' => (int) $pair['topic_id'],
        'user_id' => (int) $pair['user_id'],
        'status' => 'approved',
        'deleted_at' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ];
    $assert(topicDownloadApproveAccessGrant($pdo, $settings, $activeComment, $now), 'Aktif hak oluşturulamadı.');
    $activeState = topicDownloadAccessState($pdo, $settings, (int) $pair['topic_id'], (int) $pair['user_id']);
    $assert(empty($activeState['locked']), 'Yeni hak erişimi açmadı.');
    $assert(trim((string) ($activeState['access_until_text'] ?? '')) !== '', 'Aktif süre bilgisi üretilmedi.');

    if (function_exists('notificationDispatch')) {
        $dedupeKey = 'download_access_smoke:' . $activeCommentId;
        $notificationSent = notificationDispatch(
            $pdo,
            'comment_approved',
            (int) $pair['user_id'],
            null,
            'comment',
            $activeCommentId,
            [
                'title' => 'İndirme erişiminiz açıldı',
                'message' => 'Smoke testi: indirme bağlantıları kullanıma hazır.',
                'topic_title' => 'Smoke Konusu',
                'link' => '/konu/smoke-' . (int) $pair['topic_id'],
                'dedupe_key' => $dedupeKey,
            ]
        );
        $assert($notificationSent, 'Yorum onay bildirimi gönderilemedi.');
        $notificationStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE dedupe_key = ?');
        $notificationStmt->execute([$dedupeKey]);
        $assert((int) $notificationStmt->fetchColumn() === 1, 'Yorum onay bildirimi tek kayıt üretmedi.');
    }

    $pdo->prepare('UPDATE comments SET deleted_at = NOW() WHERE id = ?')->execute([$activeCommentId]);
    topicDownloadRevokeAccessGrant($pdo, $activeCommentId, 'comment_deleted');
    $deletedState = topicDownloadAccessState($pdo, $settings, (int) $pair['topic_id'], (int) $pair['user_id']);
    $assert(!empty($deletedState['locked']), 'Yorum silinince erişim kilitlenmedi.');

    $pdo->prepare('UPDATE comments SET deleted_at = NULL WHERE id = ?')->execute([$activeCommentId]);
    $assert(topicDownloadRestoreAccessGrant($pdo, $settings, $activeComment), 'Silinen yorumun geçerli hakkı geri yüklenmedi.');
    $restoredState = topicDownloadAccessState($pdo, $settings, (int) $pair['topic_id'], (int) $pair['user_id']);
    $assert(empty($restoredState['locked']), 'Geri yüklenen geçerli hak erişimi açmadı.');

    $pdo->prepare("UPDATE comments SET status = 'rejected' WHERE id = ?")->execute([$activeCommentId]);
    topicDownloadRevokeAccessGrant($pdo, $activeCommentId, 'comment_rejected');
    $rejectedState = topicDownloadAccessState($pdo, $settings, (int) $pair['topic_id'], (int) $pair['user_id']);
    $assert(!empty($rejectedState['locked']), 'Reddedilen yorumun erişimi kilitlenmedi.');

    echo "download access grant smoke OK\n";
    $pdo->rollBack();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
