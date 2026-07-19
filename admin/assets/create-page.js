function topicFileEscape(value) {
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
    row.innerHTML = '<input type="text" name="dl_name[]" class="ui-admin-form-control ui-admin-download-name" placeholder="Kaynak adi" value="' + topicFileEscape(name) + '">'
        + '<input type="url" name="dl_url[]" class="ui-admin-form-control ui-admin-download-url" placeholder="https://..." value="' + topicFileEscape(url) + '">'
        + '<button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" data-ui-remove-closest=".dl-row" title="Kaldir"><i class="bi bi-x-lg"></i></button>';
    document.getElementById('dlRows')?.appendChild(row);
}
window.addDlRow = addDlRow;

function syncInputFiles(input, files) {
    const dt = new DataTransfer();
    files.forEach(function(file) {
        dt.items.add(file);
    });
    input.files = dt.files;
}

function removePreviewFile(input, previewId, maxFiles, index) {
    const files = Array.from(input.files || []);
    files.splice(index, 1);
    syncInputFiles(input, files);
    renderFilePreviews(input, previewId, maxFiles);
}

function renderFilePreviews(input, previewId, maxFiles) {
    const preview = document.getElementById(previewId);
    if (!preview) return;
    preview.innerHTML = '';

    const files = Array.from(input.files || []).slice(0, maxFiles);
    files.forEach(function(file, index) {
        const item = document.createElement('div');
        item.className = 'media-preview-item';

        if (file.type.startsWith('image/')) {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            item.appendChild(img);
        } else {
            item.innerHTML = '<div class="ui-admin-file-placeholder"><i class="bi bi-file-earmark"></i></div>';
        }

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'media-preview-remove';
        removeBtn.textContent = 'Kaldir';
        removeBtn.addEventListener('click', function() {
            removePreviewFile(input, previewId, maxFiles, index);
        });
        item.appendChild(removeBtn);

        const label = document.createElement('div');
        label.className = 'media-preview-name';
        label.textContent = file.name;
        item.appendChild(label);
        preview.appendChild(item);
    });
}

function bindTopicMediaPickers(configs) {
    document.querySelectorAll('[data-open-input]').forEach(function(trigger) {
        trigger.addEventListener('click', function() {
            const input = document.getElementById(this.getAttribute('data-open-input'));
            if (input) input.click();
        });
    });

    configs.forEach(function(config) {
        const input = document.getElementById(config.inputId);
        const zone = input ? input.closest('.media-dropzone-modern') : null;
        if (!input || !zone) return;

        input.addEventListener('change', function() {
            renderFilePreviews(input, config.previewId, config.maxFiles);
        });

        ['dragenter', 'dragover'].forEach(function(eventName) {
            zone.addEventListener(eventName, function(e) {
                e.preventDefault();
                zone.classList.add('is-active');
            });
        });

        ['dragleave', 'drop'].forEach(function(eventName) {
            zone.addEventListener(eventName, function(e) {
                e.preventDefault();
                zone.classList.remove('is-active');
            });
        });

        zone.addEventListener('drop', function(e) {
            const droppedFiles = Array.from(e.dataTransfer.files || []).slice(0, config.maxFiles);
            syncInputFiles(input, droppedFiles);
            renderFilePreviews(input, config.previewId, config.maxFiles);
        });
    });
}

function syncDownloadRowsToHidden() {
    const hidden = document.getElementById('dlHidden');
    if (!hidden) return;

    const names = document.querySelectorAll('input[name="dl_name[]"]');
    const urls = document.querySelectorAll('input[name="dl_url[]"]');
    const lines = [];
    names.forEach(function(n, i) {
        const u = urls[i] ? urls[i].value.trim() : '';
        if (u) lines.push((n.value.trim() || 'Link') + '|' + u);
    });
    hidden.value = lines.join('\n');
}

function initCreatePage() {
    if (window.TMUI && typeof window.TMUI.registerAction === 'function') {
        window.TMUI.registerAction('addDlRow', function() { addDlRow(); });
    }

    bindTopicMediaPickers([
        { inputId: 'coverInput', previewId: 'coverPreview', maxFiles: 1 },
        { inputId: 'galleryInput', previewId: 'galleryPreview', maxFiles: 10 }
    ]);

    document.getElementById('topicForm')?.addEventListener('submit', function(e) {
        syncDownloadRowsToHidden();

        const filesInput = document.getElementById('galleryInput');
        if (filesInput && filesInput.files.length > 10) {
            e.preventDefault();
            if (typeof showToast === 'function') {
                showToast('En fazla 10 adet resim yukleyebilirsiniz.', 'warning');
            }
        }
    });
}

window.adminPage.register('create', initCreatePage, {
    id: 'create-page',
    selector: '#topicForm'
});
