<?php

declare(strict_types=1);
require_once __DIR__ . '/init.php';
adminRequirePermission('topics.delete', 'Konu silmek icin gerekli izin hesabiniza tanimlanmamis.');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik hatası.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $permanent = (string) ($_POST['permanent'] ?? '') === '1';
        if ($pdo && $id > 0) {
            try {
                if ($permanent) {
                    $result = permanentlyDeleteTopic($pdo, $id, (string) $baseUri);
                    if ($result['success']) {
                        flash('success', (string) $result['message']);
                    } else {
                        flash('error', (string) $result['message']);
                    }
                } else {
                    $pdo->prepare("UPDATE topics SET deleted_at = NOW(), updated_at = NOW() WHERE id = ?")
                        ->execute([$id]);
                    logActivity($pdo, 'topic_deleted', 'topic', $id);
                    flash('success', 'Konu çöp kutusuna taşındı. Geri almak için çöp kutusunu kullanabilirsiniz.');
                }
            } catch (Throwable $e) {
                flash('error', safeErrorMessage($e, $permanent ? 'Kalıcı silme sırasında bir hata oluştu.' : 'Silme sırasında bir hata oluştu.'));
            }
        }
    }
}

header('Location: topics.php');
exit;
