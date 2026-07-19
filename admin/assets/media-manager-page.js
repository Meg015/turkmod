function mediaManagerToast(message, type = 'info', duration) {
    if (window.adminToast && typeof window.adminToast[type] === 'function') {
        window.adminToast[type](message, duration);
        return;
    }
    if (typeof window.showToast === 'function') {
        window.showToast(message, type, duration);
    }
}

function escapeHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function toggleUploadPanel() {
    var panel = document.getElementById('uploadPanel');
    if (!panel) return;
    panel.classList.toggle('mm-hidden');
}

function openMediaPreview(el) {
    var url = el.dataset.url || '';
    var name = el.dataset.name || '';
    var ext = (el.dataset.ext || '').toLowerCase();
    var path = el.dataset.path || '';
    var imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    document.getElementById('previewTitle').textContent = name;
    document.getElementById('previewName').textContent = name;
    document.getElementById('previewSizeType').textContent = (el.dataset.size || '') + ' · ' + ext.toUpperCase();
    document.getElementById('previewDate').textContent = el.dataset.date || '';
    document.getElementById('previewUrl').value = location.origin + url;
    document.getElementById('previewDownload').href = url;
    document.getElementById('previewDeletePath').value = path;

    var img = document.getElementById('previewImage');
    var wrap = document.getElementById('previewImageWrap');
    if (imageExts.indexOf(ext) !== -1) {
        img.src = url;
        img.alt = name;
        wrap.classList.remove('mm-hidden');
    } else {
        img.removeAttribute('src');
        img.alt = '';
        wrap.classList.add('mm-hidden');
    }

    var modal = document.getElementById('mediaPreviewModal');
    if (modal) {
        if (window.adminDialog && typeof window.adminDialog.open === 'function') {
            window.adminDialog.open(modal, {
                bodyClass: 'ui-admin-dialog-open',
                initialFocus: '#mediaPreviewClose',
                returnFocus: document.activeElement,
                onClose: resetMediaPreviewContent
            });
            modal.classList.add('active', 'ui-admin-modal-open');
        } else {
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            modal.classList.add('active', 'is-open', 'ui-admin-modal-open');
            document.body.classList.add('ui-admin-dialog-open');
        }
    }
    
    // Check usage
    var usageContainer = document.getElementById('previewUsage');
    usageContainer.innerHTML = '<i class="bi bi-hourglass-split"></i> Kontrol ediliyor...';
    
    var formData = new FormData();
    formData.append('action', 'check_usage');
    formData.append('_token', document.querySelector('input[name="_token"]').value);
    formData.append('url', url);
    formData.append('path', path);
    
    window.adminFetchJson('media-manager.php', {
        method: 'POST',
        body: formData,
        notifyError: false
    })
    .then(data => {
        if (data.success || data.ok) {
            if (data.usages.length === 0) {
                usageContainer.innerHTML = '<span class="mm-usage-muted"><i class="bi bi-info-circle"></i> Bu dosya hiçbir yerde kullanılmıyor gibi görünüyor.</span>';
            } else {
                var html = '<ul class="mm-usage-list">';
                data.usages.forEach(function(u) {
                    html += '<li><a href="' + u.link + '" target="_blank" class="mm-usage-link">' + u.name + '</a></li>';
                });
                html += '</ul>';
                usageContainer.innerHTML = html;
            }
        } else {
            usageContainer.innerHTML = '<span class="mm-usage-error"><i class="bi bi-exclamation-circle"></i> Kontrol edilemedi: ' + (data.error || 'Hata') + '</span>';
        }
    })
    .catch(err => {
        var message = err && err.message ? err.message : 'Bir hata oluştu.';
        usageContainer.innerHTML = '<span class="mm-usage-error"><i class="bi bi-exclamation-circle"></i> ' + escapeHtml(message) + '</span>';
    });
}

function closeMediaPreview() {
    var modal = document.getElementById('mediaPreviewModal');
    if (modal) {
        if (window.adminDialog && typeof window.adminDialog.close === 'function') {
            window.adminDialog.close(modal, function () {
                modal.classList.remove('active', 'ui-admin-modal-open');
                resetMediaPreviewContent();
            });
            modal.classList.remove('active', 'ui-admin-modal-open');
            return;
        }
        modal.classList.remove('active', 'is-open', 'ui-admin-modal-open');
        modal.setAttribute('aria-hidden', 'true');
        modal.hidden = true;
        document.body.classList.remove('ui-admin-dialog-open');
    }
    resetMediaPreviewContent();
}

function resetMediaPreviewContent() {
    var modal = document.getElementById('mediaPreviewModal');
    if (modal) {
        modal.classList.remove('active', 'ui-admin-modal-open');
    }
    var img = document.getElementById('previewImage');
    if (img) {
        img.removeAttribute('src');
        img.alt = '';
    }
}

function copyPreviewUrl() {
    var input = document.getElementById('previewUrl');
    if (!input) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(input.value);
        return;
    }
    input.select();
    document.execCommand('copy');
}

function showPreviews(files) {
    var list = document.getElementById('previewList');
    if (!list) return;
    list.innerHTML = '';

    Array.prototype.forEach.call(files, function(file) {
        var card = document.createElement('div');
        card.className = 'media-preview-card';

        if (file.type && file.type.indexOf('image/') === 0) {
            var img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            card.appendChild(img);
        } else {
            card.innerHTML = '<div class="media-preview-empty ui-empty"><i class="bi bi-file-earmark"></i></div>';
        }

        var label = document.createElement('div');
        label.className = 'media-preview-label';
        label.textContent = file.name;
        card.appendChild(label);
        list.appendChild(card);
    });
}

function initMediaManagerPage() {
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeMediaPreview();
        }
    });

    document.getElementById('mediaUploadToggle')?.addEventListener('click', toggleUploadPanel);
    document.getElementById('mediaDropzoneTrigger')?.addEventListener('click', function() {
        document.getElementById('fileInput')?.click();
    });
    document.querySelectorAll('[data-media-preview-card]').forEach(function(card) {
        card.addEventListener('click', function() {
            openMediaPreview(card);
        });
    });
    document.getElementById('mediaPreviewModal')?.addEventListener('click', function(event) {
        if (event.target === event.currentTarget) {
            closeMediaPreview();
        }
    });
    document.getElementById('mediaPreviewClose')?.addEventListener('click', closeMediaPreview);
    document.getElementById('previewCopyButton')?.addEventListener('click', copyPreviewUrl);

    var dropZone = document.getElementById('dropZone');
    var fileInput = document.getElementById('fileInput');

    if (dropZone && fileInput) {
        ['dragenter', 'dragover'].forEach(function(eventName) {
            dropZone.addEventListener(eventName, function(e) {
                e.preventDefault();
                dropZone.classList.add('is-dragover');
            });
        });

        ['dragleave', 'drop'].forEach(function(eventName) {
            dropZone.addEventListener(eventName, function(e) {
                e.preventDefault();
                dropZone.classList.remove('is-dragover');
            });
        });

        dropZone.addEventListener('drop', function(e) {
            fileInput.files = e.dataTransfer.files;
            showPreviews(e.dataTransfer.files);
        });

        fileInput.addEventListener('change', function() {
            showPreviews(this.files);
        });
    }

    var uploadForm = document.querySelector('#uploadPanel form');
    if (!uploadForm) {
        return;
    }

    uploadForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        var files = document.getElementById('fileInput').files;
        if (files.length === 0) {
            mediaManagerToast('Lütfen en az bir dosya seçin.', 'warning');
            return;
        }

        var formData = new FormData(this);
        var xhr = new XMLHttpRequest();
        var progressContainer = document.getElementById('uploadProgressContainer');
        var progressBar = document.getElementById('uploadProgressBar');
        var progressText = document.getElementById('uploadProgressText');
        var submitBtn = document.getElementById('uploadSubmitBtn');

        var uploadButtonState = window.adminAsync ? window.adminAsync.setButtonLoading(submitBtn, {
            loadingHtml: '<i class="bi bi-hourglass-split"></i> Yukleniyor...'
        }) : null;
        progressContainer.classList.remove('mm-hidden');
        if (window.adminAsync) {
            window.adminAsync.setProgress(progressBar, progressText, 0);
        } else {
            progressBar.style.width = '0%';
            progressText.textContent = '0%';
        }

        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                var percent = Math.round((e.loaded / e.total) * 100);
                if (window.adminAsync) {
                    window.adminAsync.setProgress(progressBar, progressText, percent);
                } else {
                    progressBar.style.width = percent + '%';
                    progressText.textContent = percent + '%';
                }
            }
        });

        xhr.addEventListener('load', function() {
            if (window.adminAsync) window.adminAsync.restoreButton(uploadButtonState);
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        progressText.textContent = 'Tamamlandı!';
                        progressBar.style.background = 'var(--alert-success-text, #10b981)'; // success color
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        progressText.textContent = 'Hata!';
                        progressBar.style.background = 'var(--alert-error-text, #ef4444)'; // error color
                        mediaManagerToast(data.message || 'Yükleme sırasında hata oluştu.', 'error');
                    }
                } catch(err) {
                    mediaManagerToast('Sunucu yanıtı okunamadı.', 'error');
                }
            } else {
                mediaManagerToast('Yükleme başarısız. HTTP ' + xhr.status, 'error');
            }
        });

        xhr.addEventListener('error', function() {
            if (window.adminAsync) window.adminAsync.restoreButton(uploadButtonState);
            progressText.textContent = 'Bağlantı hatası!';
            progressBar.style.background = 'var(--alert-error-text, #ef4444)';
            mediaManagerToast('Ağ hatası.', 'error');
        });

        xhr.open('POST', this.action, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.send(formData);
    });
}

window.toggleUploadPanel = toggleUploadPanel;
window.openMediaPreview = openMediaPreview;
window.closeMediaPreview = closeMediaPreview;
window.copyPreviewUrl = copyPreviewUrl;

window.adminPage.register('media-manager', initMediaManagerPage, {
    id: 'media-manager-page',
    selector: '#uploadPanel, [data-media-preview-card], #mediaPreviewModal'
});
