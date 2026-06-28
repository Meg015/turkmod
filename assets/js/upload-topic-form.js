function addDlRow(name, url) {
    const row = document.createElement('div');
    row.className = 'dl-row';
    row.innerHTML = '<input type="text" name="dl_name[]" class="ui-admin-form-control w-25" placeholder="Kaynak (Örn: Drive)" value="' + (name || '') + '">'
        + '<input type="url" name="dl_url[]" class="ui-admin-form-control flex-grow-1" placeholder="https://..." value="' + (url || '') + '">'
        + '<button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" data-ui-remove-closest=".dl-row" title="Kaldır"><i class="bi bi-trash3"></i></button>';
    document.getElementById('dlRows').appendChild(row);
    bindUploadRuleInputs(row);
}
if (window.TMUI && typeof window.TMUI.registerAction === 'function') {
    window.TMUI.registerAction('addDlRow', function() { addDlRow(); });
}

function setUploadLiveHint(key, message, state) {
    const hint = document.querySelector('[data-live-hint="' + key + '"]');
    if (!hint) return;
    hint.textContent = message || '';
    hint.classList.remove('is-ok', 'is-warning', 'is-error');
    if (state) hint.classList.add('is-' + state);
}

function uploadRuleForm() {
    return document.getElementById('uploadForm');
}

function uploadContentText() {
    const editor = document.querySelector('.ql-editor');
    if (editor) return editor.textContent.trim();
    const fallbackEditor = document.querySelector('.upload-rich-editor');
    if (fallbackEditor) return fallbackEditor.textContent.trim();
    const textarea = document.querySelector('textarea[name="content"]');
    return textarea ? textarea.value.replace(/<[^>]*>/g, '').trim() : '';
}

function syncDownloadLinksHidden() {
    const names = document.querySelectorAll('input[name="dl_name[]"]');
    const urls = document.querySelectorAll('input[name="dl_url[]"]');
    const lines = [];
    names.forEach(function(n, i) {
        const u = urls[i] ? urls[i].value.trim() : '';
        if (u) lines.push((n.value.trim() || 'Link') + '|' + u);
    });
    const hidden = document.getElementById('dlHidden');
    if (hidden) hidden.value = lines.join('\n');
    return lines;
}

function isAllowedVideoHost(url, allowedHosts) {
    if (!url || allowedHosts.length === 0) return true;
    try {
        const host = new URL(url).hostname.toLowerCase();
        return allowedHosts.some(function(allowedHost) {
            return host === allowedHost || host.endsWith('.' + allowedHost);
        });
    } catch (error) {
        return false;
    }
}

function validateUploadVideoUrl(showToast) {
    const form = uploadRuleForm();
    const input = document.querySelector('input[name="topic_video_url"]');
    if (!form || !input) return true;
    const value = input.value.trim();
    const allowedHosts = (form.dataset.allowedVideoHosts || '').split(',').map(function(host) {
        return host.trim().toLowerCase();
    }).filter(Boolean);

    if (form.dataset.allowVideoUrl !== '1') {
        setUploadLiveHint('video', 'Video URL alanı kapalı.', 'warning');
        return true;
    }
    if (!value) {
        setUploadLiveHint('video', allowedHosts.length ? 'İzinli sağlayıcılar: ' + allowedHosts.join(', ') : 'Video URL isteğe bağlı.', 'warning');
        return true;
    }
    if (!isAllowedVideoHost(value, allowedHosts)) {
        const message = 'Video URL izinli sağlayıcılardan biri olmalı: ' + allowedHosts.join(', ') + '.';
        setUploadLiveHint('video', message, 'error');
        if (showToast) notifyUploadImageRule(message, 'error');
        return false;
    }
    setUploadLiveHint('video', 'Video URL uygun görünüyor.', 'ok');
    return true;
}

function validateUploadDownloadLinks(showToast) {
    const form = uploadRuleForm();
    if (!form) return true;
    const lines = syncDownloadLinksHidden();
    let validCount = 0;
    document.querySelectorAll('input[name="dl_url[]"]').forEach(function(input) {
        if (!input.value.trim()) return;
        try {
            const url = new URL(input.value.trim());
            if (url.protocol === 'http:' || url.protocol === 'https:') validCount++;
        } catch (error) {}
    });

    if (form.dataset.requireDownloadLink === '1' && validCount === 0) {
        const message = 'En az bir geçerli indirme bağlantısı eklemelisiniz.';
        setUploadLiveHint('download', message, 'error');
        if (showToast) notifyUploadImageRule(message, 'error');
        return false;
    }
    if (lines.length === 0) {
        setUploadLiveHint('download', 'İndirme linki isteğe bağlı.', 'warning');
        return true;
    }
    setUploadLiveHint('download', validCount + ' geçerli indirme linki algılandı.', 'ok');
    return true;
}

function validateUploadFieldRules(showToast) {
    const form = uploadRuleForm();
    if (!form) return true;
    let ok = true;
    const title = document.querySelector('input[name="title"]');
    const author = document.querySelector('input[name="author_topic"]');
    const version = document.querySelector('input[name="topic_version"]');
    const attachment = document.querySelector('input[name="attachment"]');
    const minTitle = Number(form.dataset.minTitleLength || 0);
    const maxTitle = Number(form.dataset.maxTitleLength || 0);
    const minContent = Number(form.dataset.minContentLength || 0);

    if (title) {
        const length = title.value.trim().length;
        if (length === 0) {
            setUploadLiveHint('title', minTitle + '-' + maxTitle + ' karakter aralığında başlık yazın.', 'warning');
        } else if ((minTitle > 0 && length < minTitle) || (maxTitle > 0 && length > maxTitle)) {
            ok = false;
            setUploadLiveHint('title', 'Başlık ' + minTitle + '-' + maxTitle + ' karakter olmalı. Şu an: ' + length + '.', 'error');
        } else {
            setUploadLiveHint('title', 'Başlık uzunluğu uygun: ' + length + ' karakter.', 'ok');
        }
    }

    const contentLength = uploadContentText().length;
    if (contentLength === 0) {
        setUploadLiveHint('content', 'Açıklama en az ' + minContent + ' karakter olmalı.', 'warning');
    } else if (minContent > 0 && contentLength < minContent) {
        ok = false;
        setUploadLiveHint('content', 'Açıklama en az ' + minContent + ' karakter olmalı. Şu an: ' + contentLength + '.', 'error');
    } else {
        setUploadLiveHint('content', 'Açıklama uzunluğu uygun: ' + contentLength + ' karakter.', 'ok');
    }

    if (author) {
        const missing = form.dataset.requireAuthor === '1' && !author.value.trim();
        setUploadLiveHint('author', missing ? 'Yapımcı alanı zorunlu.' : (author.value.trim() ? 'Yapımcı bilgisi girildi.' : 'Yapımcı isteğe bağlı.'), missing ? 'error' : 'ok');
        ok = ok && !missing;
    }
    if (version) {
        const missing = form.dataset.requireVersion === '1' && !version.value.trim();
        setUploadLiveHint('version', missing ? 'Oyun sürümü zorunlu.' : (version.value.trim() ? 'Sürüm bilgisi girildi.' : 'Sürüm isteğe bağlı.'), missing ? 'error' : 'ok');
        ok = ok && !missing;
    }
    if (attachment && attachment.files.length > 0) {
        const maxMb = Number(form.dataset.attachmentMaxSizeMb || 0);
        const tooLarge = maxMb > 0 && attachment.files[0].size > maxMb * 1024 * 1024;
        setUploadLiveHint('attachment', tooLarge ? 'Mod dosyası en fazla ' + maxMb + ' MB olabilir.' : 'Mod dosyası boyutu uygun.', tooLarge ? 'error' : 'ok');
        ok = ok && !tooLarge;
    }

    ok = validateUploadVideoUrl(showToast) && ok;
    ok = validateUploadDownloadLinks(showToast) && ok;
    if (!ok && showToast) notifyUploadImageRule('Lütfen kural uyarılarını düzeltin.', 'error');
    return ok;
}

function bindUploadRuleInputs(scope) {
    (scope || document).querySelectorAll('input[name="title"], textarea[name="content"], input[name="author_topic"], input[name="topic_version"], input[name="topic_video_url"], input[name="attachment"], input[name="dl_name[]"], input[name="dl_url[]"]').forEach(function(input) {
        if (input.dataset.liveRuleBound === '1') return;
        input.dataset.liveRuleBound = '1';
        input.addEventListener('input', function() {
            validateUploadFieldRules(false);
            scheduleUploadTopicDraftSave();
        });
        input.addEventListener('change', function() {
            validateUploadFieldRules(false);
            scheduleUploadTopicDraftSave();
        });
    });
}

const uploadTopicDraftKey = 'mod2.uploadTopicDraft.v1';
let uploadTopicDraftTimer = null;
let uploadTopicDraftRestoring = false;

function collectUploadTopicDraft() {
    return {
        title: document.querySelector('input[name="title"]')?.value || '',
        content: document.querySelector('textarea[name="content"]')?.value || '',
        author: document.querySelector('input[name="author_topic"]')?.value || '',
        version: document.querySelector('input[name="topic_version"]')?.value || '',
        videoUrl: document.querySelector('input[name="topic_video_url"]')?.value || '',
        links: Array.from(document.querySelectorAll('.dl-row')).map(function(row) {
            return {
                name: row.querySelector('input[name="dl_name[]"]')?.value || '',
                url: row.querySelector('input[name="dl_url[]"]')?.value || ''
            };
        }),
        savedAt: Date.now()
    };
}

function saveUploadTopicDraft() {
    if (uploadTopicDraftRestoring) return;
    try {
        localStorage.setItem(uploadTopicDraftKey, JSON.stringify(collectUploadTopicDraft()));
    } catch (error) {}
}

function scheduleUploadTopicDraftSave() {
    window.clearTimeout(uploadTopicDraftTimer);
    uploadTopicDraftTimer = window.setTimeout(saveUploadTopicDraft, 250);
}

function restoreUploadTopicDraft() {
    let draft = null;
    try {
        draft = JSON.parse(localStorage.getItem(uploadTopicDraftKey) || 'null');
    } catch (error) {
        draft = null;
    }
    if (!draft || typeof draft !== 'object') return;

    uploadTopicDraftRestoring = true;
    const title = document.querySelector('input[name="title"]');
    const content = document.querySelector('textarea[name="content"]');
    const author = document.querySelector('input[name="author_topic"]');
    const version = document.querySelector('input[name="topic_version"]');
    const video = document.querySelector('input[name="topic_video_url"]');

    if (title && !title.value && draft.title) title.value = draft.title;
    if (content && !content.value && draft.content) content.value = draft.content;
    if (author && !author.value && draft.author) author.value = draft.author;
    if (version && !version.value && draft.version) version.value = draft.version;
    if (video && !video.value && draft.videoUrl) video.value = draft.videoUrl;

    if (Array.isArray(draft.links) && draft.links.length > 0) {
        const rows = Array.from(document.querySelectorAll('.dl-row'));
        rows.slice(1).forEach(function(row) { row.remove(); });
        const firstRow = document.querySelector('.dl-row');
        draft.links.forEach(function(link, index) {
            if (index === 0 && firstRow) {
                const name = firstRow.querySelector('input[name="dl_name[]"]');
                const url = firstRow.querySelector('input[name="dl_url[]"]');
                if (name && !name.value) name.value = link.name || '';
                if (url && !url.value) url.value = link.url || '';
                return;
            }
            addDlRow(link.name || '', link.url || '');
        });
    }

    syncDownloadLinksHidden();
    uploadTopicDraftRestoring = false;
}

function clearUploadTopicDraft() {
    try {
        localStorage.removeItem(uploadTopicDraftKey);
    } catch (error) {}
}

function syncInputFiles(input, files) {
    const dt = new DataTransfer();
    files.forEach(function(file) {
        dt.items.add(file);
    });
    input.files = dt.files;
}

function notifyUploadImageRule(message, type) {
    if (window.showToast) {
        window.showToast(message, type || 'warning');
        return;
    }
    console.warn(message);
}

function getUploadImageRules() {
    const form = document.getElementById('uploadForm');
    const allowed = (form?.dataset.allowedImageExt || 'jpg,jpeg,png,webp')
        .split(',')
        .map(function(ext) { return ext.trim().toLowerCase(); })
        .filter(Boolean);

    return {
        allowedExt: allowed,
        minWidth: Number(form?.dataset.imageMinWidth || 0),
        minHeight: Number(form?.dataset.imageMinHeight || 0),
        maxWidth: Number(form?.dataset.imageMaxWidth || 0),
        maxHeight: Number(form?.dataset.imageMaxHeight || 0)
    };
}

function formatUploadDimensionRules(rules) {
    const parts = [];
    if (rules.minWidth > 0 || rules.maxWidth > 0) {
        parts.push('genişlik ' + (rules.minWidth > 0 ? 'min ' + rules.minWidth + ' px' : 'min yok') + ' / ' + (rules.maxWidth > 0 ? 'max ' + rules.maxWidth + ' px' : 'max yok'));
    }
    if (rules.minHeight > 0 || rules.maxHeight > 0) {
        parts.push('yükseklik ' + (rules.minHeight > 0 ? 'min ' + rules.minHeight + ' px' : 'min yok') + ' / ' + (rules.maxHeight > 0 ? 'max ' + rules.maxHeight + ' px' : 'max yok'));
    }
    return parts.length ? parts.join(', ') : 'piksel sınırı yok';
}

function readUploadFileAsDataUrl(file) {
    return new Promise(function(resolve, reject) {
        const reader = new FileReader();
        reader.addEventListener('load', function(event) {
            resolve(event.target.result);
        });
        reader.addEventListener('error', function() {
            reject(new Error('Görsel dosyası okunamadı.'));
        });
        reader.readAsDataURL(file);
    });
}

function loadUploadImageDimensions(file) {
    return new Promise(function(resolve, reject) {
        const img = new Image();
        img.addEventListener('load', function() {
            resolve({ width: img.naturalWidth || img.width, height: img.naturalHeight || img.height });
        });
        img.addEventListener('error', function() {
            reject(new Error('Görsel okunamadı.'));
        });
        readUploadFileAsDataUrl(file).then(function(dataUrl) {
            img.src = dataUrl;
        }).catch(reject);
    });
}

async function validateUploadImageFile(file, config) {
    const rules = getUploadImageRules();
    const ext = (file.name.split('.').pop() || '').toLowerCase();
    const label = config.label || 'Görsel';
    const maxSizeMb = Number(config.maxSizeMb || 0);

    if (!ext || !rules.allowedExt.includes(ext)) {
        return { ok: false, message: label + ' "' + file.name + '" için izinli uzantılar: ' + rules.allowedExt.join(', ') + '.' };
    }
    if (maxSizeMb > 0 && file.size > maxSizeMb * 1024 * 1024) {
        return { ok: false, message: label + ' "' + file.name + '" en fazla ' + maxSizeMb + ' MB olabilir.' };
    }
    if (file.type && !file.type.startsWith('image/')) {
        return { ok: false, message: label + ' "' + file.name + '" geçerli bir görsel değil.' };
    }

    try {
        const dimensions = await loadUploadImageDimensions(file);
        if (rules.minWidth > 0 && dimensions.width < rules.minWidth) {
            return { ok: false, message: label + ' "' + file.name + '" genişliği minimum ' + rules.minWidth + ' px olmalıdır. Seçilen ölçü: ' + dimensions.width + 'x' + dimensions.height + ' px.' };
        }
        if (rules.minHeight > 0 && dimensions.height < rules.minHeight) {
            return { ok: false, message: label + ' "' + file.name + '" yüksekliği minimum ' + rules.minHeight + ' px olmalıdır. Seçilen ölçü: ' + dimensions.width + 'x' + dimensions.height + ' px.' };
        }
        if (rules.maxWidth > 0 && dimensions.width > rules.maxWidth) {
            return { ok: false, message: label + ' "' + file.name + '" genişliği maksimum ' + rules.maxWidth + ' px olmalıdır. Seçilen ölçü: ' + dimensions.width + 'x' + dimensions.height + ' px.' };
        }
        if (rules.maxHeight > 0 && dimensions.height > rules.maxHeight) {
            return { ok: false, message: label + ' "' + file.name + '" yüksekliği maksimum ' + rules.maxHeight + ' px olmalıdır. Seçilen ölçü: ' + dimensions.width + 'x' + dimensions.height + ' px.' };
        }
    } catch (error) {
        return { ok: false, message: label + ' "' + file.name + '" ölçüleri okunamadı. Aktif piksel kuralı: ' + formatUploadDimensionRules(rules) + '.' };
    }

    return { ok: true };
}

async function filterUploadImageSelection(input, files, config) {
    const limitedFiles = Array.from(files || []).slice(0, config.maxFiles);
    const validFiles = [];

    if ((files?.length || 0) > config.maxFiles) {
        notifyUploadImageRule('En fazla ' + config.maxFiles + ' adet görsel seçebilirsiniz. Fazla dosyalar eklenmedi.', 'warning');
    }

    for (const file of limitedFiles) {
        const result = await validateUploadImageFile(file, config);
        if (result.ok) {
            validFiles.push(file);
        } else {
            notifyUploadImageRule(result.message, 'error');
        }
    }

    syncInputFiles(input, validFiles);
    return validFiles.length === limitedFiles.length && limitedFiles.length > 0;
}

async function validateImageSelection(input, config) {
    const files = Array.from(input?.files || []);
    if (!input || files.length === 0) return true;
    const validFiles = await filterUploadImageSelection(input, files, config);
    renderPublicPreviews(input, config.previewId, config.maxFiles);
    return validFiles;
}

function removePublicPreviewFile(input, previewId, maxFiles, index) {
    const files = Array.from(input.files || []);
    files.splice(index, 1);
    syncInputFiles(input, files);
    renderPublicPreviews(input, previewId, maxFiles);
}

function reorderPublicPreviewFile(input, previewId, maxFiles, fromIndex, toIndex) {
    if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0) return;
    const files = Array.from(input.files || []);
    if (!files[fromIndex] || !files[toIndex]) return;
    const moved = files.splice(fromIndex, 1)[0];
    files.splice(toIndex, 0, moved);
    syncInputFiles(input, files);
    renderPublicPreviews(input, previewId, maxFiles);
    notifyUploadImageRule('Galeri sırası güncellendi.', 'success');
    scheduleUploadTopicDraftSave();
}

function renderPublicPreviews(input, previewId, maxFiles) {
    const preview = document.getElementById(previewId);
    if (!preview) return;
    preview.innerHTML = '';

    const files = Array.from(input.files || []).slice(0, maxFiles);
    files.forEach(function(file, index) {
        const item = document.createElement('div');
        item.className = 'public-preview-item';
        item.dataset.index = String(index);
        const sortable = previewId === 'publicGalleryPreview' && files.length > 1;
        if (sortable) {
            item.classList.add('is-sortable');
            item.draggable = true;
            item.tabIndex = 0;
            item.title = 'Sıralamak için sürükleyin veya ok tuşlarını kullanın';
            item.setAttribute('aria-label', (index + 1) + '. galeri görseli. Sıralamak için sürükleyin veya ok tuşlarını kullanın.');
            item.addEventListener('dragstart', function(event) {
                item.classList.add('is-dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(index));
            });
            item.addEventListener('dragend', function() {
                item.classList.remove('is-dragging');
            });
            item.addEventListener('dragover', function(event) {
                event.preventDefault();
                item.classList.add('is-drop-target');
                event.dataTransfer.dropEffect = 'move';
            });
            item.addEventListener('dragleave', function() {
                item.classList.remove('is-drop-target');
            });
            item.addEventListener('drop', function(event) {
                event.preventDefault();
                item.classList.remove('is-drop-target');
                const fromIndex = Number(event.dataTransfer.getData('text/plain'));
                reorderPublicPreviewFile(input, previewId, maxFiles, fromIndex, index);
            });
            item.addEventListener('keydown', function(event) {
                if (!['ArrowLeft', 'ArrowUp', 'ArrowRight', 'ArrowDown'].includes(event.key)) {
                    return;
                }
                event.preventDefault();
                const direction = event.key === 'ArrowLeft' || event.key === 'ArrowUp' ? -1 : 1;
                reorderPublicPreviewFile(input, previewId, maxFiles, index, index + direction);
            });
        }

        if (file.type.startsWith('image/')) {
            const img = document.createElement('img');
            img.alt = file.name;
            img.width = 128;
            img.height = 96;
            img.decoding = 'async';
            item.appendChild(img);

            const reader = new FileReader();
            reader.addEventListener('load', function(event) {
                img.src = event.target.result;
            });
            reader.addEventListener('error', function() {
                item.classList.add('is-preview-error');
                img.remove();
                item.insertAdjacentHTML('afterbegin', '<div class="public-preview-fallback"><i class="bi bi-image"></i><span>Önizleme yok</span></div>');
            });
            reader.readAsDataURL(file);
        } else {
            item.innerHTML = '<div class="public-preview-fallback"><i class="bi bi-file-earmark"></i><span>Dosya</span></div>';
        }

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'public-preview-remove-bar';
        removeBtn.innerHTML = '<i class="bi bi-trash3"></i> Kaldır';
        removeBtn.title = 'Resmi Kaldır';
        removeBtn.addEventListener('click', function() {
            removePublicPreviewFile(input, previewId, maxFiles, index);
        });
        item.appendChild(removeBtn);

        const label = document.createElement('div');
        label.className = 'public-preview-name';
        label.textContent = (previewId === 'publicGalleryPreview' ? (index + 1) + '. ' : '') + file.name;
        item.appendChild(label);
        if (previewId === 'publicGalleryPreview') {
            const order = document.createElement('span');
            order.className = 'public-preview-order';
            order.textContent = String(index + 1);
            item.appendChild(order);
        }
        preview.appendChild(item);
    });
}

document.querySelectorAll('[data-open-input]').forEach(function(trigger) {
    trigger.addEventListener('click', function() {
        const input = document.getElementById(this.getAttribute('data-open-input'));
        if (input) input.click();
    });
});

[
    {
        inputId: 'publicCoverInput',
        previewId: 'publicCoverPreview',
        maxFiles: 1,
        maxSizeMb: Number(document.getElementById('uploadForm')?.dataset.coverMaxSizeMb || 10),
        label: 'Kapak görseli'
    },
    {
        inputId: 'publicGalleryInput',
        previewId: 'publicGalleryPreview',
        maxFiles: Number(document.getElementById('uploadForm')?.dataset.maxImages || 10),
        maxSizeMb: Number(document.getElementById('uploadForm')?.dataset.galleryMaxSizeMb || 10),
        label: 'Galeri görseli'
    }
].forEach(function(config) {
    const input = document.getElementById(config.inputId);
    const zone = input ? input.closest('.public-dropzone') : null;
    if (!input || !zone) return;

    input.addEventListener('change', async function() {
        await filterUploadImageSelection(input, Array.from(input.files || []), config);
        renderPublicPreviews(input, config.previewId, config.maxFiles);
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

    zone.addEventListener('drop', async function(e) {
        const droppedFiles = Array.from(e.dataTransfer.files || []);
        await filterUploadImageSelection(input, droppedFiles, config);
        renderPublicPreviews(input, config.previewId, config.maxFiles);
    });
});

bindUploadRuleInputs(document);
restoreUploadTopicDraft();
document.getElementById('dlRows')?.addEventListener('click', function() {
    setTimeout(function() {
        validateUploadFieldRules(false);
        scheduleUploadTopicDraftSave();
    }, 0);
});
validateUploadFieldRules(false);

const uploadWizard = (function() {
    const form = document.getElementById('uploadForm');
    if (form && form.dataset.wizardEnabled !== '1') {
        document.querySelectorAll('.upload-wizard-panel').forEach(function(panel) {
            panel.hidden = false;
            panel.classList.add('is-active');
        });
        return { validateStep: function() { return true; }, setStep: function() {} };
    }
    const panels = Array.from(document.querySelectorAll('.upload-wizard-panel'));
    const steps = Array.from(document.querySelectorAll('.upload-wizard-step'));
    const prevBtn = document.querySelector('[data-wizard-prev]');
    const nextBtn = document.querySelector('[data-wizard-next]');
    const status = document.getElementById('uploadWizardStatus');
    const titles = {
        1: 'Temel bilgiler',
        2: 'Kapak görseli',
        3: 'Açıklama',
        4: 'Galeri ve video',
        5: 'Yapımcı ve oyun sürümü',
        6: 'İndirme kaynakları',
        7: 'Kontrol ve onay'
    };
    let current = 1;

    function notify(message) {
        if (window.showToast) {
            window.showToast(message, 'warning');
            return;
        }
        console.warn(message);
    }

    function getContentValue() {
        const editor = document.querySelector('.ql-editor');
        if (editor) return editor.textContent.trim();
        const textarea = document.querySelector('textarea[name="content"]');
        return textarea ? textarea.value.trim() : '';
    }

    async function validateStep(step) {
        if (step === 1) {
            const title = document.querySelector('input[name="title"]');
            const category = document.querySelector('select[name="category_id"]');
            if (!title || !title.value.trim()) {
                notify('Mod başlığı zorunludur.');
                title && title.focus();
                return false;
            }
            if (title && !title.checkValidity()) {
                validateUploadFieldRules(true);
                title.reportValidity();
                return false;
            }
            if (!category || !category.value) {
                notify('Kategori seçimi zorunludur.');
                category && category.focus();
                return false;
            }
        }

        if (step === 2) {
            const cover = document.getElementById('publicCoverInput');
            if (cover && cover.required && cover.files.length === 0) {
                notify('Kapak görseli yüklemelisiniz.');
                return false;
            }
            if (cover && !(await validateImageSelection(cover, {
                previewId: 'publicCoverPreview',
                maxFiles: 1,
                maxSizeMb: Number(document.getElementById('uploadForm')?.dataset.coverMaxSizeMb || 10),
                label: 'Kapak görseli'
            }))) {
                return false;
            }
        }

        if (step === 3) {
            const textarea = document.querySelector('textarea[name="content"]');
            const minLength = Number(textarea?.dataset.minLength || 0);
            const contentText = getContentValue();
            if (!contentText) {
                notify('Mod açıklaması zorunludur.');
                return false;
            }
            if (minLength > 0 && contentText.length < minLength) {
                notify('Mod açıklaması en az ' + minLength + ' karakter olmalıdır.');
                return false;
            }
        }

        if (step === 4) {
            const gallery = document.getElementById('publicGalleryInput');
            if (gallery && gallery.required && gallery.files.length === 0) {
                notify('Galeri için en az 1 görsel yüklemelisiniz.');
                return false;
            }
            const maxImages = Number(document.getElementById('uploadForm')?.dataset.maxImages || 10);
            if (gallery && gallery.files.length > maxImages) {
                notify('En fazla ' + maxImages + ' adet galeri görseli yükleyebilirsiniz.');
                return false;
            }
            if (gallery && !(await validateImageSelection(gallery, {
                previewId: 'publicGalleryPreview',
                maxFiles: maxImages,
                maxSizeMb: Number(document.getElementById('uploadForm')?.dataset.galleryMaxSizeMb || 10),
                label: 'Galeri görseli'
            }))) {
                return false;
            }
        }

        if (step === 5) {
            return validateUploadFieldRules(true);
        }

        if (step === 6) {
            return validateUploadFieldRules(true);
        }

        return true;
    }

    function setStep(step) {
        current = Math.max(1, Math.min(7, step));
        panels.forEach(function(panel) {
            const isActive = Number(panel.dataset.step) === current;
            panel.hidden = !isActive;
            panel.classList.toggle('is-active', isActive);
        });
        steps.forEach(function(button) {
            const target = Number(button.dataset.stepTarget);
            button.classList.toggle('is-active', target === current);
            button.classList.toggle('is-complete', target < current);
        });
        if (prevBtn) prevBtn.disabled = current === 1;
        if (nextBtn) nextBtn.hidden = current === 7;
        if (status) status.textContent = current + ' / 7 - ' + titles[current];
        if (window.ensureUploadQuillEditors) window.ensureUploadQuillEditors();
    }

    steps.forEach(function(button) {
        button.addEventListener('click', async function() {
            const target = Number(button.dataset.stepTarget);
            const allowSkip = document.getElementById('uploadForm')?.dataset.allowStepSkip === '1';
            if (allowSkip || target <= current || await validateStep(current)) setStep(target);
        });
    });

    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            setStep(current - 1);
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', async function() {
            if (await validateStep(current)) setStep(current + 1);
        });
    }

    setStep(1);
    return { validateStep: validateStep, setStep: setStep };
})();

document.getElementById('uploadForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const form = this;
    if (form.dataset.submitting === '1' || form.dataset.submitted === '1') {
        window.showToast?.('Bu konu zaten gönderiliyor veya gönderildi.', 'warning', {
            solution: 'Yükleme bittikten sonra Profil > Konularım ekranından durumunu kontrol edebilirsiniz.',
            actionLabel: 'Konularım',
            actionUrl: document.getElementById('uploadForm')?.getAttribute('data-profile-topics-url') || 'profile.php?tab=topics'
        });
        return;
    }

    if (window.ensureUploadQuillEditors) window.ensureUploadQuillEditors();
    document.querySelectorAll('textarea.rich-editor').forEach(function(textarea) {
        if (textarea.quillInstance) {
            textarea.value = textarea.quillInstance.root.innerHTML;
            return;
        }
        const fallbackEditor = textarea.parentNode ? textarea.parentNode.querySelector('.upload-rich-editor') : null;
        if (fallbackEditor) {
            textarea.value = fallbackEditor.innerHTML.trim();
        }
    });

    const names = document.querySelectorAll('input[name="dl_name[]"]');
    const urls = document.querySelectorAll('input[name="dl_url[]"]');
    const lines = [];
    names.forEach(function(n, i) {
        const u = urls[i] ? urls[i].value.trim() : '';
        if (u) lines.push((n.value.trim() || 'Link') + '|' + u);
    });
    document.getElementById('dlHidden').value = lines.join('\n');
    syncDownloadLinksHidden();
    if (!validateUploadFieldRules(true)) {
        return;
    }

    const filesInput = document.getElementById('publicGalleryInput');
    const coverInput = document.getElementById('publicCoverInput');
    const maxImages = Number(form.dataset.maxImages || 10);
    if (filesInput && filesInput.files.length > maxImages) {
        window.showToast?.('En fazla ' + maxImages + ' adet resim yükleyebilirsiniz.', 'error', {
            solution: 'Fazla görselleri kaldırıp tekrar gönderin.'
        });
        return;
    }

    if (coverInput && !(await validateImageSelection(coverInput, {
        previewId: 'publicCoverPreview',
        maxFiles: 1,
        maxSizeMb: Number(form.dataset.coverMaxSizeMb || 10),
        label: 'Kapak görseli'
    }))) {
        return;
    }
    if (filesInput && !(await validateImageSelection(filesInput, {
        previewId: 'publicGalleryPreview',
        maxFiles: maxImages,
        maxSizeMb: Number(form.dataset.galleryMaxSizeMb || 10),
        label: 'Galeri görseli'
    }))) {
        return;
    }

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const submitButton = form.querySelector('.upload-final-actions button[type="submit"]');
    const originalButtonHtml = submitButton ? submitButton.innerHTML : '';
    form.dataset.submitting = '1';
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.classList.add('is-submitting');
        submitButton.innerHTML = '<i class="bi bi-hourglass-split"></i> Gönderiliyor...';
    }

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        const payload = await response.json().catch(function() {
            return { success: false, message: 'Sunucudan geçerli cevap alınamadı.' };
        });

        if (!response.ok || !payload.success) {
            if (response.status === 409 && submitButton) {
                form.dataset.submitted = '1';
                submitButton.classList.remove('is-submitting');
                submitButton.classList.add('is-submit-locked');
                submitButton.innerHTML = '<i class="bi bi-lock"></i> Gönderim Kilitlendi';
            }
            throw new Error(payload.message || 'Mod gönderilemedi.');
        }

        if (form.dataset.lockAfterSubmit === '1') {
            form.dataset.submitted = '1';
        }
        clearUploadTopicDraft();
        window.showToast?.(payload.message || 'Mod kaydedildi.', 'success', {
            actionLabel: 'Konularım',
            actionUrl: document.getElementById('uploadForm')?.getAttribute('data-profile-draft-url') || 'profile.php?tab=topics&topic_status=draft'
        });
        if (submitButton) {
            submitButton.classList.remove('is-submitting');
            if (form.dataset.lockAfterSubmit === '1') {
                submitButton.classList.add('is-submitted');
            } else {
                submitButton.disabled = false;
            }
            submitButton.innerHTML = '<i class="bi bi-check2-circle"></i> Gönderildi';
        }
        if (payload.redirect) {
            window.setTimeout(function() {
                window.location.href = payload.redirect;
            }, 900);
        }
    } catch (error) {
        delete form.dataset.submitting;
        window.showToast?.(error.message || 'Mod gönderilemedi.', 'error', {
            solution: 'Zorunlu alanları, görsel limitlerini ve indirme linkini kontrol edip tekrar deneyin.'
        });
        if (submitButton && form.dataset.submitted !== '1') {
            submitButton.disabled = false;
            submitButton.classList.remove('is-submitting');
            submitButton.innerHTML = originalButtonHtml;
        }
    }
});
