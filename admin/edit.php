<?php

declare(strict_types=1);
require_once __DIR__ . '/init.php';
adminRequirePermission('topics.edit', 'Konu duzenlemek icin gerekli izin hesabiniza tanimlanmamis.');

$id = (int)($_GET['id'] ?? 0);
$topic = null;

if ($pdo && $id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT t.*, cat.name AS category, m.path AS primary_media_path
                               FROM topics t
                               LEFT JOIN categories cat ON t.category_id = cat.id
                               LEFT JOIN media_files m ON t.primary_media_file_id = m.id
                               WHERE t.id = :id AND t.deleted_at IS NULL");
        $stmt->execute(['id' => $id]);
        $topic = $stmt->fetch();
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
}

if (!$topic) {
    flash('error', 'Konu bulunamadı.');
    header('Location: topics.php');
    exit;
}

$categoryOptions = getAdminCategoryOptions($pdo);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik hatası.');
        header('Location: edit.php?id=' . $id);
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $authorTopic = trim($_POST['author_topic'] ?? '');
    $topicVersion = trim($_POST['topic_version'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $status = trim($_POST['status'] ?? 'draft');
    if (!in_array($status, ['draft', 'published'], true)) {
        $status = 'draft';
    }
    $keepMedia = $_POST['keep_media'] ?? [];
    $videoUrl = trim($_POST['topic_video_url'] ?? '');
    $topicDownloadLinks = trim($_POST['topic_download_links'] ?? '');
    $keepMediaIds = array_values(array_filter(array_map('intval', is_array($keepMedia) ? $keepMedia : [])));

    if ($pdo && $categoryId <= 0) {
        $categoryId = ensureDefaultCategory($pdo);
    }

    if ($pdo && $title !== '' && $content !== '' && $categoryId > 0) {
        // Unique slug oluştur (mevcut ID hariç)
        $slug = generateUniqueSlug($pdo, $title, 'topics', $id);
        
        try {
            $uploadedPaths = [];
            topicRevisionEnsureSchema($pdo);
            $pdo->beginTransaction();
            topicRevisionCapture($pdo, $id, (int)($_SESSION['_auth_user_id'] ?? 0), 'admin_update');

            $existingMediaRecords = getTopicMediaRecords($pdo, $id, true);
            $existingById = [];
            foreach ($existingMediaRecords as $record) {
                $existingById[(int)($record['id'] ?? 0)] = $record;
            }

            foreach ($existingById as $mediaId => $record) {
                if (!in_array($mediaId, $keepMediaIds, true)) {
                    deleteTopicMediaRecord($pdo, $mediaId);
                }
            }

            if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $attachment = handleFileUpload($pdo, $id, $_FILES['attachment'], 'attachment', 0, false, $title);
                if (isset($attachment['error'])) {
                    throw new RuntimeException((string) $attachment['error']);
                }
                if (isset($attachment['path'])) {
                    $uploadedPaths[] = (string) $attachment['path'];
                }
            }

            $primaryMediaId = !empty($topic['primary_media_file_id']) ? (int) $topic['primary_media_file_id'] : null;
            if (isset($_POST['remove_first_image']) && $_POST['remove_first_image'] === '1') {
                if ($primaryMediaId) {
                    deleteTopicMediaRecord($pdo, $primaryMediaId);
                }
                $primaryMediaId = null;
            }
            $imageSequence = 1;
            if (!empty($_FILES['topic_first_image_file']) && $_FILES['topic_first_image_file']['error'] === UPLOAD_ERR_OK) {
                if ($primaryMediaId) {
                    deleteTopicMediaRecord($pdo, $primaryMediaId);
                }
                $res = handleFileUpload($pdo, $id, $_FILES['topic_first_image_file'], 'image', 0, true, $title, $imageSequence++);
                if (isset($res['error'])) {
                    throw new RuntimeException((string) $res['error']);
                }
                if (isset($res['path'])) {
                    $uploadedPaths[] = (string) $res['path'];
                }
                if (isset($res['id'])) {
                    $primaryMediaId = (int) $res['id'];
                }
            }

            $displayOrder = 1;
            if ($videoUrl !== '') {
                createTopicMediaRecord($pdo, $id, $videoUrl, 'video', $displayOrder++, false, 'remote');
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
                            'size' => $_FILES['topic_images_files']['size'][$i]
                        ];
                        $res = handleFileUpload($pdo, $id, $singleFile, 'image', $displayOrder++, false, $title, $imageSequence++);
                        if (isset($res['error'])) {
                            throw new RuntimeException((string) $res['error']);
                        }
                        if (isset($res['path'])) {
                            $uploadedPaths[] = (string) $res['path'];
                        }
                    }
                }
            }

            $pdo->prepare("UPDATE topics
                SET category_id = ?, title = ?, slug = ?, author_topic = ?, topic_version = ?, topic_descriptions = ?, primary_media_file_id = ?, status = ?, updated_at = NOW(), published_at = COALESCE(published_at, ?)
                WHERE id = ?")
                ->execute([$categoryId, $title, $slug, $authorTopic !== '' ? $authorTopic : null, $topicVersion !== '' ? $topicVersion : null, $content, $primaryMediaId, $status, $status === 'published' ? date('Y-m-d H:i:s') : null, $id]);
            syncTopicDownloadLinks($pdo, $id, $topicDownloadLinks);

            $pdo->commit();
            logActivity($pdo, 'topic_updated', 'topic', $id, ['title' => $title]);
            flash('success', 'Konu başarıyla güncellendi.');
            header('Location: topics.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach (($uploadedPaths ?? []) as $uploadedPath) {
                topicDeletePhysicalFile((string) $uploadedPath);
            }
            flash('error', safeErrorMessage($e, 'Güncelleme sırasında bir hata oluştu.'));
        }
    } else {
        flash('error', 'Başlık, kategori ve içerik alanları zorunludur.');
    }
}

$pageTitle = 'Konu Düzenle';
$errorMsg = get_flash('error');
require_once __DIR__ . '/header.php';
?>
<div class="admin-card ui-panel">
    <div class="card-header ui-admin-card-header-actions ui-panel__head ui-card">
        <span><i class="bi bi-pencil me-2"></i>Konu Düzenle — #<?= $id ?></span>
        <a class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" href="topic-revisions.php?topic_id=<?= (int)$id ?>"><i class="bi bi-clock-history"></i> Versiyonlar</a>
    </div>
    <div class="card-body ui-panel__body">
        <form id="topicForm" method="post" action="edit.php?id=<?= $id ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="seo-checklist ui-admin-seo-checklist">
                <div class="ui-admin-seo-checklist-title">
                    <i class="bi bi-clipboard-check"></i> SEO Kalite Kontrol
                </div>
                <div class="ui-admin-seo-checklist-grid ui-grid">
                    <div><i class="bi bi-check2-circle ui-admin-seo-check"></i> Baslik, slug ve kategori uyumlu kalsin.</div>
                    <div><i class="bi bi-check2-circle ui-admin-seo-check"></i> Eski URL degisti ise SEO yonlendirme olussun.</div>
                    <div><i class="bi bi-check2-circle ui-admin-seo-check"></i> Icerik ve indirme baglantilari guncel olsun.</div>
                    <div><i class="bi bi-check2-circle ui-admin-seo-check"></i> Kapak gorseli arama sonucunda guven versin.</div>
                </div>
            </div>
            <div class="ui-admin-editor-layout ui-section">
                <div>
                    <label class="ui-admin-form-label">Başlık</label>
                    <input type="text" name="title" class="ui-admin-form-control" value="<?= htmlspecialchars($topic['title']) ?>" required>
                </div>
                <div>
                    <label class="ui-admin-form-label">Kategori</label>
                    <select name="category_id" class="ui-admin-form-select" required>
                        <?php foreach ($categoryOptions as $category): ?>
                            <option value="<?= (int)$category['id'] ?>" <?= (int)$topic['category_id'] === (int)$category['id'] ? 'selected' : '' ?>>
                                <?= str_repeat('— ', (int)($category['depth'] ?? 0)) . htmlspecialchars((string)$category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="ui-admin-mb-md">
                <label class="ui-admin-form-label">İçerik</label>
                <textarea name="content" rows="12" class="ui-admin-form-control rich-editor" required><?= htmlspecialchars((string)($topic['topic_descriptions'] ?? '')) ?></textarea>
            </div>
            <div class="ui-admin-form-grid-two ui-grid">
                <div>
                    <label class="ui-admin-form-label">Mod Yapımcısı</label>
                    <input type="text" name="author_topic" class="ui-admin-form-control" value="<?= htmlspecialchars((string)($topic['author_topic'] ?? '')) ?>" placeholder="Örn: SCS Software">
                </div>
                <div>
                    <label class="ui-admin-form-label">Gerekli Oyun Sürümü</label>
                    <input type="text" name="topic_version" class="ui-admin-form-control" value="<?= htmlspecialchars((string)($topic['topic_version'] ?? '')) ?>" placeholder="Örn: 1.50">
                </div>
            </div>
            <?php
            $currentMediaRecords = getTopicMediaRecords($pdo, $id, true);
            $currentPrimaryImage = getTopicPrimaryMediaPath($topic);
            $currentPrimaryMediaId = !empty($topic['primary_media_file_id']) ? (int) $topic['primary_media_file_id'] : 0;
            $currentVideoUrl = '';
            $currentImages = [];
            foreach ($currentMediaRecords as $mediaRecord) {
                $mediaId = (int)($mediaRecord['id'] ?? 0);
                $mediaPath = trim((string)($mediaRecord['path'] ?? ''));
                $mediaType = (string)($mediaRecord['type'] ?? '');
                $mediaMime = (string)($mediaRecord['mime_type'] ?? '');
                if ($mediaPath === '') {
                    continue;
                }
                if ($mediaType === 'video' || preg_match('/(youtube\.com|youtu\.be|vimeo\.com)/i', $mediaPath)) {
                    $currentVideoUrl = $mediaPath;
                    continue;
                }
                if (($mediaType === 'image' || str_starts_with($mediaMime, 'image/')) && $mediaId !== $currentPrimaryMediaId) {
                    $currentImages[] = ['id' => $mediaId, 'path' => $mediaPath];
                }
            }
            ?>

            <div class="media-uploader-shell ui-section">
                <div class="media-uploader-card ui-card">
                    <div class="media-uploader-head ui-panel__head">
                        <div>
                            <h3>Kapak Görseli</h3>
                            <p>Mevcut kapağı koruyabilir, silebilir veya yeni bir kapakla değiştirebilirsiniz.</p>
                        </div>
                        <div class="media-upload-pill"><i class="bi bi-image"></i> Mevcut Kapak</div>
                    </div>
                    <?php if (!empty($currentPrimaryImage)): ?>
                        <div class="media-existing-grid ui-grid">
                            <div class="media-existing-item" data-existing-media-card title="Kapağı silmek/geri almak için tıklayın">
                                <img src="<?= htmlspecialchars(strpos($currentPrimaryImage, 'http') === 0 ? $currentPrimaryImage : $baseUri . '/' . ltrim($currentPrimaryImage, '/')) ?>" alt="Mevcut kapak görseli" width="120" height="95">
                                <label class="media-existing-action-bar">
                                    <?php if ($currentPrimaryMediaId > 0): ?>
                                        <input type="hidden" name="keep_media[]" value="<?= $currentPrimaryMediaId ?>">
                                    <?php endif; ?>
                                    <input type="checkbox" name="remove_first_image" value="1" class="d-none ui-admin-hidden-file-input">
                                    <div class="cover-action-bar-content">
                                        <span class="state-trash"><i class="bi bi-trash3"></i> Kaldır</span>
                                        <span class="state-restore"><i class="bi bi-arrow-counterclockwise"></i> Geri Al</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="media-dropzone-modern" data-uploader="cover">
                        <input type="file" name="topic_first_image_file" id="editCoverInput" class="d-none ui-admin-hidden-file-input" accept=".png,.jpg,.jpeg,.webp" hidden aria-hidden="true" tabindex="-1">
                        <div class="media-dropzone-trigger" data-open-input="editCoverInput">
                            <i class="bi bi-card-image"></i>
                            <strong>Yeni kapak görselini sürükleyin veya seçmek için tıklayın</strong>
                            <span>Yeni bir kapak seçerseniz mevcut kapak değiştirilir</span>
                        </div>
                        <div class="media-preview-grid ui-grid" id="editCoverPreview"></div>
                    </div>
                </div>

                <div class="media-uploader-card ui-card">
                    <div class="media-uploader-head ui-panel__head">
                        <div>
                            <h3>Mod Resimleri</h3>
                            <p>Mevcut galeriyi düzenleyin ve yeni resimleri sürükle-bırak ile ekleyin.</p>
                        </div>
                        <div class="media-upload-pill"><i class="bi bi-collection"></i> Yeni Galeri Resimleri</div>
                    </div>
                    <?php if (!empty($currentImages)): ?>
                        <div class="ui-admin-mb-md">
                            <label class="ui-admin-form-label">Mevcut Galeri</label>
                            <div class="media-existing-grid ui-grid">
                                <?php foreach ($currentImages as $mediaImage): ?>
                                    <div class="media-existing-item" data-existing-media-card title="Silmek veya geri almak için tıklayın">
                                        <img src="<?= htmlspecialchars(strpos($mediaImage['path'], 'http') === 0 ? $mediaImage['path'] : $baseUri . '/' . ltrim($mediaImage['path'], '/')) ?>" alt="Mevcut galeri görseli" width="120" height="95">
                                        <label class="media-existing-action-bar">
                                            <input type="checkbox" name="keep_media[]" value="<?= (int)$mediaImage['id'] ?>" checked class="d-none ui-admin-hidden-file-input">
                                            <div class="action-bar-content">
                                                <span class="state-keep"><i class="bi bi-trash3"></i> Kaldır</span>
                                                <span class="state-remove"><i class="bi bi-arrow-counterclockwise"></i> Geri Al</span>
                                            </div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="media-dropzone-modern" data-uploader="gallery">
                        <input type="file" name="topic_images_files[]" id="editGalleryInput" class="d-none ui-admin-hidden-file-input" accept=".png,.jpg,.jpeg,.webp" multiple hidden aria-hidden="true" tabindex="-1">
                        <div class="media-dropzone-trigger" data-open-input="editGalleryInput">
                            <i class="bi bi-images"></i>
                            <strong>Yeni galeri resimlerini toplu yükleyin</strong>
                            <span>En fazla 10 görsel seçebilir, önizlemeden istemediklerinizi kaldırabilirsiniz</span>
                        </div>
                        <div class="media-preview-grid ui-grid" id="editGalleryPreview"></div>
                    </div>
                    <div class="ui-admin-video-row">
                        <label class="ui-admin-form-label">Video URL Ekle/Değiştir</label>
                        <input type="url" name="topic_video_url" class="ui-admin-form-control" value="<?= htmlspecialchars($currentVideoUrl) ?>" placeholder="https://www.youtube.com/watch?v=...">
                    </div>
                </div>

                <div class="media-uploader-card ui-card">
                    <div class="media-uploader-head ui-panel__head">
                        <div>
                            <h3>Ek Dosya</h3>
                            <p>Opsiyonel arşiv, PDF veya ek görsel dosyası ekleyin.</p>
                        </div>
                        <div class="media-upload-pill"><i class="bi bi-file-earmark-arrow-up"></i> Maks 50MB</div>
                    </div>
                    <div class="media-dropzone-modern" data-uploader="attachment">
                        <input type="file" name="attachment" id="editAttachmentInput" class="d-none ui-admin-hidden-file-input" accept=".zip,.rar,.7z,.pdf,.png,.jpg,.jpeg,.webp" hidden aria-hidden="true" tabindex="-1">
                        <div class="media-dropzone-trigger" data-open-input="editAttachmentInput">
                            <i class="bi bi-file-earmark-zip"></i>
                            <strong>Ek dosyayı sürükleyin veya seçmek için tıklayın</strong>
                            <span>ZIP, RAR, 7Z, PDF, PNG, JPG, JPEG veya WEBP</span>
                        </div>
                        <div class="media-preview-grid ui-grid" id="editAttachmentPreview"></div>
                    </div>
                </div>
            </div>
            <div class="ui-admin-mb-md">
                <label class="ui-admin-form-label">İndirme Bağlantıları (opsiyonel)</label>
                <?php
                $existingDl = getTopicDownloadLinks($pdo, $id, (string)($topic['topic_download_links'] ?? ''));
                ?>
                <div id="dlRows">
                    <?php if (empty($existingDl)): ?>
                    <div class="dl-row ui-admin-download-row">
                        <input type="text" name="dl_name[]" class="ui-admin-form-control ui-admin-download-name" placeholder="Kaynak adı (ör: Google Drive)">
                        <input type="url" name="dl_url[]" class="ui-admin-form-control ui-admin-download-url" placeholder="https://...">
                        <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" data-ui-remove-closest=".dl-row" title="Kaldır"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <?php else: ?>
                        <?php foreach ($existingDl as $dl):
                            $dlName = (string)($dl['name'] ?? '');
                            $dlUrl = (string)($dl['url'] ?? '');
                        ?>
                        <div class="dl-row ui-admin-download-row">
                            <input type="text" name="dl_name[]" class="ui-admin-form-control ui-admin-download-name" placeholder="Kaynak adı" value="<?= htmlspecialchars($dlName) ?>">
                            <input type="url" name="dl_url[]" class="ui-admin-form-control ui-admin-download-url" placeholder="https://..." value="<?= htmlspecialchars($dlUrl) ?>">
                            <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" data-ui-remove-closest=".dl-row" title="Kaldır"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm ui-admin-btn-offset-top" data-ui-action="addDlRow"><i class="bi bi-plus-lg"></i> Bağlantı Ekle</button>
                <input type="hidden" name="topic_download_links" id="dlHidden">
                <script src="<?= asset_url('admin/assets/edit-page-editor.js', $baseUri) ?>" defer></script>
                <script src="<?= asset_url('admin/assets/edit-page-media.js', $baseUri) ?>" defer></script>
            </div>
            <div class="ui-admin-status-narrow">
                <label class="ui-admin-form-label">Durum</label>
                <select name="status" class="ui-admin-form-select">
                    <option value="draft" <?= $topic['status'] === 'draft' ? 'selected' : '' ?>>Taslak Olarak Kaydet</option>
                    <option value="published" <?= $topic['status'] === 'published' ? 'selected' : '' ?>>Hemen Yayınla</option>
                </select>
            </div>
            <div class="ui-admin-form-actions">
                <button type="submit" class="ui-admin-btn ui-admin-btn-primary"><i class="bi bi-save"></i>Değişiklikleri Kaydet</button>
                <a href="topics.php" class="ui-admin-btn ui-admin-btn-outline">İptal</a>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
