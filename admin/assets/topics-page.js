function adminTopicToast(message, type = 'info', duration) {
    if (window.adminToast && typeof window.adminToast[type] === 'function') {
        window.adminToast[type](message, duration);
        return;
    }
    if (typeof window.showToast === 'function') {
        window.showToast(message, type, duration);
    }
}

(function(){
    const selectAll = document.getElementById('selectAllTopics');
    const rowChecks = Array.from(document.querySelectorAll('.topic-row-checkbox'));
    const selectedCount = document.getElementById('selectedTopicCount');
    const bulkTitle = document.getElementById('topicsBulkTitle');
    const bulkHint = document.getElementById('topicsBulkHint');
    const bulkBar = document.querySelector('[data-topic-bulk-bar]');
    const bulkApply = document.getElementById('bulkTopicApply');
    const bulkAction = document.getElementById('bulkTopicAction');
    const clearSelection = document.getElementById('clearTopicSelection');

    function syncSelectionState() {
        const checkedCount = rowChecks.filter(function(input){ return input.checked; }).length;
        if (selectedCount) {
            selectedCount.textContent = String(checkedCount);
        }
        if (bulkTitle) {
            bulkTitle.textContent = checkedCount > 0 ? checkedCount + ' konu secildi' : 'Konu secilmedi';
        }
        if (bulkHint) {
            bulkHint.textContent = checkedCount > 0
                ? 'Secimi temizle veya asagidan toplu islem uygula.'
                : 'Listeden konu secince islemler aktiflesir.';
        }
        if (selectAll) {
            selectAll.checked = checkedCount > 0 && checkedCount === rowChecks.length;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < rowChecks.length;
        }
        if (bulkBar) {
            bulkBar.classList.toggle('is-active', checkedCount > 0);
        }
        if (bulkApply) {
            bulkApply.disabled = checkedCount === 0 || !bulkAction || bulkAction.value === '';
        }
        if (clearSelection) {
            clearSelection.disabled = checkedCount === 0;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function(){
            rowChecks.forEach(function(input){ input.checked = selectAll.checked; });
            syncSelectionState();
        });
    }

    rowChecks.forEach(function(input){
        input.addEventListener('change', syncSelectionState);
    });
    if (bulkAction) {
        bulkAction.addEventListener('change', syncSelectionState);
    }
    if (clearSelection) {
        clearSelection.addEventListener('click', function(){
            rowChecks.forEach(function(input){ input.checked = false; });
            if (selectAll) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            }
            syncSelectionState();
        });
    }

    syncSelectionState();
})();

(function(){
    const interactiveSelector = 'a, button, input, select, textarea, label, form, [role="button"], [data-moderation-note-open]';
    const bindEditableRows = function(selector, attributeName) {
        document.querySelectorAll(selector).forEach(function(row) {
            const goToEdit = function() {
                const url = row.getAttribute(attributeName);
                if (url) {
                    window.location.href = url;
                }
            };

            row.addEventListener('click', function(event) {
                const target = event.target instanceof Element ? event.target : event.target.parentElement;
                if (target && target.closest(interactiveSelector)) {
                    return;
                }
                goToEdit();
            });

            row.addEventListener('keydown', function(event) {
                const target = event.target instanceof Element ? event.target : event.target.parentElement;
                if ((event.key === 'Enter' || event.key === ' ') && (!target || !target.closest(interactiveSelector))) {
                    event.preventDefault();
                    goToEdit();
                }
            });
        });
    };

    bindEditableRows('.ui-admin-topic-click-row[data-topic-edit-url]', 'data-topic-edit-url');
    bindEditableRows('[data-health-edit-url]', 'data-health-edit-url');
})();

(function(){
    const modal = document.getElementById('moderationNoteModal');
    const title = document.getElementById('moderationNoteTitle');
    const body = document.getElementById('moderationNoteBody');
    let lastTrigger = null;

    if (!modal || !title || !body) {
        return;
    }

    function closeModal() {
        if (window.TMUI && typeof window.TMUI.closeDialog === 'function' && modal._tmuiDialog) {
            window.TMUI.closeDialog(modal);
            return;
        }
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        modal.classList.remove('is-open', 'ui-admin-modal-open');
        document.body.classList.remove('ui-admin-dialog-open');
        if (lastTrigger) {
            lastTrigger.focus();
        }
    }

    document.addEventListener('click', function(event) {
        const trigger = event.target.closest('[data-moderation-note-open]');
        if (trigger) {
            lastTrigger = trigger;
            const topicTitle = trigger.getAttribute('data-moderation-topic') || '';
            title.textContent = topicTitle ? 'Moderasyon notu: ' + topicTitle : 'Son moderasyon notu';
            body.textContent = trigger.getAttribute('data-moderation-note') || '';
            if (window.TMUI && typeof window.TMUI.openDialog === 'function') {
                window.TMUI.openDialog(modal, {
                    bodyClass: 'ui-admin-dialog-open',
                    initialFocus: '[data-moderation-note-close]',
                    returnFocus: lastTrigger,
                    onClose: function () {
                        modal.classList.remove('ui-admin-modal-open');
                    }
                });
                modal.classList.add('ui-admin-modal-open');
            } else {
                modal.hidden = false;
                modal.setAttribute('aria-hidden', 'false');
                modal.classList.add('is-open', 'ui-admin-modal-open');
                document.body.classList.add('ui-admin-dialog-open');
                const closeButton = modal.querySelector('[data-moderation-note-close]');
                if (closeButton) {
                    closeButton.focus();
                }
            }
            return;
        }

        if (event.target.closest('[data-moderation-note-close]')) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (window.TMUI && typeof window.TMUI.openDialog === 'function') return;
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });
})();

(function(){
    const modal = document.getElementById('moderationActionNoteModal');
    const title = document.getElementById('moderationActionNoteTitle');
    const textarea = document.getElementById('moderationActionNoteText');
    const error = document.getElementById('moderationActionNoteError');
    const submitButton = document.getElementById('moderationActionNoteSubmit');
    let pendingForm = null;
    let pendingField = null;
    let lastTrigger = null;
    let noteRequired = false;

    if (!modal || !title || !textarea || !error || !submitButton) {
        return;
    }

    function closeModal() {
        if (window.TMUI && typeof window.TMUI.closeDialog === 'function' && modal._tmuiDialog) {
            window.TMUI.closeDialog(modal);
            return;
        }
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        modal.classList.remove('is-open', 'ui-admin-modal-open');
        document.body.classList.remove('ui-admin-dialog-open');
        error.textContent = '';
        error.hidden = true;
        pendingForm = null;
        pendingField = null;
        if (lastTrigger) {
            lastTrigger.focus();
        }
    }

    document.querySelectorAll('[data-moderation-note-form]').forEach(function(form) {
        form.addEventListener('submit', function(event) {
            const field = form.querySelector('input[name="moderation_note"]');
            if (!field) {
                return;
            }

            event.preventDefault();
            pendingForm = form;
            pendingField = field;
            lastTrigger = event.submitter || form.querySelector('button[type="submit"]');
            noteRequired = form.getAttribute('data-moderation-note-required') === '1';
            title.textContent = form.getAttribute('data-moderation-note-title') || 'Moderasyon notu';
            textarea.value = field.value || '';
            error.textContent = '';
            error.hidden = true;
            if (window.TMUI && typeof window.TMUI.openDialog === 'function') {
                window.TMUI.openDialog(modal, {
                    bodyClass: 'ui-admin-dialog-open',
                    initialFocus: '#moderationActionNoteText',
                    returnFocus: lastTrigger,
                    onClose: function () {
                        modal.classList.remove('ui-admin-modal-open');
                        error.textContent = '';
                        error.hidden = true;
                        pendingForm = null;
                        pendingField = null;
                    }
                });
                modal.classList.add('ui-admin-modal-open');
            } else {
                modal.hidden = false;
                modal.setAttribute('aria-hidden', 'false');
                modal.classList.add('is-open', 'ui-admin-modal-open');
                document.body.classList.add('ui-admin-dialog-open');
                textarea.focus();
            }
        });
    });

    submitButton.addEventListener('click', function() {
        const note = textarea.value.trim();
        if (noteRequired && note === '') {
            error.textContent = 'Lütfen kullanıcıya gösterilecek kısa bir moderasyon notu yazın.';
            error.hidden = false;
            textarea.focus();
            return;
        }

        if (pendingForm && pendingField) {
            pendingField.value = note;
            pendingForm.submit();
        }
    });

    textarea.addEventListener('input', function() {
        if (error.textContent !== '') {
            error.textContent = '';
            error.hidden = true;
        }
    });

    document.addEventListener('click', function(event) {
        if (event.target.closest('[data-moderation-action-note-close]')) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (window.TMUI && typeof window.TMUI.openDialog === 'function') return;
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });
})();

async function handleBulkTopicSubmit(form) {
    const selected = document.querySelectorAll('.topic-row-checkbox:checked');
    const actionField = document.getElementById('bulkTopicAction');
    const action = actionField ? actionField.value : '';

    if (!action) {
        adminTopicToast('Lütfen bir işlem seçin.', 'warning');
        return false;
    }
    if (selected.length === 0) {
        adminTopicToast('Lütfen en az bir konu seçin.', 'warning');
        return false;
    }
    if (action === 'delete') return adminConfirm('Seçilen konular çöp kutusuna taşınacak. Onaylıyor musunuz?', { title: 'Toplu işlem', ok: 'Çöpe Taşı', tone: 'danger' });
    if (action === 'restore') return adminConfirm('Seçilen konular geri yüklenecek. Onaylıyor musunuz?', { title: 'Toplu işlem', ok: 'Geri Yükle', tone: 'warning' });
    if (action === 'purge') return adminConfirm('Kalıcı olarak silinecek. Bu işlem geri alınamaz. Onaylıyor musunuz?', { title: 'Kalıcı silme', ok: 'Kalıcı Sil', tone: 'danger' });
    return true;
}

document.getElementById('bulkTopicsForm')?.addEventListener('submit', async function(event) {
    if (this.dataset.adminBulkConfirmed === '1') {
        return;
    }

    event.preventDefault();
    if (!await handleBulkTopicSubmit(this)) {
        return;
    }

    this.dataset.adminBulkConfirmed = '1';
    const button = document.getElementById('bulkTopicApply');
    if (button) {
        button.classList.add('is-ui-loading');
        button.disabled = true;
        button.innerHTML = '<i class="bi bi-hourglass-split"></i> Uygulanıyor';
    }
    this.submit();
});

(function(){
    const shell = document.querySelector('[data-topic-health-shell]');
    if (!shell) {
        return;
    }

    const actions = shell.querySelector('.topic-health-scan-actions');
    const startButton = document.getElementById('topicHealthScanStart');
    const progressText = document.getElementById('topicHealthProgressText');
    const progressPercent = document.getElementById('topicHealthProgressPercent');
    const progressBar = document.getElementById('topicHealthProgressBar');

    if (!actions || !startButton || !progressText || !progressPercent || !progressBar) {
        return;
    }

    const apiUrl = actions.getAttribute('data-health-api') || '';
    let csrfToken = actions.getAttribute('data-health-token') || '';
    const batchSize = Math.max(1, Math.min(10, Number(actions.getAttribute('data-health-batch-size') || 3) || 3));
    const formatter = new Intl.NumberFormat('tr-TR');
    let isRunning = false;
    const STATE_KEY = 'topicHealthScanState';

    try {
        const currentUrl = new URL(window.location.href);
        if (currentUrl.searchParams.get('health_cleared') === '1') {
            localStorage.removeItem(STATE_KEY);
            ['checked', 'ok', 'warning', 'broken', 'download_link_issues', 'image_issues'].forEach(function(key) {
                document.querySelectorAll('[data-health-summary="' + key + '"]').forEach(function(node) {
                    node.textContent = formatter.format(0);
                });
            });
            progressText.textContent = 'Sağlık geçmişi temizlendi.';
            progressPercent.textContent = '0%';
            progressBar.style.width = '0%';
            document.querySelectorAll('.topics-admin-tabs .badge').forEach(function(node) {
                node.remove();
            });
            currentUrl.searchParams.delete('health_cleared');
            currentUrl.searchParams.delete('health_clear_ts');
            window.history.replaceState({}, '', currentUrl.toString());
        }
    } catch (error) {}

    function setProgress(processed, total, message) {
        const safeTotal = Math.max(0, Number(total) || 0);
        const safeProcessed = Math.min(Math.max(0, Number(processed) || 0), safeTotal);
        const percent = safeTotal > 0 ? Math.round((safeProcessed / safeTotal) * 100) : 0;

        progressText.textContent = message || (formatter.format(safeProcessed) + ' / ' + formatter.format(safeTotal) + ' konu kontrol edildi.');
        progressPercent.textContent = percent + '%';
        progressBar.style.width = percent + '%';
    }

    function updateSummary(summary) {
        if (!summary || typeof summary !== 'object') {
            return;
        }

        Object.keys(summary).forEach(function(key) {
            document.querySelectorAll('[data-health-summary="' + key + '"]').forEach(function(node) {
                node.textContent = formatter.format(Number(summary[key]) || 0);
            });
        });
    }

    async function runBatch(offset) {
        const body = new FormData();
        body.append('_token', csrfToken);
        body.append('offset', String(offset));
        body.append('batch_size', String(batchSize));

        const response = await fetch(apiUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        });

        const payload = await response.json();
        if (payload._token) {
            csrfToken = payload._token;
            actions.setAttribute('data-health-token', csrfToken);
        }
        if (!response.ok || !payload.success) {
            throw new Error(payload.message || 'Sağlık kontrolü başarısız.');
        }

        return payload;
    }

    async function startScan(resumeOffset = 0, resumeTotal = 0) {
        if (isRunning) {
            return;
        }

        isRunning = true;
        startButton.disabled = true;
        startButton.classList.add('is-loading');
        startButton.innerHTML = '<i class="bi bi-hourglass-split"></i> Kontrol ediliyor';
        
        let total = resumeTotal || Number(actions.getAttribute('data-health-total') || 0);
        setProgress(resumeOffset, total, 'Kontrol başlatılıyor...');

        let offset = resumeOffset;

        try {
            while (true) {
                localStorage.setItem(STATE_KEY, JSON.stringify({ offset: offset, total: total, isRunning: true }));

                const payload = await runBatch(offset);
                total = Number(payload.total || total || 0);
                offset = Number(payload.next_offset || payload.processed || offset + 1);

                if (payload.summary) {
                    updateSummary(payload.summary);
                }

                const lastTitle = Array.isArray(payload.results) && payload.results[0] && payload.results[0].title
                    ? ' Son: ' + payload.results[0].title
                    : '';
                setProgress(Number(payload.processed || offset), total, formatter.format(Number(payload.processed || offset)) + ' / ' + formatter.format(total) + ' konu kontrol edildi.' + lastTitle);

                if (payload.done) {
                    break;
                }
            }

            localStorage.removeItem(STATE_KEY);
            setProgress(total, total, 'Kontrol tamamlandı. Sonuçlar yenileniyor...');
            adminTopicToast('Konu sağlığı kontrolü tamamlandı.', 'success');
            setTimeout(function() {
                window.location.href = 'topics.php?tab=health';
            }, 900);
        } catch (error) {
            localStorage.setItem(STATE_KEY, JSON.stringify({ offset: offset, total: total, isRunning: false }));
            progressText.textContent = error.message || 'Kontrol sırasında hata oluştu.';
            adminTopicToast(progressText.textContent, 'error', 8000);
            startButton.disabled = false;
            startButton.classList.remove('is-loading');
            startButton.innerHTML = '<i class="bi bi-play-circle"></i> Devam Et';
            isRunning = false;
        }
    }

    function handleStartClick() {
        try {
            const savedState = JSON.parse(localStorage.getItem(STATE_KEY));
            if (savedState && typeof savedState.offset === 'number') {
                startScan(savedState.offset, savedState.total);
                return;
            }
        } catch(e) {}
        startScan(0, 0);
    }

    startButton.addEventListener('click', handleStartClick);

    // Auto resume if page was refreshed while running
    try {
        const savedState = JSON.parse(localStorage.getItem(STATE_KEY));
        if (savedState && savedState.isRunning) {
            startScan(savedState.offset || 0, savedState.total || 0);
        } else if (savedState && !savedState.isRunning) {
            startButton.innerHTML = '<i class="bi bi-play-circle"></i> Devam Et';
            setProgress(savedState.offset || 0, savedState.total || 0, 'Önceki kontrol kaydedildi, devam edilebilir.');
        }
    } catch (e) {
        // ignore
    }
})();
