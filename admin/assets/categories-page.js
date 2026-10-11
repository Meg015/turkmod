function categoryCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function deleteCategory(id, trigger) {
    const row = document.getElementById('cat-row-' + id);
    if (row) row.classList.add('ui-admin-row-pending');

    const fd = new FormData();
    fd.append('_token', categoryCsrfToken());
    fd.append('action', 'delete');
    fd.append('id', id);

    const request = window.adminAsync ? window.adminAsync.fetchJson('categories.php', {
        button: trigger || null,
        loadingHtml: '<i class="bi bi-hourglass-split"></i>',
        method: 'POST',
        body: fd,
        notifyError: false
    }) : window.adminFetchJson('categories.php', {
        method: 'POST',
        body: fd,
        notifyError: false
    });

    request
    .then(function(data) {
        if (data.ok || data.success) {
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
    .catch(function(error) {
        if (row) row.classList.remove('ui-admin-row-pending');
        adminAlert(error && error.message ? error.message : 'Sunucu ile iletişim kurulamadı.', { title: 'Hata', tone: 'danger' });
    });
}

function initCategoriesPage() {
    document.addEventListener('click', function(event) {
        const deleteTrigger = event.target.closest('[data-category-delete]');
        if (!deleteTrigger) return;
        if (deleteTrigger.disabled) return;
        deleteCategory(deleteTrigger.getAttribute('data-category-delete'), deleteTrigger);
    });
}

window.adminPage.register('categories', initCategoriesPage, {
    id: 'categories-page',
    selector: '[data-category-delete]'
});
