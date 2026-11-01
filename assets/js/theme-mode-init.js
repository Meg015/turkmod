(function () {
    'use strict';

    function readStoredMode() {
        try {
            if (!window.localStorage) {
                return '';
            }
            return localStorage.getItem('theme-mode') || '';
        } catch (error) {
            return '';
        }
    }

    var html = document.documentElement;
    var siteMode = html.getAttribute('data-theme-mode') || 'auto';
    var storedMode = readStoredMode();
    var mode = storedMode || siteMode;
    mode = (mode === 'light' || mode === 'dark' || mode === 'auto') ? mode : 'auto';
    var theme = mode;
    if (mode === 'auto') {
        theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    html.setAttribute('data-theme', theme);
    html.setAttribute('data-theme-mode', mode);
    html.setAttribute('data-bs-theme', theme);
})();
