function initSettingsFileManagerPage() {
    document.querySelectorAll('.file-manager-subtab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var tabId = this.getAttribute('data-file-manager-tab');
            if (!tabId) {
                return;
            }

            document.querySelectorAll('.file-manager-subtab-panel').forEach(function(panel) {
                panel.classList.remove('is-active');
            });

            var panel = document.getElementById(tabId);
            if (panel) {
                panel.classList.add('is-active');
            }

            document.querySelectorAll('.file-manager-subtab-btn').forEach(function(item) {
                item.classList.remove('active');
            });
            this.classList.add('active');
        });
    });
}

window.adminPage.register('settings', initSettingsFileManagerPage, {
    id: 'settings-page:file-manager',
    selector: '.file-manager-subtab-btn'
});
