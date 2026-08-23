document.addEventListener('DOMContentLoaded', function() {
    window.TMUI.registerAction('toggleWidget', function(trigger) {
        if (typeof window.toggleWidget === 'function') {
            window.toggleWidget(trigger);
        }
    });
});
