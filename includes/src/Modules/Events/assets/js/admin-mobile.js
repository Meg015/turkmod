/**
 * Mobile-Friendly Admin Navigation
 */

(function() {
    'use strict';

    /**
     * Initialize mobile navigation
     */
    function initMobileNav() {
        const toggle = document.querySelector('.admin-sidebar-toggle');
        const sidebar = document.querySelector('.admin-sidebar');

        if (!toggle || !sidebar) return;

        // Toggle sidebar
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', sidebar.classList.contains('is-open'));
        });

        // Close sidebar when clicking outside
        document.addEventListener('click', (e) => {
            if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
                sidebar.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });

        // Close sidebar on link click
        sidebar.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                sidebar.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            });
        });

        // Close sidebar on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && sidebar.classList.contains('is-open')) {
                sidebar.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    /**
     * Initialize touch gestures
     */
    function initTouchGestures() {
        let touchStartX = 0;
        let touchEndX = 0;

        document.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
        }, false);

        document.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        }, false);

        function handleSwipe() {
            const sidebar = document.querySelector('.admin-sidebar');
            const toggle = document.querySelector('.admin-sidebar-toggle');

            if (!sidebar || !toggle) return;

            // Swipe right to open
            if (touchEndX - touchStartX > 50) {
                sidebar.classList.add('is-open');
                toggle.setAttribute('aria-expanded', 'true');
            }

            // Swipe left to close
            if (touchStartX - touchEndX > 50) {
                sidebar.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        }
    }

    /**
     * Optimize tables for mobile
     */
    function optimizeTables() {
        const tables = document.querySelectorAll('.ui-events-table');

        tables.forEach(table => {
            // Add data-label attributes to cells
            const rows = table.querySelectorAll('tbody tr');
            const headers = table.querySelectorAll('thead th');

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                cells.forEach((cell, index) => {
                    if (headers[index]) {
                        cell.setAttribute('data-label', headers[index].textContent);
                    }
                });
            });

            // Make table scrollable on mobile
            if (window.innerWidth < 768) {
                const wrapper = document.createElement('div');
                wrapper.className = 'table-responsive';
                wrapper.style.overflowX = 'auto';
                wrapper.style.webkitOverflowScrolling = 'touch';
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
            }
        });
    }

    /**
     * Optimize forms for mobile
     */
    function optimizeForms() {
        const inputs = document.querySelectorAll('input, select, textarea');

        inputs.forEach(input => {
            // Add proper input types for mobile keyboards
            if (input.type === 'text') {
                const name = input.name || input.id || '';
                if (name.includes('email')) {
                    input.type = 'email';
                    input.inputMode = 'email';
                } else if (name.includes('phone')) {
                    input.type = 'tel';
                    input.inputMode = 'tel';
                } else if (name.includes('number') || name.includes('count')) {
                    input.type = 'number';
                    input.inputMode = 'numeric';
                } else if (name.includes('url')) {
                    input.type = 'url';
                    input.inputMode = 'url';
                }
            }

            // Ensure minimum touch target size
            const style = window.getComputedStyle(input);
            const height = parseInt(style.height);
            if (height < 44) {
                input.style.minHeight = '44px';
                input.style.padding = '8px 12px';
            }
        });
    }

    /**
     * Optimize buttons for mobile
     */
    function optimizeButtons() {
        const buttons = document.querySelectorAll('button, .ui-admin-btn');

        buttons.forEach(button => {
            // Ensure minimum touch target size
            const style = window.getComputedStyle(button);
            const height = parseInt(style.height);
            if (height < 44) {
                button.style.minHeight = '44px';
            }
        });
    }

    /**
     * Handle viewport changes
     */
    function handleViewportChange() {
        const sidebar = document.querySelector('.admin-sidebar');
        const toggle = document.querySelector('.admin-sidebar-toggle');

        if (window.innerWidth >= 768) {
            // Desktop: show sidebar
            if (sidebar) sidebar.classList.remove('is-open');
            if (toggle) toggle.style.display = 'none';
        } else {
            // Mobile: hide sidebar by default
            if (sidebar) sidebar.classList.remove('is-open');
            if (toggle) toggle.style.display = 'inline-flex';
        }
    }

    /**
     * Initialize all mobile features
     */
    function init() {
        initMobileNav();
        initTouchGestures();
        optimizeTables();
        optimizeForms();
        optimizeButtons();
        handleViewportChange();

        // Handle window resize
        window.addEventListener('resize', handleViewportChange);
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose to global scope
    window.eventsAdminMobile = {
        optimizeTables,
        optimizeForms,
        optimizeButtons
    };
})();
