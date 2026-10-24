function adminTopicToast(message, type = 'info', duration) {
    if (window.adminToast && typeof window.adminToast[type] === 'function') {
        window.adminToast[type](message, duration);
        return;
    }
    window.showToast(message, type, duration);
}

function initTopicsSelection() {
    const selectAll = document.getElementById('selectAllTopics');
    const rowChecks = Array.from(document.querySelectorAll('.topic-row-checkbox'));
    const bulkTitle = document.getElementById('topicsBulkTitle');
    const bulkBar = document.querySelector('[data-topic-bulk-bar]');
    const bulkApply = document.getElementById('bulkTopicApply');
    const bulkAction = document.getElementById('bulkTopicAction');
    const clearSelection = document.getElementById('clearTopicSelection');

    function syncSelectionState() {
        const checkedCount = rowChecks.filter(function(input){ return input.checked; }).length;
        if (bulkTitle) {
            bulkTitle.textContent = checkedCount > 0 ? checkedCount + ' konu seçildi' : 'Konu seçilmedi';
        }
        if (selectAll) {
            selectAll.checked = checkedCount > 0 && checkedCount === rowChecks.length;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < rowChecks.length;
        }
        if (bulkBar) {
            bulkBar.hidden = checkedCount === 0;
            bulkBar.classList.toggle('is-active', checkedCount > 0);
        }
        rowChecks.forEach(function(input) {
            const row = input.closest('tr');
            if (row) row.classList.toggle('is-selected', input.checked);
        });
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
}

function initTopicsActionPopovers() {
    const panels = Array.from(document.querySelectorAll('.topic-actions-popover'));

    panels.forEach(function(panel) {
        const trigger = document.querySelector('[popovertarget="' + panel.id + '"]');
        if (!trigger) return;

        panel.addEventListener('toggle', function(event) {
            if (event.newState !== 'open') return;
            const anchor = trigger.getBoundingClientRect();
            const bounds = panel.getBoundingClientRect();
            const viewportWidth = document.documentElement.clientWidth;
            const viewportHeight = document.documentElement.clientHeight;
            const left = Math.max(8, Math.min(anchor.right - bounds.width, viewportWidth - bounds.width - 8));
            const below = anchor.bottom + 6;
            const top = below + bounds.height <= viewportHeight - 8
                ? below
                : Math.max(8, anchor.top - bounds.height - 6);
            panel.style.left = left + 'px';
            panel.style.top = top + 'px';
        });
    });

    function closePopovers() {
        panels.forEach(function(panel) {
            if (panel.matches(':popover-open')) panel.hidePopover();
        });
    }
    window.addEventListener('resize', closePopovers);
    document.addEventListener('scroll', function(event) {
        if (!(event.target instanceof Element) || !event.target.closest('.topic-actions-popover')) {
            closePopovers();
        }
    }, true);
    document.addEventListener('click', function(event) {
        if (event.target.closest('[data-moderation-note-form]')) closePopovers();
    });
}

function initTopicsEditableRows() {
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

    bindEditableRows('.topics-editable-row[data-topic-edit-url]', 'data-topic-edit-url');
}

function initTopicsModerationNoteModal() {
    const modal = document.getElementById('moderationNoteModal');
    const title = document.getElementById('moderationNoteTitle');
    const body = document.getElementById('moderationNoteBody');
    let lastTrigger = null;

    if (!modal || !title || !body) {
        return;
    }

    function closeModal() {
        window.adminModal.close(modal, function () {
            modal.classList.remove('ui-admin-modal-open');
            if (lastTrigger) lastTrigger.focus({ preventScroll: true });
        });
    }

    document.addEventListener('click', function(event) {
        const trigger = event.target.closest('[data-moderation-note-open]');
        if (trigger) {
            lastTrigger = trigger;
            const topicTitle = trigger.getAttribute('data-moderation-topic') || '';
            title.textContent = topicTitle ? 'Moderasyon notu: ' + topicTitle : 'Son moderasyon notu';
            body.textContent = trigger.getAttribute('data-moderation-note') || '';
            window.adminModal.open(modal, {
                bodyClass: 'ui-admin-dialog-open',
                initialFocus: '[data-moderation-note-close]',
                returnFocus: lastTrigger,
                onClose: function () {
                    modal.classList.remove('ui-admin-modal-open');
                }
            });
            return;
        }

        if (event.target.closest('[data-moderation-note-close]')) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && !modal.hidden) {
            event.preventDefault();
            event.stopPropagation();
            closeModal();
        }
    }, true);
}

function initTopicsModerationActionNoteModal() {
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
        window.adminModal.close(modal, function () {
            modal.classList.remove('ui-admin-modal-open');
            error.textContent = '';
            error.hidden = true;
            pendingForm = null;
            pendingField = null;
            if (lastTrigger) lastTrigger.focus({ preventScroll: true });
        });
        error.textContent = '';
        error.hidden = true;
        pendingForm = null;
        pendingField = null;
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
            const actionPanel = form.closest('.topic-actions-popover');
            lastTrigger = actionPanel
                ? document.querySelector('[popovertarget="' + actionPanel.id + '"]')
                : event.submitter || form.querySelector('button[type="submit"]');
            noteRequired = form.getAttribute('data-moderation-note-required') === '1';
            title.textContent = form.getAttribute('data-moderation-note-title') || 'Moderasyon notu';
            textarea.value = field.value || '';
            error.textContent = '';
            error.hidden = true;
            window.adminModal.open(modal, {
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
        if (event.key === 'Escape' && !modal.hidden) {
            event.preventDefault();
            event.stopPropagation();
            closeModal();
        }
    }, true);
}

function validateBulkTopicSubmit() {
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
    return true;
}

function initTopicsBulkSubmit() {
    document.getElementById('bulkTopicsForm')?.addEventListener('submit', function(event) {
        if (!validateBulkTopicSubmit()) {
            event.preventDefault();
        }
    });
}

function initTopicsPage() {
    initTopicsSelection();
    initTopicsActionPopovers();
    initTopicsEditableRows();
    initTopicsModerationNoteModal();
    initTopicsModerationActionNoteModal();
    initTopicsBulkSubmit();
}

window.adminPage.register('topics', initTopicsPage, {
    id: 'topics-page',
    selector: '#bulkTopicsForm, .topic-row-checkbox'
});
