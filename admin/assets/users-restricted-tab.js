function adminUsersCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function openRestrictedManagedModal(modal, options) {
    if (!modal) return null;
    const opts = options || {};
    if (window.TMUI && typeof window.TMUI.openDialog === 'function') {
        const dialog = window.TMUI.openDialog(modal, {
            openClass: 'is-open',
            bodyClass: 'ui-admin-dialog-open',
            initialFocus: opts.initialFocus,
            returnFocus: opts.returnFocus || document.activeElement,
            onClose: opts.onClose
        });
        modal.classList.add('ui-admin-modal-open');
        return dialog;
    }
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add('is-open', 'ui-admin-modal-open');
    if (opts.initialFocus) {
        const focusTarget = modal.querySelector(opts.initialFocus);
        if (focusTarget) setTimeout(() => focusTarget.focus(), 80);
    }
    return null;
}

function closeRestrictedManagedModal(modal, resetCallback) {
    if (!modal) return;
    if (window.TMUI && typeof window.TMUI.closeDialog === 'function' && modal._tmuiDialog) {
        window.TMUI.closeDialog(modal);
        modal.classList.remove('ui-admin-modal-open');
        if (typeof resetCallback === 'function') resetCallback();
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

document.addEventListener('click', function(event) {
    const addTrigger = event.target.closest('[data-add-restriction]');
    if (addTrigger) {
        openAddRestrictionModal(addTrigger.getAttribute('data-add-restriction'), addTrigger.getAttribute('data-user-name') || '');
        return;
    }

    const removeTrigger = event.target.closest('[data-remove-restrictions]');
    if (removeTrigger) {
        removeAllRestrictions(removeTrigger.getAttribute('data-remove-restrictions'));
        return;
    }

    if (event.target.closest('[data-add-restriction-close]')) {
        closeAddRestrictionModal();
    }
});

function submitAddRestriction(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    formData.append('action', 'add_restriction');

    fetch('users.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            showToast(data.message, 'success');
            window.location.reload();
        } else {
            showToast('Hata: ' + data.message, 'error');
        }
    })
    .catch(() => showToast('Bir hata oluştu', 'error'));

    return false;
}
document.getElementById('addRestrictionForm')?.addEventListener('submit', submitAddRestriction);

async function removeAllRestrictions(userId) {
    if (!await adminConfirm('Bu kullanıcının tüm kısıtlamalarını kaldırmak istediğinizden emin misiniz?', {
        title: 'Tüm kısıtlamalar kaldırılsın mı?',
        ok: 'Kaldır',
        tone: 'danger'
    })) return;

    const formData = new FormData();
    formData.append('_token', adminUsersCsrfToken());
    formData.append('action', 'remove_all_restrictions');
    formData.append('user_id', userId);

    fetch('users.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            showToast(data.message, 'success');
            window.location.reload();
        } else {
            showToast('Hata: ' + data.message, 'error');
        }
    })
    .catch(() => showToast('Bir hata oluştu', 'error'));
}

// Close modals on outside click
document.getElementById('addRestrictionModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeAddRestrictionModal();
});

document.getElementById('viewRestrictionsModal')?.addEventListener('click', function(e) {
    if (e.target === this) window.location.href = 'users.php?tab=restricted';
});
