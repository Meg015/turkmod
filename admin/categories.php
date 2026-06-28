<?php

declare(strict_types=1);
require_once __DIR__ . '/init.php';
adminRequirePermission('categories.view', 'Kategorileri goruntulemek icin gerekli izin hesabiniza tanimlanmamis.');

$pageTitle = 'Kategori Yönetimi';
$editId = (int)($_GET['edit'] ?? 0);

if ($pdo) {
    ensureAdminSchema($pdo);
}

// AJAX JSON handler
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        echo json_encode(['ok' => false, 'message' => 'Güvenlik hatası.']);
        exit;
    }
    if (!adminCurrentUserCan('categories.delete')) {
        echo json_encode(['ok' => false, 'message' => 'Kategori silmek icin gerekli izin hesabiniza tanimlanmamis.']);
        exit;
    }
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    try {
        if (!$pdo) throw new RuntimeException('Veritabanı bağlantısı yok.');
        if ($action === 'delete') {
            if (categoryHasTopics($pdo, $id)) throw new RuntimeException('Bu kategoriye bağlı konular olduğu için silinemez.');
            $pdo->prepare("UPDATE categories SET parent_id = NULL WHERE parent_id = ?")->execute([$id]);
            $pdo->prepare("UPDATE categories SET status = 'inactive', deleted_at = NOW() WHERE id = ?")->execute([$id]);
            logActivity($pdo, 'category_deleted', 'category', $id);
            echo json_encode(['ok' => true, 'message' => 'Kategori silindi.']);
        } else {
            echo json_encode(['ok' => false, 'message' => 'Bilinmeyen işlem.']);
        }
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'message' => safeErrorMessage($e)]);
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik hatası.');
        header('Location: categories.php');
        exit;
    }

    $action = (string)($_POST['action'] ?? 'save');
    $id = (int)($_POST['id'] ?? 0);
    $requiredPermission = $action === 'delete'
        ? 'categories.delete'
        : ($id > 0 ? 'categories.edit' : 'categories.create');
    if (!adminCurrentUserCan($requiredPermission)) {
        adminDenyAction('Kategori islemi yapmak icin gerekli izin hesabiniza tanimlanmamis.', 'categories.php' . ($id > 0 ? '?edit=' . $id : ''));
    }

    try {
        if (!$pdo) {
            throw new RuntimeException('Veritabanı bağlantısı bulunamadı.');
        }

        if ($action === 'delete') {
            if (categoryHasTopics($pdo, $id)) {
                throw new RuntimeException('Bu kategoriye bağlı konular olduğu için silinemez.');
            }

            $pdo->prepare("UPDATE categories SET parent_id = NULL WHERE parent_id = ?")->execute([$id]);
            $pdo->prepare("UPDATE categories SET status = 'inactive', deleted_at = NOW() WHERE id = ?")->execute([$id]);
            logActivity($pdo, 'category_deleted', 'category', $id);
            flash('success', 'Kategori silindi.');
            header('Location: categories.php');
            exit;
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $slug = trim((string)($_POST['slug'] ?? ''));
        $slug = $slug !== '' ? slugify($slug) : slugify($name);
        $parentId = (int)($_POST['parent_id'] ?? 0);
        $parentId = $parentId > 0 ? $parentId : null;
        $status = in_array(($_POST['status'] ?? 'active'), ['active', 'inactive'], true) ? (string)$_POST['status'] : 'active';
        $displayOrder = max(0, (int)($_POST['display_order'] ?? 0));
        $description = trim((string)($_POST['description'] ?? ''));
        $seoTitle = trim((string)($_POST['seo_title'] ?? ''));
        $seoDescription = trim((string)($_POST['seo_description'] ?? ''));

        if ($name === '' || $slug === '') {
            throw new RuntimeException('Kategori adı ve slug zorunludur.');
        }

        if ($id > 0 && $parentId === $id) {
            throw new RuntimeException('Bir kategori kendi üst kategorisi olamaz.');
        }

        $duplicate = $pdo->prepare("SELECT id FROM categories WHERE slug = ? AND id <> ?");
        $duplicate->execute([$slug, $id]);
        if ($duplicate->fetchColumn()) {
            throw new RuntimeException('Bu slug başka bir kategori tarafından kullanılıyor.');
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE categories
                SET parent_id = :parent_id, name = :name, slug = :slug, description = :description, status = :status,
                    display_order = :display_order, seo_title = :seo_title, seo_description = :seo_description, updated_at = NOW()
                WHERE id = :id");
            $stmt->execute([
                'parent_id' => $parentId,
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'status' => $status,
                'display_order' => $displayOrder,
                'seo_title' => $seoTitle,
                'seo_description' => $seoDescription,
                'id' => $id,
            ]);
            logActivity($pdo, 'category_updated', 'category', $id, ['name' => $name]);
            flash('success', 'Kategori güncellendi.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories
                (parent_id, name, slug, description, status, display_order, seo_title, seo_description, created_at, updated_at)
                VALUES (:parent_id, :name, :slug, :description, :status, :display_order, :seo_title, :seo_description, NOW(), NOW())");
            $stmt->execute([
                'parent_id' => $parentId,
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'status' => $status,
                'display_order' => $displayOrder,
                'seo_title' => $seoTitle,
                'seo_description' => $seoDescription,
            ]);
            logActivity($pdo, 'category_created', 'category', (int)$pdo->lastInsertId(), ['name' => $name]);
            flash('success', 'Kategori eklendi.');
        }

        header('Location: categories.php');
        exit;
    } catch (Throwable $e) {
        flash('error', safeErrorMessage($e));
        header('Location: categories.php' . ($id > 0 ? '?edit=' . $id : ''));
        exit;
    }
}

$categories = getAdminCategories($pdo);
$categoryTree = buildAdminCategoryTree($categories);
$editing = null;
foreach ($categories as $category) {
    if ((int)$category['id'] === $editId) {
        $editing = $category;
        break;
    }
}

$successMsg = get_flash('success');
$errorMsg = get_flash('error');
require_once __DIR__ . '/header.php';

$csrfToken = csrf_token();
?>
<div class="category-page">
<?php if ($successMsg): ?>
<div class="ui-admin-alert ui-admin-alert-success ui-alert ui-alert--success">
    <i class="bi bi-check-circle-fill"></i>
    <?= htmlspecialchars($successMsg) ?>
    <button type="button" class="ui-admin-alert-close">&times;</button>
</div>
<?php endif; ?>

<?php if ($errorMsg): ?>
<div class="ui-admin-alert ui-admin-alert-error ui-alert ui-alert--error">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <?= htmlspecialchars($errorMsg) ?>
    <button type="button" class="ui-admin-alert-close">&times;</button>
</div>
<?php endif; ?>

<!-- Hero Header -->
<section class="ui-admin-page-hero">
    <div class="ui-admin-page-hero-text">
        <h2><i class="bi bi-folder2-open ui-admin-icon-gap"></i>Kategori Yönetimi</h2>
        <p>Ana kategori, alt kategori, SEO alanları, sıralama ve durum tek ekrandan yönetilir.</p>
    </div>
    <div class="ui-admin-page-hero-actions">
        <a class="ui-admin-btn-hero" href="categories.php#categoryForm">
            <i class="bi bi-plus-lg"></i> Yeni Kategori
        </a>
    </div>
</section>

<!-- Stat Tiles -->
<div class="admin-stat-grid category-summary ui-grid">
    <div class="admin-stat-card stat-info category-stat ui-card">
        <div class="stat-icon"><i class="bi bi-folder2-open"></i></div>
        <div class="stat-content">
            <span class="stat-label">Toplam</span>
            <span class="stat-value"><?= count($categories) ?></span>
        </div>
    </div>
    <div class="admin-stat-card stat-success category-stat ui-card">
        <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
        <div class="stat-content">
            <span class="stat-label">Aktif</span>
            <span class="stat-value"><?= count(array_filter($categories, fn($c) => ($c['status'] ?? '') === 'active')) ?></span>
        </div>
    </div>
    <div class="admin-stat-card stat-warning category-stat ui-card">
        <div class="stat-icon"><i class="bi bi-diagram-3"></i></div>
        <div class="stat-content">
            <span class="stat-label">Alt Kategori</span>
            <span class="stat-value"><?= count(array_filter($categories, fn($c) => !empty($c['parent_id']))) ?></span>
        </div>
    </div>
    <div class="admin-stat-card stat-info category-stat ui-card">
        <div class="stat-icon"><i class="bi bi-card-heading"></i></div>
        <div class="stat-content">
            <span class="stat-label">Konu Bağlı</span>
            <span class="stat-value"><?= array_sum(array_map(fn($c) => (int)($c['topic_count'] ?? 0), $categories)) ?></span>
        </div>
    </div>
</div>

<!-- Two-column layout -->
<div class="ui-admin-two-col">
    <!-- Category List -->
    <div class="ui-admin-premium-card ui-card">
        <div class="ui-admin-premium-card-header ui-panel__head ui-card">
            <i class="bi bi-list-nested"></i> Kategori Listesi
        </div>
        <div class="ui-admin-table-wrap-x">
            <table class="ui-admin-premium-table">
                <thead>
                    <tr>
                        <th>Ad</th>
                        <th>Slug</th>
                        <th>Durum</th>
                        <th>Konu</th>
                        <th class="ui-admin-table-head-actions">İşlemler</th>
                    </tr>
                </thead>
                <tbody id="categoryTableBody">
                    <?php if (empty($categoryTree)): ?>
                        <tr><td colspan="5">
                            <div class="ui-admin-empty ui-empty">
                                <div class="ui-admin-empty-icon tone-info ui-empty"><i class="bi bi-inbox"></i></div>
                                <h3 class="ui-admin-empty-title ui-empty">Henüz kategori yok</h3>
                                <p class="ui-admin-empty-desc ui-empty">Yeni kategori oluşturduğunuzda liste burada görünecek.</p>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($categoryTree as $category): ?>
                        <tr id="cat-row-<?= (int)$category['id'] ?>">
                            <td>
                                <div class="ui-admin-category-name ui-admin-category-depth-<?= min(8, max(0, (int)$category['depth'])) ?>">
                                    <?php if ((int)$category['depth'] > 0): ?>
                                        <span class="ui-admin-category-indent">↳</span>
                                    <?php endif; ?>
                                    <i class="bi <?= (int)$category['depth'] === 0 ? 'bi-folder-fill' : 'bi-folder2' ?> ui-admin-category-icon"></i>
                                    <?= htmlspecialchars((string)$category['name']) ?>
                                </div>
                            </td>
                            <td class="ui-admin-category-slug"><?= htmlspecialchars((string)$category['slug']) ?></td>
                            <td>
                                <span class="ui-admin-badge <?= ($category['status'] ?? '') === 'active' ? 'ui-admin-badge-success' : 'ui-admin-badge-muted' ?>">
                                    <?= ($category['status'] ?? '') === 'active' ? 'Aktif' : 'Pasif' ?>
                                </span>
                            </td>
                            <td>
                                <span class="ui-admin-count-pill">
                                    <?= (int)($category['topic_count'] ?? 0) ?>
                                </span>
                            </td>
                            <td class="ui-admin-table-cell-actions">
                                <div class="ui-admin-actions-inline">
                                    <a class="ui-admin-action-btn edit" href="categories.php?edit=<?= (int)$category['id'] ?>" title="Düzenle">
                                        <i class="bi bi-pencil"></i> Düzenle
                                    </a>
                                    <button class="ui-admin-action-btn danger"
                                        data-category-delete="<?= (int)$category['id'] ?>"
                                        data-category-name="<?= htmlspecialchars((string)$category['name'], ENT_QUOTES) ?>"
                                        title="Sil">
                                        <i class="bi bi-trash"></i> Sil
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Form Panel -->
    <form method="post" action="categories.php" id="categoryForm" class="ui-admin-premium-card ui-admin-sticky-panel ui-card ui-panel">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">

        <div class="ui-admin-premium-card-header ui-panel__head ui-card">
            <?= $editing
                ? '<i class="bi bi-pencil-square ui-admin-category-icon"></i> Kategori Düzenle'
                : '<i class="bi bi-plus-circle ui-admin-category-icon-add"></i> Yeni Kategori' ?>
        </div>

        <div class="ui-admin-premium-card-body ui-panel__body ui-card">
            <div class="ui-admin-field">
                <label>Ad</label>
                <input class="ui-admin-input" name="name" value="<?= htmlspecialchars((string)($editing['name'] ?? '')) ?>" required placeholder="Kategori adı">
            </div>
            <div class="ui-admin-field">
                <label>Slug <span class="ui-admin-label-note">(boş bırakılırsa otomatik)</span></label>
                <input class="ui-admin-input" name="slug" value="<?= htmlspecialchars((string)($editing['slug'] ?? '')) ?>" placeholder="kategori-url">
            </div>
            <div class="ui-admin-field">
                <label>Üst Kategori</label>
                <select class="ui-admin-input" name="parent_id">
                    <option value="0">--- Ana kategori ---</option>
                    <?php foreach ($categoryTree as $category): ?>
                        <?php if ((int)$category['id'] === (int)($editing['id'] ?? 0)) { continue; } ?>
                        <option value="<?= (int)$category['id'] ?>" <?= (int)($editing['parent_id'] ?? 0) === (int)$category['id'] ? 'selected' : '' ?>>
                            <?= str_repeat('— ', (int)$category['depth']) . htmlspecialchars((string)$category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ui-admin-form-grid-2 ui-grid">
                <div class="ui-admin-field">
                    <label>Durum</label>
                    <select class="ui-admin-input" name="status">
                        <option value="active" <?= ($editing['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Aktif</option>
                        <option value="inactive" <?= ($editing['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Pasif</option>
                    </select>
                </div>
                <div class="ui-admin-field">
                    <label>Sıralama</label>
                    <input class="ui-admin-input" type="number" name="display_order" min="0" value="<?= htmlspecialchars((string)($editing['display_order'] ?? 0)) ?>">
                </div>
            </div>
            <div class="ui-admin-field">
                <label>Açıklama</label>
                <textarea class="ui-admin-input" name="description" rows="3" placeholder="Kategori açıklaması..."><?= htmlspecialchars((string)($editing['description'] ?? '')) ?></textarea>
            </div>
            <hr class="ui-admin-divider">
            <h4 class="ui-admin-heading-minor">
                <i class="bi bi-search"></i> SEO Ayarları
            </h4>
            <div class="ui-admin-field">
                <label>SEO Başlık</label>
                <input class="ui-admin-input" name="seo_title" value="<?= htmlspecialchars((string)($editing['seo_title'] ?? '')) ?>" placeholder="Arama motorları için başlık">
            </div>
            <div class="ui-admin-field">
                <label>SEO Açıklama</label>
                <textarea class="ui-admin-input" name="seo_description" rows="3" placeholder="Arama motorları için açıklama..."><?= htmlspecialchars((string)($editing['seo_description'] ?? '')) ?></textarea>
            </div>
            <button type="submit" class="ui-admin-btn-save">
                <i class="bi bi-save2"></i>
                <?= $editing ? 'Değişiklikleri Kaydet' : 'Kategoriyi Oluştur' ?>
            </button>
        </div>
    </form>
</div>

</div>

<script src="<?= asset_url('admin/assets/categories-page.js', $baseUri) ?>" defer></script>

<?php require_once __DIR__ . '/footer.php'; ?>
