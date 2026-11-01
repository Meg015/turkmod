<?php declare(strict_types=1); ?>

<div class="media-modal-overlay ui-admin-modal-overlay" id="userDeleteModal" role="dialog" aria-modal="true" aria-labelledby="userDeleteTitle" aria-describedby="userDeleteDescription" hidden aria-hidden="true">
    <div class="media-modal ui-admin-modal-shell ui-modal-shell ui-panel user-delete-modal">
        <div class="media-modal-header ui-modal__head ui-panel__head">
            <h3 class="ui-admin-modal-title" id="userDeleteTitle"><span class="user-delete-icon" aria-hidden="true"><i class="bi bi-trash3"></i></span> Kullanıcıyı Kalıcı Sil</h3>
            <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-ghost user-delete-close" data-user-delete-close aria-label="Kapat"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        </div>
        <form id="userDeleteForm" class="user-delete-form" method="post" action="users.php?tab=users">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="user_id" id="deleteUserId">
            <div class="media-modal-body ui-modal__body ui-panel__body user-delete-body">
                <p class="user-delete-description" id="userDeleteDescription"><strong data-user-delete-name></strong> hesabı silinecek. <span>Bu işlem geri alınamaz.</span></p>
                <fieldset class="user-delete-options">
                    <legend class="user-delete-label">Konular ve yorumlar</legend>
                    <div class="user-delete-options-list">
                        <label class="user-delete-option">
                            <input type="radio" name="content_mode" value="preserve" checked>
                            <span><strong>İçerikleri koru <em class="user-delete-recommended">Önerilen</em></strong><small>Konular, yorumlar ve ekler Silinmiş Kullanıcı’ya devredilir.</small></span>
                        </label>
                        <label class="user-delete-option user-delete-option-purge">
                            <input type="radio" name="content_mode" value="purge" <?= $canDeleteUserContent ? '' : 'disabled' ?>>
                            <span><strong>İçerikleri de sil</strong><small>Kullanıcının tüm konuları ve yorumları kalıcı silinir.</small></span>
                        </label>
                    </div>
                    <?php if (!$canDeleteUserContent): ?>
                        <p class="user-delete-hint">İçerikleri silmek için konu ve yorum silme yetkileri gerekir.</p>
                    <?php endif; ?>
                </fieldset>
                <div class="user-delete-warning" data-user-delete-purge-warning hidden role="status">
                    Silinen konulardaki diğer kullanıcı yorumları da silinir. Kalan konulardaki yanıtlar bağımsız yorum olarak korunur.
                </div>
                <div class="user-delete-field">
                    <label class="user-delete-label" for="deleteUserReason">Silme gerekçesi</label>
                    <textarea class="ui-admin-form-control" name="reason" id="deleteUserReason" rows="2" maxlength="1000" placeholder="Kısa bir gerekçe yazın" required></textarea>
                </div>
                <div class="user-delete-field">
                    <label class="user-delete-label user-delete-confirmation-label" for="deleteUserConfirmation"><span>Kullanıcı adını yazarak onayla</span><strong id="deleteUserExpectedName" data-user-delete-name></strong></label>
                    <input class="ui-admin-form-control" type="text" name="confirm_username" id="deleteUserConfirmation" autocomplete="off" spellcheck="false" required aria-describedby="deleteUserExpectedName">
                </div>
                <label class="user-delete-confirm">
                    <input type="checkbox" name="confirm_delete" value="1" required>
                    Hesabın kalıcı olarak silineceğini anlıyorum.
                </label>
            </div>
            <div class="media-modal-footer ui-modal__foot ui-panel__foot">
                <button type="button" class="ui-admin-btn ui-admin-btn-outline" data-user-delete-close>İptal</button>
                <button type="submit" class="ui-admin-btn ui-admin-btn-danger"><i class="bi bi-trash3" aria-hidden="true"></i> Kalıcı Sil</button>
            </div>
        </form>
    </div>
</div>
