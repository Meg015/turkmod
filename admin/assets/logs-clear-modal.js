function initLogsClearModal() {
    function modal() {
        return document.getElementById('clearLogsModal');
    }

    function openClearLogsModal() {
        const dialog = modal();
        if (!dialog) return;
        window.adminModal.open(dialog, {
            bodyClass: 'ui-admin-dialog-open',
            initialFocus: '[data-clear-logs-cancel]',
            returnFocus: document.activeElement
        });
    }

    function closeClearLogsModal() {
        const dialog = modal();
        if (!dialog) return;
        window.adminModal.close(dialog);
    }

    function submitClearLogs(event) {
        event.preventDefault();
        const form = event.target;

        const button = form.querySelector('button[type="submit"]');
        if (form.dataset.clearLogsPending === '1' || button?.disabled) return false;
        form.dataset.clearLogsPending = '1';

        window.adminAsync.submitForm(form, {
            button: button,
            loadingHtml: '<i class="bi bi-hourglass-split"></i> Siliniyor...',
            notify: false,
            notifyError: false
        })
            .then((data) => {
                if (!data) return;
                if (data.ok || data.success) {
                    closeClearLogsModal();
                    window.location.reload();
                    return;
                }
                adminAlert(data.message || 'Bir hata oluştu.', { title: 'Hata', tone: 'danger' });
            })
            .catch((error) => {
                const message = error && error.message ? error.message : 'Bir bağlantı hatası oluştu. Lütfen tekrar deneyin.';
                adminAlert(message, { title: 'Hata', tone: 'danger' });
            })
            .finally(() => {
                delete form.dataset.clearLogsPending;
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

    document.querySelectorAll('[data-clear-logs-form], #clearLogsForm').forEach((form) => {
        form.addEventListener('submit', submitClearLogs);
    });
}

window.adminPage.register('*', initLogsClearModal, {
    id: 'logs-clear-modal',
    selector: '[data-clear-logs-form], #clearLogsForm, [data-clear-logs-open]'
});
