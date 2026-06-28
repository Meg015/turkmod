document.addEventListener('DOMContentLoaded', function() {
    if (!window.TMUI || typeof window.TMUI.registerAction !== 'function') return;
    window.TMUI.registerAction('toggleWidget', function(trigger) {
        if (typeof window.toggleWidget === 'function') {
            window.toggleWidget(trigger);
        }
    });
});
