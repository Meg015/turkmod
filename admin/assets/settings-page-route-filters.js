function initSettingsRouteFiltersPage() {
    document.querySelectorAll('.route-filter-subtab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var tabId = this.getAttribute('data-route-tab');
            if (!tabId) {
                return;
            }

            document.querySelectorAll('.route-filter-subtab-panel').forEach(function(panel) {
                if (!panel.classList.contains('cron-subtab-panel')) {
                    panel.classList.remove('is-active');
                }
            });

            var panel = document.getElementById(tabId);
            if (panel) {
                panel.classList.add('is-active');
            }

            document.querySelectorAll('.route-filter-subtab-btn').forEach(function(item) {
                if (!item.classList.contains('cron-subtab-btn')) {
                    item.classList.remove('active');
                }
            });
            this.classList.add('active');
        });
    });
}

window.adminPage.register('settings', initSettingsRouteFiltersPage, {
    id: 'settings-page:route-filters',
    selector: '.route-filter-subtab-btn'
});
