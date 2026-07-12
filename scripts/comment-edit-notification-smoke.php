<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$adminEmail = $argv[1] ?? 'mehmetgun015@gmail.com';
$historyId = 0;
$commentId = 0;
$original = null;
$dedupeKey = '';

try {
    $adminStmt = $pdo->prepare('SELECT id, username FROM users WHERE email = ? LIMIT 1');
    $adminStmt->execute([$adminEmail]);
    $admin = $adminStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$admin) {
        throw new RuntimeException('Admin kullanicisi bulunamadi.');
    }

    $commentStmt = $pdo->prepare(
        'SELECT c.id, c.topic_id, c.user_id, c.body, c.is_edited, c.edited_at, c.updated_at
         FROM comments c
         INNER JOIN topics t ON t.id = c.topic_id AND t.deleted_at IS NULL
         WHERE c.deleted_at IS NULL AND c.user_id IS NOT NULL AND c.user_id <> ?
         ORDER BY c.id DESC LIMIT 1'
    );
    $commentStmt->execute([(int) $admin['id']]);
    $original = $commentStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$original) {
        throw new RuntimeException('Test icin baska kullaniciya ait yorum bulunamadi.');
    }

    $commentId = (int) $original['id'];
    $marker = '[smoke-' . date('YmdHis') . ']';
    $reason = 'Smoke test duzenleme nedeni ' . $marker;
    $newBody = rtrim((string) $original['body']) . "\n" . $marker;

    $editResult = commentUpdateWithHistory(
        $pdo,
        $original,
        $newBody,
        (int) $admin['id'],
        $reason,
        true
    );
    if (empty($editResult['changed']) || (int) ($editResult['history_id'] ?? 0) <= 0) {
        throw new RuntimeException('Revizyon kaydi olusturulamadi.');
    }
    $historyId = (int) $editResult['history_id'];

    $historyStmt = $pdo->prepare('SELECT user_id, old_body, new_body, edit_reason FROM comment_edit_history WHERE id = ? LIMIT 1');
    $historyStmt->execute([$historyId]);
    $history = $historyStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (
        !$history
        || (int) $history['user_id'] !== (int) $admin['id']
        || (string) $history['old_body'] !== (string) $original['body']
        || (string) $history['new_body'] !== $newBody
        || (string) $history['edit_reason'] !== $reason
    ) {
        throw new RuntimeException('Revizyon icerigi beklenen degerlerle eslesmiyor.');
    }

    $sent = notificationDispatchCommentEdited(
        $pdo,
        $original,
        (int) $admin['id'],
        (string) ($admin['username'] ?? 'Yonetim'),
        $editResult
    );
    $dedupeKey = 'comment_edited_by_staff:' . (int) $original['user_id'] . ':' . $commentId . ':' . $historyId;
    if (!$sent) {
        throw new RuntimeException('Yetkili duzenleme bildirimi gonderilemedi.');
    }

    $notificationStmt = $pdo->prepare('SELECT title, message, link, actor_user_id FROM notifications WHERE dedupe_key = ? LIMIT 1');
    $notificationStmt->execute([$dedupeKey]);
    $notification = $notificationStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (
        !$notification
        || (int) ($notification['actor_user_id'] ?? 0) !== (int) $admin['id']
        || !str_contains((string) ($notification['message'] ?? ''), $reason)
        || !str_contains((string) ($notification['link'] ?? ''), '#comment-' . $commentId)
    ) {
        throw new RuntimeException('Bildirim icerigi beklenen degerlerle eslesmiyor.');
    }

    $selfSent = notificationDispatchCommentEdited(
        $pdo,
        $original,
        (int) $original['user_id'],
        'Yorum Sahibi',
        $editResult
    );
    if ($selfSent) {
        throw new RuntimeException('Kendi yorumunu duzenleme bildirimi engellenmedi.');
    }

    $noChange = commentUpdateWithHistory(
        $pdo,
        array_merge($original, ['body' => $newBody]),
        $newBody,
        (int) $admin['id'],
        '',
        true
    );
    if (!empty($noChange['changed']) || (int) ($noChange['history_id'] ?? 0) !== 0) {
        throw new RuntimeException('Degismeyen metin icin gereksiz revizyon olustu.');
    }

    echo "OK comment_id={$commentId} history_id={$historyId}\n";
} finally {
    if ($dedupeKey !== '') {
        $deleteNotification = $pdo->prepare('DELETE FROM notifications WHERE dedupe_key = ?');
        $deleteNotification->execute([$dedupeKey]);
    }
    if ($historyId > 0) {
        $deleteHistory = $pdo->prepare('DELETE FROM comment_edit_history WHERE id = ?');
        $deleteHistory->execute([$historyId]);
    }
    if ($commentId > 0 && is_array($original)) {
        $restoreStmt = $pdo->prepare('UPDATE comments SET body = ?, is_edited = ?, edited_at = ?, updated_at = ? WHERE id = ?');
        $restoreStmt->execute([
            (string) $original['body'],
            (int) ($original['is_edited'] ?? 0),
            $original['edited_at'] ?? null,
            $original['updated_at'] ?? null,
            $commentId,
        ]);
    }
}
