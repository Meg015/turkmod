(() => {
    'use strict';

    if (window.__publicToastBridgeInit === true) {
        return;
    }
    window.__publicToastBridgeInit = true;

    const normalizeMessage = (value) => String(value || '').replace(/\s+/g, ' ').trim();
    const toLower = (value) => String(value || '').toLowerCase();

    const resolveAlertType = (node) => {
        const className = toLower(node.className || '');
        if (className.includes('ui-alert--error') || className.includes('danger') || className.includes('error')) {
            return 'error';
        }
        if (className.includes('ui-alert--success') || className.includes('success')) {
            return 'success';
        }
        if (className.includes('ui-alert--warning') || className.includes('warning')) {
            return 'warning';
        }
        return 'info';
    };

    const isPublicSurface = () => {
        if (!document.body) {
            return false;
        }
        const className = document.body.className || '';
        return className.includes('public-theme-layout') || className.includes('public-page-');
    };

    const collectFlashMessages = (container) => {
        const flashByType = new Map([
            ['success', new Set()],
            ['error', new Set()],
            ['warning', new Set()],
            ['info', new Set()]
        ]);

        if (!container) {
            return flashByType;
        }

        const push = (type, value) => {
            const normalized = normalizeMessage(value);
            if (!normalized) {
                return;
            }
            if (!flashByType.has(type)) {
                flashByType.set(type, new Set());
            }
            flashByType.get(type).add(normalized);
        };

        push('success', container.getAttribute('data-toast-success'));
        push('error', container.getAttribute('data-toast-error'));
        push('warning', container.getAttribute('data-toast-warning'));
        push('info', container.getAttribute('data-toast-info'));
        return flashByType;
    };

    const hideInlineAlert = (node) => {
        node.classList.add('ui-toast-bridged-alert');
        node.setAttribute('aria-hidden', 'true');
        node.hidden = true;
    };

    const bridgeInlineAlerts = () => {
        const container = document.getElementById('toastContainer');
        if (!container || container.classList.contains('is-hidden')) {
            return false;
        }
        if (typeof window.showToast !== 'function') {
            return false;
        }
        if (!isPublicSurface()) {
            return false;
        }

        const flashByType = collectFlashMessages(container);
        const allFlashMessages = new Set();
        flashByType.forEach((messages) => {
            messages.forEach((message) => allFlashMessages.add(message));
        });
        const nodes = document.querySelectorAll([
            '.ui-admin-alert',
            '.messages-alert',
            '.notifications-alert',
            '.ui-alert--error',
            '.ui-alert--success',
            '[data-toast-bridge="on"]'
        ].join(', '));

        nodes.forEach((node) => {
            if (!(node instanceof HTMLElement)) {
                return;
            }
            if (node.hidden || node.dataset.toastBridged === '1' || node.dataset.toastBridge === 'off') {
                return;
            }
            if (node.closest('[hidden], [aria-hidden=\"true\"]')) {
                return;
            }
            const computedStyle = window.getComputedStyle(node);
            if (computedStyle.display === 'none' || computedStyle.visibility === 'hidden') {
                return;
            }

            const message = normalizeMessage(node.textContent);
            if (!message) {
                return;
            }

            const type = resolveAlertType(node);
            const duplicatedFlash = allFlashMessages.has(message) || (flashByType.get(type)?.has(message) || false);
            node.dataset.toastBridged = '1';

            if (!duplicatedFlash) {
                window.showToast(message, type);
            }
            hideInlineAlert(node);
        });

        return true;
    };

    const tryBridge = () => {
        let attempts = 0;
        const maxAttempts = 25;
        const run = () => {
            attempts += 1;
            const bridged = bridgeInlineAlerts();
            if (bridged || attempts >= maxAttempts) {
                return;
            }
            window.setTimeout(run, 120);
        };
        run();
    };

    document.addEventListener('DOMContentLoaded', () => {
        tryBridge();

        const observer = new MutationObserver((records) => {
            for (const record of records) {
                if (!record.addedNodes || record.addedNodes.length === 0) {
                    continue;
                }
                if (bridgeInlineAlerts()) {
                    break;
                }
            }
        });

        if (document.body) {
            observer.observe(document.body, { childList: true, subtree: true });
        }
    });
})();
