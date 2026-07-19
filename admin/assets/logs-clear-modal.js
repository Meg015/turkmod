function initLogsClearModal() {
    function modal() {
        return document.getElementById('clearLogsModal');
    }

    function scopeSelect(form) {
        return form ? form.querySelector('[data-clear-logs-scope], select[name="scope"], select[name="action"]') : null;
    }

    function openClearLogsModal() {
        const dialog = modal();
        if (!dialog) return;
        if (window.adminDialog && typeof window.adminDialog.open === 'function') {
            window.adminDialog.open(dialog, {
                bodyClass: 'ui-admin-dialog-open',
                initialFocus: '[data-clear-logs-scope], select[name="scope"], select[name="action"]',
                returnFocus: document.activeElement
            });
            return;
        }
        dialog.hidden = false;
        dialog.setAttribute('aria-hidden', 'false');
        dialog.classList.add('is-open', 'ui-admin-modal-open');
        dialog.classList.remove('is-closing');
        scopeSelect(dialog.querySelector('form'))?.focus();
    }

    function closeClearLogsModal() {
        const dialog = modal();
        if (!dialog) return;
        if (window.adminDialog && typeof window.adminDialog.close === 'function') {
            window.adminDialog.close(dialog);
            return;
        }
        dialog.classList.add('is-closing');
        setTimeout(() => {
            dialog.classList.remove('is-open', 'is-closing', 'ui-admin-modal-open');
            dialog.hidden = true;
            dialog.setAttribute('aria-hidden', 'true');
        }, 160);
    }

    function updateDependentFields(form) {
        const select = scopeSelect(form);
        if (!select) return;
        const selected = select.options[select.selectedIndex];
        form.querySelectorAll('[data-clear-logs-field]').forEach((field) => {
            const onlyFor = (field.getAttribute('data-clear-logs-field') || '').split(',').map((value) => value.trim()).filter(Boolean);
            const isActive = onlyFor.length === 0 || onlyFor.includes(selected.value);
            field.hidden = !isActive;
            field.querySelectorAll('input, select, textarea').forEach((input) => {
                input.disabled = !isActive;
            });
        });
    }

    function submitClearLogs(event) {
        event.preventDefault();
        const form = event.target;
        const select = scopeSelect(form);
        const selected = select ? select.options[select.selectedIndex] : null;
        const scopeText = selected ? selected.textContent.trim() : 'Seçilen temizlik';
        const dialog = modal();
        const title = selected?.dataset.confirmTitle || form.dataset.clearLogsConfirmTitle || dialog?.dataset.confirmTitle || 'Günlüğü Temizle';
        const message = selected?.dataset.confirmMessage || form.dataset.clearLogsConfirm || `"${scopeText}" işlemini yapmak üzeresiniz. Bu işlem kesinlikle geri alınamaz. Emin misiniz?`;
        const ok = selected?.dataset.confirmOk || form.dataset.clearLogsConfirmOk || 'Evet, Kalıcı Olarak Sil';
        const cancel = form.dataset.clearLogsConfirmCancel || 'İptal';

        adminConfirm(message, {
            title,
            ok,
            cancel,
            tone: 'danger',
            kind: 'logs-clear',
            icon: 'bi-trash'
        }).then((confirmed) => {
            if (!confirmed) return;

            const button = form.querySelector('button[type="submit"]');

            window.adminAsync.submitForm(form, {
                button: button,
                loadingHtml: '<i class="bi bi-hourglass-split"></i> Siliniyor...',
                notifyError: false
            })
                .then((data) => {
                    if (!data) return;
                    if (data.ok || data.success) {
                        closeClearLogsModal();
                        adminAlert(data.message || 'Günlük temizlendi.', { title: 'Başarılı', tone: 'success' }).then(() => window.location.reload());
                        return;
                    }
                    adminAlert(data.message || 'Bir hata oluştu.', { title: 'Hata', tone: 'danger' });
                })
                .catch((error) => {
                    const message = error && error.message ? error.message : 'Bir bağlantı hatası oluştu. Lütfen tekrar deneyin.';
                    adminAlert(message, { title: 'Hata', tone: 'danger' });
                });
        });
        return false;
    }

    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-clear-logs-open]')) {
            openClearLogsModal();
            return;
        }
        if (event.target.closest('[data-clear-logs-close]')) {
            closeClearLogsModal();
        }
    });

    document.addEventListener('change', function (event) {
        const target = event.target;
        if (target && target.matches('[data-clear-logs-scope], select[name="scope"], select[name="action"]')) {
            updateDependentFields(target.closest('form'));
        }
    });

    document.querySelectorAll('[data-clear-logs-form], #clearLogsForm').forEach((form) => {
        updateDependentFields(form);
        form.addEventListener('submit', submitClearLogs);
    });
}

window.adminPage.register('*', initLogsClearModal, {
    id: 'logs-clear-modal',
    selector: '[data-clear-logs-form], #clearLogsForm, [data-clear-logs-open]'
});
