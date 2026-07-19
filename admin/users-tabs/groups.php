<?php
/**
 * Kullanıcı Grupları Sekmesi
 */

$groups = $groups ?? (function_exists('usersGetGroups') ? usersGetGroups($pdo, false) : []);
$permissionCatalog = $permissionCatalog ?? (function_exists('usersPermissionCatalog') ? usersPermissionCatalog() : []);
$permissionDescriptions = function_exists('usersPermissionDescriptions') ? usersPermissionDescriptions() : [];
$groupView = 'list';

$permissionGroupLabels = [
    'admin' => 'Yönetim & Moderasyon Yetkileri',
    'public' => 'Üye & Genel Yetkiler',
];

$publicKeys = [
    'topics.view' => true,
    'topics.create' => true,
    'comments.view' => true,
    'comments.create' => true,
];

$permissionGroups = [
    'admin' => [],
    'public' => [],
];

foreach ($permissionCatalog as $permissionKey => $permissionLabel) {
    $category = isset($publicKeys[$permissionKey]) ? 'public' : 'admin';
    $permissionGroups[$category][(string)$permissionKey] = (string)$permissionLabel;
}


$groupPermissionsMap = [];
foreach ($groups as $group) {
    $groupId = (int)$group['id'];
    $groupPermissionsMap[$groupId] = array_keys(usersGetGroupPermissionMap($pdo, $groupId));
}

$groupPriorityForAdmin = static function (array $group, int $fallback): int {
    $priority = (int)($group['priority'] ?? 0);
    $displayOrder = (int)($group['display_order'] ?? 0);

    if ($priority > 0 && $priority < 100 && ($displayOrder <= 0 || $displayOrder === $priority)) {
        return $priority;
    }

    if ($displayOrder > 0 && $displayOrder < 10) {
        return $displayOrder;
    }

    return max(1, $fallback);
};
?>

<div class="user-groups-workspace">
    <?= adminRenderActionButtons([
        [
            'href' => 'users.php?tab=groups&group_view=list',
            'icon' => 'bi-list-ul',
            'label' => 'Mevcut Gruplar',
            'class' => 'ui-admin-btn-primary',
        ],
        [
            'icon' => 'bi-plus-circle',
            'label' => 'Yeni Grup Ekle',
            'class' => 'ui-admin-btn-outline',
            'attrs' => [
                'data-group-action' => 'add',
                'id' => 'addGroupBtnTop',
            ],
        ],
    ], [
        'class' => 'user-groups-subtabs ui-cluster',
        'attrs' => ['aria-label' => 'Grup yönetimi aksiyonları'],
    ]) ?>

    <?= adminRenderPanelOpen([
        'class' => 'user-groups-table-panel',
        'title' => 'Mevcut Gruplar',
        'icon' => 'bi-diagram-3',
        'subtitle' => 'Sistemdeki tüm grupları, üye sayılarını, önceliklerini ve temel yetki kapsamlarını yönetin.',
        'header_class' => 'user-groups-head',
        'body_class' => 'ui-admin-card-body-flush',
        'actions_html' => '<button type="button" class="ui-admin-btn ui-admin-btn-primary" data-group-action="add" id="addGroupBtnHead"><i class="bi bi-plus-circle"></i> Yeni Grup Ekle</button>',
    ]) ?>
            <?= adminRenderTableOpen([
                'Grup',
                'Üyeler',
                'Yetki',
                'Öncelik',
                'Oluşturulma',
                'Durum',
                ['label' => 'İşlemler', 'class' => 'ui-admin-table-actions'],
            ], [
                'class' => 'user-groups-table',
                'wrap_class' => 'ui-admin-table-responsive',
                'label' => 'Kullanıcı grupları',
            ]) ?>
                    <?php foreach ($groups as $groupIndex => $group): ?>
                        <?php
                        $groupId = (int)($group['id'] ?? 0);
                        $groupSlug = (string)($group['slug'] ?? '');
                        $groupPriority = $groupPriorityForAdmin($group, $groupIndex + 1);
                        $groupColor = (string)($group['color'] ?? '');
                        $isActive = (int)($group['is_active'] ?? 0) === 1;
                        ?>
                        <tr class="user-group-summary-row">
                            <td>
                                <div class="user-group-name-cell">
                                    <span class="user-group-color-swatch" <?= $groupColor !== '' && function_exists('uiStyleAttribute') ? uiStyleAttribute(['--group-color' => uiCssColorValue($groupColor)]) : '' ?>></span>
                                    <span>
                                        <strong><?= htmlspecialchars((string)($group['name'] ?? 'Grup')) ?></strong>
                                        <small><?= htmlspecialchars($groupSlug) ?></small>
                                    </span>
                                </div>
                            </td>
                            <td><?= number_format((int)($group['member_count'] ?? 0)) ?></td>
                            <td><?= number_format((int)($group['permission_count'] ?? 0)) ?></td>
                            <td><?= number_format($groupPriority) ?></td>
                            <td><span class="ui-admin-muted-sm"><?= !empty($group['created_at']) ? htmlspecialchars(date('d.m.Y', strtotime((string)$group['created_at']))) : '-' ?></span></td>
                            <td>
                                <span class="ui-admin-badge ui-admin-badge-<?= $isActive ? 'success' : 'secondary' ?>">
                                    <?= $isActive ? 'Aktif' : 'Pasif' ?>
                                </span>
                            </td>
                            <td class="ui-admin-table-actions">
                                <div class="user-actions-inline">
                                    <button type="button" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-outline" 
                                        data-group-action="edit" 
                                        data-group-tab="general"
                                        data-group-id="<?= $groupId ?>"
                                        data-group-name="<?= htmlspecialchars((string)($group['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-group-slug="<?= htmlspecialchars($groupSlug, ENT_QUOTES, 'UTF-8') ?>"
                                        data-group-priority="<?= $groupPriority ?>"
                                        data-group-color="<?= htmlspecialchars($groupColor, ENT_QUOTES, 'UTF-8') ?>"
                                        data-group-description="<?= htmlspecialchars((string)($group['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-group-is-active="<?= $isActive ? 1 : 0 ?>"
                                        data-group-is-default="<?= (int)($group['is_default'] ?? 0) ?>"
                                        data-group-is-staff="<?= (int)($group['is_staff'] ?? 0) ?>"
                                        title="Genel Ayarlar">
                                        <i class="bi bi-sliders"></i>
                                    </button>
                                    <button type="button" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-outline" 
                                        data-group-action="edit" 
                                        data-group-tab="permissions"
                                        data-group-id="<?= $groupId ?>"
                                        data-group-name="<?= htmlspecialchars((string)($group['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-group-slug="<?= htmlspecialchars($groupSlug, ENT_QUOTES, 'UTF-8') ?>"
                                        data-group-priority="<?= $groupPriority ?>"
                                        data-group-color="<?= htmlspecialchars($groupColor, ENT_QUOTES, 'UTF-8') ?>"
                                        data-group-description="<?= htmlspecialchars((string)($group['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-group-is-active="<?= $isActive ? 1 : 0 ?>"
                                        data-group-is-default="<?= (int)($group['is_default'] ?? 0) ?>"
                                        data-group-is-staff="<?= (int)($group['is_staff'] ?? 0) ?>"
                                        title="Yetkileri Düzenle">
                                        <i class="bi bi-key"></i>
                                    </button>
                                    <button type="button" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-outline" 
                                        data-group-action="copy" 
                                        data-group-id="<?= $groupId ?>"
                                        data-group-name="<?= htmlspecialchars((string)($group['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-group-slug="<?= htmlspecialchars($groupSlug, ENT_QUOTES, 'UTF-8') ?>"
                                        data-group-priority="<?= $groupPriority ?>"
                                        data-group-color="<?= htmlspecialchars($groupColor, ENT_QUOTES, 'UTF-8') ?>"
                                        data-group-description="<?= htmlspecialchars((string)($group['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-group-is-active="<?= $isActive ? 1 : 0 ?>"
                                        data-group-is-default="<?= (int)($group['is_default'] ?? 0) ?>"
                                        data-group-is-staff="<?= (int)($group['is_staff'] ?? 0) ?>"
                                        title="Grup Kopyala">
                                        <i class="bi bi-files"></i>
                                    </button>
                                    <?php if ($groupSlug !== 'admin'): ?>
                                        <form method="post" action="users.php?tab=groups&group_view=list" class="ui-admin-inline-form"<?= adminConfirmAttrs(['message' => 'Bu grup pasife alınacak. Devam edilsin mi?', 'title' => 'Grup pasife alınsın mı?', 'ok' => 'Pasife Al', 'tone' => 'warning']) ?>>
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="action" value="delete_group">
                                            <input type="hidden" name="group_id" value="<?= $groupId ?>">
                                            <button type="submit" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-danger" title="Sil">
                                                <i class="bi bi-archive"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
            <?= adminRenderTableClose() ?>
    <?= adminRenderPanelClose() ?>
</div>

<!-- Group Edit Modal -->
<div class="media-modal-overlay group-edit-modal" id="groupEditModal" role="dialog" aria-modal="true" aria-label="Grup Düzenle" hidden aria-hidden="true">
    <div class="media-modal ui-panel">
        <div class="media-modal-header ui-panel__head">
            <div>
                <h3 class="ui-admin-modal-title" id="groupModalTitle"><i class="bi bi-sliders"></i> Grup Ayarları</h3>
                <p class="group-edit-help" id="groupModalSubtitle">Grup kimliği, sıralama, renk ve yetki kapsamlarını düzenleyin.</p>
            </div>
            <button type="button" class="group-edit-close" data-ui-modal-close aria-label="Kapat" id="groupModalClose"><i class="bi bi-x-lg"></i></button>
        </div>
        
        <!-- Tab Navigation inside Modal -->
        <div class="ui-admin-modal-tabs">
            <button type="button" class="ui-admin-btn ui-admin-btn-outline active" data-group-tab-btn="general">
                <i class="bi bi-sliders"></i> Genel Bilgiler
            </button>
            <button type="button" class="ui-admin-btn ui-admin-btn-outline" data-group-tab-btn="permissions">
                <i class="bi bi-key"></i> Grup Yetkileri
            </button>
        </div>

        <form method="post" action="users.php?tab=groups" id="groupEditForm">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="save_group">
            <input type="hidden" name="group_id" id="groupEditId" value="0">

            <div class="media-modal-body ui-panel__body">
                <!-- Tab Pane: General -->
                <div class="group-tab-pane active" data-group-tab-pane="general">
                    <div class="user-group-form-grid ui-grid">
                        <div>
                            <label class="ui-admin-form-label" for="groupName">Grup Adı</label>
                            <input id="groupName" class="ui-admin-form-control" name="name" maxlength="100" required value="">
                        </div>
                        <div>
                            <label class="ui-admin-form-label" for="groupSlug">Slug</label>
                            <input id="groupSlug" class="ui-admin-form-control" name="slug" maxlength="100" placeholder="otomatik" value="">
                        </div>
                        <div>
                            <label class="ui-admin-form-label" for="groupPriority">Öncelik Sırası</label>
                            <input id="groupPriority" class="ui-admin-form-control" type="number" min="1" step="1" name="priority" value="1">
                        </div>
                        <div>
                            <label class="ui-admin-form-label" for="groupColor">Grup Rengi</label>
                            <input id="groupColor" class="ui-admin-form-control" type="color" name="color" value="#64748b">
                        </div>
                    </div>

                    <div class="ui-admin-mt-md">
                        <label class="ui-admin-form-label" for="groupDescription">Açıklama</label>
                        <textarea id="groupDescription" class="ui-admin-form-control" name="description" rows="3"></textarea>
                    </div>

                    <div class="user-group-switch-row ui-admin-mt-md">
                        <label class="ui-admin-switch">
                            <input type="checkbox" name="is_active" id="groupIsActive" value="1" checked>
                            <span class="ui-admin-switch-label">Aktif</span>
                        </label>
                        <label class="ui-admin-switch">
                            <input type="checkbox" name="is_default" id="groupIsDefault" value="1">
                            <span class="ui-admin-switch-label">Varsayılan grup</span>
                        </label>
                        <label class="ui-admin-switch">
                            <input type="checkbox" name="is_staff" id="groupIsStaff" value="1">
                            <span class="ui-admin-switch-label">Yönetim grubu</span>
                        </label>
                    </div>
                </div>

                <!-- Tab Pane: Permissions -->
                <div class="group-tab-pane" data-group-tab-pane="permissions" hidden>
                    <div class="user-permission-shell" id="permissions" data-group-permission-tools>
                        <div class="user-permission-head">
                            <div>
                                <h4><i class="bi bi-key"></i> Grup Yetkileri</h4>
                                <p>Yetkileri kategori, arama ve toplu seçim araçlarıyla yönetin.</p>
                            </div>
                            <div class="user-permission-toolbar">
                                <input type="search" class="ui-admin-form-control" placeholder="Yetki ara..." data-permission-search>
                                <select class="ui-admin-form-select" data-permission-filter>
                                    <option value="">Tüm kategoriler</option>
                                    <?php foreach (array_keys($permissionGroups) as $prefix): ?>
                                        <option value="<?= htmlspecialchars($prefix) ?>"><?= htmlspecialchars($permissionGroupLabels[$prefix] ?? ucfirst($prefix)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" data-permission-select-all><i class="bi bi-check2-square"></i> Tümünü Seç</button>
                                <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" data-permission-clear-all><i class="bi bi-square"></i> Tümünü Kaldır</button>
                            </div>
                        </div>
                        <div class="user-permission-groups">
                            <?php foreach ($permissionGroups as $prefix => $items): ?>
                                <fieldset class="user-permission-group ui-card" data-permission-category="<?= htmlspecialchars($prefix) ?>">
                                    <legend><?= htmlspecialchars($permissionGroupLabels[$prefix] ?? ucfirst($prefix)) ?></legend>
                                    <div class="user-permission-grid">
                                        <?php foreach ($items as $permissionKey => $permissionLabel): ?>
                                            <?php $permissionDescription = (string)($permissionDescriptions[$permissionKey] ?? 'Bu yetki icin henuz aciklama tanimlanmamis.'); ?>
                                            <label class="user-permission-item" data-permission-item data-permission-text="<?= htmlspecialchars(mb_strtolower($permissionKey . ' ' . $permissionLabel . ' ' . $permissionDescription), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($permissionKey) ?>" data-permission-checkbox="<?= htmlspecialchars($permissionKey) ?>">
                                                <span>
                                                    <strong><?= htmlspecialchars($permissionLabel) ?></strong>
                                                    <span class="user-permission-description"><?= htmlspecialchars($permissionDescription) ?></span>
                                                    <small class="user-permission-key"><?= htmlspecialchars($permissionKey) ?></small>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </fieldset>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="media-modal-footer ui-panel__foot">
                <button type="button" class="ui-admin-btn ui-admin-btn-outline" data-ui-modal-close id="groupModalCancelBtn">
                    <i class="bi bi-x-circle"></i> İptal
                </button>
                <button type="submit" class="ui-admin-btn ui-admin-btn-primary">
                    <i class="bi bi-save"></i> Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<script type="application/json" id="groupPermissionsData">
<?= json_encode($groupPermissionsMap) ?>
</script>

<script src="<?= asset_url('admin/assets/users-groups-tab.js', $baseUri) ?>" defer></script>
