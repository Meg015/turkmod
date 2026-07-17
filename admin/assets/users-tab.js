function adminUsersCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

// Enhanced modal functions with smooth animations
function openAdminManagedModal(modal, options) {
    if (!modal) return null;
    const opts = options || {};
    if (window.TMUI && typeof window.TMUI.openDialog === 'function') {
        const dialog = window.TMUI.openDialog(modal, {
            openClass: opts.openClass || 'is-open',
            bodyClass: opts.bodyClass || 'ui-admin-dialog-open',
            initialFocus: opts.initialFocus,
            returnFocus: opts.returnFocus || document.activeElement,
            onClose: opts.onClose
        });
        modal.classList.add('ui-admin-modal-open');
        return dialog;
    }
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add(opts.openClass || 'is-open', 'ui-admin-modal-open');
    modal.classList.remove('is-closing');
    if (opts.initialFocus) {
        const focusTarget = modal.querySelector(opts.initialFocus);
        if (focusTarget) setTimeout(() => focusTarget.focus(), 80);
    }
    return null;
}

function closeAdminManagedModal(modal, resetCallback) {
    if (!modal) return;
    if (window.TMUI && typeof window.TMUI.closeDialog === 'function' && modal._tmuiDialog) {
        window.TMUI.closeDialog(modal);
        modal.classList.remove('ui-admin-modal-open');
        if (typeof resetCallback === 'function') resetCallback();
        return;
    }
    modal.classList.add('is-closing');
    setTimeout(() => {
        modal.classList.remove('is-open', 'is-closing', 'ui-admin-modal-open');
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        if (typeof resetCallback === 'function') resetCallback();
    }, 160);
}

function openRestrictionModal(userId, userName) {
    document.getElementById('restrictUserId').value = userId;
    document.getElementById('restrictUserName').value = userName;
    const modal = document.getElementById('restrictionModal');
    openAdminManagedModal(modal, {
        initialFocus: '#restrictTypes',
        onClose: function () {
            document.getElementById('restrictionForm')?.reset();
        }
    });
}

function closeRestrictionModal() {
    const modal = document.getElementById('restrictionModal');
    closeAdminManagedModal(modal, function () {
        document.getElementById('restrictionForm').reset();
    });
}

function openBanModal(userId, userName) {
    document.getElementById('banUserId').value = userId;
    document.getElementById('banUserName').value = userName;
    const modal = document.getElementById('banModal');
    openAdminManagedModal(modal, {
        initialFocus: '#banReason',
        onClose: function () {
            document.getElementById('banForm')?.reset();
        }
    });
}

function closeBanModal() {
    const modal = document.getElementById('banModal');
    closeAdminManagedModal(modal, function () {
        document.getElementById('banForm')?.reset();
    });
}

function submitBan(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const reason = String(formData.get('ban_reason') || '').trim();
    if (!reason) {
        adminAlert('Ban sebebi gereklidir.', { title: 'Uyarı', tone: 'warning' });
        return false;
    }
    adminConfirm('Bu kullanıcı banlanacak. İşlemi onaylıyor musunuz?', {
        title: 'Kullanıcıyı banla',
        ok: 'Banla',
        cancel: 'İptal',
        tone: 'danger'
    }).then((confirmed) => {
        if (!confirmed) return;
        fetch('users.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                adminAlert(data.message, { title: 'Başarılı', tone: 'success' }).then(() => window.location.reload());
            } else {
                adminAlert(data.message, { title: 'Hata', tone: 'danger' });
            }
        })
        .catch(() => adminAlert('Bir hata oluştu. Lütfen tekrar deneyin.', { title: 'Hata', tone: 'danger' }));
    });
    return false;
}
document.getElementById('banForm')?.addEventListener('submit', submitBan);

function openAdminNoteModal(userId, userName) {
    document.getElementById('adminNoteUserId').value = userId;
    document.getElementById('adminNoteUserName').value = userName;
    const modal = document.getElementById('adminNoteModal');
    openAdminManagedModal(modal, {
        initialFocus: 'textarea[name="admin_note"]',
        onClose: function () {
            document.getElementById('adminNoteForm')?.reset();
        }
    });
}

function closeAdminNoteModal() {
    const modal = document.getElementById('adminNoteModal');
    closeAdminManagedModal(modal, function () {
        document.getElementById('adminNoteForm')?.reset();
    });
}

function openUserEditModal(trigger) {
    const modal = document.getElementById('userEditModal');
    if (!modal || !trigger) return;

    const setValue = (id, value) => {
        const field = document.getElementById(id);
        if (field) field.value = value || '';
    };

    setValue('editUserId', trigger.dataset.userId);
    setValue('editUsername', trigger.dataset.userUsername || trigger.dataset.userName);
    setValue('editUserEmail', trigger.dataset.userEmail);
    setValue('editUserGroup', trigger.dataset.userGroup);
    setValue('editUserStatus', trigger.dataset.userStatus || 'active');
    setValue('editUserLocation', trigger.dataset.userLocation);
    setValue('editUserWebsite', trigger.dataset.userWebsite);
    setValue('editUserGithub', trigger.dataset.userGithub);
    setValue('editUserTwitter', trigger.dataset.userTwitter);
    setValue('editUserDiscord', trigger.dataset.userDiscord);
    setValue('editUserBio', trigger.dataset.userBio);
    setValue('editUserPassword', '');

    const preview = document.getElementById('userEditEmailPreview');
    if (preview) preview.textContent = trigger.dataset.userEmail || '';

    openAdminManagedModal(modal, {
        initialFocus: '#editUsername',
        returnFocus: trigger,
        onClose: function () {
            const password = document.getElementById('editUserPassword');
            if (password) password.value = '';
            resetEditPasswordVisibility();
        }
    });
}

function closeUserEditModal() {
    const modal = document.getElementById('userEditModal');
    closeAdminManagedModal(modal, function () {
        const password = document.getElementById('editUserPassword');
        if (password) password.value = '';
        resetEditPasswordVisibility();
    });
}

function resetEditPasswordVisibility() {
    const password = document.getElementById('editUserPassword');
    const toggle = document.getElementById('editPasswordToggle');
    if (!password || !toggle) return;

    password.type = 'password';
    toggle.setAttribute('aria-label', 'Şifreyi göster');
    toggle.setAttribute('aria-pressed', 'false');
    const icon = toggle.querySelector('i');
    if (icon) {
        icon.className = 'bi bi-eye';
    }
}

function toggleEditPasswordVisibility() {
    const password = document.getElementById('editUserPassword');
    const toggle = document.getElementById('editPasswordToggle');
    if (!password || !toggle) return;

    const isVisible = password.type === 'text';
    password.type = isVisible ? 'password' : 'text';
    toggle.setAttribute('aria-label', isVisible ? 'Şifreyi göster' : 'Şifreyi gizle');
    toggle.setAttribute('aria-pressed', isVisible ? 'false' : 'true');
    const icon = toggle.querySelector('i');
    if (icon) {
        icon.className = isVisible ? 'bi bi-eye' : 'bi bi-eye-slash';
    }
}

function submitRestriction(e) {
    e.preventDefault();
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');

    // Get selected restriction types from multi-select
    const selectElement = form.querySelector('#restrictTypes');
    const selectedTypes = Array.from(selectElement.selectedOptions).map(option => option.value);

    if (selectedTypes.length === 0) {
        adminAlert('En az bir kısıtlama türü seçmelisiniz.', { title: 'Uyarı!', tone: 'warning' });
        return false;
    }

    // Add loading state
    submitBtn.disabled = true;
    submitBtn.classList.add('loading');

    const formData = new FormData(form);
    formData.set('action', 'add_restriction');

    // Show confirmation with selected types
    const typeLabels = {
        'all': 'Tüm İşlemler',
        'comment': 'Yorum Yapma',
        'topic': 'Konu Oluşturma',
        'upload': 'Dosya Yükleme',
        'download': 'İndirme',
        'profile': 'Profil Düzenleme',
        'events': 'Etkinlik Kullanımı'
    };
    const selectedLabels = selectedTypes.map(t => typeLabels[t] || t).join('<br>');

    adminConfirm(`<div class="ui-admin-text-left"><strong>${selectedTypes.length}</strong> kısıtlama eklenecek:<br><br>${selectedLabels}</div>`, {
        title: 'Kısıtlama Ekle?',
        ok: 'Evet, Ekle',
        cancel: 'İptal',
        tone: 'warning'
    }).then((confirmed) => {
        if (confirmed) {
            if (window.adminToast) adminToast.info('Kısıtlamalar ekleniyor...');

            fetch('users.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');

                if (data.ok) {
                    adminAlert(data.message, { title: 'Başarılı!', tone: 'success' }).then(() => {
                        window.location.reload();
                    });
                } else {
                    adminAlert(data.message, { title: 'Hata!', tone: 'danger' });
                }
            })
            .catch(() => {
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
                adminAlert('Bir hata oluştu. Lütfen tekrar deneyin.', { title: 'Hata!', tone: 'danger' });
            });
        } else {
            submitBtn.disabled = false;
            submitBtn.classList.remove('loading');
        }
    });

    return false;
}
document.getElementById('restrictionForm')?.addEventListener('submit', submitRestriction);

function banUser(userId) {
    adminPrompt('Ban Sebebi', {
        title: 'Kullanıcıyı Banla',
        placeholder: 'Ban sebebini yazın...',
        input: 'textarea',
        ok: 'Banla',
        cancel: 'İptal',
        tone: 'danger'
    }).then((value) => {
        if (value !== null) {
            if (!value.trim()) {
                adminAlert('Ban sebebi gereklidir!', { title: 'Uyarı', tone: 'warning' });
                return;
            }
            const formData = new FormData();
            formData.append('_token', adminUsersCsrfToken());
            formData.append('action', 'ban');
            formData.append('user_id', userId);
            formData.append('ban_reason', value);

            if (window.adminToast) adminToast.info('İşleniyor...');

            fetch('users.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    adminAlert(data.message, { title: 'Başarılı!', tone: 'success' }).then(() => {
                        window.location.reload();
                    });
                } else {
                    adminAlert(data.message, { title: 'Hata!', tone: 'danger' });
                }
            })
            .catch(() => {
                adminAlert('Bir hata oluştu. Lütfen tekrar deneyin.', { title: 'Hata!', tone: 'danger' });
            });
        }
    });
}

function unbanUser(userId) {
    adminConfirm('Bu kullanıcının banını kaldırmak istediğinizden emin misiniz?', {
        title: 'Ban Kaldır?',
        ok: 'Evet, Kaldır',
        cancel: 'İptal',
        tone: 'success'
    }).then((confirmed) => {
        if (confirmed) {
            const formData = new FormData();
            formData.append('_token', adminUsersCsrfToken());
            formData.append('action', 'unban');
            formData.append('user_id', userId);

            if (window.adminToast) adminToast.info('İşleniyor...');

            fetch('users.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    adminAlert(data.message, { title: 'Başarılı!', tone: 'success' }).then(() => {
                        window.location.reload();
                    });
                } else {
                    adminAlert(data.message, { title: 'Hata!', tone: 'danger' });
                }
            })
            .catch(() => {
                adminAlert('Bir hata oluştu. Lütfen tekrar deneyin.', { title: 'Hata!', tone: 'danger' });
            });
        }
    });
}

// Close modal on outside click
document.getElementById('restrictionModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeRestrictionModal();
});

document.getElementById('banModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeBanModal();
});

document.getElementById('adminNoteModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeAdminNoteModal();
});

document.getElementById('viewRestrictionsModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        const urlParams = new URLSearchParams(window.location.search);
        const currentTab = urlParams.get('tab') || 'users';
        const nextParams = new URLSearchParams({ tab: currentTab });
        if (currentTab === 'moderation') {
            nextParams.set('moderation', urlParams.get('moderation') || 'restricted');
        }
        window.location.href = 'users.php?' + nextParams.toString();
    }
});

document.getElementById('userEditModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeUserEditModal();
});

document.querySelectorAll('[data-user-edit-open]').forEach(button => {
    button.addEventListener('click', function(event) {
        event.preventDefault();
        event.stopPropagation();
        openUserEditModal(this);
    });
});

document.querySelectorAll('[data-admin-note-open]').forEach(button => {
    button.addEventListener('click', function(event) {
        event.preventDefault();
        event.stopPropagation();
        openAdminNoteModal(this.getAttribute('data-user-id'), this.getAttribute('data-user-name') || '');
    });
});

document.addEventListener('click', function(event) {
    const editTrigger = event.target.closest('[data-user-edit-open]');
    if (editTrigger) {
        event.preventDefault();
        if (editTrigger.closest('#userDetailModal')) {
            closeUserDetail();
        }
        openUserEditModal(editTrigger);
        return;
    }

    const noteTrigger = event.target.closest('[data-admin-note-open]');
    if (noteTrigger) {
        event.preventDefault();
        if (noteTrigger.closest('#userDetailModal')) {
            closeUserDetail();
        }
        openAdminNoteModal(noteTrigger.getAttribute('data-user-id'), noteTrigger.getAttribute('data-user-name') || '');
        return;
    }

    const unbanTrigger = event.target.closest('[data-user-unban]');
    if (unbanTrigger) {
        event.preventDefault();
        if (unbanTrigger.closest('#userDetailModal')) {
            closeUserDetail();
        }
        unbanUser(unbanTrigger.getAttribute('data-user-unban'));
        return;
    }

    const banTrigger = event.target.closest('[data-user-ban]');
    if (banTrigger) {
        event.preventDefault();
        if (banTrigger.closest('#userDetailModal')) {
            closeUserDetail();
        }
        openBanModal(banTrigger.getAttribute('data-user-ban'), banTrigger.getAttribute('data-user-name') || '');
        return;
    }

    const restrictionTrigger = event.target.closest('[data-user-restrict]');
    if (restrictionTrigger) {
        event.preventDefault();
        if (restrictionTrigger.closest('#userDetailModal')) {
            closeUserDetail();
        }
        openRestrictionModal(restrictionTrigger.getAttribute('data-user-restrict'), restrictionTrigger.getAttribute('data-user-name') || '');
        return;
    }

    if (event.target.closest('[data-edit-password-toggle]')) {
        toggleEditPasswordVisibility();
        return;
    }

    if (event.target.closest('[data-user-edit-close]')) {
        closeUserEditModal();
        return;
    }

    if (event.target.closest('[data-ban-close]')) {
        closeBanModal();
        return;
    }

    if (event.target.closest('[data-admin-note-close]')) {
        closeAdminNoteModal();
        return;
    }

    if (event.target.closest('[data-restriction-close]')) {
        closeRestrictionModal();
        return;
    }

    if (event.target.closest('[data-user-detail-close]')) {
        closeUserDetail();
        return;
    }

    if (event.target.hasAttribute('data-user-detail-backdrop')) {
        closeUserDetail();
    }
});

// Close alert messages
document.querySelectorAll('.ui-admin-alert-close').forEach(btn => {
    btn.addEventListener('click', function() {
        this.closest('.ui-admin-alert').style.opacity = '0';
        setTimeout(() => {
            this.closest('.ui-admin-alert').remove();
        }, 200);
    });
});

(function bindUserBulkActions() {
    const form = document.querySelector('[data-user-bulk-form]');
    if (!form) return;

    const selectAll = form.querySelector('[data-user-select-all]');
    const checkboxes = Array.from(form.querySelectorAll('[data-user-row-checkbox]'));
    const countEl = form.querySelector('[data-user-selected-count]');
    const actionSelect = form.querySelector('[data-user-bulk-action]');
    const submitBtn = form.querySelector('.user-bulk-submit');
    const extraFields = Array.from(form.querySelectorAll('[data-user-bulk-field]'));

    function selectedCount() {
        return checkboxes.filter(function (checkbox) { return checkbox.checked; }).length;
    }

    function selectedUsers() {
        return checkboxes.filter(function (checkbox) { return checkbox.checked; }).map(function (checkbox) {
            return checkbox.getAttribute('data-user-name') || ('#' + checkbox.value);
        });
    }

    function plainText(html) {
        var node = document.createElement('div');
        node.innerHTML = html;
        return node.textContent || node.innerText || '';
    }

    function buildBulkConfirm(action, count) {
        const selected = selectedUsers();
        const visibleNames = selected.slice(0, 3).map(escHtml).join(', ');
        const restText = count > 3 ? ' ve ' + (count - 3) + ' kişi daha' : '';
        const selectedLine = visibleNames ? visibleNames + restText : count + ' kullanıcı';
        const groupField = form.querySelector('[data-user-bulk-field="change_group"]');
        const reasonField = form.querySelector('[data-user-bulk-field="ban"]');
        const groupName = groupField && groupField.selectedOptions[0] ? groupField.selectedOptions[0].textContent.trim() : '';
        const banReason = reasonField ? String(reasonField.value || '').trim() : '';
        const config = {
            activate: {
                label: 'Aktif yap',
                detail: 'Seçili hesaplar aktif duruma alınacak.',
                title: 'Kullanıcılar aktif yapılsın mı?',
                ok: 'Aktif Yap',
                tone: 'success'
            },
            deactivate: {
                label: 'Pasif yap',
                detail: 'Seçili hesaplar pasif duruma alınacak.',
                title: 'Kullanıcılar pasif yapılsın mı?',
                ok: 'Pasif Yap',
                tone: 'warning'
            },
            change_group: {
                label: 'Grup değiştir',
                detail: groupName ? 'Hedef grup: ' + groupName : 'Hedef grup seçilecek.',
                title: 'Kullanıcı grupları değiştirilsin mi?',
                ok: 'Grubu Değiştir',
                tone: 'warning'
            },
            ban: {
                label: 'Banla',
                detail: banReason ? 'Ban gerekçesi: ' + banReason : 'Ban gerekçesi girilecek.',
                title: 'Kullanıcılar banlansın mı?',
                ok: 'Banla',
                tone: 'danger'
            },
            unban: {
                label: 'Ban kaldır',
                detail: 'Seçili hesapların ban durumu kaldırılacak.',
                title: 'Banlar kaldırılsın mı?',
                ok: 'Ban Kaldır',
                tone: 'success'
            }
        }[action] || {
            label: 'Toplu işlem',
            detail: 'Seçili kullanıcılara işlem uygulanacak.',
            title: 'Toplu işlem uygulansın mı?',
            ok: 'Uygula',
            tone: 'warning'
        };

        return {
            title: config.title,
            ok: config.ok,
            tone: config.tone,
            message: count + ' kullanıcı seçildi.'
                + '\nİşlem: ' + config.label
                + '\nEtki: ' + config.detail
                + '\nSeçim: ' + selectedLine
        };
    }

    function syncExtraFields() {
        const action = actionSelect ? actionSelect.value : '';
        extraFields.forEach(function (field) {
            const visible = field.getAttribute('data-user-bulk-field') === action;
            field.hidden = !visible;
            field.disabled = !visible;
            if (!visible) {
                field.value = '';
            }
        });
    }

    function syncBulkState() {
        const count = selectedCount();
        if (countEl) {
            countEl.textContent = count + ' seçili';
        }
        if (selectAll) {
            selectAll.checked = count > 0 && count === checkboxes.length;
            selectAll.indeterminate = count > 0 && count < checkboxes.length;
        }
        if (submitBtn) {
            submitBtn.disabled = count === 0;
        }
        syncExtraFields();
    }

    selectAll?.addEventListener('change', function () {
        checkboxes.forEach(function (checkbox) {
            checkbox.checked = selectAll.checked;
        });
        syncBulkState();
    });

    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', syncBulkState);
    });

    actionSelect?.addEventListener('change', syncBulkState);

    form.addEventListener('submit', function (event) {
        const count = selectedCount();
        const action = actionSelect ? actionSelect.value : '';
        const alertUser = function (message) {
            if (typeof adminAlert === 'function') {
                adminAlert(message, { title: 'Uyarı', tone: 'warning' });
            } else {
                alert(message);
            }
        };

        if (count === 0) {
            event.preventDefault();
            event.stopPropagation();
            alertUser('Toplu işlem için en az bir kullanıcı seçin.');
            return;
        }
        if (!action) {
            event.preventDefault();
            event.stopPropagation();
            alertUser('Uygulanacak toplu işlemi seçin.');
            return;
        }
        if (action === 'change_group') {
            const groupField = form.querySelector('[data-user-bulk-field="change_group"]');
            if (!groupField || !groupField.value) {
                event.preventDefault();
                event.stopPropagation();
                alertUser('Grup değiştirmek için hedef grup seçin.');
                return;
            }
        }
        if (action === 'ban') {
            const reasonField = form.querySelector('[data-user-bulk-field="ban"]');
            if (!reasonField || !String(reasonField.value || '').trim()) {
                event.preventDefault();
                event.stopPropagation();
                alertUser('Toplu ban işlemi için gerekçe yazın.');
                return;
            }
        }

        if (form.dataset.userBulkConfirmed === '1') {
            delete form.dataset.userBulkConfirmed;
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        const confirmData = buildBulkConfirm(action, count);
        const confirmPromise = typeof adminConfirm === 'function'
            ? adminConfirm(confirmData.message, {
                title: confirmData.title,
                ok: confirmData.ok,
                cancel: 'Vazgeç',
                tone: confirmData.tone
            })
            : Promise.resolve(window.confirm(plainText(confirmData.message)));

        confirmPromise.then(function (confirmed) {
            if (!confirmed) {
                return;
            }
            form.dataset.userBulkConfirmed = '1';
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(submitBtn || undefined);
            } else {
                HTMLFormElement.prototype.submit.call(form);
            }
        });
    });

    document.addEventListener('click', function (event) {
        document.querySelectorAll('.user-row-actions-menu[open]').forEach(function (menu) {
            if (!menu.contains(event.target)) {
                menu.removeAttribute('open');
            }
        });
    });

    syncBulkState();
})();

// Add smooth fade-in animation to modal on load
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('viewRestrictionsModal');
    if (modal && modal.classList.contains('is-open')) {
        modal.style.opacity = '0';
        requestAnimationFrame(() => {
            modal.style.opacity = '1';
        });
    }

});

// ── 360° Kullanıcı Detay Modalı ──
function escHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}
function openUserDetail(userId) {
    var overlay = document.getElementById('userDetailModal');
    var body = document.getElementById('userDetailBody');
    if (!overlay || !body || !userId) {
        return;
    }
    openAdminManagedModal(overlay, {
        initialFocus: '.ui-admin-detail-close'
    });
    body.innerHTML = ''
        + '<div class="ui-admin-detail-head ui-panel__head">'
        +   '<div class="ui-admin-skeleton-flex"><span class="ui-admin-skeleton ui-admin-skeleton-text sk-w-60"></span><span class="ui-admin-skeleton ui-admin-skeleton-text sk-w-40"></span></div>'
        +   '<span class="ui-admin-skeleton ui-admin-skeleton-btn"></span>'
        + '</div>'
        + '<div class="ui-admin-detail-stats">'
        +   '<span class="ui-admin-skeleton ui-admin-skeleton-stat"></span>'
        +   '<span class="ui-admin-skeleton ui-admin-skeleton-stat"></span>'
        +   '<span class="ui-admin-skeleton ui-admin-skeleton-stat"></span>'
        +   '<span class="ui-admin-skeleton ui-admin-skeleton-stat"></span>'
        + '</div>'
        + '<div class="ui-admin-detail-grid ui-grid">'
        +   '<div><span class="ui-admin-skeleton ui-admin-skeleton-text sk-w-40"></span><span class="ui-admin-skeleton ui-admin-skeleton-text sk-w-80"></span><span class="ui-admin-skeleton ui-admin-skeleton-text sk-w-60"></span></div>'
        +   '<div><span class="ui-admin-skeleton ui-admin-skeleton-text sk-w-40"></span><span class="ui-admin-skeleton ui-admin-skeleton-text sk-w-80"></span><span class="ui-admin-skeleton ui-admin-skeleton-text sk-w-60"></span></div>'
        + '</div>';

    fetch('api/user-details.php?id=' + encodeURIComponent(userId), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res || !res.success) {
                body.innerHTML = '<div class="ui-admin-detail-loading">Detaylar yüklenemedi.</div>';
                return;
            }
            var d = res.data || {};
            var s = d.stats || {};
            function listOrEmpty(arr, render) {
                if (!arr || !arr.length) {
                    return '<p class="ui-admin-muted ui-admin-detail-empty-text ui-empty">— Kayıt yok —</p>';
                }
                return '<ul class="ui-admin-detail-list">' + arr.map(render).join('') + '</ul>';
            }
            function editAttrs(user) {
                return ' data-user-id="' + escHtml(user.id) + '"'
                    + ' data-user-name="' + escHtml(user.name || user.username || '') + '"'
                    + ' data-user-username="' + escHtml(user.username || user.name || '') + '"'
                    + ' data-user-email="' + escHtml(user.email || '') + '"'
                    + ' data-user-group="' + escHtml(user.group_id || '') + '"'
                    + ' data-user-status="' + escHtml(user.status || 'active') + '"'
                    + ' data-user-location="' + escHtml(user.location || '') + '"'
                    + ' data-user-website="' + escHtml(user.website || '') + '"'
                    + ' data-user-github="' + escHtml(user.social_github || '') + '"'
                    + ' data-user-twitter="' + escHtml(user.social_twitter || '') + '"'
                    + ' data-user-discord="' + escHtml(user.social_discord || '') + '"'
                    + ' data-user-bio="' + escHtml(user.bio || '') + '"';
            }
            function quickActionHtml(user, userName) {
                var actions = ''
                    + '<a href="users.php?tab=activity&amp;user_id=' + encodeURIComponent(user.id || '') + '" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline"><i class="bi bi-activity"></i> Aktivite</a>'
                    + '<button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline" data-admin-note-open data-user-id="' + escHtml(user.id) + '" data-user-name="' + escHtml(userName) + '"><i class="bi bi-journal-plus"></i> Not Ekle</button>'
                    + '<button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-primary" data-user-edit-open' + editAttrs(user) + '><i class="bi bi-pencil"></i> Düzenle</button>';

                if (user.can_moderate) {
                    actions += user.is_banned
                        ? '<button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-success" data-user-unban="' + escHtml(user.id) + '"><i class="bi bi-check-circle"></i> Ban Kaldır</button>'
                        : '<button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-danger" data-user-ban="' + escHtml(user.id) + '" data-user-name="' + escHtml(userName) + '"><i class="bi bi-slash-circle"></i> Banla</button>';
                    actions += '<button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline" data-user-restrict="' + escHtml(user.id) + '" data-user-name="' + escHtml(userName) + '"><i class="bi bi-shield-exclamation"></i> Kısıtla</button>';
                }

                return '<div class="ui-admin-detail-actions">' + actions + '</div>';
            }

            var userName = d.name || d.username || ('#' + d.id);
            var activeRestrictionCount = (d.restrictions && d.restrictions.length) || 0;
            var noteCount = (d.admin_notes && d.admin_notes.length) || 0;
            var statusLabel = ({ active: 'Aktif', inactive: 'Pasif' })[String(d.status || '').toLowerCase()] || (d.status || 'Aktif');
            var banBadge = d.is_banned
                ? '<span class="ui-admin-badge ui-admin-badge-danger">Yasaklı</span>' + (d.ban_reason ? ' <span class="ui-admin-muted">(' + escHtml(d.ban_reason) + ')</span>' : '')
                : '<span class="ui-admin-badge ui-admin-badge-success">' + escHtml(statusLabel) + '</span>';

            var html = ''
                + '<div class="ui-admin-detail-head ui-panel__head">'
                +   '<div class="ui-admin-detail-identity"><strong>' + escHtml(userName) + '</strong><span>' + escHtml(d.email) + '</span><small>Son aktivite: ' + escHtml(d.last_activity_at || 'Kayıt yok') + '</small></div>'
                +   '<div class="ui-admin-detail-badges">' + banBadge + ' <span class="ui-admin-badge">' + escHtml(d.group_name || 'Üye') + '</span></div>'
                + '</div>'
                + '<div class="ui-admin-detail-stats">'
                +   '<span><b>' + (s.total_topics || 0) + '</b> konu</span>'
                +   '<span><b>' + (s.total_comments || 0) + '</b> yorum</span>'
                +   '<span><b>' + (s.total_downloads || 0) + '</b> indirme</span>'
                +   '<span><b>' + (d.reports_about || 0) + '</b> şikayet</span>'
                + '</div>'
                + '<div class="ui-admin-detail-decision">'
                +   '<div><span>Son giriş</span><strong>' + escHtml(d.last_login_at || 'Kayıt yok') + '</strong></div>'
                +   '<div><span>Son IP</span><strong>' + escHtml(d.last_login_ip || 'Yok') + '</strong></div>'
                +   '<div><span>Aktif kısıtlama</span><strong>' + activeRestrictionCount + '</strong></div>'
                +   '<div><span>Admin notu</span><strong>' + noteCount + '</strong></div>'
                + '</div>'
                + quickActionHtml(d, userName)
                + '<div class="ui-admin-detail-grid ui-grid">'
                +   '<div class="ui-admin-detail-full"><h4>Son Aktivite</h4>' + listOrEmpty(d.recent_activity, function (a) {
                        var detail = [a.group, a.device, a.ip_address].filter(Boolean).map(escHtml).join(' · ');
                        return '<li><b>' + escHtml(a.event || a.title || 'Aktivite') + '</b> <span class="ui-admin-muted">' + escHtml(a.created_at || '') + '</span>' + (detail ? '<br><span class="ui-admin-muted">' + detail + '</span>' : '') + (a.title && a.title !== a.event ? '<br><span>' + escHtml(a.title) + '</span>' : '') + '</li>';
                    }) + '</div>'
                +   '<div><h4>Son Konular</h4>' + listOrEmpty(d.recent_topics, function (t) {
                        var topicHref = t.url || '#';
                        return '<li><a href="' + escHtml(topicHref) + '" target="_blank" rel="noopener">' + escHtml(t.title) + '</a> <span class="ui-admin-muted">' + escHtml(t.created_at) + '</span></li>';
                    }) + '</div>'
                +   '<div><h4>Son Yorumlar</h4>' + listOrEmpty(d.recent_comments, function (c) {
                        return '<li>' + escHtml(c.excerpt) + ' <span class="ui-admin-muted">' + escHtml(c.created_at) + '</span></li>';
                    }) + '</div>'
                +   '<div><h4>IP Adresleri</h4>' + listOrEmpty(d.login_ips, function (ip) {
                        return '<li><code>' + escHtml(ip) + '</code></li>';
                    }) + '</div>'
                +   '<div><h4>Aktif Kısıtlamalar</h4>' + listOrEmpty(d.restrictions, function (r) {
                        return '<li>' + escHtml(r.type || r.restriction_type || 'kısıtlama') + ' <span class="ui-admin-muted">' + escHtml(r.reason || '') + '</span></li>';
                    }) + '</div>'
                +   '<div><h4>Admin Notları</h4>' + listOrEmpty(d.admin_notes, function (n) {
                        return '<li><b>' + escHtml(n.admin || 'Admin') + '</b> <span class="ui-admin-muted">' + escHtml(n.created_at || '') + '</span><br><span>' + escHtml(n.note || '') + '</span>' + (n.tags ? '<br><span class="ui-admin-muted">' + escHtml(n.tags) + '</span>' : '') + '</li>';
                    }) + '</div>'
                +   '<div><h4>Kısıtlama Kayıtları</h4>' + listOrEmpty(d.restriction_history, function (r) {
                        return '<li><b>' + escHtml(r.type || 'Kısıtlama') + '</b> ' + (r.active ? '<span class="ui-admin-badge ui-admin-badge-warning">aktif</span>' : '<span class="ui-admin-badge ui-admin-badge-muted">geçmiş</span>') + '<br><span class="ui-admin-muted">' + escHtml(r.created_at || '') + ' - Bitiş: ' + escHtml(r.expires_at || 'Süresiz') + '</span>' + (r.reason ? '<br><span>' + escHtml(r.reason) + '</span>' : '') + '</li>';
                    }) + '</div>'
                +   '<div class="ui-admin-detail-full"><h4>Yönetici İşlem Geçmişi</h4>' + listOrEmpty(d.audit_history, function (a) {
                        return '<li><b>' + escHtml(a.action) + '</b> - ' + escHtml(a.actor) + ' <span class="ui-admin-muted">' + escHtml(a.created_at) + '</span>' + (a.reverted ? ' <span class="ui-admin-badge ui-admin-badge-muted">geri alındı</span>' : '') + (a.reason ? '<br><span class="ui-admin-muted">' + escHtml(a.reason) + '</span>' : '') + '</li>';
                    }) + '</div>'
                + '</div>';

            body.innerHTML = html;
        })
        .catch(function () {
            body.innerHTML = '<div class="ui-admin-detail-loading">Bağlantı hatası.</div>';
        });
}
function closeUserDetail() {
    var overlay = document.getElementById('userDetailModal');
    closeAdminManagedModal(overlay);
}
window.openUserDetail = openUserDetail;
window.closeUserDetail = closeUserDetail;
document.querySelectorAll('[data-user-detail-open]').forEach(function(button) {
    button.addEventListener('click', function() {
        openUserDetail(this.getAttribute('data-user-id'));
    });
});
document.addEventListener('keydown', function (e) {
    if (window.TMUI && typeof window.TMUI.openDialog === 'function') return;
    if (e.key === 'Escape') { closeUserDetail(); }
});

(function bindGroupPermissionTools() {
    const shell = document.querySelector('[data-group-permission-tools]');
    if (!shell) return;

    const items = Array.from(shell.querySelectorAll('[data-permission-item]'));
    const categories = Array.from(shell.querySelectorAll('[data-permission-category]'));
    const search = shell.querySelector('[data-permission-search]');
    const filter = shell.querySelector('[data-permission-filter]');

    function applyFilters() {
        const query = String(search?.value || '').trim().toLowerCase();
        const category = String(filter?.value || '').trim();

        categories.forEach(group => {
            const categoryMatches = !category || group.getAttribute('data-permission-category') === category;
            let visibleCount = 0;
            group.querySelectorAll('[data-permission-item]').forEach(item => {
                const text = String(item.getAttribute('data-permission-text') || '').toLowerCase();
                const visible = categoryMatches && (!query || text.includes(query));
                item.hidden = !visible;
                if (visible) visibleCount += 1;
            });
            group.hidden = visibleCount === 0;
        });
    }

    search?.addEventListener('input', applyFilters);
    filter?.addEventListener('change', applyFilters);

    shell.querySelector('[data-permission-select-all]')?.addEventListener('click', function () {
        items.forEach(item => {
            if (!item.hidden && !item.closest('[data-permission-category]')?.hidden) {
                const input = item.querySelector('input[type="checkbox"]');
                if (input) input.checked = true;
            }
        });
    });

    shell.querySelector('[data-permission-clear-all]')?.addEventListener('click', function () {
        items.forEach(item => {
            if (!item.hidden && !item.closest('[data-permission-category]')?.hidden) {
                const input = item.querySelector('input[type="checkbox"]');
                if (input) input.checked = false;
            }
        });
    });
})();
