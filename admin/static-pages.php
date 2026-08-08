<?php

declare(strict_types=1);

use App\Modules\StaticPages\Services\StaticPageService;
use App\Modules\StaticPages\Services\StaticPageValidationException;

require_once __DIR__ . '/init.php';

$pageTitle = 'Sabit Sayfalar';
adminRequirePermission('manage_static_pages', 'Sabit sayfaları yönetmek için gerekli izin hesabınıza tanımlanmamış.');

$service = new StaticPageService($pdo);
$schemaReady = $service->schemaReady();
$currentAdminId = (int) ($_SESSION['_auth_user_id'] ?? 0);
$errors = [];
$editingPage = null;
$formData = null;

if ($schemaReady && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token((string) ($_POST['_token'] ?? ''))) {
        flash('error', 'Güvenlik doğrulaması başarısız.');
        header('Location: static-pages.php');
        exit;
    }

    $action = trim((string) ($_POST['action'] ?? ''));
    $targetId = (int) ($_POST['id'] ?? 0);
    try {
        if ($action === 'save') {
            $result = $service->save($_POST, $targetId > 0 ? $targetId : null, $currentAdminId);
            $savedPage = $result['page'];
            $savedId = (int) ($savedPage['id'] ?? 0);
            $event = $targetId > 0 ? 'static_page_updated' : 'static_page_created';
            logActivity($pdo, $event, 'static_page', $savedId, ['title' => (string) ($savedPage['title'] ?? ''), 'status' => (string) ($savedPage['status'] ?? '')]);
            adminAuditLogger()->logAction($pdo, $event, 'static_page', $savedId, 'Sabit sayfa kaydedildi', [], [
                'title' => (string) ($savedPage['title'] ?? ''),
                'slug' => (string) ($savedPage['slug'] ?? ''),
                'status' => (string) ($savedPage['status'] ?? ''),
            ], false);
            $message = 'Sabit sayfa kaydedildi.';
            if (!empty($result['redirect_created'])) {
                $message .= ' Önceki URL için 301 yönlendirmesi oluşturuldu.';
            }
            flash('success', $message);
            header('Location: static-pages.php?edit=' . $savedId);
            exit;
        }
        if ($action === 'archive' && $service->archive($targetId, $currentAdminId)) {
            logActivity($pdo, 'static_page_archived', 'static_page', $targetId);
            flash('success', 'Sayfa arşivlendi.');
        } elseif ($action === 'restore' && $service->restoreToDraft($targetId, $currentAdminId)) {
            logActivity($pdo, 'static_page_restored', 'static_page', $targetId);
            flash('success', 'Sayfa taslak olarak geri alındı.');
        } else {
            flash('error', 'İşlem tamamlanamadı.');
        }
        header('Location: static-pages.php');
        exit;
    } catch (StaticPageValidationException $exception) {
        $errors = $exception->errors();
        $formData = $_POST;
        $editingPage = $targetId > 0 ? $service->find($targetId) : null;
    } catch (Throwable $exception) {
        appLogException($exception, ['source' => 'admin/static-pages.php', 'action' => $action]);
        $errors['page'] = 'Sayfa kaydedilirken beklenmeyen bir hata oluştu.';
        $formData = $_POST;
        $editingPage = $targetId > 0 ? $service->find($targetId) : null;
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
$isCreate = isset($_GET['new']);
if ($schemaReady && $formData === null && $editId > 0) {
    $editingPage = $service->find($editId);
    if ($editingPage === null) {
        flash('error', 'Sabit sayfa bulunamadı.');
        header('Location: static-pages.php');
        exit;
    }
    $formData = $editingPage;
}
if ($schemaReady && $formData === null && $isCreate) {
    $formData = [
        'title' => '', 'slug' => '', 'body_html' => '', 'status' => 'draft',
        'seo_title' => '', 'meta_description' => '', 'og_image' => '',
        'robots_noindex' => 0, 'robots_nofollow' => 0, 'sitemap_include' => 1,
        'show_in_footer' => 0, 'footer_label' => '', 'footer_order' => 0,
    ];
}

$showForm = $schemaReady && is_array($formData);
$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? 'active'));
$list = $schemaReady && !$showForm
    ? $service->paginate($search, $statusFilter, (int) ($_GET['page'] ?? 1), 20)
    : ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => 20];

$publishedCount = 0;
$draftCount = 0;
$archivedCount = 0;
if ($schemaReady) {
    $publishedCount = $service->paginate('', 'published', 1, 5)['total'];
    $draftCount = $service->paginate('', 'draft', 1, 5)['total'];
    $archivedCount = $service->paginate('', 'archived', 1, 5)['total'];
}

require __DIR__ . '/header.php';
?>
<div class="static-pages-shell">
    <div class="static-pages-commandbar">
        <div class="static-pages-commandbar-copy">
            <h2><?= $showForm ? htmlspecialchars((string) (($editingPage['id'] ?? 0) > 0 ? 'Sayfayı düzenle' : 'Yeni sayfa')) : 'Kurumsal ve yasal içerikler' ?></h2>
            <p>Yayın, SEO ve footer görünürlüğünü tek yerden yönetin.</p>
        </div>
        <?php if ($schemaReady): ?>
            <?php if ($showForm): ?>
                <a href="static-pages.php" class="ui-admin-btn ui-admin-btn-outline"><i class="bi bi-arrow-left"></i> Listeye dön</a>
            <?php else: ?>
                <a href="static-pages.php?new=1" class="ui-admin-btn ui-admin-btn-primary"><i class="bi bi-plus-lg"></i> Yeni sayfa</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if (!$schemaReady): ?>
        <div class="ui-panel ui-panel__body">
            <div class="ui-admin-alert ui-admin-alert-warning">
                <i class="bi bi-database-exclamation"></i>
                <div><strong>Veritabanı kurulumu bekleniyor.</strong><br>Static Pages modül migration'ını Veritabanı Senkronizasyonu ekranından uygulayın.</div>
            </div>
        </div>
    <?php elseif ($showForm): ?>
        <?php if ($errors !== []): ?>
            <div class="ui-admin-alert ui-admin-alert-danger" role="alert">
                <i class="bi bi-exclamation-octagon"></i>
                <ul class="static-pages-error-list"><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <form method="post" action="static-pages.php<?= ($editingPage['id'] ?? 0) > 0 ? '?edit=' . (int) $editingPage['id'] : '?new=1' ?>" data-static-page-form>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) ($editingPage['id'] ?? 0) ?>">
            <div class="static-pages-form-grid">
                <section class="ui-panel static-pages-editor-panel">
                    <div class="static-pages-panel-head">
                        <h3>İçerik</h3>
                        <p>Başlık, temiz URL ve ziyaretçiye gösterilecek metin.</p>
                    </div>
                    <div class="static-pages-panel-body static-pages-field-grid">
                        <div class="static-pages-field static-pages-field--full">
                            <label for="static-page-title">Başlık</label>
                            <input id="static-page-title" class="ui-admin-form-control" type="text" name="title" maxlength="255" required value="<?= htmlspecialchars((string) ($formData['title'] ?? '')) ?>" data-static-page-title>
                        </div>
                        <div class="static-pages-field static-pages-field--full">
                            <label for="static-page-slug">URL yolu</label>
                            <div class="ui-admin-input-group"><span>/</span><input id="static-page-slug" class="ui-admin-form-control" type="text" name="slug" maxlength="191" required value="<?= htmlspecialchars((string) ($formData['slug'] ?? '')) ?>" data-static-page-slug></div>
                        </div>
                        <div class="static-pages-field static-pages-field--full">
                            <label>Sayfa içeriği</label>
                            <textarea class="static-pages-body-input" name="body_html" data-static-page-body><?= htmlspecialchars((string) ($formData['body_html'] ?? '')) ?></textarea>
                            <div class="static-pages-editor-host" data-static-page-editor></div>
                        </div>
                    </div>
                </section>

                <aside class="ui-panel static-pages-settings-panel">
                    <div class="static-pages-panel-head">
                        <h3>Yayın ve görünürlük</h3>
                        <p>Sayfanın erişim ve keşfedilme davranışı.</p>
                    </div>
                    <div class="static-pages-panel-body static-pages-field-grid">
                        <div class="static-pages-field static-pages-field--full">
                            <label for="static-page-status">Durum</label>
                            <select id="static-page-status" class="ui-admin-form-select" name="status">
                                <?php foreach (['draft' => 'Taslak', 'published' => 'Yayında', 'archived' => 'Arşivde'] as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= (string) ($formData['status'] ?? 'draft') === $value ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="static-pages-toggle-list static-pages-field--full">
                            <?php foreach ([
                                ['sitemap_include', 'Sitemap', 'Yayındaki sayfayı site haritasına ekle.'],
                                ['show_in_footer', 'Footer bağlantısı', 'Sayfayı footer bağlantıları arasında göster.'],
                                ['robots_noindex', 'Noindex', 'Arama motorlarının sayfayı indekslemesini engelle.'],
                                ['robots_nofollow', 'Nofollow', 'Sayfadaki bağlantıların takip edilmemesini iste.'],
                            ] as [$key, $label, $help]): ?>
                                <label class="static-pages-toggle"><input type="checkbox" name="<?= $key ?>" value="1" <?= !empty($formData[$key]) ? 'checked' : '' ?>><span><strong><?= $label ?></strong><small><?= $help ?></small></span></label>
                            <?php endforeach; ?>
                        </div>
                        <div class="static-pages-field static-pages-field--full">
                            <label for="footer-label">Footer etiketi</label>
                            <input id="footer-label" class="ui-admin-form-control" type="text" name="footer_label" maxlength="255" value="<?= htmlspecialchars((string) ($formData['footer_label'] ?? '')) ?>" placeholder="Boşsa sayfa başlığı kullanılır">
                        </div>
                        <div class="static-pages-field static-pages-field--full">
                            <label for="footer-order">Footer sırası</label>
                            <input id="footer-order" class="ui-admin-form-control" type="number" name="footer_order" min="-9999" max="9999" value="<?= (int) ($formData['footer_order'] ?? 0) ?>">
                        </div>
                    </div>
                    <div class="static-pages-panel-head">
                        <h3>Arama ve paylaşım</h3>
                        <p>Boş alanlar site varsayılanlarını kullanır.</p>
                    </div>
                    <div class="static-pages-panel-body static-pages-field-grid">
                        <div class="static-pages-field static-pages-field--full"><label for="seo-title">SEO başlığı</label><input id="seo-title" class="ui-admin-form-control" type="text" name="seo_title" maxlength="255" value="<?= htmlspecialchars((string) ($formData['seo_title'] ?? '')) ?>"></div>
                        <div class="static-pages-field static-pages-field--full"><label for="meta-description">Meta açıklaması</label><textarea id="meta-description" class="ui-admin-form-control" name="meta_description" rows="4" maxlength="500"><?= htmlspecialchars((string) ($formData['meta_description'] ?? '')) ?></textarea></div>
                        <div class="static-pages-field static-pages-field--full"><label for="og-image">Open Graph görseli</label><input id="og-image" class="ui-admin-form-control" type="text" name="og_image" maxlength="2048" value="<?= htmlspecialchars((string) ($formData['og_image'] ?? '')) ?>" placeholder="https://... veya /uploads/..."></div>
                    </div>
                </aside>
            </div>
            <div class="static-pages-form-actions ui-panel ui-panel__body">
                <span><?= ($editingPage['id'] ?? 0) > 0 ? 'Son güncelleme: ' . htmlspecialchars((string) ($editingPage['updated_at'] ?? '')) : 'Yeni sayfa taslak olarak kaydedilebilir.' ?></span>
                <div class="static-pages-actions">
                    <?php if (($editingPage['id'] ?? 0) > 0): ?><a class="ui-admin-btn ui-admin-btn-outline" target="_blank" rel="noopener" href="static-page-preview.php?id=<?= (int) $editingPage['id'] ?>"><i class="bi bi-eye"></i> Önizle</a><?php endif; ?>
                    <button class="ui-admin-btn ui-admin-btn-primary" type="submit"><i class="bi bi-floppy"></i> Kaydet</button>
                </div>
            </div>
        </form>
    <?php else: ?>
        <div class="static-pages-metrics">
            <div class="static-pages-metric"><span>Yayında</span><strong><?= $publishedCount ?></strong></div>
            <div class="static-pages-metric"><span>Taslak</span><strong><?= $draftCount ?></strong></div>
            <div class="static-pages-metric"><span>Arşivde</span><strong><?= $archivedCount ?></strong></div>
        </div>
        <form method="get" class="ui-panel static-pages-filterbar">
            <div class="static-pages-filter-fields">
                <input class="ui-admin-form-control" type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Başlık veya URL ara">
                <select class="ui-admin-form-select" name="status">
                    <?php foreach (['active' => 'Aktif sayfalar', 'published' => 'Yayında', 'draft' => 'Taslak', 'archived' => 'Arşivde'] as $value => $label): ?><option value="<?= $value ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
                </select>
                <button class="ui-admin-btn ui-admin-btn-outline" type="submit"><i class="bi bi-funnel"></i> Filtrele</button>
            </div>
            <?php if ($search !== '' || $statusFilter !== 'active'): ?><a class="ui-admin-btn ui-admin-btn-ghost" href="static-pages.php"><i class="bi bi-x-lg"></i> Temizle</a><?php endif; ?>
        </form>
        <section class="ui-panel static-pages-table-wrap">
            <table class="static-pages-table">
                <thead><tr><th>Sayfa</th><th>Durum</th><th>Footer</th><th>Güncelleme</th><th><span class="visually-hidden">İşlemler</span></th></tr></thead>
                <tbody>
                <?php if ($list['items'] === []): ?>
                    <tr><td colspan="5"><div class="ui-admin-empty-state"><i class="bi bi-file-earmark-text"></i><strong>Sayfa bulunamadı</strong><span>Filtreyi değiştirin veya yeni bir sayfa oluşturun.</span></div></td></tr>
                <?php endif; ?>
                <?php foreach ($list['items'] as $page): ?>
                    <?php $status = (string) ($page['status'] ?? 'draft'); ?>
                    <tr>
                        <td class="static-pages-title-cell"><strong><?= htmlspecialchars((string) $page['title']) ?></strong><code>/<?= htmlspecialchars((string) $page['slug']) ?></code></td>
                        <td><span class="static-pages-badge static-pages-badge--<?= htmlspecialchars($status) ?>"><i class="bi <?= $status === 'published' ? 'bi-broadcast' : ($status === 'archived' ? 'bi-archive' : 'bi-pencil') ?>"></i><?= ['published' => 'Yayında', 'archived' => 'Arşivde', 'draft' => 'Taslak'][$status] ?? $status ?></span></td>
                        <td><?= !empty($page['show_in_footer']) ? '<i class="bi bi-check2-circle"></i> Görünür' : '<span class="text-muted">Kapalı</span>' ?></td>
                        <td><?= htmlspecialchars((string) $page['updated_at']) ?></td>
                        <td><div class="static-pages-actions">
                            <?php if ($status === 'published'): ?><a class="ui-admin-btn ui-admin-btn-ghost ui-admin-btn-sm" href="<?= htmlspecialchars($service->publicPath((string) $page['slug'])) ?>" target="_blank" rel="noopener" title="Sayfayı aç"><i class="bi bi-box-arrow-up-right"></i></a><?php else: ?><a class="ui-admin-btn ui-admin-btn-ghost ui-admin-btn-sm" href="static-page-preview.php?id=<?= (int) $page['id'] ?>" target="_blank" rel="noopener" title="Önizle"><i class="bi bi-eye"></i></a><?php endif; ?>
                            <a class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" href="static-pages.php?edit=<?= (int) $page['id'] ?>"><i class="bi bi-pencil-square"></i> Düzenle</a>
                            <form method="post" class="ui-admin-inline-form"<?= adminConfirmAttrs(['message' => $status === 'archived' ? 'Sayfa taslak olarak geri alınacak.' : 'Sayfa arşivlenecek ve ziyaretçilere kapanacak.', 'title' => $status === 'archived' ? 'Sayfayı geri al' : 'Sayfayı arşivle', 'ok' => $status === 'archived' ? 'Geri Al' : 'Arşivle', 'cancel' => 'Vazgeç', 'tone' => $status === 'archived' ? 'success' : 'warning', 'icon' => $status === 'archived' ? 'bi-arrow-counterclockwise' : 'bi-archive']) ?>><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $page['id'] ?>"><button class="ui-admin-btn ui-admin-btn-ghost ui-admin-btn-sm" type="submit" name="action" value="<?= $status === 'archived' ? 'restore' : 'archive' ?>" title="<?= $status === 'archived' ? 'Geri al' : 'Arşivle' ?>"><i class="bi <?= $status === 'archived' ? 'bi-arrow-counterclockwise' : 'bi-archive' ?>"></i></button></form>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ((int) $list['pages'] > 1): ?>
                <nav class="static-pages-pagination" aria-label="Sayfalama">
                    <?php for ($i = 1; $i <= (int) $list['pages']; $i++): ?><a class="ui-admin-btn ui-admin-btn-sm <?= $i === (int) $list['page'] ? 'ui-admin-btn-primary' : 'ui-admin-btn-outline' ?>" href="?<?= http_build_query(['q' => $search, 'status' => $statusFilter, 'page' => $i]) ?>"><?= $i ?></a><?php endfor; ?>
                </nav>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/footer.php'; ?>
