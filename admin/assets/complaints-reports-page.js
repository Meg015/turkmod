function openComplaintsModal(modal, trigger) {
    if (!modal) return;
    if (window.TMUI && typeof window.TMUI.openDialog === 'function') {
        window.TMUI.openDialog(modal, {
            bodyClass: 'ui-admin-dialog-open',
            initialFocus: '[data-complaints-modal-close]',
            returnFocus: trigger || document.activeElement
        });
        modal.classList.add('ui-admin-modal-open');
        return;
    }
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add('is-open', 'ui-admin-modal-open');
    document.body.classList.add('ui-admin-dialog-open');
    modal.querySelector('[data-complaints-modal-close]')?.focus();
}

function closeComplaintsModal(modal) {
    if (!modal) return;
    if (window.TMUI && typeof window.TMUI.closeDialog === 'function' && modal._tmuiDialog) {
        window.TMUI.closeDialog(modal);
        modal.classList.remove('ui-admin-modal-open');
        return;
    }
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    modal.classList.remove('is-open', 'ui-admin-modal-open');
    document.body.classList.remove('ui-admin-dialog-open');
}

document.addEventListener('click', function(event) {
    var openButton = event.target.closest('[data-complaints-modal-open]');
    if (openButton) {
        var modal = document.getElementById(openButton.getAttribute('data-complaints-modal-open'));
        openComplaintsModal(modal, openButton);
    }

    if (event.target.closest('[data-complaints-modal-close]')) {
        var closeTarget = event.target.closest('.complaints-modal');
        if (!closeTarget) {
            closeTarget = event.target.closest('[data-complaints-modal-close]') && event.target.closest('[data-complaints-modal-close]').closest('.complaints-modal');
        }
        closeComplaintsModal(closeTarget);
    }
});
document.addEventListener('keydown', function(event) {
    if (window.TMUI && typeof window.TMUI.openDialog === 'function') return;
    if (event.key !== 'Escape') return;
    document.querySelectorAll('.complaints-modal:not([hidden])').forEach(function(modal) {
        closeComplaintsModal(modal);
    });
});
(function(){
    var selectAll = document.getElementById('selectAllReports');
    var rowChecks = Array.prototype.slice.call(document.querySelectorAll('.complaints-row-checkbox'));
    var selectedCount = document.getElementById('selectedReportCount');
    var bulkBar = document.querySelector('.complaints-bulk-bar');
    function sync() {
        var count = rowChecks.filter(function(input) { return input.checked; }).length;
        if (selectedCount) selectedCount.textContent = String(count);
        if (bulkBar) bulkBar.classList.toggle('is-active', count > 0);
        if (selectAll) {
            selectAll.checked = count > 0 && count === rowChecks.length;
            selectAll.indeterminate = count > 0 && count < rowChecks.length;
        }
    }
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            rowChecks.forEach(function(input) { input.checked = selectAll.checked; });
            sync();
        });
    }
    rowChecks.forEach(function(input) { input.addEventListener('change', sync); });
    sync();
})();

(function(){
    var form = document.querySelector('[data-report-reasons-form]');
    if (!form) return;
    var list = form.querySelector('[data-report-reasons-list]');
    var template = document.querySelector('[data-report-reason-template]');
    var addButton = document.querySelector('[data-report-reason-add]');

    function rows() {
        return Array.prototype.slice.call(list.querySelectorAll('[data-report-reason-row]'));
    }

    function slug(value) {
        var map = {'ç':'c','ğ':'g','ı':'i','i':'i','ö':'o','ş':'s','ü':'u'};
        return String(value || '').toLocaleLowerCase('tr-TR').split('').map(function(char) {
            return map[char] || char;
        }).join('').replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 40);
    }

    function syncButtons() {
        var currentRows = rows();
        currentRows.forEach(function(row, index) {
            var up = row.querySelector('[data-report-reason-up]');
            var down = row.querySelector('[data-report-reason-down]');
            var remove = row.querySelector('[data-report-reason-remove]');
            if (up) up.disabled = index === 0;
            if (down) down.disabled = index === currentRows.length - 1;
            if (remove) remove.disabled = currentRows.length <= 1;
        });
        if (addButton) addButton.disabled = currentRows.length >= 20;
    }

    if (addButton && template) {
        addButton.addEventListener('click', function() {
            if (rows().length >= 20) return;
            list.appendChild(template.content.cloneNode(true));
            var newRow = rows()[rows().length - 1];
            newRow.querySelector('input[name="reason_labels[]"]')?.focus();
            syncButtons();
        });
    }

    list.addEventListener('input', function(event) {
        var labelInput = event.target.closest('input[name="reason_labels[]"]');
        if (!labelInput) return;
        var row = labelInput.closest('[data-report-reason-row]');
        var keyInput = row?.querySelector('input[name="reason_keys[]"]');
        if (row?.classList.contains('is-new') && keyInput && !keyInput.dataset.manualKey) {
            keyInput.value = slug(labelInput.value);
        }
    });

    list.addEventListener('input', function(event) {
        var keyInput = event.target.closest('input[name="reason_keys[]"]');
        if (keyInput && !keyInput.readOnly) keyInput.dataset.manualKey = '1';
    });

    list.addEventListener('click', function(event) {
        var row = event.target.closest('[data-report-reason-row]');
        if (!row) return;
        if (event.target.closest('[data-report-reason-remove]')) {
            if (rows().length > 1) row.remove();
        } else if (event.target.closest('[data-report-reason-up]')) {
            var previous = row.previousElementSibling;
            if (previous) list.insertBefore(row, previous);
        } else if (event.target.closest('[data-report-reason-down]')) {
            var next = row.nextElementSibling;
            if (next) list.insertBefore(next, row);
        }
        syncButtons();
    });

    syncButtons();
})();
