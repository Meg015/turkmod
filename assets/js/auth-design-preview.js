(function () {
    'use strict';

    function toggleTheme() {
        var html = document.documentElement;
        var current = html.getAttribute('data-theme-mode');
        var newTheme = current === 'light' ? 'dark' : 'light';
        html.setAttribute('data-theme-mode', newTheme);
        localStorage.setItem('theme', newTheme);
    }

    function switchTab(trigger, tabName) {
        document.querySelectorAll('.demo-content').forEach(function (el) {
            el.classList.remove('active');
        });
        document.querySelectorAll('.demo-tab').forEach(function (el) {
            el.classList.remove('active');
        });

        var panel = document.getElementById(tabName);
        if (panel) {
            panel.classList.add('active');
        }
        trigger.classList.add('active');
    }

    document.addEventListener('click', function (event) {
        var themeToggle = event.target.closest('[data-auth-preview-theme-toggle]');
        if (themeToggle) {
            event.preventDefault();
            toggleTheme();
            return;
        }

        var tab = event.target.closest('[data-auth-preview-tab]');
        if (tab) {
            event.preventDefault();
            switchTab(tab, tab.getAttribute('data-auth-preview-tab') || 'login');
        }
    });

    document.addEventListener('submit', function (event) {
        if (event.target.closest('[data-auth-preview-form]')) {
            event.preventDefault();
        }
    });

    var saved = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme-mode', saved);
})();
