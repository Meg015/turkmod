(function() {
    const form = document.getElementById('uploadForm');
    if (!form || form.dataset.uploadTopicStandalone !== '1' || form.dataset.turkmodUploadInit === '1') return;
    form.dataset.turkmodUploadInit = '1';
    form.noValidate = true;

    function notifyEditForm(message) {
        window.showToast(message, 'warning', { dedupeKey: 'upload-rule:warning:' + message });
    }

    const panels = Array.from(form.querySelectorAll('.upload-wizard-panel'));
    const steps = Array.from(form.querySelectorAll('.upload-wizard-step'));
    const prevBtn = form.querySelector('[data-wizard-prev]');
    const nextBtn = form.querySelector('[data-wizard-next]');
    const status = document.getElementById('uploadWizardStatus');
    const titles = {
        1: 'Temel Bilgiler',
        2: 'Kapak Görseli',
        3: 'Açıklama',
        4: 'Galeri ve Video',
        5: 'Yapımcı / Sürüm',
        6: 'İndirme Kaynakları',
        7: 'Kontrol ve Onay'
    };
    let current = 1;
    let navigating = false;
    let navigationRevision = 0;

    function setFieldInvalid(field) {
        if (!field) return;
        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');
    }

    function clearFieldInvalid(field) {
        if (!field) return;
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
    }

    function focusField(field) {
        if (!field) return;
        setFieldInvalid(field);
        const target = field.quillInstance ? field.quillInstance.root
            : field.type === 'file' ? form.querySelector('[data-open-input="' + field.id + '"]') : field;
        if (!target) return;
        if (typeof target.focus === 'function') target.focus();
        if (typeof target.scrollIntoView === 'function') {
            try {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } catch (error) {
                target.scrollIntoView();
            }
        }
    }

    function invalidFieldMessage(field) {
        if (!field) return 'Lütfen zorunlu alanları kontrol edin.';
        switch (field.name) {
            case 'title':
                return 'Mod başlığı zorunludur.';
            case 'category_id':
                return 'Kategori seçimi zorunludur.';
            case 'content':
                return 'Mod açıklaması zorunludur.';
            case 'author_topic':
                return 'Yapımcı alanı zorunludur.';
            case 'topic_version':
                return 'Oyun sürümü zorunludur.';
            case 'dl_url[]':
                return 'İndirme bağlantısı alanını kontrol edin.';
            default:
                return 'Lütfen zorunlu alanları kontrol edin.';
        }
    }

    function setStep(step) {
        navigationRevision++;
        current = Math.max(1, Math.min(7, step));
        panels.forEach(function(panel) {
            const active = form.dataset.wizardEnabled === '0' || Number(panel.dataset.step) === current;
            panel.hidden = !active;
            panel.classList.toggle('is-active', active);
        });
        steps.forEach(function(button) {
            const target = Number(button.dataset.stepTarget);
            button.classList.toggle('is-active', target === current);
            button.classList.toggle('is-complete', target < current);
            button.setAttribute('aria-current', target === current ? 'step' : 'false');
        });
        if (prevBtn) prevBtn.disabled = current === 1;
        if (nextBtn) nextBtn.hidden = current === 7;
        if (status) status.textContent = current + ' / 7 - ' + titles[current];
        if (window.ensureUploadQuillEditors) window.ensureUploadQuillEditors();
    }

    function fail(message, field) {
        setFieldInvalid(field);
        notifyEditForm(message);
        return false;
    }

    function contentText() {
        const textarea = form.querySelector('[name="content"]');
        if (textarea?.quillInstance) return textarea.quillInstance.root.textContent.trim();
        const template = document.createElement('template');
        template.innerHTML = textarea?.value || '';
        return template.content.textContent.trim();
    }

    function validateDownloadLinks(showToast) {
        const inputs = Array.from(form.querySelectorAll('[name="dl_url[]"]'));
        let validCount = 0;
        let invalid = null;
        inputs.forEach(input => {
            input.required = false;
            if (!input.value.trim()) return;
            try {
                const url = new URL(input.value.trim());
                if (url.protocol === 'http:' || url.protocol === 'https:') validCount++;
                else invalid = input;
            } catch (error) { invalid = input; }
        });
        const missing = form.dataset.requireDownloadLink === '1' && validCount === 0;
        const message = missing ? 'En az bir geçerli indirme bağlantısı eklemelisiniz.'
            : invalid ? 'İndirme bağlantıları http:// veya https:// ile başlayan geçerli adresler olmalı.' : '';
        const hint = form.querySelector('[data-live-hint="download"]');
        if (hint) {
            hint.textContent = message || (validCount ? validCount + ' geçerli indirme linki algılandı.' : 'İndirme linki isteğe bağlı.');
            hint.classList.toggle('is-error', Boolean(message));
        }
        if (message) return showToast ? fail(message, invalid || inputs[0]) : false;
        return true;
    }

    async function validateImageSelection(input, step) {
        if (!input) return true;
        const kept = form.querySelectorAll('[data-step="' + step + '"] [name="keep_media[]"]:checked').length;
        const files = Array.from(input.files || []);
        const cover = step === 2;
        const required = input.required || form.dataset[cover ? 'requireCover' : 'requireGallery'] === '1' && kept === 0;
        if (required && files.length === 0) return fail(cover ? 'Kapak görseli yüklemelisiniz.' : 'Galeri için en az 1 görsel yüklemelisiniz.', input);
        if (!cover && files.length + kept > Number(form.dataset.maxImages || 10)) return fail('Galeri en fazla ' + (form.dataset.maxImages || 10) + ' görsel içerebilir.', input);
        const maxMb = Number(form.dataset[cover ? 'coverMaxSizeMb' : 'galleryMaxSizeMb'] || 10);
        const allowed = (form.dataset.allowedImageExt || 'jpg,jpeg,png,webp').split(',').map(ext => ext.trim().toLowerCase());
        for (const file of files) {
            if (!allowed.includes(file.name.split('.').pop().toLowerCase()) || file.type && !file.type.startsWith('image/')) return fail('İzinli görsel uzantıları: ' + allowed.join(', ') + '.', input);
            if (file.size > maxMb * 1024 * 1024) return fail('Görsel en fazla ' + maxMb + ' MB olabilir.', input);
            let dimensions;
            try {
                dimensions = await new Promise((resolve, reject) => {
                    const url = URL.createObjectURL(file);
                    const image = new Image();
                    image.onload = () => { URL.revokeObjectURL(url); resolve({width:image.naturalWidth, height:image.naturalHeight}); };
                    image.onerror = () => { URL.revokeObjectURL(url); reject(new Error('Görsel okunamadı.')); };
                    image.src = url;
                });
            } catch (error) { return fail('Görsel dosyası okunamadı.', input); }
            for (const [key, value, minimum] of [['imageMinWidth', dimensions.width, true], ['imageMinHeight', dimensions.height, true], ['imageMaxWidth', dimensions.width, false], ['imageMaxHeight', dimensions.height, false]]) {
                const limit = Number(form.dataset[key] || 0);
                if (limit > 0 && (minimum ? value < limit : value > limit)) return fail('Görsel ölçüleri izin verilen piksel sınırlarına uymuyor.', input);
            }
        }
        return true;
    }

    async function validateStep(step) {
        const panel = panels.find(item => Number(item.dataset.step) === step);
        if (!panel) return true;
        if (step === 1) {
            const title = panel.querySelector('[name="title"]');
            const length = title?.value.trim().length || 0;
            if (!length) return fail('Mod başlığı zorunludur.', title);
            const min = Number(form.dataset.minTitleLength || 0);
            const max = Number(form.dataset.maxTitleLength || 0);
            if (length < min || max > 0 && length > max) return fail('Mod başlığı ' + min + '-' + max + ' karakter olmalı.', title);
        }
        if (step === 2 && !(await validateImageSelection(document.getElementById('publicCoverInput'), step))) return false;
        if (step === 3) {
            const textarea = panel.querySelector('[name="content"]');
            const length = contentText().length;
            if (!length) return fail('Mod açıklaması zorunludur.', textarea);
            const min = Number(textarea?.dataset.minLength || form.dataset.minContentLength || 0);
            if (length < min) return fail('Mod açıklaması en az ' + min + ' karakter olmalı.', textarea);
        }
        if (step === 4) {
            if (!(await validateImageSelection(document.getElementById('publicGalleryInput'), step))) return false;
            const video = panel.querySelector('[name="topic_video_url"]');
            if (video?.value.trim() && form.dataset.allowVideoUrl === '1') {
                const allowed = (form.dataset.allowedVideoHosts || '').split(',').map(host => host.trim().toLowerCase()).filter(Boolean);
                try {
                    const url = new URL(video.value.trim());
                    if (!['http:', 'https:'].includes(url.protocol) || allowed.length && !allowed.some(host => url.hostname === host || url.hostname.endsWith('.' + host))) throw new Error('Invalid video URL');
                } catch (error) { return fail('Video bağlantısı izin verilen sağlayıcılardan geçerli bir adres olmalı.', video); }
            }
        }
        if (step === 5) {
            for (const [name, setting] of [['author_topic', 'requireAuthor'], ['topic_version', 'requireVersion']]) {
                const field = panel.querySelector('[name="' + name + '"]');
                if (field && form.dataset[setting] === '1' && !field.value.trim()) return fail(invalidFieldMessage(field), field);
            }
        }
        if (step === 6) {
            if (!validateDownloadLinks(true)) return false;
            const attachment = panel.querySelector('[name="attachment"]');
            const maxMb = Number(form.dataset.attachmentMaxSizeMb || 0);
            if (maxMb > 0 && attachment?.files[0]?.size > maxMb * 1024 * 1024) return fail('Mod dosyası en fazla ' + maxMb + ' MB olabilir.', attachment);
        }
        for (const field of panel.querySelectorAll('input, select, textarea')) {
            if (!field.checkValidity()) return fail(invalidFieldMessage(field), field);
        }
        return true;
    }

    function revealStepError(step) {
        setStep(step);
        const panel = panels.find(item => Number(item.dataset.step) === step);
        focusField(panel?.querySelector('.is-invalid, :invalid'));
    }

    async function navigateTo(target, allowSkip) {
        if (allowSkip || target <= current) { setStep(target); return; }
        if (navigating) return;
        navigating = true;
        const revision = navigationRevision;
        try {
            for (let step = current; step < target; step++) {
                const valid = await validateStep(step);
                if (revision !== navigationRevision) return;
                if (!valid) { revealStepError(step); return; }
            }
            setStep(target);
        } finally { navigating = false; }
    }

    steps.forEach(function(button) {
        button.addEventListener('click', function() {
            navigateTo(Number(button.dataset.stepTarget), form.dataset.allowStepSkip === '1');
        });
    });
    prevBtn && prevBtn.addEventListener('click', function() { setStep(current - 1); });
    nextBtn && nextBtn.addEventListener('click', function() {
        navigateTo(current + 1, false);
    });

    document.querySelectorAll('[data-open-input]').forEach(function(trigger) {
        trigger.addEventListener('click', function() {
            document.getElementById(trigger.dataset.openInput)?.click();
        });
    });

    ['input', 'change'].forEach(function(eventName) {
        form.addEventListener(eventName, function(event) {
            clearFieldInvalid(event.target);
        });
    });

    function previewFiles(input, targetId) {
        const target = document.getElementById(targetId);
        if (!target) return;
        target.innerHTML = '';
        Array.from(input.files || []).forEach(function(file) {
            const item = document.createElement('div');
            item.className = 'public-preview-item';
            const img = document.createElement('img');
            img.alt = file.name;
            img.width = 128;
            img.height = 96;
            img.decoding = 'async';
            img.src = URL.createObjectURL(file);
            const release = () => URL.revokeObjectURL(img.src);
            img.addEventListener('load', release, {once:true});
            img.addEventListener('error', release, {once:true});
            const name = document.createElement('div');
            name.className = 'public-preview-name';
            name.textContent = file.name;
            item.appendChild(img);
            item.appendChild(name);
            target.appendChild(item);
        });
    }

    document.getElementById('publicCoverInput')?.addEventListener('change', function() {
        previewFiles(this, 'publicCoverPreview');
    });
    document.getElementById('publicGalleryInput')?.addEventListener('change', function() {
        previewFiles(this, 'publicGalleryPreview');
    });

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        if (form.dataset.validating === '1') return;
        if (form.dataset.submitting === '1' || form.dataset.submitted === '1') {
            notifyEditForm('Bu değişiklik zaten gönderiliyor veya gönderildi.');
            return;
        }

        form.dataset.validating = '1';
        const submitButton = form.querySelector('.upload-final-actions button[type="submit"]') || form.querySelector('button[type="submit"]');
        const originalButtonHtml = submitButton ? submitButton.innerHTML : '';

        try {
            if (window.ensureUploadQuillEditors) window.ensureUploadQuillEditors();
            form.querySelectorAll('textarea.rich-editor').forEach(function(textarea) {
                if (textarea.quillInstance) textarea.value = textarea.quillInstance.root.innerHTML;
            });
            for (let step = 1; step <= 6; step++) {
                if (!(await validateStep(step))) { revealStepError(step); return; }
            }
            if (!form.checkValidity()) {
                notifyEditForm(invalidFieldMessage(form.querySelector(':invalid')));
                return;
            }
            form.dataset.submitting = '1';
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.classList.add('is-submitting');
                submitButton.innerHTML = '<i class="bi bi-hourglass-split"></i> Gönderiliyor...';
            }
            const payload = await window.publicFetchJson(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'Accept': 'application/json'
                },
                notifyError: false,
                errorMessage: 'Mod güncellenemedi.'
            });

            if (!payload.success) {
                throw new Error(payload.message || 'Mod güncellenemedi.');
            }

            form.dataset.submitted = '1';
            window.showToast(payload.message || 'Değişiklikler onaya gönderildi.', 'success');
            if (submitButton) {
                submitButton.classList.remove('is-submitting');
                submitButton.classList.add('is-submitted');
                submitButton.innerHTML = '<i class="bi bi-check2-circle"></i> Gönderildi';
            }
            if (payload.redirect) {
                window.setTimeout(function() {
                    window.location.href = payload.redirect;
                }, 900);
            }
        } catch (error) {
            window.showToast(error.message || 'Mod güncellenemedi.', 'error');
            if (submitButton && form.dataset.submitted !== '1') {
                submitButton.disabled = false;
                submitButton.classList.remove('is-submitting');
                submitButton.innerHTML = originalButtonHtml;
            }
        } finally {
            delete form.dataset.validating;
            delete form.dataset.submitting;
        }
    });

    setStep(1);
    validateDownloadLinks(false);

function addDlRow() {
    const row = document.createElement('div');
    row.className = 'dl-row';
    row.innerHTML = `
        <input type="text" name="dl_name[]" class="ui-admin-form-control w-25" placeholder="Kaynak Adı">
        <input type="url" name="dl_url[]" class="ui-admin-form-control flex-grow-1" placeholder="https://...">
        <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" data-ui-remove-closest=".dl-row" title="Kaldır"><i class="bi bi-trash3"></i></button>
    `;
    document.getElementById('dlRows')?.appendChild(row);
}
window.addDlRow = addDlRow;
window.TMUI.registerAction('addDlRow', function() { addDlRow(); });
})();
