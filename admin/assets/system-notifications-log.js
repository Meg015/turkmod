function initSystemNotificationsLogPage() {
    const form = document.querySelector('[data-system-notifications-form]');
    const modal = document.getElementById('systemNotificationDetailModal');
    let modalNotificationId = '';

    function confirmDialog(message, options) {
        if (window.adminDialog && typeof window.adminDialog.confirm === 'function') {
            return window.adminDialog.confirm(message, options);
        }
        if (typeof window.adminConfirm === 'function') {
            return window.adminConfirm(message, options);
        }

        return Promise.resolve(window.confirm(message));
    }

    function alertDialog(message, options) {
        if (window.adminDialog && typeof window.adminDialog.alert === 'function') {
            return window.adminDialog.alert(message, options);
        }
        if (typeof window.adminAlert === 'function') {
            return window.adminAlert(message, options);
        }
        window.alert(message);
        return Promise.resolve();
    }

    function rowCheckboxes() {
        return form ? Array.from(form.querySelectorAll('[data-system-notification-checkbox]')) : [];
    }

    function selectedCheckboxes() {
        return rowCheckboxes().filter((checkbox) => checkbox.checked);
    }

    function updateBulkState() {
        if (!form) return;
        const checkboxes = rowCheckboxes();
        const selected = selectedCheckboxes();
        const count = selected.length;
        const counter = form.querySelector('[data-system-notifications-selected-count]');
        const bulkButton = form.querySelector('[data-system-notifications-bulk-delete]');
        const checkAll = form.querySelector('[data-system-notifications-check-all]');

        if (counter) {
            counter.textContent = count + ' seçili';
        }
        if (bulkButton) {
            bulkButton.disabled = count === 0;
        }
        if (checkAll) {
            checkAll.checked = checkboxes.length > 0 && count === checkboxes.length;
            checkAll.indeterminate = count > 0 && count < checkboxes.length;
        }
    }

    function setOnlySelected(notificationId) {
        rowCheckboxes().forEach((checkbox) => {
            checkbox.checked = checkbox.value === String(notificationId);
        });
        updateBulkState();
    }

    function submitDelete(actionLabel) {
        if (!form) return;
        const selected = selectedCheckboxes();
        if (selected.length === 0) {
            alertDialog('Silmek için en az bir sistem bildirimi seçin.', { title: 'Seçim gerekli', tone: 'warning' });
            return;
        }

        const message = actionLabel === 'single'
            ? 'Bu sistem bildirimi kalıcı olarak silinsin mi?'
            : selected.length + ' sistem bildirimi kalıcı olarak silinsin mi?';

        confirmDialog(message, {
            title: 'Sistem Bildirimlerini Sil',
            ok: 'Kalıcı Olarak Sil',
            cancel: 'İptal',
            tone: 'danger',
            kind: 'system-notifications-delete',
            icon: 'bi-trash'
        }).then((confirmed) => {
            if (!confirmed) return;

            const button = actionLabel === 'bulk'
                ? form.querySelector('[data-system-notifications-bulk-delete]')
                : document.querySelector('[data-system-notification-delete][data-notification-id="' + selected[0].value + '"]')
                    || document.querySelector('[data-system-notification-detail-delete]');

            window.adminAsync.submitForm(form, {
                button: button,
                loadingHtml: '<i class="bi bi-hourglass-split"></i> Siliniyor...',
                notifyError: false
            })
                .then((data) => {
                    if (!data) return;
                    if (data.ok || data.success) {
                        closeModal();
                        alertDialog(data.message || 'Seçili bildirimler silindi.', { title: 'Başarılı', tone: 'success' })
                            .then(() => window.location.reload());
                        return;
                    }
                    alertDialog(data.message || 'Silme işlemi tamamlanamadı.', { title: 'Hata', tone: 'danger' });
                })
                .catch((error) => {
                    const message = error && error.message ? error.message : 'Bir bağlantı hatası oluştu. Lütfen tekrar deneyin.';
                    alertDialog(message, { title: 'Hata', tone: 'danger' });
                });
        });
    }

    function text(selector, value) {
        const target = modal ? modal.querySelector(selector) : null;
        if (target) {
            target.textContent = value || '—';
        }
    }

    function openModal(button) {
        if (!modal || !button) return;
        modalNotificationId = button.dataset.id || '';
        text('[data-system-notification-detail-id]', '#' + modalNotificationId);
        text('[data-system-notification-detail-title]', button.dataset.title || 'Bildirim');
        text('[data-system-notification-detail-created]', button.dataset.created || '—');
        text('[data-system-notification-detail-target]', button.dataset.target || '—');
        text('[data-system-notification-detail-event]', button.dataset.eventKey || '—');
        text('[data-system-notification-detail-read]', button.dataset.readLabel || 'Okunma yok');
        text('[data-system-notification-detail-message]', button.dataset.message || 'Mesaj yok');

        const linkValue = button.dataset.link || '';
        const linkLabel = modal.querySelector('[data-system-notification-detail-link-label]');
        const link = modal.querySelector('[data-system-notification-detail-link]');
        if (linkValue !== '') {
            if (linkLabel) linkLabel.textContent = linkValue;
            if (link) {
                link.href = linkValue;
                link.hidden = false;
            }
        } else {
            if (linkLabel) linkLabel.textContent = '—';
            if (link) {
                link.hidden = true;
                link.removeAttribute('href');
            }
        }

        if (window.adminDialog && typeof window.adminDialog.open === 'function') {
            window.adminDialog.open(modal, {
                bodyClass: 'ui-admin-dialog-open',
                initialFocus: '[data-system-notification-detail-close]',
                returnFocus: button
            });
            return;
        }

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('is-open', 'ui-admin-modal-open');
        modal.classList.remove('is-closing');
        modal.querySelector('[data-system-notification-detail-close]')?.focus();
    }

    function closeModal() {
        if (!modal) return;
        if (window.adminDialog && typeof window.adminDialog.close === 'function') {
            window.adminDialog.close(modal);
            return;
        }

        modal.classList.add('is-closing');
        setTimeout(() => {
            modal.classList.remove('is-open', 'is-closing', 'ui-admin-modal-open');
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
        }, 160);
    }

    if (form) {
        form.addEventListener('change', (event) => {
            const target = event.target;
            if (target && target.matches('[data-system-notifications-check-all]')) {
                rowCheckboxes().forEach((checkbox) => {
                    checkbox.checked = target.checked;
                });
            }
            if (target && (target.matches('[data-system-notification-checkbox]') || target.matches('[data-system-notifications-check-all]'))) {
                updateBulkState();
            }
        });

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            submitDelete('bulk');
        });

        updateBulkState();
    }

    document.addEventListener('click', (event) => {
        const detailButton = event.target.closest('[data-system-notification-detail-open]');
        if (detailButton) {
            openModal(detailButton);
            return;
        }

        const deleteButton = event.target.closest('[data-system-notification-delete]');
        if (deleteButton) {
            setOnlySelected(deleteButton.dataset.notificationId || '');
            submitDelete('single');
            return;
        }

        if (event.target.closest('[data-system-notification-detail-delete]')) {
            setOnlySelected(modalNotificationId);
            submitDelete('single');
            return;
        }

        if (event.target.closest('[data-system-notification-detail-close]') || event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal && !modal.hidden) {
            closeModal();
        }
    });
}

window.adminPage.register('logs', initSystemNotificationsLogPage, {
    id: 'logs:system-notifications',
    selector: '[data-system-notifications-form]',
    match: function(context) {
        return context.page === 'logs' && context.query.get('view') === 'system_notifications';
    }
});
