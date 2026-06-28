<?php

declare(strict_types=1);

require_once __DIR__ . '/_shared.php';

global $pdo, $baseUri;

$ready = eventsTasksTablesReady($pdo ?? null);
$errors = [];

if ($ready && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['_events_action'] ?? '');
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik doğrulaması başarısız.');
        header('Location: events-tasks.php');
        exit;
    }

    try {
        if ($action === 'save_task') {
            $data = eventsNormalizeTaskInput($_POST);
            $taskId = (int)$data['id'];
            $taskFields = [
                'slug' => $data['slug'],
                'title' => $data['title'],
                'description' => $data['description'],
                'task_type' => $data['task_type'],
                'reward_type' => $data['reward_type'],
                'reward_value' => $data['reward_value'],
                'reward_quantity' => $data['reward_quantity'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'min_user_points' => $data['min_user_points'],
                'group_id' => $data['group_id'],
                'display_order' => $data['display_order'],
                'is_active' => $data['is_active'],
            ];

            if ($taskId > 0) {
                $taskFields['id'] = $taskId;
                $stmt = $pdo->prepare("UPDATE events_tasks
                    SET slug = :slug, title = :title, description = :description, task_type = :task_type,
                        reward_type = :reward_type, reward_value = :reward_value, reward_quantity = :reward_quantity,
                        starts_at = :starts_at, ends_at = :ends_at, min_user_points = :min_user_points, group_id = :group_id,
                        display_order = :display_order, is_active = :is_active, updated_at = NOW()
                    WHERE id = :id");
                $stmt->execute($taskFields);
            } else {
                $stmt = $pdo->prepare("INSERT INTO events_tasks
                    (slug, title, description, task_type, reward_type, reward_value, reward_quantity, starts_at, ends_at,
                     min_user_points, group_id, display_order, is_active, created_at, updated_at)
                    VALUES (:slug, :title, :description, :task_type, :reward_type, :reward_value, :reward_quantity, :starts_at, :ends_at,
                     :min_user_points, :group_id, :display_order, :is_active, NOW(), NOW())");
                $stmt->execute($taskFields);
                $taskId = (int)$pdo->lastInsertId();
            }

            $reqStmt = $pdo->prepare("SELECT id FROM events_task_requirements WHERE task_id = ? ORDER BY id ASC LIMIT 1");
            $reqStmt->execute([$taskId]);
            $requirementId = (int)$reqStmt->fetchColumn();
            if ($requirementId > 0) {
                $stmt = $pdo->prepare("UPDATE events_task_requirements
                    SET activity_type = ?, target_count = ?, updated_at = NOW()
                    WHERE id = ?");
                $stmt->execute([$data['activity_type'], $data['target_count'], $requirementId]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO events_task_requirements
                    (task_id, activity_type, target_count, created_at, updated_at)
                    VALUES (?, ?, ?, NOW(), NOW())");
                $stmt->execute([$taskId, $data['activity_type'], $data['target_count']]);
            }

            eventsAuditLog($pdo, 'task_save', 'task', $taskId, $data);
            flash('success', 'Görev kaydedildi.');
            header('Location: events-tasks.php');
            exit;
        }

        if ($action === 'toggle_task') {
            $taskId = (int)($_POST['task_id'] ?? 0);
            $isActive = !empty($_POST['is_active']) ? 1 : 0;
            if ($taskId > 0) {
                $stmt = $pdo->prepare("UPDATE events_tasks SET is_active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$isActive, $taskId]);
                eventsAuditLog($pdo, $isActive ? 'task_activate' : 'task_deactivate', 'task', $taskId, ['is_active' => $isActive]);
                flash('success', $isActive ? 'Görev aktif edildi.' : 'Görev pasifleştirildi.');
            }
            header('Location: events-tasks.php');
            exit;
        }
    } catch (Throwable $e) {
        eventsErrorLog($pdo, 'Task admin action failed.', ['error' => $e->getMessage(), 'action' => $action], 'ERROR');
        $errors['server'] = safeErrorMessage($e, 'İşlem tamamlanamadı.');
    }
}

eventsAdminStyles($baseUri ?? '');
$tasks = [];
$editTask = null;
$groups = [];
if ($ready) {
    $tasks = $pdo->query("SELECT t.*, r.activity_type, r.target_count,
            (SELECT COUNT(*) FROM events_task_claims c WHERE c.task_id = t.id) AS claim_count
        FROM events_tasks t
        LEFT JOIN events_task_requirements r ON r.task_id = t.id
        ORDER BY t.is_active DESC, t.display_order ASC, t.id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $editId = (int)($_GET['edit'] ?? 0);
    if ($editId > 0) {
        $stmt = $pdo->prepare("SELECT t.*, r.activity_type, r.target_count
            FROM events_tasks t
            LEFT JOIN events_task_requirements r ON r.task_id = t.id
            WHERE t.id = ? LIMIT 1");
        $stmt->execute([$editId]);
        $editTask = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $groups = function_exists('usersGetGroups') ? usersGetGroups($pdo, true) : [];
}

$taskFormDefaults = [
    'id' => 0,
    'slug' => '',
    'title' => '',
    'description' => '',
    'task_type' => 'daily',
    'activity_type' => 'comment_created',
    'target_count' => 1,
    'reward_type' => 'points',
    'reward_value' => '',
    'reward_quantity' => 1,
    'starts_at' => '',
    'ends_at' => '',
    'min_user_points' => '',
    'group_id' => '',
    'display_order' => 0,
    'is_active' => 1,
];
$newTask = $taskFormDefaults;
$initialTaskKey = $editTask
    ? 'task-' . (int)$editTask['id']
    : ($tasks !== [] ? 'task-' . (int)$tasks[0]['id'] : 'new');

$renderTaskForm = static function (array $task, string $panelKey, bool $isActive, array $groups, string $baseUri): void {
    $taskId = (int)($task['id'] ?? 0);
    $title = trim((string)($task['title'] ?? ''));
    $slug = trim((string)($task['slug'] ?? ''));
    $taskType = (string)($task['task_type'] ?? 'daily');
    $activityType = (string)($task['activity_type'] ?? 'comment_created');
    $targetCount = max(1, (int)($task['target_count'] ?? 1));
    $rewardType = (string)($task['reward_type'] ?? 'points');
    $rewardValue = (string)($task['reward_value'] ?? '');
    $rewardQuantity = max(1, (int)($task['reward_quantity'] ?? 1));
    $statusActive = (int)($task['is_active'] ?? 1) === 1;
    $startsAt = !empty($task['starts_at']) ? date('Y-m-d\TH:i', strtotime((string)$task['starts_at'])) : '';
    $endsAt = !empty($task['ends_at']) ? date('Y-m-d\TH:i', strtotime((string)$task['ends_at'])) : '';
    $displayTitle = $title !== '' ? $title : 'Yeni görev';
    $displaySlug = $slug !== '' ? $slug : 'yeni-gorev';
    $taskTypeLabel = eventsAllowedTaskTypes()[$taskType] ?? $taskType;
    $activityLabel = eventsAllowedActivityTypes()[$activityType] ?? $activityType;
    $rewardLabel = eventsRewardLabel($rewardType, $rewardValue, $rewardQuantity);
    $claimCount = (int)($task['claim_count'] ?? 0);
    ?>
    <form
        class="ui-events-rule-detail-form ui-events-admin-detail-form ui-admin-detail-modal ui-events-admin-detail-modal ui-events-task-detail-form<?= $isActive ? ' is-active' : '' ?>"
        method="post"
        action="<?= htmlspecialchars(rtrim($baseUri, '/') . '/admin/events-tasks.php') ?>"
        data-ui-events-task-panel="<?= e($panelKey) ?>"
        <?= $isActive ? '' : 'hidden' ?>
    >
        <?= csrf_field() ?>
        <input type="hidden" name="_events_action" value="save_task">
        <input type="hidden" name="task_id" value="<?= $taskId ?>">

        <div class="ui-events-rule-detail-head ui-admin-detail-modal-head ui-panel__head">
            <div class="ui-events-rule-detail-titlebar">
                <div>
                    <h3><?= e($displayTitle) ?></h3>
                    <code><?= e($displaySlug) ?></code>
                </div>
                <div class="ui-events-rule-title-actions">
                    <div class="ui-events-rule-badges">
                        <span class="ui-events-badge <?= $statusActive ? 'ui-events-badge-success' : 'ui-events-badge-muted' ?>"><?= $statusActive ? 'Aktif' : 'Pasif' ?></span>
                        <span class="ui-events-badge ui-events-badge-muted"><?= $claimCount ?> claim</span>
                    </div>
                    <button class="ui-events-rule-modal-close ui-admin-detail-close" type="button" data-ui-events-task-close aria-label="Ayarları kapat"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>

            <div class="ui-events-rule-detail-summary">
                <div class="ui-events-rule-summary-card is-primary ui-card"><span>Ödül</span><strong><?= e($rewardLabel) ?></strong></div>
                <div class="ui-events-rule-summary-card ui-card"><span>Hedef</span><strong><?= $targetCount ?> · <?= e($activityLabel) ?></strong></div>
                <div class="ui-events-rule-summary-card ui-card"><span>Tip</span><strong><?= e($taskTypeLabel) ?></strong></div>
            </div>
        </div>

        <div class="ui-events-rule-detail-body ui-admin-detail-modal-body ui-panel__body">
            <div class="ui-events-rule-section-title">Hızlı ayarlar</div>
            <div class="ui-events-rule-form-grid ui-grid">
                <div class="ui-events-rule-field">
                    <label for="task-title-<?= e($panelKey) ?>">Başlık</label>
                    <input id="task-title-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" name="title" maxlength="191" value="<?= e($title) ?>" required>
                </div>
                <div class="ui-events-rule-field">
                    <label for="task-slug-<?= e($panelKey) ?>">Slug</label>
                    <input id="task-slug-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" name="slug" maxlength="191" value="<?= e($slug) ?>" placeholder="otomatik">
                </div>
                <div class="ui-events-rule-field is-wide">
                    <label for="task-description-<?= e($panelKey) ?>">Açıklama</label>
                    <textarea id="task-description-<?= e($panelKey) ?>" class="ui-events-rule-soft-control is-textarea" name="description"><?= e((string)($task['description'] ?? '')) ?></textarea>
                </div>
                <div class="ui-events-rule-field">
                    <label for="task-type-<?= e($panelKey) ?>">Görev tipi</label>
                    <select id="task-type-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" name="task_type">
                        <?php foreach (eventsAllowedTaskTypes() as $type => $label): ?>
                            <option value="<?= e($type) ?>" <?= $taskType === $type ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ui-events-rule-field">
                    <label for="task-activity-<?= e($panelKey) ?>">Aktivite</label>
                    <select id="task-activity-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" name="activity_type">
                        <?php foreach (eventsAllowedActivityTypes() as $type => $label): ?>
                            <option value="<?= e($type) ?>" <?= $activityType === $type ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ui-events-rule-field">
                    <label for="task-target-<?= e($panelKey) ?>">Hedef</label>
                    <input id="task-target-<?= e($panelKey) ?>" class="ui-events-rule-soft-control is-accent" type="number" min="1" name="target_count" value="<?= $targetCount ?>" <?= eventsReadableNumberDataAttributes(['key' => 'target_count', 'type' => 'number', 'min' => 1]) ?>>
                    <?= eventsRenderReadableNumberValue($targetCount, ['key' => 'target_count', 'type' => 'number', 'min' => 1]) ?>
                </div>
                <div class="ui-events-rule-field">
                    <label for="task-order-<?= e($panelKey) ?>">Sıra</label>
                    <input id="task-order-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" type="number" min="0" name="display_order" value="<?= (int)($task['display_order'] ?? 0) ?>" <?= eventsReadableNumberDataAttributes(['key' => 'display_order', 'type' => 'number', 'min' => 0]) ?>>
                    <?= eventsRenderReadableNumberValue((int)($task['display_order'] ?? 0), ['key' => 'display_order', 'type' => 'number', 'min' => 0]) ?>
                </div>
            </div>

            <div class="ui-events-rule-section-title">Ödül ve kapsam</div>
            <div class="ui-events-rule-form-grid ui-grid">
                <div class="ui-events-rule-field">
                    <label for="task-reward-type-<?= e($panelKey) ?>">Ödül tipi</label>
                    <select id="task-reward-type-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" name="reward_type">
                        <?php foreach (eventsAllowedTaskRewardTypes() as $type => $label): ?>
                            <option value="<?= e($type) ?>" <?= $rewardType === $type ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ui-events-rule-field">
                    <label for="task-reward-value-<?= e($panelKey) ?>">Ödül değeri</label>
                    <input id="task-reward-value-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" name="reward_value" maxlength="191" value="<?= e($rewardValue) ?>">
                </div>
                <div class="ui-events-rule-field">
                    <label for="task-reward-quantity-<?= e($panelKey) ?>">Ödül adet</label>
                    <input id="task-reward-quantity-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" type="number" min="1" name="reward_quantity" value="<?= $rewardQuantity ?>" <?= eventsReadableNumberDataAttributes(['key' => 'reward_quantity', 'type' => 'number', 'min' => 1]) ?>>
                    <?= eventsRenderReadableNumberValue($rewardQuantity, ['key' => 'reward_quantity', 'type' => 'number', 'min' => 1]) ?>
                </div>
                <div class="ui-events-rule-field">
                    <label for="task-min-points-<?= e($panelKey) ?>">Min puan</label>
                    <input id="task-min-points-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" type="number" min="0" name="min_user_points" value="<?= e((string)($task['min_user_points'] ?? '')) ?>" <?= eventsReadableNumberDataAttributes(['key' => 'min_user_points', 'type' => 'number', 'min' => 0]) ?>>
                    <?= eventsRenderReadableNumberValue($task['min_user_points'] ?? '', ['key' => 'min_user_points', 'type' => 'number', 'min' => 0]) ?>
                </div>
                <div class="ui-events-rule-field">
                    <label for="task-group-<?= e($panelKey) ?>">Grup</label>
                    <select id="task-group-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" name="group_id">
                        <option value="">Tum gruplar</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?= (int)$group['id'] ?>" <?= (string)($task['group_id'] ?? '') === (string)$group['id'] ? 'selected' : '' ?>><?= e((string)$group['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <label class="ui-events-rule-scope-card ui-card" for="task-active-<?= e($panelKey) ?>">
                    <span><strong>Aktif</strong><em>Görev kullanıcı tarafında yayınlanır.</em></span>
                    <span class="ui-events-toggle-switch">
                        <input id="task-active-<?= e($panelKey) ?>" type="checkbox" name="is_active" value="1"<?= eventsAdminChecked($statusActive) ?>>
                        <span class="ui-events-toggle-slider"></span>
                    </span>
                </label>
            </div>

            <div class="ui-events-rule-section-title">Zamanlama</div>
            <div class="ui-events-rule-form-grid ui-grid">
                <div class="ui-events-rule-field">
                    <label for="task-starts-<?= e($panelKey) ?>">Başlangıç</label>
                    <input id="task-starts-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" type="datetime-local" name="starts_at" value="<?= e($startsAt) ?>">
                </div>
                <div class="ui-events-rule-field">
                    <label for="task-ends-<?= e($panelKey) ?>">Bitiş</label>
                    <input id="task-ends-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" type="datetime-local" name="ends_at" value="<?= e($endsAt) ?>">
                </div>
            </div>
        </div>
        <div class="ui-events-settings-savebar ui-events-form-toolbar ui-events-rule-savebar ui-events-admin-savebar">
            <span>Değişiklik bu görev için uygulanır.</span>
            <button class="ui-admin-btn ui-admin-btn-primary ui-events-rule-save" type="submit"><i class="bi bi-save"></i> Görevi Kaydet</button>
        </div>
    </form>
    <?php
};
?>
<div data-ui-events-wheel-ajax-root>
<div class="ui-events-admin-page ui-events-admin-console ui-section" data-ui-events-admin-page="tasks">
    <?php eventsAdminTabs($baseUri ?? ''); ?>
    <?php eventsAdminSetupNotice(eventsTablesReady($pdo ?? null)); ?>
    <?php if (!$ready): ?><div class="ui-events-empty ui-events-setup ui-empty">Gorev tablolari icin <code>database/schema.sql</code> kurulumu tamamlanmali.</div><?php endif; ?>
    <?php
    eventsAdminPageHero(
        'Görev Yönetimi',
        'Günlük, haftalık, aylık ve başarı görevlerini hedef, ödül ve grup kapsamıyla birlikte yönetin.',
        'bi-check2-square'
    );
    ?>

    <div class="admin-card ui-events-admin-panel ui-events-rules-master-shell ui-events-admin-master-card ui-events-tasks-master-shell ui-section ui-panel ui-card">
        <div class="card-body ui-events-rules-master-body ui-events-admin-master-body ui-events-tasks-master-body ui-panel__body">
            <?php eventsAdminErrorList($errors); ?>

            <div class="ui-events-master-actionbar ui-events-admin-actionbar ui-events-wheel-section-tabs ui-cluster" data-ui-events-wheel-toolbar data-ui-events-admin-component="actionbar">
                <div class="ui-events-app-nav ui-events-app-nav-minimal ui-events-admin-tablist" aria-label="Görev yönetimi menüsü">
                    <a href="<?= htmlspecialchars(rtrim($baseUri ?? '', '/') . '/admin/events-tasks.php?tab=management') ?>" class="ui-admin-btn ui-admin-btn-primary ui-events-admin-tab" role="tab" aria-selected="true" aria-controls="ui-events-admin-tab-task-management" data-ui-events-tab="management" data-ui-events-wheel-ajax-link>
                        <i class="bi bi-check2-square"></i> Görev Yönetimi
                    </a>
                    <button class="ui-admin-btn ui-admin-btn-outline ui-events-admin-tab" type="button" data-ui-events-task-new="new"><i class="bi bi-plus-lg"></i> Yeni Görev</button>
                    <a href="<?= htmlspecialchars(rtrim($baseUri ?? '', '/') . '/admin/events-tasks.php?tab=rules') ?>" class="ui-admin-btn ui-admin-btn-outline ui-events-admin-tab" role="tab" aria-selected="false" aria-controls="ui-events-admin-tab-task-rules" data-ui-events-tab="rules" data-ui-events-wheel-ajax-link><i class="bi bi-stars"></i> Puan Kuralları</a>
                </div>
            </div>

            <div class="ui-events-rules-master-layout ui-events-admin-master-layout ui-events-tasks-master-layout ui-section" data-ui-events-task-master data-ui-events-admin-master data-ui-events-task-initial="<?= e($initialTaskKey) ?>">
                <div class="ui-events-rules-list-panel ui-events-admin-list-panel ui-events-tasks-list-panel ui-panel" aria-label="Görevler listesi">
                    <div class="ui-events-rules-table-head ui-panel__head">
                        <span>Görev</span>
                        <span>Tip</span>
                        <span>Hedef</span>
                        <span>Ödül</span>
                        <span>Claim</span>
                        <span>Durum</span>
                        <span>Ayar</span>
                    </div>

                    <?php if ($tasks === []): ?>
                        <?php eventsAdminEmptyState('bi-check2-square', 'Görev Bulunamadı', 'Sistemde henüz bir görev bulunmuyor. Yeni bir görev ekleyerek başlayın.'); ?>
                    <?php else: ?>
                        <?php foreach ($tasks as $index => $task): ?>
                            <?php
                            $taskId = (int)$task['id'];
                            $taskKey = 'task-' . $taskId;
                            $taskType = (string)$task['task_type'];
                            $activityType = (string)$task['activity_type'];
                            $taskTypeLabel = eventsAllowedTaskTypes()[$taskType] ?? $taskType;
                            $activityLabel = eventsAllowedActivityTypes()[$activityType] ?? $activityType;
                            $targetLabel = (int)$task['target_count'] . ' · ' . $activityLabel;
                            $rewardLabel = eventsRewardLabel((string)$task['reward_type'], (string)($task['reward_value'] ?? ''), (int)$task['reward_quantity']);
                            $statusKey = (int)$task['is_active'] === 1 ? 'active' : 'inactive';
                            $isSelected = ($editTask && (int)$editTask['id'] === $taskId) || (!$editTask && $index === 0);
                            ?>
                            <button
                                class="ui-events-rule-row ui-events-task-row<?= $isSelected ? ' is-selected' : '' ?>"
                                type="button"
                                data-ui-events-task-target="<?= e($taskKey) ?>"
                                data-ui-events-task-status="<?= e($statusKey) ?>"
                            >
                                <span class="ui-events-rule-row-name">
                                    <strong><?= e((string)$task['title']) ?></strong>
                                    <code><?= e((string)$task['slug']) ?></code>
                                </span>
                                <span class="ui-events-rule-row-value"><?= e($taskTypeLabel) ?></span>
                                <span class="ui-events-rule-row-value"><?= e($targetLabel) ?></span>
                                <span class="ui-events-rule-row-value"><?= e($rewardLabel) ?></span>
                                <span class="ui-events-rule-row-value"><?= (int)$task['claim_count'] ?></span>
                                <span class="ui-events-badge <?= (int)$task['is_active'] === 1 ? 'ui-events-badge-success' : 'ui-events-badge-muted' ?>"><?= (int)$task['is_active'] === 1 ? 'Aktif' : 'Pasif' ?></span>
                                <span class="ui-events-rule-open-action"><i class="bi bi-sliders"></i><span>Ayarlar</span></span>
                            </button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="ui-events-rule-detail-panel ui-events-admin-detail-panel ui-events-admin-modal-panel ui-events-admin-detail-overlay ui-events-task-detail-panel ui-panel" data-ui-events-task-modal data-ui-events-admin-modal role="dialog" aria-modal="true" aria-label="Gorev ayarlari" hidden>
                    <?php $renderTaskForm($newTask, 'new', $initialTaskKey === 'new', $groups, (string)($baseUri ?? '')); ?>
                    <?php foreach ($tasks as $task): ?>
                        <?php $taskId = (int)$task['id']; ?>
                        <?php $taskKey = 'task-' . $taskId; ?>
                        <?php $renderTaskForm(array_merge($taskFormDefaults, $task), $taskKey, $initialTaskKey === $taskKey, $groups, (string)($baseUri ?? '')); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

</div>
