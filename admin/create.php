<?php

declare(strict_types=1);
require_once __DIR__ . '/init.php';
adminRequirePermission('topics.create', 'Yeni konu olusturmak icin gerekli izin hesabiniza tanimlanmamis.');

$pageTitle = 'Yeni Konu Ekle';
$categoryOptions = getAdminCategoryOptions($pdo);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik hatası.');
        header('Location: create.php');
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($content !== '') {
        $content = sanitizeHtml($content);
    }
    $authorTopic = trim($_POST['author_topic'] ?? '');
    $topicVersion = trim($_POST['topic_version'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $status = trim($_POST['status'] ?? 'draft');
    if (!in_array($status, ['draft', 'published'], true)) {
        $status = 'draft';
    }
    $downloadLines = trim($_POST['download_lines'] ?? '');
    $videoUrl = trim($_POST['topic_video_url'] ?? '');

    if ($pdo && $categoryId <= 0) {
        $categoryId = ensureDefaultCategory($pdo);
    }

    if ($pdo && $title !== '' && $content !== '' && $categoryId > 0) {
        $slug = generateUniqueSlug($pdo, $title, 'topics');

        try {
            $authorId = (int) ($_SESSION['_auth_user_id'] ?? 1);
            $uploadedPaths = [];
            $pdo->beginTransaction();

            $pdo->prepare("INSERT INTO topics (category_id, author_id, title, slug, author_topic, topic_version, topic_descriptions, status, created_at, published_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)")
                ->execute([
                    $categoryId,
                    $authorId,
                    $title,
                    $slug,
                    $authorTopic !== '' ? $authorTopic : null,
                    $topicVersion !== '' ? $topicVersion : null,
                    $content,
                    $status,
                    $status === 'published' ? date('Y-m-d H:i:s') : null,
                ]);

            $topicId = (int) $pdo->lastInsertId();
            syncTopicDownloadLinks($pdo, $topicId, $downloadLines);

            if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $attachment = handleFileUpload($pdo, $topicId, $_FILES['attachment'], 'attachment', 0, false, $title);
                if (isset($attachment['error'])) {
                    throw new RuntimeException((string) $attachment['error']);
                }
                if (isset($attachment['path'])) {
                    $uploadedPaths[] = (string) $attachment['path'];
                }
            }

            $primaryMediaId = null;
            $imageSequence = 1;
            if (!empty($_FILES['topic_first_image_file']) && $_FILES['topic_first_image_file']['error'] === UPLOAD_ERR_OK) {
                $cover = handleFileUpload($pdo, $topicId, $_FILES['topic_first_image_file'], 'image', 0, true, $title, $imageSequence++);
                if (isset($cover['error'])) {
                    throw new RuntimeException((string) $cover['error']);
                }
                if (isset($cover['path'])) {
                    $uploadedPaths[] = (string) $cover['path'];
                }
                if (isset($cover['id'])) {
                    $primaryMediaId = (int) $cover['id'];
                }
            }

            $displayOrder = 1;
            if ($videoUrl !== '') {
                createTopicMediaRecord($pdo, $topicId, $videoUrl, 'video', $displayOrder++, false, 'remote');
            }

            if (!empty($_FILES['topic_images_files']['name'][0])) {
                $fileCount = count($_FILES['topic_images_files']['name']);
                $maxFiles = min(10, $fileCount);
                for ($i = 0; $i < $maxFiles; $i++) {
                    if ($_FILES['topic_images_files']['error'][$i] === UPLOAD_ERR_OK) {
                        $singleFile = [
                            'name' => $_FILES['topic_images_files']['name'][$i],
                            'type' => $_FILES['topic_images_files']['type'][$i],
                            'tmp_name' => $_FILES['topic_images_files']['tmp_name'][$i],
                            'error' => $_FILES['topic_images_files']['error'][$i],
                            'size' => $_FILES['topic_images_files']['size'][$i],
                        ];
                        $res = handleFileUpload($pdo, $topicId, $singleFile, 'image', $i + 1, false, $title, $imageSequence++);
                        if (isset($res['error'])) {
                            throw new RuntimeException((string) $res['error']);
                        }
                        if (isset($res['path'])) {
                            $uploadedPaths[] = (string) $res['path'];
                            $displayOrder++;
                        }
                    }
                }
            }
            $pdo->prepare("UPDATE topics SET primary_media_file_id = ? WHERE id = ?")
                ->execute([$primaryMediaId, $topicId]);

            $pdo->commit();
            seoInvalidateSitemapCaches();
            logActivity($pdo, 'topic_created', 'topic', $topicId, ['title' => $title]);
            adminAuditLogger()->logAction($pdo, 'topic_created', 'topic', $topicId, 'Konu oluşturuldu', [], ['title' => $title], false);

            flash('success', 'Konu başarıyla eklendi.');
            header('Location: topics.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach (($uploadedPaths ?? []) as $uploadedPath) {
                topicDeletePhysicalFile((string) $uploadedPath);
            }
            flash('error', safeErrorMessage($e, 'Konu eklenirken bir hata oluştu.'));
        }
    } else {
        flash('error', 'Başlık, kategori ve içerik alanları zorunludur.');
    }
}

$errorMsg = get_flash('error');
require_once __DIR__ . '/header.php';
?>
<?= adminRenderPanelOpen([
    'tag' => 'div',
    'icon' => 'bi-plus-circle',
    'title' => 'Yeni Konu Ekle',
]) ?>
        <form id="topicForm" method="post" action="create.php" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="seo-checklist ui-admin-seo-checklist">
                <div class="ui-admin-seo-checklist-title">
                    <i class="bi bi-clipboard-check"></i> SEO Kalite Kontrol
                </div>
                <div class="ui-admin-seo-checklist-grid ui-grid">
                    <div><i class="bi bi-check2-circle ui-admin-seo-check"></i> Baslik net ve aranabilir olsun.</div>
                    <div><i class="bi bi-check2-circle ui-admin-seo-check"></i> Dogru kategori secilsin.</div>
                    <div><i class="bi bi-check2-circle ui-admin-seo-check"></i> Icerik aciklama ve kurulum bilgisi icersin.</div>
                    <div><i class="bi bi-check2-circle ui-admin-seo-check"></i> Kapak gorseli ve indirme linki kontrol edilsin.</div>
                </div>
            </div>

            <div class="ui-admin-editor-layout ui-section">
                <div>
                    <label class="ui-admin-form-label">Başlık</label>
                    <input type="text" name="title" class="ui-admin-form-control" required>
                </div>
                <div>
                    <label class="ui-admin-form-label">Kategori</label>
                    <select name="category_id" class="ui-admin-form-select" required>
                        <?php foreach ($categoryOptions as $category): ?>
                            <option value="<?= (int) $category['id'] ?>"><?= str_repeat('— ', (int) ($category['depth'] ?? 0)) . htmlspecialchars((string) $category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="ui-admin-mb-md">
                <label class="ui-admin-form-label">İçerik</label>
                <textarea name="content" rows="12" class="ui-admin-form-control rich-editor" required></textarea>
            </div>

            <div class="ui-admin-form-grid-two ui-grid">
                <div>
                    <label class="ui-admin-form-label">Mod Yapımcısı</label>
                    <input type="text" name="author_topic" class="ui-admin-form-control" placeholder="Örn: SCS Software">
                </div>
                <div>
                    <label class="ui-admin-form-label">Gerekli Oyun Sürümü</label>
                    <input type="text" name="topic_version" class="ui-admin-form-control" placeholder="Örn: 1.50">
                </div>
            </div>

            <div class="media-uploader-shell ui-admin-mb-md ui-section">
                <div class="media-uploader-card ui-card">
                    <div class="media-uploader-head ui-panel__head">
                        <div>
                            <h3>Kapak Görseli</h3>
                            <p>Tek bir büyük kapak görseli seçin. PNG, JPG, JPEG ve WEBP desteklenir.</p>
                        </div>
                        <div class="media-upload-pill"><i class="bi bi-image"></i> 1 kapak resmi mecburidir</div>
                    </div>
                    <div class="media-dropzone-modern" data-uploader="cover">
                        <input type="file" name="topic_first_image_file" id="coverInput" class="d-none ui-admin-hidden-file-input" accept=".png,.jpg,.jpeg,.webp" hidden aria-hidden="true" tabindex="-1">
                        <div class="media-dropzone-trigger" data-open-input="coverInput">
                            <i class="bi bi-card-image"></i>
                            <strong>Kapak görselini sürükleyin veya seçmek için tıklayın</strong>
                            <span>Önerilen: yatay oranlı, yüksek çözünürlüklü görseller</span>
                        </div>
                        <div class="media-preview-grid ui-grid" id="coverPreview"></div>
                    </div>
                </div>

                <div class="media-uploader-card ui-card">
                    <div class="media-uploader-head ui-panel__head">
                        <div>
                            <h3>Mod Resimleri</h3>
                            <p>Birden fazla mod resmi ekleyin. İsterseniz ayrıca tanıtım videosu URL”si de ekleyin.</p>
                        </div>
                        <div class="media-upload-pill"><i class="bi bi-collection"></i> Maksimum 10 tane resim ekleyebilirsiniz</div>
                    </div>
                    <div class="media-dropzone-modern" data-uploader="gallery">
                        <input type="file" name="topic_images_files[]" id="galleryInput" class="d-none ui-admin-hidden-file-input" accept=".png,.jpg,.jpeg,.webp" multiple hidden aria-hidden="true" tabindex="-1">
                        <div class="media-dropzone-trigger" data-open-input="galleryInput">
                            <i class="bi bi-images"></i>
                            <strong>Mod resimlerini toplu yükleyin</strong>
                            <span>Birden çok dosya seçebilir, sürükle-bırak ile hızlıca önizleme alabilirsiniz</span>
                        </div>
                        <div class="media-preview-grid ui-grid" id="galleryPreview"></div>
                    </div>
                    <div class="ui-admin-video-row">
                        <label class="ui-admin-form-label">Video URL Ekle</label>
                        <input type="url" name="topic_video_url" class="ui-admin-form-control" placeholder="https://www.youtube.com/watch?v=...">
                    </div>
                </div>
            </div>

            <div class="ui-admin-mb-md">
                <label class="ui-admin-form-label">İndirme Bağlantıları (opsiyonel)</label>
                <div id="dlRows">
                    <div class="dl-row ui-admin-download-row">
                        <input type="text" name="dl_name[]" class="ui-admin-form-control ui-admin-download-name" placeholder="Kaynak adı (ör: Google Drive)">
                        <input type="url" name="dl_url[]" class="ui-admin-form-control ui-admin-download-url" placeholder="https://...">
                        <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" data-ui-remove-closest=".dl-row" title="Kaldır"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
                <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm ui-admin-btn-offset-top" data-ui-action="addDlRow"><i class="bi bi-plus-lg"></i> Bağlantı Ekle</button>
                <input type="hidden" name="download_lines" id="dlHidden">
            </div>

            <div class="ui-admin-mb-md">
                <label class="ui-admin-form-label">Dosya Ekle (opsiyonel)</label>
                <input type="file" name="attachment" class="ui-admin-form-control" accept=".zip,.rar,.7z,.pdf,.png,.jpg,.jpeg,.webp">
                <small class="ui-admin-muted-sm">Maks 50MB. ZIP, RAR, 7Z, PDF, PNG, JPG, WEBP</small>
            </div>

            <div class="ui-admin-status-narrow">
                <label class="ui-admin-form-label">Durum</label>
                <select name="status" class="ui-admin-form-select">
                    <option value="draft">Taslak Olarak Kaydet</option>
                    <option value="published">Hemen Yayınla</option>
                </select>
            </div>

            <div class="ui-admin-form-actions">
                <button type="submit" class="ui-admin-btn ui-admin-btn-primary"><i class="bi bi-save"></i>Konuyu Kaydet</button>
                <a href="topics.php" class="ui-admin-btn ui-admin-btn-outline">İptal</a>
            </div>
        </form>
<?= adminRenderPanelClose('div') ?>

<script src="<?= asset_url('admin/assets/create-page.js', $baseUri) ?>" defer></script>

<?php require_once __DIR__ . '/footer.php'; ?>
