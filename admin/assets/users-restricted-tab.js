function adminUsersCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function openRestrictedManagedModal(modal, options) {
    if (!modal) return null;
    if (window.adminModal && typeof window.adminModal.open === 'function') {
        return window.adminModal.open(modal, options || {});
    }
    if (window.openAdminManagedModal && window.openAdminManagedModal !== openRestrictedManagedModal) {
        return window.openAdminManagedModal(modal, options || {});
    }
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add('is-open', 'ui-admin-modal-open');
    return null;
}

function closeRestrictedManagedModal(modal, resetCallback) {
    if (!modal) return;
    if (window.adminModal && typeof window.adminModal.close === 'function') {
        window.adminModal.close(modal, resetCallback);
        return;
    }
    if (window.closeAdminManagedModal && window.closeAdminManagedModal !== closeRestrictedManagedModal) {
        window.closeAdminManagedModal(modal, resetCallback);
        return;
    }
    modal.classList.remove('is-open', 'ui-admin-modal-open');
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    if (typeof resetCallback === 'function') resetCallback();
}

function openAddRestrictionModal(userId, userName) {
    document.getElementById('addRestrictUserId').value = userId;
    document.getElementById('addRestrictUserName').value = userName;
    openRestrictedManagedModal(document.getElementById('addRestrictionModal'), {
        initialFocus: 'select[name="restrict_type"]',
        onClose: function () {
            document.getElementById('addRestrictionForm')?.reset();
        }
    });
}

function closeAddRestrictionModal() {
    closeRestrictedManagedModal(document.getElementById('addRestrictionModal'), function () {
        document.getElementById('addRestrictionForm').reset();
    });
}

function submitAddRestriction(e) {
    e.preventDefault();
    const form = e.target;
    const submitButton = form.querySelector('button[type="submit"]');
    const request = window.adminAsync ? window.adminAsync.submitForm(form, {
        url: 'users.php',
        button: submitButton,
        loadingText: 'Isleniyor...',
        notifyError: false,
        prepareBody: function(body) {
            body.set('action', 'add_restriction');
        }
    }) : window.adminFetchJson('users.php', {
        method: 'POST',
        body: (() => {
            const formData = new FormData(form);
            formData.set('action', 'add_restriction');
            return formData;
        })(),
        notifyError: false
    });

    request
    .then(data => {
        if (data.ok || data.success) {
            showToast(data.message, 'success');
            window.location.reload();
        } else {
            showToast('Hata: ' + data.message, 'error');
        }
    })
    .catch((error) => showToast(error && error.message ? error.message : 'Bir hata oluştu', 'error'));

    return false;
}

async function removeAllRestrictions(userId, trigger) {
    if (!await adminConfirm('Bu kullanıcının tüm kısıtlamalarını kaldırmak istediğinizden emin misiniz?', {
        title: 'Tüm kısıtlamalar kaldırılsın mı?',
        ok: 'Kaldır',
        tone: 'danger'
    })) return;

    const formData = new FormData();
    formData.append('_token', adminUsersCsrfToken());
    formData.append('action', 'remove_all_restrictions');
    formData.append('user_id', userId);

    const request = window.adminAsync ? window.adminAsync.fetchJson('users.php', {
        button: trigger || null,
        loadingHtml: '<i class="bi bi-hourglass-split"></i>',
        method: 'POST',
        body: formData,
        notifyError: false
    }) : window.adminFetchJson('users.php', {
        method: 'POST',
        body: formData,
        notifyError: false
    });

    request
    .then(data => {
        if (data.ok || data.success) {
            showToast(data.message, 'success');
            window.location.reload();
        } else {
            showToast('Hata: ' + data.message, 'error');
        }
    })
    .catch((error) => showToast(error && error.message ? error.message : 'Bir hata oluştu', 'error'));
}

function initUsersRestrictedTab() {
    document.addEventListener('click', function(event) {
        const addTrigger = event.target.closest('[data-add-restriction]');
        if (addTrigger) {
            openAddRestrictionModal(addTrigger.getAttribute('data-add-restriction'), addTrigger.getAttribute('data-user-name') || '');
            return;
        }

        const removeTrigger = event.target.closest('[data-remove-restrictions]');
        if (removeTrigger) {
            removeAllRestrictions(removeTrigger.getAttribute('data-remove-restrictions'), removeTrigger);
            return;
        }

        if (event.target.closest('[data-add-restriction-close]')) {
            closeAddRestrictionModal();
        }
    });

    document.getElementById('addRestrictionForm')?.addEventListener('submit', submitAddRestriction);

    document.getElementById('addRestrictionModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeAddRestrictionModal();
    });

    document.getElementById('viewRestrictionsModal')?.addEventListener('click', function(e) {
        if (e.target === this) window.location.href = 'users.php?tab=restricted';
    });
}

window.openAddRestrictionModal = openAddRestrictionModal;
window.closeAddRestrictionModal = closeAddRestrictionModal;
window.removeAllRestrictions = removeAllRestrictions;

window.adminPage.register('users:restricted', initUsersRestrictedTab, {
    id: 'users:restricted',
    selector: '#addRestrictionModal, [data-add-restriction], [data-remove-restrictions]'
});
