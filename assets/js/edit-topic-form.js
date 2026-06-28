(function() {
    const form = document.getElementById('uploadForm');
    if (!form) return;

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

    function setStep(step) {
        current = Math.max(1, Math.min(7, step));
        panels.forEach(function(panel) {
            const active = Number(panel.dataset.step) === current;
            panel.hidden = !active;
            panel.classList.toggle('is-active', active);
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

    function validateCurrentStep() {
        const panel = panels.find(function(item) { return Number(item.dataset.step) === current; });
        if (!panel) return true;
        const required = Array.from(panel.querySelectorAll('input[required], select[required], textarea[required]'));
        for (const field of required) {
            if (!field.checkValidity()) {
                field.reportValidity();
                return false;
            }
        }
        return true;
    }

    steps.forEach(function(button) {
        button.addEventListener('click', function() {
            setStep(Number(button.dataset.stepTarget));
        });
    });
    prevBtn && prevBtn.addEventListener('click', function() { setStep(current - 1); });
    nextBtn && nextBtn.addEventListener('click', function() {
        if (validateCurrentStep()) setStep(current + 1);
    });

    document.querySelectorAll('[data-open-input]').forEach(function(trigger) {
        trigger.addEventListener('click', function() {
            document.getElementById(trigger.dataset.openInput)?.click();
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

        if (form.dataset.submitting === '1' || form.dataset.submitted === '1') {
            window.showToast?.('Bu değişiklik zaten gönderiliyor veya gönderildi.', 'warning');
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
                throw new Error(payload.message || 'Mod güncellenemedi.');
            }

            form.dataset.submitted = '1';
            window.showToast?.(payload.message || 'Değişiklikler onaya gönderildi.', 'success');
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
        delete form.dataset.submitting;
        window.showToast?.(error.message || 'Mod güncellenemedi.', 'error');
        if (submitButton && form.dataset.submitted !== '1') {
                submitButton.disabled = false;
                submitButton.classList.remove('is-submitting');
                submitButton.innerHTML = originalButtonHtml;
            }
        }
    });

    setStep(1);
})();

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
if (window.TMUI && typeof window.TMUI.registerAction === 'function') {
    window.TMUI.registerAction('addDlRow', function() { addDlRow(); });
}
