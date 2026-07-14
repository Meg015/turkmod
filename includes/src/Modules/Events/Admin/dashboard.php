<?php

declare(strict_types=1);

require_once __DIR__ . '/_shared.php';

eventsAdminStyles($baseUri ?? '');
$stats = eventsAdminStats($pdo ?? null);
?>
<div class="ui-events-admin-page ui-events-admin-console ui-section" data-ui-events-admin-page="dashboard">
    <?php eventsAdminTabs($baseUri ?? ''); ?>
    <?php eventsAdminSetupNotice((bool)$stats['ready']); ?>
    <?php
    eventsAdminPageHero(
        'Kontrol Merkezi',
        'Etkinlik altyapısının durumunu, ilk kurulum adımlarını ve son işlem kayıtlarını tek bakışta takip edin.',
        'bi-speedometer2'
    );
    ?>

    <div class="ui-events-rule-detail-summary ui-events-admin-summary-grid ui-grid">
        <div class="ui-events-rule-summary-card ui-card">
            <span>Aktif çark ödülü</span>
            <strong><?= (int)$stats['active_wheel_rewards'] ?></strong>
        </div>
        <div class="ui-events-rule-summary-card ui-card">
            <span>Aktif çekiliş</span>
            <strong><?= (int)$stats['active_raffles'] ?></strong>
        </div>
        <a class="ui-events-rule-summary-card is-primary ui-card ui-events-summary-link" href="<?= e(rtrim((string)($baseUri ?? ''), '/') . '/admin/events-pending.php?tab=rewards') ?>">
            <span>Onay bekleyen ödül</span>
            <strong><?= (int)$stats['pending_rewards'] ?></strong>
        </a>

    </div>

    <div class="admin-card ui-events-admin-panel ui-panel">
        <div class="card-body ui-events-admin-panel-body ui-events-admin-panel-body-flush ui-panel__body ui-panel">
            <?php if (($stats['recent_audit'] ?? []) === []): ?>
                <?php eventsAdminEmptyState('bi-journal-x', 'İşlem Kaydı Yok', 'Etkinlikler modülü ile ilgili log ve işlem kayıtları burada görünür.'); ?>
            <?php else: ?>
                <?php foreach ($stats['recent_audit'] as $row): ?>
                    <?php
                    $action = (string)$row['action'];
                    $iconClass = 'info';
                    $icon = 'bi-journal-text';

                    if (str_contains($action, 'update') || str_contains($action, 'edit') || str_contains($action, 'settings') || str_contains($action, 'config')) {
                        $iconClass = 'info';
                        $icon = 'bi-pencil';
                    } elseif (str_contains($action, 'create') || str_contains($action, 'add') || str_contains($action, 'join') || str_contains($action, 'spin') || str_contains($action, 'claim')) {
                        $iconClass = 'success';
                        $icon = 'bi-check-circle';
                    } elseif (str_contains($action, 'delete') || str_contains($action, 'remove') || str_contains($action, 'reject')) {
                        $iconClass = 'danger';
                        $icon = 'bi-trash';
                    } elseif (str_contains($action, 'fail') || str_contains($action, 'error')) {
                        $iconClass = 'danger';
                        $icon = 'bi-exclamation-triangle';
                    }

                    $actionMap = [
                        'config_update' => 'Ayarlar Güncellendi',
                        'wheel_spin' => 'Çark Çevrildi',
                        'raffle_join' => 'Çekilişe Katılındı',
                        'reward_claim' => 'Ödül Alındı',
                        'create' => 'Oluşturuldu',
                        'update' => 'Güncellendi',
                        'delete' => 'Silindi',
                        'edit' => 'Düzenlendi',
                        'add' => 'Eklendi',
                        'remove' => 'Kaldırıldı'
                    ];

                    $wordMap = [
                        'config' => 'Ayar', 'update' => 'Güncelleme', 'create' => 'Oluşturma', 'delete' => 'Silme',
                        'edit' => 'Düzenleme', 'add' => 'Ekleme', 'remove' => 'Kaldırma', 'wheel' => 'Çark',
                        'spin' => 'Çevirme', 'raffle' => 'Çekiliş', 'join' => 'Katılma', 'reward' => 'Ödül',
                        'claim' => 'Talep', 'fail' => 'Başarısız', 'error' => 'Hata', 'user' => 'Kullanıcı',
                        'pool' => 'Havuz', 'task' => 'Görev', 'rewards' => 'Ödülleri', 'raffles' => 'Çekilişler',
                        'pools' => 'Havuzlar', 'tasks' => 'Görevler', 'events' => 'Etkinlik'
                    ];

                    if (isset($actionMap[$action])) {
                        $actionTitle = $actionMap[$action];
                    } else {
                        $words = explode('_', $action);
                        $translated = array_map(fn($w) => $wordMap[$w] ?? ucfirst($w), $words);
                        $actionTitle = implode(' ', $translated);
                    }

                    $subjectType = (string)($row['subject_type'] ?? '');
                    $subjectId = (string)($row['subject_id'] ?? '');

                    $subjectMap = [
                        'events_config' => 'Etkinlik Ayarları',
                        'events_wheel_rewards' => 'Çark Ödülü',
                        'events_raffles' => 'Çekiliş',
                        'events_tasks' => 'Görev'
                    ];

                    if ($subjectType === '') {
                        $subject = 'Sistem Konfigürasyonu';
                    } else {
                        if (isset($subjectMap[$subjectType])) {
                            $subjectName = $subjectMap[$subjectType];
                        } else {
                            $words = explode('_', str_replace('events_', '', $subjectType));
                            $translated = array_map(fn($w) => $wordMap[$w] ?? ucfirst($w), $words);
                            $subjectName = implode(' ', $translated);
                        }
                        $subject = $subjectName . ($subjectId ? " (#$subjectId)" : '');
                    }
                    ?>
                    <div class="ui-events-admin-activity-item">
                        <div class="ui-events-admin-activity-icon <?= $iconClass ?>"><i class="bi <?= $icon ?>"></i></div>
                        <div class="ui-events-admin-activity-content ui-section">
                            <span class="ui-events-admin-activity-title"><?= e($actionTitle) ?></span>
                            <span class="ui-events-admin-activity-desc"><?= e($subject) ?></span>
                            <span class="ui-events-admin-activity-time"><?= e(eventsFormatDateTime((string)$row['created_at'])) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
