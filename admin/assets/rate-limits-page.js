document.getElementById('selectAllRateLimits')?.addEventListener('change', function() {
    document.querySelectorAll('.rate-limit-check').forEach(input => {
        input.checked = this.checked;
    });
});
