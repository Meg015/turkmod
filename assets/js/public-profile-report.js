let userReportModalController = null;

function openUserReportModal(trigger) {
    const modal = document.getElementById('userReportModal');
    if (!modal) return;

    userReportModalController = window.TMUI.openDialog(modal, {
        bodyClass: 'topic-report-modal-open',
        initialFocus: 'select[name="reason"]',
        returnFocus: trigger || document.activeElement,
        onClose: function () {
            userReportModalController = null;
        }
    });
}

function closeUserReportModal() {
    const modal = document.getElementById('userReportModal');
    if (!modal) return;
    if (userReportModalController && typeof userReportModalController.close === 'function') {
        userReportModalController.close(true);
    }
}

document.addEventListener('click', function(event) {
    const opener = event.target.closest('[data-user-report-modal-open]');
    if (opener) openUserReportModal(opener);
    if (event.target.closest('[data-user-report-modal-close]')) {
        closeUserReportModal();
    }
});
document.addEventListener('keydown', function(event) {
    if (event.key !== 'Escape') return;
    closeUserReportModal();
});

document.addEventListener('submit', function(event) {
    const form = event.target.closest('.user-report-form');
    if (!form) return;
    event.preventDefault();
    const modal = form.closest('#userReportModal') || document.getElementById('userReportModal');
    const feedback = form.querySelector('.topic-report-feedback');
    const button = event.submitter || (modal ? modal.querySelector('button[form="' + form.id + '"]') : null) || form.querySelector('button[type="submit"]');
    if (!button) return;
    const original = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="bi bi-hourglass-split"></i> Gönderiliyor...';
    const payload = Object.fromEntries(new FormData(form).entries());
    const requestOptions = {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
        body: payload
    };
    window.publicFetchJson(form.action, Object.assign({}, requestOptions, { notifyError: false })).then(function(payload) {
        return {ok: true, payload: payload};
    }).then(function(result) {
        const isSuccess = !!(result.ok && result.payload.success);
        const message = result.payload.message || (isSuccess ? 'Şikayet gönderildi.' : 'Şikayet gönderilemedi.');
        feedback.textContent = message;
        feedback.className = 'topic-report-feedback ' + (isSuccess ? 'is-success' : 'is-error');
        if (isSuccess) {
            form.reset();
            closeUserReportModal();
            window.showToast(message, 'success');
            return;
        }
        window.showToast(message, 'error');
    }).catch(function(error) {
        feedback.textContent = error && error.message ? error.message : 'Bağlantı hatası. Lütfen tekrar deneyin.';
        feedback.className = 'topic-report-feedback is-error';
        window.showToast('Şikayet gönderilemedi.', 'error');
    }).finally(function() {
        button.disabled = false;
        button.innerHTML = original;
    });
});
