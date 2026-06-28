const _catCsrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

function confirmDeleteCategory(id, name) {
    adminConfirm('<strong>' + name + '</strong> kategorisi kalıcı olarak silinecek.', {
        title: 'Kategoriyi sil?',
        ok: '<i class="bi bi-trash"></i> Evet, sil',
        cancel: 'İptal',
        tone: 'danger'
    }).then(function(confirmed) {
        if (!confirmed) return;

        const row = document.getElementById('cat-row-' + id);
        if (row) row.classList.add('ui-admin-row-pending');

        const fd = new FormData();
        fd.append('_token', _catCsrf);
        fd.append('action', 'delete');
        fd.append('id', id);

        fetch('categories.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd,
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                if (row) {
                    row.classList.remove('ui-admin-row-pending');
                    row.classList.add('ui-admin-row-removing');
                    setTimeout(function() { row.remove(); }, 300);
                }
                if (window.adminToast) adminToast.success(data.message);
                else showToast(data.message, 'success');
            } else {
                if (row) row.classList.remove('ui-admin-row-pending');
                adminAlert(data.message, { title: 'Hata', tone: 'danger' });
            }
        })
        .catch(function() {
            if (row) row.classList.remove('ui-admin-row-pending');
            adminAlert('Sunucu ile iletişim kurulamadı.', { title: 'Hata', tone: 'danger' });
        });
    });
}

document.addEventListener('click', function(event) {
    const deleteTrigger = event.target.closest('[data-category-delete]');
    if (!deleteTrigger) return;
    confirmDeleteCategory(deleteTrigger.getAttribute('data-category-delete'), deleteTrigger.getAttribute('data-category-name') || '');
});
