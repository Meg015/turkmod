function initSettingsCronPage() {
    document.querySelectorAll('.cron-subtab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var tabId = this.getAttribute('data-cron-tab');
            if (!tabId) {
                return;
            }

            document.querySelectorAll('.cron-subtab-panel').forEach(function(panel) {
                panel.classList.remove('is-active');
            });

            var panel = document.getElementById(tabId);
            if (panel) {
                panel.classList.add('is-active');
            }

            document.querySelectorAll('.cron-subtab-btn').forEach(function(item) {
                item.classList.remove('active');
            });
            this.classList.add('active');
        });
    });
}

window.adminPage.register('settings', initSettingsCronPage, {
    id: 'settings-page:cron',
    selector: '.cron-subtab-btn'
});
