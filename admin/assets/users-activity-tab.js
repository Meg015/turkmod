function openClearLogsModal() {
    const modal = document.getElementById('clearLogsModal');
    if (!modal) return;
    if (window.TMUI && typeof window.TMUI.openDialog === 'function') {
        window.TMUI.openDialog(modal, {
            bodyClass: 'ui-admin-dialog-open',
            initialFocus: 'select[name="scope"]',
            returnFocus: document.activeElement
        });
        modal.classList.add('ui-admin-modal-open');
        return;
    }
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add('is-open', 'ui-admin-modal-open');
    modal.classList.remove('is-closing');
    modal.querySelector('select[name="scope"]')?.focus();
}
function closeClearLogsModal() {
    const modal = document.getElementById('clearLogsModal');
    if (!modal) return;
    if (window.TMUI && typeof window.TMUI.closeDialog === 'function' && modal._tmuiDialog) {
        window.TMUI.closeDialog(modal);
        modal.classList.remove('ui-admin-modal-open');
        return;
    }
    modal.classList.add('is-closing');
    setTimeout(() => {
        modal.classList.remove('is-open', 'is-closing', 'ui-admin-modal-open');
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
    }, 160);
}
document.addEventListener('click', function(event) {
    if (event.target.closest('[data-clear-logs-open]')) {
        openClearLogsModal();
        return;
    }
    if (event.target.closest('[data-clear-logs-close]')) {
        closeClearLogsModal();
    }
});
function submitClearLogs(e) {
    e.preventDefault();
    const form = e.target;
    const scopeSelect = form.querySelector('select[name="scope"]');
    const scopeText = scopeSelect.options[scopeSelect.selectedIndex].text;
    
    adminConfirm(`"${scopeText}" işlemini yapmak üzeresiniz. Bu işlem kesinlikle geri alınamaz. Emin misiniz?`, {
        title: 'Kayıtları Sil',
        ok: 'Evet, Kalıcı Olarak Sil',
        cancel: 'İptal',
        tone: 'danger'
    }).then((confirmed) => {
        if (!confirmed) return;
        
        const btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Siliniyor...';
        
        fetch('users.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form)
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                adminAlert(data.message, { title: 'Başarılı', tone: 'success' }).then(() => window.location.reload());
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-trash"></i> Seçilenleri Kalıcı Olarak Sil';
                adminAlert(data.message || 'Bir hata oluştu.', { title: 'Hata', tone: 'danger' });
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-trash"></i> Seçilenleri Kalıcı Olarak Sil';
            adminAlert('Bir bağlantı hatası oluştu. Lütfen tekrar deneyin.', { title: 'Hata', tone: 'danger' });
        });
    });
    return false;
}
document.getElementById('clearLogsForm')?.addEventListener('submit', submitClearLogs);
