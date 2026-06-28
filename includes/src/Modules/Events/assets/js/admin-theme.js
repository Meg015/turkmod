/**
 * Dark Mode Theme Toggle for Events Admin
 */

(function() {
    'use strict';

    const THEME_KEY = 'events_admin_theme';
    const THEME_LIGHT = 'light';
    const THEME_DARK = 'dark';

    /**
     * Get current theme
     */
    function getCurrentTheme() {
        // Check localStorage
        const stored = localStorage.getItem(THEME_KEY);
        if (stored) {
            return stored;
        }

        // Check system preference
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return THEME_DARK;
        }

        return THEME_LIGHT;
    }

    /**
     * Set theme
     */
    function setTheme(theme) {
        const html = document.documentElement;

        if (theme === THEME_DARK) {
            html.setAttribute('data-theme', THEME_DARK);
            localStorage.setItem(THEME_KEY, THEME_DARK);
        } else {
            html.removeAttribute('data-theme');
            localStorage.setItem(THEME_KEY, THEME_LIGHT);
        }

        // Update toggle button
        updateThemeToggle(theme);

        // Dispatch event
        window.dispatchEvent(new CustomEvent('themechange', { detail: { theme } }));
    }

    /**
     * Toggle theme
     */
    function toggleTheme() {
        const current = getCurrentTheme();
        const next = current === THEME_DARK ? THEME_LIGHT : THEME_DARK;
        setTheme(next);
    }

    /**
     * Update theme toggle button
     */
    function updateThemeToggle(theme) {
        const toggle = document.getElementById('theme-toggle');
        if (!toggle) return;

        const icon = toggle.querySelector('i');
        const label = toggle.getAttribute('aria-label');

        if (theme === THEME_DARK) {
            toggle.setAttribute('aria-pressed', 'true');
            if (icon) {
                icon.className = 'bi bi-sun-fill';
            }
            toggle.title = 'Light mode';
        } else {
            toggle.setAttribute('aria-pressed', 'false');
            if (icon) {
                icon.className = 'bi bi-moon-fill';
            }
            toggle.title = 'Dark mode';
        }
    }

    /**
     * Initialize theme
     */
    function initTheme() {
        const theme = getCurrentTheme();
        setTheme(theme);

        // Listen for system theme changes
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                if (!localStorage.getItem(THEME_KEY)) {
                    setTheme(e.matches ? THEME_DARK : THEME_LIGHT);
                }
            });
        }

        // Setup toggle button
        const toggle = document.getElementById('theme-toggle');
        if (toggle) {
            toggle.addEventListener('click', toggleTheme);
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTheme);
    } else {
        initTheme();
    }

    // Expose to global scope
    window.eventsAdminTheme = {
        getCurrentTheme,
        setTheme,
        toggleTheme
    };
})();
