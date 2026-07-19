function initRateLimitsPage() {
    document.getElementById('selectAllRateLimits')?.addEventListener('change', function() {
        document.querySelectorAll('.rate-limit-check').forEach(input => {
            input.checked = this.checked;
        });
    });
}

window.adminPage.register('rate-limits', initRateLimitsPage, {
    id: 'rate-limits-page',
    selector: '#selectAllRateLimits'
});
