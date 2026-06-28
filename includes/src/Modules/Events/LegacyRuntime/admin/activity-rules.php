<?php

declare(strict_types=1);

require_once __DIR__ . '/_shared.php';

global $pdo, $baseUri;

$ready = eventsTasksTablesReady($pdo ?? null);
$errors = [];

if ($ready && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik doğrulaması başarısız.');
        header('Location: events-tasks.php?tab=rules');
        exit;
    }

    try {
        $data = eventsNormalizeActivityRuleInput($_POST);
        $stmt = $pdo->prepare("INSERT INTO events_activity_rules
            (activity_type, label, points, daily_limit, weekly_limit, monthly_limit, cooldown_minutes, repeat_policy,
             min_length, min_account_age_days, required_group_id, allow_self_subject, requires_approved_subject,
             reversal_enabled, is_active, starts_at, ends_at, admin_note, created_at, updated_at)
            VALUES (:activity_type, :label, :points, :daily_limit, :weekly_limit, :monthly_limit, :cooldown_minutes,
             :repeat_policy, :min_length, :min_account_age_days, :required_group_id, :allow_self_subject,
             :requires_approved_subject, :reversal_enabled, :is_active, :starts_at, :ends_at, :admin_note, NOW(), NOW())
            ON DUPLICATE KEY UPDATE label = VALUES(label), points = VALUES(points), daily_limit = VALUES(daily_limit),
                weekly_limit = VALUES(weekly_limit), monthly_limit = VALUES(monthly_limit),
                cooldown_minutes = VALUES(cooldown_minutes), repeat_policy = VALUES(repeat_policy),
                min_length = VALUES(min_length), min_account_age_days = VALUES(min_account_age_days),
                required_group_id = VALUES(required_group_id), allow_self_subject = VALUES(allow_self_subject),
                requires_approved_subject = VALUES(requires_approved_subject), reversal_enabled = VALUES(reversal_enabled),
                is_active = VALUES(is_active), starts_at = VALUES(starts_at), ends_at = VALUES(ends_at),
                admin_note = VALUES(admin_note), updated_at = NOW()");
        $stmt->execute($data);
        eventsAuditLog($pdo, 'activity_rule_update', 'activity_rule', null, $data);
        flash('success', 'Aktivite puan kuralı kaydedildi.');
        header('Location: events-tasks.php?tab=rules');
        exit;
    } catch (Throwable $e) {
        eventsErrorLog($pdo, 'Activity rule admin action failed.', ['error' => $e->getMessage()], 'ERROR');
        $errors['server'] = safeErrorMessage($e, 'İşlem tamamlanamadı.');
    }
}

eventsAdminStyles($baseUri ?? '');
$rules = [];
$groups = [];
$rulesByType = [];
$hookCatalog = [];
if ($ready) {
    eventsSeedDefaultActivityRules($pdo);
    $orderSql = implode(', ', array_map(static fn(string $type): string => $pdo->quote($type), array_keys(eventsAllowedActivityTypes())));
    $rules = $pdo->query("SELECT * FROM events_activity_rules ORDER BY FIELD(activity_type, {$orderSql}), activity_type ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rules as $rule) {
        $rulesByType[(string)$rule['activity_type']] = $rule;
    }
    $hookCatalog = eventsActivityHookCatalog($rulesByType);

    $groups = function_exists('usersGetGroups') ? usersGetGroups($pdo, true) : [];
}
?>
<div data-ui-events-wheel-ajax-root>
<div class="ui-events-admin-page ui-events-admin-console ui-section" data-ui-events-admin-page="tasks">
    <?php eventsAdminTabs($baseUri ?? ''); ?>
    <?php eventsAdminSetupNotice(eventsTablesReady($pdo ?? null)); ?>
    <?php if (!$ready): ?><div class="ui-events-empty ui-events-setup ui-empty">Gorev tablolari icin <code>database/schema.sql</code> kurulumu tamamlanmali.</div><?php endif; ?>
    <?php
    eventsAdminPageHero(
        'Aktivite Puan Kuralları',
        'Puan kazandıran hareketleri limit, tekrar politikası, grup kapsamı ve onay koşullarıyla birlikte yönetin.',
        'bi-stars'
    );
    ?>

    <div class="admin-card ui-events-admin-panel ui-events-rules-master-shell ui-events-admin-master-card ui-section ui-panel ui-card">
        <div class="card-body ui-events-rules-master-body ui-events-admin-master-body ui-panel__body">
            <?php eventsAdminErrorList($errors); ?>

            <div class="ui-events-master-actionbar ui-events-admin-actionbar ui-events-wheel-section-tabs ui-cluster" data-ui-events-wheel-toolbar data-ui-events-admin-component="actionbar">
                <div class="ui-events-app-nav ui-events-app-nav-minimal ui-events-admin-tablist" aria-label="Görev yönetimi menüsü">
                    <a href="<?= htmlspecialchars(rtrim($baseUri ?? '', '/') . '/admin/events-tasks.php?tab=management') ?>" class="ui-admin-btn ui-admin-btn-outline ui-events-admin-tab" role="tab" aria-selected="false" aria-controls="ui-events-admin-tab-task-management" data-ui-events-tab="management" data-ui-events-wheel-ajax-link>
                        <i class="bi bi-check2-square"></i> Görev Yönetimi
                    </a>
                    <a href="<?= htmlspecialchars(rtrim($baseUri ?? '', '/') . '/admin/events-tasks.php?tab=rules') ?>" class="ui-admin-btn ui-admin-btn-primary ui-events-admin-tab" role="tab" aria-selected="true" aria-controls="ui-events-admin-tab-task-rules" data-ui-events-tab="rules" data-ui-events-wheel-ajax-link><i class="bi bi-stars"></i> Puan Kuralları</a>
                </div>
            </div>
            <?php if ($rules === []): ?>
                <?php eventsAdminEmptyState('bi-stars', 'Puan Kuralı Bulunamadı', 'Sistemde henüz aktif bir puan kazanma kuralı bulunmuyor.'); ?>
            <?php else: ?>
                <div class="ui-events-rules-master-layout ui-events-admin-master-layout ui-section" data-ui-events-rule-master data-ui-events-admin-master>
                    <div class="ui-events-rules-list-panel ui-events-admin-list-panel ui-panel" aria-label="Aktivite puan kuralları listesi">
                        <div class="ui-events-rules-table-head ui-panel__head">
                            <span>Hareket</span>
                            <span>Puan</span>
                            <span>Günlük</span>
                            <span>Tekrar</span>
                            <span>Durum</span>
                            <span>Ayar</span>
                        </div>

                        <?php foreach ($rules as $index => $rule): ?>
                            <?php
                            $activityType = (string)$rule['activity_type'];
                            $catalogItem = $hookCatalog[$activityType] ?? null;
                            $points = (int)($rule['points'] ?? 0);
                            $dailyLimit = (int)($rule['daily_limit'] ?? 0);
                            $repeatPolicy = (string)($rule['repeat_policy'] ?? 'once_per_subject');
                            $dailyLimitLabel = $dailyLimit > 0 ? (string)$dailyLimit : 'Limitsiz';
                            $statusKey = (int)$rule['is_active'] === 1 ? 'active' : 'inactive';
                            ?>
                            <button
                                class="ui-events-rule-row<?= $index === 0 ? ' is-selected' : '' ?>"
                                type="button"
                                data-ui-events-rule-target="<?= e($activityType) ?>"
                                data-ui-events-rule-status="<?= e($statusKey) ?>"
                            >
                                <span class="ui-events-rule-row-name">
                                    <strong><?= e((string)$rule['label']) ?></strong>
                                    <code><?= e($activityType) ?></code>
                                </span>
                                <span class="ui-events-rule-row-value ui-events-rule-row-points">+<?= $points ?></span>
                                <span class="ui-events-rule-row-value"><?= e($dailyLimitLabel) ?></span>
                                <span class="ui-events-rule-row-value"><?= e(eventsActivityRepeatPolicyLabel($repeatPolicy)) ?></span>
                                <span class="ui-events-badge <?= (int)$rule['is_active'] === 1 ? 'ui-events-badge-success' : 'ui-events-badge-muted' ?>"><?= (int)$rule['is_active'] === 1 ? 'Kural aktif' : 'Kural pasif' ?></span>
                                <span class="ui-events-rule-open-action"><i class="bi bi-sliders"></i><span>Ayarlar</span></span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="ui-events-rule-detail-panel ui-events-admin-detail-panel ui-events-admin-modal-panel ui-events-admin-detail-overlay ui-panel" data-ui-events-rule-modal data-ui-events-admin-modal role="dialog" aria-modal="true" aria-label="Etkinlik kurali ayarlari" hidden>
                        <?php foreach ($rules as $index => $rule): ?>
                            <?php
                            $activityType = (string)$rule['activity_type'];
                            $catalogItem = $hookCatalog[$activityType] ?? null;
                            $points = (int)($rule['points'] ?? 0);
                            $dailyLimit = (int)($rule['daily_limit'] ?? 0);
                            $weeklyLimit = (int)($rule['weekly_limit'] ?? 0);
                            $monthlyLimit = (int)($rule['monthly_limit'] ?? 0);
                            $cooldownMinutes = (int)($rule['cooldown_minutes'] ?? 0);
                            $repeatPolicy = (string)($rule['repeat_policy'] ?? 'once_per_subject');
                            $dailyLimitLabel = $dailyLimit > 0 ? (string)$dailyLimit : 'Limitsiz';
                            $cooldownLabel = $cooldownMinutes > 0 ? $cooldownMinutes . ' dk' : 'Yok';
                            ?>
                            <form
                                class="ui-events-rule-detail-form ui-events-admin-detail-form ui-admin-detail-modal ui-events-admin-detail-modal<?= $index === 0 ? ' is-active' : '' ?>"
                                method="post"
                                action="<?= htmlspecialchars(rtrim($baseUri ?? '', '/') . '/admin/events-tasks.php?tab=rules') ?>"
                                data-ui-events-rule-panel="<?= e($activityType) ?>"
                                <?= $index === 0 ? '' : 'hidden' ?>
                            >
                                <?= csrf_field() ?>
                                <input type="hidden" name="activity_type" value="<?= e($activityType) ?>">

                                <div class="ui-events-rule-detail-head ui-admin-detail-modal-head ui-panel__head">
                                    <div class="ui-events-rule-detail-titlebar">
                                        <div>
                                            <h3><?= e((string)$rule['label']) ?></h3>
                                            <code><?= e($activityType) ?></code>
                                        </div>
                                        <div class="ui-events-rule-title-actions">
                                            <div class="ui-events-rule-badges">
                                                <span class="ui-events-badge <?= (int)$rule['is_active'] === 1 ? 'ui-events-badge-success' : 'ui-events-badge-muted' ?>"><?= (int)$rule['is_active'] === 1 ? 'Kural aktif' : 'Kural pasif' ?></span>
                                                <?php if ($catalogItem): ?><span class="ui-events-badge ui-events-badge-muted"><?= e((string)$catalogItem['status_label'] === 'Aktif' ? 'Hook aktif' : (string)$catalogItem['status_label']) ?></span><?php endif; ?>
                                            </div>
                                            <button class="ui-events-rule-modal-close ui-admin-detail-close" type="button" data-ui-events-rule-close aria-label="Ayarları kapat"><i class="bi bi-x-lg"></i></button>
                                        </div>
                                    </div>

                                    <div class="ui-events-rule-detail-summary">
                                        <div class="ui-events-rule-summary-card is-primary ui-card"><span>Puan</span><strong>+<?= $points ?></strong></div>
                                        <div class="ui-events-rule-summary-card ui-card"><span>Günlük limit</span><strong><?= e($dailyLimitLabel) ?></strong></div>
                                        <div class="ui-events-rule-summary-card ui-card"><span>Tekrar</span><strong><?= e(eventsActivityRepeatPolicyLabel($repeatPolicy)) ?></strong></div>
                                    </div>
                                </div>

                                <div class="ui-events-rule-detail-body ui-admin-detail-modal-body ui-panel__body">
                                    <div class="ui-events-rule-section-title">Hızlı ayarlar</div>
                                    <div class="ui-events-rule-form-grid ui-grid">
                                        <div class="ui-events-rule-field is-wide">
                                            <label for="label-<?= e($activityType) ?>">Aktivite adı</label>
                                            <input id="label-<?= e($activityType) ?>" class="ui-events-rule-soft-control" name="label" value="<?= e((string)$rule['label']) ?>">
                                        </div>
                                        <div class="ui-events-rule-field">
                                            <label for="points-<?= e($activityType) ?>">Puan</label>
                                            <input id="points-<?= e($activityType) ?>" class="ui-events-rule-soft-control is-accent" type="number" min="0" name="points" value="<?= $points ?>" <?= eventsReadableNumberDataAttributes(['key' => 'points', 'type' => 'number', 'min' => 0]) ?>>
                                            <?= eventsRenderReadableNumberValue($points, ['key' => 'points', 'type' => 'number', 'min' => 0]) ?>
                                        </div>
                                        <div class="ui-events-rule-field">
                                            <label for="daily-<?= e($activityType) ?>">Günlük limit</label>
                                            <input id="daily-<?= e($activityType) ?>" class="ui-events-rule-soft-control" type="number" min="0" name="daily_limit" value="<?= $dailyLimit ?>" <?= eventsReadableNumberDataAttributes(['key' => 'daily_limit', 'type' => 'number', 'min' => 0]) ?>>
                                            <?= eventsRenderReadableNumberValue($dailyLimit, ['key' => 'daily_limit', 'type' => 'number', 'min' => 0]) ?>
                                        </div>
                                        <div class="ui-events-rule-field">
                                            <label for="min-length-<?= e($activityType) ?>">Min uzunluk</label>
                                            <input id="min-length-<?= e($activityType) ?>" class="ui-events-rule-soft-control" type="number" min="0" name="min_length" value="<?= (int)$rule['min_length'] ?>" <?= eventsReadableNumberDataAttributes(['key' => 'min_length', 'type' => 'number', 'min' => 0]) ?>>
                                            <?= eventsRenderReadableNumberValue((int)$rule['min_length'], ['key' => 'min_length', 'type' => 'number', 'min' => 0]) ?>
                                        </div>
                                        <div class="ui-events-rule-field">
                                            <label for="repeat-<?= e($activityType) ?>">Tekrar kuralı</label>
                                            <select id="repeat-<?= e($activityType) ?>" class="ui-events-rule-soft-control" name="repeat_policy">
                                                <?php foreach (eventsActivityRepeatPolicyOptions() as $policy => $option): ?>
                                                    <option value="<?= e($policy) ?>" <?= $repeatPolicy === $policy ? 'selected' : '' ?>><?= e((string)$option['label']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="ui-events-repeat-help"><?= e(eventsActivityRepeatPolicyDescription($repeatPolicy)) ?></small>
                                        </div>
                                    </div>

                                    <div class="ui-events-rule-section-title">Kapsam</div>
                                    <div class="ui-events-rule-scope-grid ui-grid">
                                        <label class="ui-events-rule-scope-card ui-card" for="self-<?= e($activityType) ?>">
                                            <span><strong>Kendi içeriğinden puan alabilir</strong><em>Kullanıcı kendi içeriğinden puan kazanabilir.</em></span>
                                            <span class="ui-events-toggle-switch">
                                                <input id="self-<?= e($activityType) ?>" type="checkbox" name="allow_self_subject" value="1"<?= eventsAdminChecked($rule['allow_self_subject']) ?>>
                                                <span class="ui-events-toggle-slider"></span>
                                            </span>
                                        </label>
                                        <label class="ui-events-rule-scope-card ui-card" for="active-<?= e($activityType) ?>">
                                            <span><strong>Aktif</strong><em>Kural puan sisteminde kullanılacak.</em></span>
                                            <span class="ui-events-toggle-switch">
                                                <input id="active-<?= e($activityType) ?>" type="checkbox" name="is_active" value="1"<?= eventsAdminChecked($rule['is_active']) ?>>
                                                <span class="ui-events-toggle-slider"></span>
                                            </span>
                                        </label>
                                    </div>

                                    <div class="ui-events-rule-section-title">Gelişmiş ayarlar</div>
                                    <div class="ui-events-rule-form-grid ui-grid">
                                        <div class="ui-events-rule-field">
                                            <label for="weekly-<?= e($activityType) ?>">Haftalık limit</label>
                                            <input id="weekly-<?= e($activityType) ?>" class="ui-events-rule-soft-control" type="number" min="0" name="weekly_limit" value="<?= $weeklyLimit ?>" <?= eventsReadableNumberDataAttributes(['key' => 'weekly_limit', 'type' => 'number', 'min' => 0]) ?>>
                                            <?= eventsRenderReadableNumberValue($weeklyLimit, ['key' => 'weekly_limit', 'type' => 'number', 'min' => 0]) ?>
                                        </div>
                                        <div class="ui-events-rule-field">
                                            <label for="monthly-<?= e($activityType) ?>">Aylık limit</label>
                                            <input id="monthly-<?= e($activityType) ?>" class="ui-events-rule-soft-control" type="number" min="0" name="monthly_limit" value="<?= $monthlyLimit ?>" <?= eventsReadableNumberDataAttributes(['key' => 'monthly_limit', 'type' => 'number', 'min' => 0]) ?>>
                                            <?= eventsRenderReadableNumberValue($monthlyLimit, ['key' => 'monthly_limit', 'type' => 'number', 'min' => 0]) ?>
                                        </div>
                                        <div class="ui-events-rule-field">
                                            <label for="cooldown-<?= e($activityType) ?>">Bekleme süresi</label>
                                            <input id="cooldown-<?= e($activityType) ?>" class="ui-events-rule-soft-control" type="number" min="0" name="cooldown_minutes" value="<?= $cooldownMinutes ?>" <?= eventsReadableNumberDataAttributes(['key' => 'cooldown_minutes', 'type' => 'number', 'min' => 0]) ?>>
                                            <?= eventsRenderReadableNumberValue($cooldownMinutes, ['key' => 'cooldown_minutes', 'type' => 'number', 'min' => 0]) ?>
                                        </div>
                                        <div class="ui-events-rule-field">
                                            <label for="age-<?= e($activityType) ?>">Min hesap yaşı</label>
                                            <input id="age-<?= e($activityType) ?>" class="ui-events-rule-soft-control" type="number" min="0" name="min_account_age_days" value="<?= (int)($rule['min_account_age_days'] ?? 0) ?>" <?= eventsReadableNumberDataAttributes(['key' => 'events_min_account_age_days', 'type' => 'number', 'min' => 0, 'max' => 3650]) ?>>
                                            <?= eventsRenderReadableNumberValue((int)($rule['min_account_age_days'] ?? 0), ['key' => 'events_min_account_age_days', 'type' => 'number', 'min' => 0, 'max' => 3650]) ?>
                                        </div>
                                        <div class="ui-events-rule-field">
                                            <label for="group-<?= e($activityType) ?>">Grup</label>
                                            <select id="group-<?= e($activityType) ?>" class="ui-events-rule-soft-control" name="required_group_id">
                                                <option value="">Tum gruplar</option>
                                                <?php foreach ($groups as $group): ?><option value="<?= (int)$group['id'] ?>" <?= (string)($rule['required_group_id'] ?? '') === (string)$group['id'] ? 'selected' : '' ?>><?= e((string)$group['name']) ?></option><?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="ui-events-rule-field">
                                            <label for="approved-<?= e($activityType) ?>">Onaylı içerik</label>
                                            <select id="approved-<?= e($activityType) ?>" class="ui-events-rule-soft-control" name="requires_approved_subject">
                                                <option value="0" <?= (int)($rule['requires_approved_subject'] ?? 0) === 0 ? 'selected' : '' ?>>Gerekmez</option>
                                                <option value="1" <?= (int)($rule['requires_approved_subject'] ?? 0) === 1 ? 'selected' : '' ?>>Gerekli</option>
                                            </select>
                                        </div>
                                        <div class="ui-events-rule-field">
                                            <label for="starts-<?= e($activityType) ?>">Başlangıç</label>
                                            <input id="starts-<?= e($activityType) ?>" class="ui-events-rule-soft-control" type="datetime-local" name="starts_at" value="<?= e(!empty($rule['starts_at']) ? date('Y-m-d\TH:i', strtotime((string)$rule['starts_at'])) : '') ?>">
                                        </div>
                                        <div class="ui-events-rule-field">
                                            <label for="ends-<?= e($activityType) ?>">Bitiş</label>
                                            <input id="ends-<?= e($activityType) ?>" class="ui-events-rule-soft-control" type="datetime-local" name="ends_at" value="<?= e(!empty($rule['ends_at']) ? date('Y-m-d\TH:i', strtotime((string)$rule['ends_at'])) : '') ?>">
                                        </div>
                                        <label class="ui-events-rule-scope-card ui-card" for="reversal-<?= e($activityType) ?>">
                                            <span><strong>Geri al</strong><em>İçerik silindiğinde puanı geri çek.</em></span>
                                            <span class="ui-events-toggle-switch">
                                                <input id="reversal-<?= e($activityType) ?>" type="checkbox" name="reversal_enabled" value="1"<?= eventsAdminChecked($rule['reversal_enabled'] ?? 1) ?>>
                                                <span class="ui-events-toggle-slider"></span>
                                            </span>
                                        </label>
                                        <div class="ui-events-rule-field is-wide">
                                            <label for="note-<?= e($activityType) ?>">Admin notu</label>
                                            <input id="note-<?= e($activityType) ?>" class="ui-events-rule-soft-control" name="admin_note" maxlength="500" value="<?= e((string)($rule['admin_note'] ?? '')) ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="ui-events-settings-savebar ui-events-form-toolbar ui-events-rule-savebar ui-events-admin-savebar">
                                    <span>Değişiklik bu kural için uygulanır.</span>
                                    <button class="ui-admin-btn ui-admin-btn-primary ui-events-rule-save" type="submit"><i class="bi bi-save"></i> Kuralı Kaydet</button>
                                </div>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="admin-card ui-events-admin-panel ui-panel">
        <?php eventsAdminPanelHeader('bi-diagram-3', 'Puanlanabilir Hareketler', 'Kural listesinde görünen her hareketin sistemde hangi hook ile beslendiğini kontrol edin.'); ?>
        <div class="card-body ui-events-table-wrap ui-events-admin-table-wrap ui-panel__body ui-table-wrap ui-surface">
            <?php if ($hookCatalog === []): ?>
                <?php eventsAdminEmptyState('bi-diagram-3', 'Katalog Hazır Değil', 'Puanlama sistemi hook kataloğunu görüntüleyebilmek için görev tablolarının kurulmuş olması gerekir.'); ?>
            <?php else: ?>
                <table class="ui-events-table ui-events-hook-table">
                    <thead><tr><th>Hareket</th><th>Key</th><th>Durum</th><th>Risk</th><th>Hook</th></tr></thead>
                    <tbody>
                    <?php foreach ($hookCatalog as $item): ?>
                        <tr>
                            <td><strong><?= e((string)$item['label']) ?></strong></td>
                            <td><code><?= e((string)$item['type']) ?></code></td>
                            <td><span class="ui-events-badge <?= (string)$item['status_label'] === 'Aktif' ? 'ui-events-badge-success' : 'ui-events-badge-muted' ?>"><?= e((string)$item['status_label']) ?></span></td>
                            <td><?= e((string)$item['risk']) ?></td>
                            <td><span class="ui-events-list-meta"><?= e((string)$item['location']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

</div>
