document.querySelectorAll('.comments-subtab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var tabId = this.getAttribute('data-comments-tab');
        if (!tabId) {
            return;
        }

        document.querySelectorAll('.comments-subtab-panel').forEach(function(panel) {
            panel.classList.remove('is-active');
        });

        var panel = document.getElementById(tabId);
        if (panel) {
            panel.classList.add('is-active');
        }

        document.querySelectorAll('.comments-subtab-btn').forEach(function(item) {
            item.classList.remove('active');
        });
        this.classList.add('active');
    });
});
