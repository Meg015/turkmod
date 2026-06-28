(function () {
    'use strict';

    function readStoredMode() {
        try {
            if (!window.localStorage) {
                return '';
            }
            return localStorage.getItem('theme-mode') || localStorage.getItem('theme') || '';
        } catch (error) {
            return '';
        }
    }

    try {
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
    } catch (error) {
        var root = document.documentElement;
        var fallbackMode = root.getAttribute('data-theme-mode') || 'auto';
        var fallbackTheme = fallbackMode === 'dark' ? 'dark' : 'light';
        if (fallbackMode === 'auto' && window.matchMedia) {
            fallbackTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        root.setAttribute('data-theme', fallbackTheme);
        root.setAttribute('data-bs-theme', fallbackTheme);
        root.setAttribute('data-theme-mode', (fallbackMode === 'dark' || fallbackMode === 'light' || fallbackMode === 'auto') ? fallbackMode : 'auto');
    }
})();
