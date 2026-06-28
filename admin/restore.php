<?php

declare(strict_types=1);
require_once __DIR__ . '/init.php';
adminRequirePermission('topics.edit', 'Konu geri yuklemek icin gerekli izin hesabiniza tanimlanmamis.');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        flash('error', 'Geçersiz istek.');
        header('Location: topics.php');
        exit;
    }

    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik doğrulaması başarısız.');
        header('Location: topics.php');
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0 && $pdo) {
        if (restoreTopic($pdo, $id)) {
            flash('success', 'Konu başarıyla geri yüklendi.');
        } else {
            flash('error', 'Konu geri yüklenemedi.');
        }
    } else {
        flash('error', 'Geçersiz konu ID.');
    }
} catch (Throwable $e) {
    flash('error', 'Geri yükleme sırasında bir hata oluştu: ' . safeErrorMessage($e));
}

header('Location: topics.php');
exit;
