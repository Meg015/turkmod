function initUsersAppealsTab() {
        var selectAll = document.getElementById('selectAllAppeals');
        var counter = document.getElementById('appealsBulkCount');
        var boxes = function () { return Array.prototype.slice.call(document.querySelectorAll('.appeal-row-checkbox')); };
        function updateCount() {
            var n = boxes().filter(function (b) { return b.checked; }).length;
            if (counter) { counter.textContent = n + ' seçili'; }
            if (selectAll) {
                var all = boxes();
                selectAll.checked = all.length > 0 && n === all.length;
                selectAll.indeterminate = n > 0 && n < all.length;
            }
        }
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                boxes().forEach(function (b) { b.checked = selectAll.checked; });
                updateCount();
            });
        }
        boxes().forEach(function (b) { b.addEventListener('change', updateCount); });
        updateCount();
}

window.adminPage.register('users:appeals', initUsersAppealsTab, {
    id: 'users:appeals',
    selector: '#selectAllAppeals, .appeal-row-checkbox'
});
