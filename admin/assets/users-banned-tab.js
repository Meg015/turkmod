function adminUsersCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function unbanUser(userId) {
    if (!await adminConfirm('Bu kullanıcının banını kaldırmak istediğinizden emin misiniz?', {
        title: 'Ban kaldırılsın mı?',
        ok: 'Kaldır',
        tone: 'warning'
    })) return;

    const formData = new FormData();
    formData.append('_token', adminUsersCsrfToken());
    formData.append('action', 'unban');
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

document.addEventListener('click', function(event) {
    const unbanTrigger = event.target.closest('[data-user-unban]');
    if (unbanTrigger) {
        unbanUser(unbanTrigger.getAttribute('data-user-unban'));
    }
});
