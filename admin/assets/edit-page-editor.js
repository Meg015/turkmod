function topicEditorEscape(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function addDlRow(name, url) {
    const row = document.createElement('div');
    row.className = 'dl-row ui-admin-download-row';
    row.innerHTML = '<input type="text" name="dl_name[]" class="ui-admin-form-control ui-admin-download-name" placeholder="Kaynak adi" value="' + topicEditorEscape(name) + '">'
        + '<input type="url" name="dl_url[]" class="ui-admin-form-control ui-admin-download-url" placeholder="https://..." value="' + topicEditorEscape(url) + '">'
        + '<button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" data-ui-remove-closest=".dl-row" title="Kaldir"><i class="bi bi-x-lg"></i></button>';
    document.getElementById('dlRows')?.appendChild(row);
}
window.addDlRow = addDlRow;

function syncEditDownloadRowsToHidden() {
    const hidden = document.getElementById('dlHidden');
    if (!hidden) return;

    const names = document.querySelectorAll('input[name="dl_name[]"]');
    const urls = document.querySelectorAll('input[name="dl_url[]"]');
    const lines = [];
    names.forEach((n, i) => {
        const u = urls[i]?.value?.trim();
        if (u) lines.push((n.value.trim() || 'Link') + '|' + u);
    });
    hidden.value = lines.join('\n');
}

function initEditPageEditor() {
    if (window.TMUI && typeof window.TMUI.registerAction === 'function') {
        window.TMUI.registerAction('addDlRow', function() { addDlRow(); });
    }

    document.getElementById('topicForm')?.addEventListener('submit', syncEditDownloadRowsToHidden);
}

window.adminPage.register('edit', initEditPageEditor, {
    id: 'edit-page:editor',
    selector: '#topicForm'
});
