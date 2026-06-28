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
