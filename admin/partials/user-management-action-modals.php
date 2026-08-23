<?php
$userManagementModalGroups = is_array($userManagementModalGroups ?? null) ? $userManagementModalGroups : [];
?>
<div class="media-modal-overlay ui-admin-modal-overlay user-edit-modal" id="commentUserEditModal" role="dialog" aria-modal="true" aria-label="Kullanıcı düzenle" hidden aria-hidden="true">
    <div class="media-modal ui-admin-modal-shell ui-modal-shell ui-panel">
        <div class="media-modal-header ui-modal__head ui-panel__head">
            <div>
                <h3 class="ui-admin-modal-title"><i class="bi bi-pencil-square"></i> Kullanıcıyı Düzenle</h3>
                <p class="user-edit-help" id="commentUserEditEmailPreview"></p>
            </div>
            <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-ghost" data-comment-user-edit-close aria-label="Kapat"><i class="bi bi-x-lg"></i></button>
        </div>
        <form id="commentUserEditForm" class="user-edit-form" data-comment-user-management-form>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_user">
            <input type="hidden" name="user_id" id="commentEditUserId">
            <div class="media-modal-body ui-modal__body ui-panel__body">
                <div class="user-edit-grid ui-grid">
                    <div><label class="ui-admin-form-label" for="commentEditUsername">Kullanıcı adı</label><input name="username" id="commentEditUsername" class="ui-admin-form-control" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_-]{3,30}"></div>
                    <div><label class="ui-admin-form-label" for="commentEditUserEmail">E-posta</label><input name="email" id="commentEditUserEmail" type="email" class="ui-admin-form-control" required></div>
                    <div><label class="ui-admin-form-label" for="commentEditUserGroup">Grup</label><select name="group_id" id="commentEditUserGroup" class="ui-admin-form-select" required><?php foreach ($userManagementModalGroups as $group): ?><option value="<?= (int) ($group['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($group['name'] ?? 'Grup')) ?></option><?php endforeach; ?></select></div>
                    <div><label class="ui-admin-form-label" for="commentEditUserStatus">Durum</label><select name="status" id="commentEditUserStatus" class="ui-admin-form-select"><option value="active">Aktif</option><option value="inactive">Pasif</option></select></div>
                    <div><label class="ui-admin-form-label" for="commentEditUserLocation">Konum</label><input name="location" id="commentEditUserLocation" class="ui-admin-form-control"></div>
                    <div><label class="ui-admin-form-label" for="commentEditUserWebsite">Web sitesi</label><input name="website" id="commentEditUserWebsite" type="url" class="ui-admin-form-control"></div>
                    <div><label class="ui-admin-form-label" for="commentEditUserGithub">GitHub</label><input name="social_github" id="commentEditUserGithub" class="ui-admin-form-control"></div>
                    <div><label class="ui-admin-form-label" for="commentEditUserTwitter">Twitter</label><input name="social_twitter" id="commentEditUserTwitter" class="ui-admin-form-control"></div>
                    <div><label class="ui-admin-form-label" for="commentEditUserDiscord">Discord</label><input name="social_discord" id="commentEditUserDiscord" class="ui-admin-form-control"></div>
                    <div><label class="ui-admin-form-label" for="commentEditUserPassword">Yeni şifre</label><input name="password" id="commentEditUserPassword" type="password" class="ui-admin-form-control" autocomplete="new-password" minlength="6"></div>
                </div>
                <div class="ui-admin-mt-md"><label class="ui-admin-form-label" for="commentEditUserBio">Biyografi</label><textarea name="bio" id="commentEditUserBio" class="ui-admin-form-control" rows="4"></textarea></div>
            </div>
            <div class="media-modal-footer user-edit-footer ui-modal__foot ui-panel__foot"><button type="button" class="ui-admin-btn ui-admin-btn-outline" data-comment-user-edit-close>İptal</button><button type="submit" class="ui-admin-btn ui-admin-btn-primary">Kaydet</button></div>
        </form>
    </div>
</div>

<div class="media-modal-overlay ui-admin-modal-overlay" id="commentUserAdminNoteModal" role="dialog" aria-modal="true" aria-label="Admin notu" hidden aria-hidden="true">
    <div class="media-modal ui-admin-modal-sm ui-admin-modal-shell ui-modal-shell ui-panel">
        <div class="media-modal-header ui-modal__head ui-panel__head"><h3 class="ui-admin-modal-title"><i class="bi bi-journal-plus"></i> Admin Notu</h3><button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-ghost" data-comment-user-note-close aria-label="Kapat"><i class="bi bi-x-lg"></i></button></div>
        <form id="commentUserAdminNoteForm" data-comment-user-management-form>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_admin_note"><input type="hidden" name="user_id" id="commentAdminNoteUserId">
            <div class="media-modal-body ui-modal__body ui-panel__body"><label class="ui-admin-form-label" for="commentAdminNoteUserName">Kullanıcı</label><input id="commentAdminNoteUserName" class="ui-admin-form-control" readonly><label class="ui-admin-form-label ui-admin-mt-md" for="commentAdminNoteText">Not</label><textarea name="admin_note" id="commentAdminNoteText" class="ui-admin-form-control" rows="4" required></textarea></div>
            <div class="media-modal-footer ui-admin-modal-footer-flush ui-modal__foot ui-panel__foot"><button type="button" class="ui-admin-btn ui-admin-btn-outline" data-comment-user-note-close>İptal</button><button type="submit" class="ui-admin-btn ui-admin-btn-primary">Notu Kaydet</button></div>
        </form>
    </div>
</div>
