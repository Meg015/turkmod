let userReportModalController = null;

function openUserReportModal(trigger) {
    const modal = document.getElementById('userReportModal');
    if (!modal) return;

    if (window.TMUI && typeof window.TMUI.openDialog === 'function') {
        userReportModalController = window.TMUI.openDialog(modal, {
            bodyClass: 'topic-report-modal-open',
            initialFocus: 'select[name="reason"]',
            returnFocus: trigger || document.activeElement,
            onClose: function () {
                userReportModalController = null;
            }
        });
        return;
    }

    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('topic-report-modal-open');
    const first = modal.querySelector('select, textarea, button');
    if (first) first.focus();
}

function closeUserReportModal() {
    const modal = document.getElementById('userReportModal');
    if (!modal) return;
    if (userReportModalController && typeof userReportModalController.close === 'function') {
        userReportModalController.close(true);
        return;
    }
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('topic-report-modal-open');
}

document.addEventListener('click', function(event) {
    const opener = event.target.closest('[data-user-report-modal-open]');
    if (opener) openUserReportModal(opener);
    if (event.target.closest('[data-user-report-modal-close]')) {
        closeUserReportModal();
    }
});
document.addEventListener('keydown', function(event) {
    if (window.TMUI || event.key !== 'Escape') return;
    closeUserReportModal();
});

document.addEventListener('submit', function(event) {
    const form = event.target.closest('.user-report-form');
    if (!form) return;
    event.preventDefault();
    const feedback = form.querySelector('.topic-report-feedback');
    const button = form.querySelector('button[type="submit"]');
    const original = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="bi bi-hourglass-split"></i> Gönderiliyor...';
    const payload = Object.fromEntries(new FormData(form).entries());
    const requestOptions = {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
        body: payload
    };
    (window.publicFetchJson
        ? window.publicFetchJson(form.action, Object.assign({}, requestOptions, { notifyError: false })).then(function(payload) {
            return {ok: true, payload: payload};
        })
        : Promise.reject(new Error('Public API helper yuklenemedi.'))
    ).then(function(result) {
        const isSuccess = !!(result.ok && result.payload.success);
        const message = result.payload.message || (isSuccess ? 'Şikayet gönderildi.' : 'Şikayet gönderilemedi.');
        feedback.textContent = message;
        feedback.className = 'topic-report-feedback ' + (isSuccess ? 'is-success' : 'is-error');
        if (isSuccess) {
            form.reset();
            closeUserReportModal();
            window.showToast?.(message, 'success');
            return;
        }
        if (window.showToast) {
            window.showToast(message, 'error');
        }
    }).catch(function(error) {
        feedback.textContent = error && error.message ? error.message : 'Bağlantı hatası. Lütfen tekrar deneyin.';
        feedback.className = 'topic-report-feedback is-error';
        if (window.showToast) {
            window.showToast('Şikayet gönderilemedi.', 'error');
        }
    }).finally(function() {
        button.disabled = false;
        button.innerHTML = original;
    });
});
