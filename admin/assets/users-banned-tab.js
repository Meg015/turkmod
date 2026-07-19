function unbanUser(userId, trigger) {
    if (typeof window.openUnbanModal === 'function') {
        window.openUnbanModal(userId, trigger?.getAttribute('data-user-name') || '');
        return;
    }

    if (typeof adminAlert === 'function') {
        adminAlert('Ban kaldırma penceresi yüklenemedi. Sayfayı yenileyip tekrar deneyin.', { title: 'Uyarı', tone: 'warning' });
    }
}

function initUsersBannedTab() {
    document.addEventListener('click', function(event) {
        const unbanTrigger = event.target.closest('[data-user-unban]');
        if (unbanTrigger) {
            event.preventDefault();
            unbanUser(unbanTrigger.getAttribute('data-user-unban'), unbanTrigger);
        }
    });
}

window.unbanUser = unbanUser;

window.adminPage.register('users:banned', initUsersBannedTab, {
    id: 'users:banned',
    selector: '[data-user-unban]'
});
