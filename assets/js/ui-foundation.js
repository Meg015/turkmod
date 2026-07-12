/* Shared UI behavior and toast/action compatibility layer. */
(function () {
    'use strict';

    var activeDialog = null;
    var registeredActions = {};
    var focusableSelector = [
        'a[href]',
        'area[href]',
        'button:not([disabled])',
        'input:not([disabled]):not([type="hidden"])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        'iframe',
        'object',
        'embed',
        '[contenteditable="true"]',
        '[tabindex]:not([tabindex="-1"])'
    ].join(',');

    function toElement(target) {
        if (!target) return null;
        if (typeof target === 'string') return document.querySelector(target);
        return target.nodeType === 1 ? target : null;
    }

    function isVisible(element) {
        if (!element) return false;
        return Boolean(element.offsetWidth || element.offsetHeight || element.getClientRects().length);
    }

    function focusableWithin(container) {
        var root = toElement(container);
        if (!root) return [];
        return Array.prototype.slice.call(root.querySelectorAll(focusableSelector)).filter(function (element) {
            return isVisible(element) && element.getAttribute('aria-hidden') !== 'true';
        });
    }

    function focusFirst(container, fallback) {
        var focusable = focusableWithin(container);
        var target = focusable[0] || fallback || toElement(container);
        if (target && typeof target.focus === 'function') {
            target.focus({ preventScroll: true });
        }
    }

    function trapTab(container, event) {
        if (!container || event.key !== 'Tab') return;
        var focusable = focusableWithin(container);
        if (!focusable.length) {
            event.preventDefault();
            container.focus({ preventScroll: true });
            return;
        }
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function setInertSiblings(container, inert) {
        if (!container || !container.parentElement) return [];
        var changed = [];
        Array.prototype.forEach.call(document.body.children, function (child) {
            if (child === container || child.contains(container)) return;
            if (inert) {
                if (!child.hasAttribute('aria-hidden')) {
                    child.setAttribute('aria-hidden', 'true');
                    child.dataset.tmuiHidden = '1';
                    changed.push(child);
                }
            } else if (child.dataset && child.dataset.tmuiHidden === '1') {
                child.removeAttribute('aria-hidden');
                delete child.dataset.tmuiHidden;
            }
        });
        return changed;
    }

    function openDialog(target, options) {
        var dialog = toElement(target);
        if (!dialog) return null;
        var opts = options || {};
        var previouslyFocused = document.activeElement && document.activeElement !== document.body
            ? document.activeElement
            : toElement(opts.returnFocus);

        if (activeDialog && activeDialog.close) {
            activeDialog.close(false);
        }

        dialog.hidden = false;
        dialog.removeAttribute('aria-hidden');
        dialog.setAttribute('role', dialog.getAttribute('role') || 'dialog');
        dialog.setAttribute('aria-modal', dialog.getAttribute('aria-modal') || 'true');
        dialog.classList.add(opts.openClass || 'is-open');
        document.body.classList.add(opts.bodyClass || 'ui-modal-open');

        var hiddenSiblings = opts.inert === false ? [] : setInertSiblings(dialog, true);

        function closeCurrentDialog(restoreFocus) {
            dialog.classList.remove(opts.openClass || 'is-open');
            if (opts.hideOnClose !== false) {
                dialog.hidden = true;
                dialog.setAttribute('aria-hidden', 'true');
            }
            document.body.classList.remove(opts.bodyClass || 'ui-modal-open');
            document.removeEventListener('keydown', onKeydown, true);
            dialog.removeEventListener('click', onClick);
            hiddenSiblings.forEach(function (child) {
                child.removeAttribute('aria-hidden');
                if (child.dataset) delete child.dataset.tmuiHidden;
            });
            if (restoreFocus !== false && previouslyFocused && typeof previouslyFocused.focus === 'function') {
                previouslyFocused.focus({ preventScroll: true });
            }
            if (activeDialog && activeDialog.dialog === dialog) {
                activeDialog = null;
            }
            if (typeof opts.onClose === 'function') {
                opts.onClose(dialog);
            }
        }

        function onKeydown(event) {
            if (event.key === 'Escape' && opts.closeOnEscape !== false) {
                event.preventDefault();
                closeCurrentDialog(true);
                return;
            }
            trapTab(dialog, event);
        }

        function onClick(event) {
            if (opts.closeOnBackdrop === false) return;
            var closeTrigger = event.target.closest && event.target.closest('[data-ui-modal-close]');
            if (event.target === dialog || closeTrigger) {
                closeCurrentDialog(true);
            }
        }

        document.addEventListener('keydown', onKeydown, true);
        dialog.addEventListener('click', onClick);
        window.setTimeout(function () {
            focusFirst(dialog, toElement(opts.initialFocus));
        }, 0);

        activeDialog = {
            dialog: dialog,
            close: closeCurrentDialog
        };
        dialog._tmuiDialog = activeDialog;
        return activeDialog;
    }

    function closeDialog(target) {
        var dialog = toElement(target);
        if (dialog && dialog._tmuiDialog && dialog._tmuiDialog.close) {
            dialog._tmuiDialog.close(true);
            return;
        }
        if (!dialog && activeDialog && activeDialog.close) {
            activeDialog.close(true);
        }
    }

    function bindDisclosures(root) {
        var scope = root || document;
        scope.addEventListener('click', function (event) {
            var trigger = event.target.closest && event.target.closest('[data-ui-toggle]');
            if (!trigger) return;
            var panel = toElement(trigger.getAttribute('data-ui-toggle'));
            if (!panel) return;
            var expanded = trigger.getAttribute('aria-expanded') === 'true';
            trigger.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            panel.hidden = expanded;
            panel.classList.toggle('is-open', !expanded);
        });
    }

    function bindTabs(root) {
        var scope = root || document;
        scope.querySelectorAll('[data-ui-tabs]').forEach(function (tabsRoot) {
            var tabs = Array.prototype.slice.call(tabsRoot.querySelectorAll('[role="tab"]'));
            tabs.forEach(function (tab, index) {
                tab.addEventListener('keydown', function (event) {
                    var nextIndex = index;
                    if (event.key === 'ArrowRight') nextIndex = Math.min(tabs.length - 1, index + 1);
                    else if (event.key === 'ArrowLeft') nextIndex = Math.max(0, index - 1);
                    else if (event.key === 'Home') nextIndex = 0;
                    else if (event.key === 'End') nextIndex = tabs.length - 1;
                    else return;
                    event.preventDefault();
                    tabs[nextIndex].focus();
                    tabs[nextIndex].click();
                });
            });
        });
    }

    function bindActions(root) {
        var scope = root || document;
        scope.addEventListener('click', function (event) {
            var confirmTrigger = event.target.closest && event.target.closest('[data-ui-confirm]');
            if (confirmTrigger) {
                var message = confirmTrigger.getAttribute('data-ui-confirm') || '';
                if (message && !window.confirm(message)) {
                    event.preventDefault();
                    event.stopPropagation();
                    return;
                }
            }

            var actionTrigger = event.target.closest && event.target.closest('[data-ui-action]');
            if (actionTrigger) {
                var actionName = actionTrigger.getAttribute('data-ui-action') || '';
                if (/^[a-zA-Z][a-zA-Z0-9_.:-]*$/.test(actionName) && typeof registeredActions[actionName] === 'function') {
                    event.preventDefault();
                    registeredActions[actionName](actionTrigger, event);
                    return;
                }
            }

            var reloadTrigger = event.target.closest && event.target.closest('[data-ui-reload]');
            if (reloadTrigger) {
                event.preventDefault();
                window.location.reload();
                return;
            }

            var hrefTrigger = event.target.closest && event.target.closest('[data-ui-href]');
            if (hrefTrigger) {
                event.preventDefault();
                if (hrefTrigger.hasAttribute('data-ui-stop')) {
                    event.stopPropagation();
                }
                var href = hrefTrigger.getAttribute('data-ui-href');
                if (href && !hrefTrigger.hasAttribute('disabled')) {
                    window.location.href = href;
                }
                return;
            }

            var removeTrigger = event.target.closest && event.target.closest('[data-ui-remove-closest]');
            if (removeTrigger) {
                event.preventDefault();
                var selector = removeTrigger.getAttribute('data-ui-remove-closest');
                var target = selector ? removeTrigger.closest(selector) : null;
                if (target) target.remove();
                return;
            }

            var copyTrigger = event.target.closest && event.target.closest('[data-ui-copy-previous]');
            if (copyTrigger) {
                event.preventDefault();
                var source = copyTrigger.previousElementSibling;
                var text = source && 'value' in source ? source.value : '';
                if (navigator.clipboard && text) {
                    navigator.clipboard.writeText(text);
                }
            }
        });

        scope.addEventListener('focusin', function (event) {
            var selectTrigger = event.target.closest && event.target.closest('[data-ui-select-on-focus]');
            if (selectTrigger && typeof selectTrigger.select === 'function') {
                selectTrigger.select();
            }
        });

        scope.addEventListener('change', function (event) {
            var sortTrigger = event.target.closest && event.target.closest('[data-ui-query-sort]');
            if (sortTrigger) {
                var prefix = sortTrigger.getAttribute('data-ui-query-sort') || '?sort=';
                window.location.search = prefix + encodeURIComponent(sortTrigger.value || '');
                return;
            }

            var submitTrigger = event.target.closest && event.target.closest('[data-ui-submit-form]');
            if (!submitTrigger || !submitTrigger.form) return;
            if (typeof submitTrigger.form.requestSubmit === 'function') {
                submitTrigger.form.requestSubmit();
            } else {
                submitTrigger.form.submit();
            }
        });
    }

    function bindStyleData(root) {
        var scope = root || document;
        scope.querySelectorAll('[data-ui-style-color]').forEach(function (element) {
            String(element.getAttribute('data-ui-style-color') || '').split(';').forEach(function (pair) {
                var index = pair.indexOf(':');
                if (index < 1) return;
                var name = pair.slice(0, index).trim();
                var value = pair.slice(index + 1).trim();
                if (/^--[a-z0-9_-]+$/i.test(name) && /^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test(value)) {
                    element.style.setProperty(name, value);
                }
            });
        });

        scope.querySelectorAll('[data-ui-style-number]').forEach(function (element) {
            String(element.getAttribute('data-ui-style-number') || '').split(';').forEach(function (pair) {
                var index = pair.indexOf(':');
                if (index < 1) return;
                var name = pair.slice(0, index).trim();
                var value = pair.slice(index + 1).trim();
                if (/^--[a-z0-9_-]+$/i.test(name) && /^-?\d+(?:\.\d+)?(?:px|rem|em|%|vh|vw)?$/i.test(value)) {
                    element.style.setProperty(name, value);
                }
            });
        });

        scope.querySelectorAll('[data-ui-style-url]').forEach(function (element) {
            String(element.getAttribute('data-ui-style-url') || '').split(';').forEach(function (pair) {
                var index = pair.indexOf(':');
                if (index < 1) return;
                var name = pair.slice(0, index).trim();
                var value = pair.slice(index + 1).trim();
                if (!/^--[a-z0-9_-]+$/i.test(name) || /[\u0000-\u001f"'()\\]/.test(value)) return;
                try {
                    var parsed = new URL(value, window.location.origin);
                    if (parsed.protocol === 'http:' || parsed.protocol === 'https:' || parsed.origin === window.location.origin) {
                        element.style.setProperty(name, 'url("' + parsed.href.replace(/"/g, '%22') + '")');
                    }
                } catch (error) {
                    if (value.charAt(0) === '/') {
                        element.style.setProperty(name, 'url("' + value.replace(/"/g, '%22') + '")');
                    }
                }
            });
        });
    }

    function defaultAvatarFallback() {
        var configured = document.querySelector('[data-ui-avatar-fallback]');
        if (configured && configured.getAttribute('data-ui-avatar-fallback')) {
            return configured.getAttribute('data-ui-avatar-fallback');
        }
        var base = document.querySelector('base[href]');
        if (base && base.href) {
            return new URL('assets/images/noavatar-neon-helmet.svg', base.href).href;
        }
        var path = window.location.pathname || '/';
        var marker = '/admin/';
        var adminIndex = path.indexOf(marker);
        if (adminIndex >= 0) {
            path = path.slice(0, adminIndex + 1);
        } else {
            path = path.replace(/\/[^/]*$/, '/');
        }
        return path.replace(/\/$/, '') + '/assets/images/noavatar-neon-helmet.svg';
    }

    function avatarFallbackFor(image) {
        if (!image) return defaultAvatarFallback();
        var ownFallback = image.getAttribute('data-ui-avatar-fallback');
        if (ownFallback) return ownFallback;
        var holder = image.closest && image.closest('[data-avatar-fallback],[data-ui-avatar-fallback]');
        if (holder) {
            return holder.getAttribute('data-avatar-fallback') || holder.getAttribute('data-ui-avatar-fallback') || defaultAvatarFallback();
        }
        return defaultAvatarFallback();
    }

    function bindAvatarFallbacks(root) {
        var scope = root || document;
        scope.addEventListener('error', function (event) {
            var image = event.target;
            if (!image || image.tagName !== 'IMG' || !image.hasAttribute('data-ui-avatar-img')) return;
            if (image.dataset.uiAvatarFailed === '1') return;
            var fallback = avatarFallbackFor(image);
            if (!fallback || image.src === fallback) return;
            image.dataset.uiAvatarFailed = '1';
            image.src = fallback;
        }, true);
    }

    function registerAction(name, handler) {
        if (!/^[a-zA-Z][a-zA-Z0-9_.:-]*$/.test(String(name || '')) || typeof handler !== 'function') {
            return false;
        }
        registeredActions[name] = handler;
        return true;
    }

    function confirm(message, options) {
        if (typeof window.adminConfirm === 'function') {
            return window.adminConfirm(message, options);
        }
        if (typeof window.appConfirm === 'function') {
            return window.appConfirm(message, options);
        }
        return Promise.resolve(window.confirm(String(message || '')));
    }

    window.TMUI = Object.assign(window.TMUI || {}, {
        focusableWithin: focusableWithin,
        focusFirst: focusFirst,
        openDialog: openDialog,
        closeDialog: closeDialog,
        bindDisclosures: bindDisclosures,
        bindTabs: bindTabs,
        bindActions: bindActions,
        bindStyleData: bindStyleData,
        bindAvatarFallbacks: bindAvatarFallbacks,
        registerAction: registerAction,
        confirm: confirm,
        toast: function (message, type, duration) {
            if (typeof window.showToast === 'function') {
                window.showToast(message, type, duration);
            }
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        bindStyleData(document);
        bindDisclosures(document);
        bindTabs(document);
        bindActions(document);
        bindAvatarFallbacks(document);
    });
})();

(function () {
    'use strict';

    function getConfig() {
        var container = document.getElementById('toastContainer');
        if (!container) return null;
        var d = container.dataset || {};
        return {
            container: container,
            duration: parseInt(d.toastDuration, 10) || 5000,
            durSuccess: parseInt(d.toastDurSuccess, 10) || 0,
            durError: parseInt(d.toastDurError, 10) || 0,
            durWarning: parseInt(d.toastDurWarning, 10) || 0,
            theme: d.toastTheme || 'default',
            animation: d.toastAnimation || 'slide',
            progressBar: d.toastProgress !== 'false',
            closeButton: d.toastClose !== 'false',
            maxVisible: parseInt(d.toastMax, 10) || 5,
            stackDirection: d.toastStack || 'down',
            clickToClose: d.toastClickClose !== 'false',
            pauseOnHover: d.toastPauseHover !== 'false'
        };
    }

    var icons = {
        success: 'bi-check-circle-fill',
        error: 'bi-exclamation-triangle-fill',
        warning: 'bi-exclamation-circle-fill',
        info: 'bi-info-circle'
    };
    var aliases = { danger: 'error', failed: 'error', warn: 'warning', ok: 'success' };

    function dismissToast(toast, reason) {
        if (!toast || toast._dismissed) return;
        toast._dismissed = true;
        toast._dismissReason = reason || toast._dismissReason || 'dismissed';
        var onClose = toast._onClose;
        toast._onClose = null;
        toast.classList.add('toast-out');
        setTimeout(function () {
            toast.remove();
            if (typeof onClose === 'function') {
                try {
                    onClose(toast._dismissReason, toast);
                } catch (error) {
                    // Kapanış geri çağrısı toast akışını bozmasın.
                }
            }
        }, 350);
    }

    function normalizeArgs(message, type, duration) {
        var options = {};
        if (message && typeof message === 'object') {
            options = message;
            message = options.message || options.text || '';
            type = options.type || type;
            duration = options.duration || duration;
        } else if (duration && typeof duration === 'object') {
            options = duration;
            duration = options.duration;
        }
        return {
            message: String(message || ''),
            type: aliases[type] || type || 'info',
            duration: duration,
            options: options
        };
    }

    window.showToast = function (message, type, duration) {
        var cfg = getConfig();
        if (!cfg) return;

        var args = normalizeArgs(message, type, duration);
        var options = args.options;
        type = args.type;
        message = args.message;
        duration = args.duration;

        if (typeof duration !== 'number' || duration <= 0) {
            if (type === 'success' && cfg.durSuccess > 0) duration = cfg.durSuccess;
            else if (type === 'error' && cfg.durError > 0) duration = cfg.durError;
            else if (type === 'warning' && cfg.durWarning > 0) duration = cfg.durWarning;
            else duration = cfg.duration;
        }
        if (type === 'success' && !options.duration && cfg.durSuccess <= 0) {
            duration = Math.min(duration, 3200);
        }
        if (type === 'error' && options.solution && !options.duration) {
            duration = Math.max(duration, 7600);
        }
        if (options.sticky) {
            duration = 0;
        }

        var existing = cfg.container.querySelectorAll('.topic-toast:not(.toast-out)');
        while (existing.length >= cfg.maxVisible) {
            dismissToast(existing[0], 'overflow');
            existing = cfg.container.querySelectorAll('.topic-toast:not(.toast-out)');
        }

        var toast = document.createElement('div');
        toast.className = 'topic-toast toast-' + type + ' toast-theme-' + cfg.theme + ' toast-anim-' + cfg.animation;
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        toast._onClose = typeof options.onClose === 'function' ? options.onClose : null;

        var iconEl = document.createElement('i');
        iconEl.className = 'bi ' + (icons[type] || icons.info) + ' toast-icon';
        toast.appendChild(iconEl);

        var content = document.createElement('span');
        content.className = 'toast-content';
        if (options.title) {
            var titleEl = document.createElement('span');
            titleEl.className = 'toast-title';
            titleEl.textContent = options.title;
            content.appendChild(titleEl);
        }
        var bodyEl = document.createElement('span');
        bodyEl.className = 'toast-message';
        bodyEl.textContent = message;
        content.appendChild(bodyEl);
        if (options.solution || options.detail) {
            var detailEl = document.createElement('span');
            detailEl.className = 'toast-detail';
            detailEl.textContent = options.solution || options.detail;
            content.appendChild(detailEl);
        }
        if (options.actionLabel && (options.actionUrl || typeof options.onAction === 'function')) {
            var action = options.actionUrl ? document.createElement('a') : document.createElement('button');
            action.className = 'toast-action';
            action.textContent = options.actionLabel;
            if (options.actionUrl) {
                action.href = options.actionUrl;
                if (options.actionTarget) action.target = options.actionTarget;
                if (options.actionTarget === '_blank') action.rel = 'noopener';
            } else {
                action.type = 'button';
            }
            action.addEventListener('click', function (event) {
                event.stopPropagation();
                if (typeof options.onAction === 'function') options.onAction(event, toast);
                if (options.dismissOnAction !== false) dismissToast(toast, 'action');
            });
            content.appendChild(action);
        }
        toast.appendChild(content);

        if (cfg.closeButton) {
            var closeBtn = document.createElement('button');
            closeBtn.className = 'toast-close-btn';
            closeBtn.innerHTML = '&times;';
            closeBtn.setAttribute('aria-label', 'Kapat');
            closeBtn.addEventListener('click', function (event) {
                event.stopPropagation();
                dismissToast(toast, 'button');
            });
            toast.appendChild(closeBtn);
        }

        var progressEl = null;
        if (cfg.progressBar && duration > 0) {
            var progressWrap = document.createElement('div');
            progressWrap.className = 'toast-progress-wrap';
            progressEl = document.createElement('div');
            progressEl.className = 'toast-progress toast-progress-' + type;
            progressEl.style.animationDuration = duration + 'ms';
            progressWrap.appendChild(progressEl);
            toast.appendChild(progressWrap);
        }

        if (cfg.clickToClose && !options.actionLabel) {
            toast.style.cursor = 'pointer';
            toast.addEventListener('click', function () {
                dismissToast(toast, 'click');
            });
        }

        if (cfg.stackDirection === 'up') {
            cfg.container.insertBefore(toast, cfg.container.firstChild);
        } else {
            cfg.container.appendChild(toast);
        }

        if (duration <= 0) return;

        var timer = null;
        var remaining = duration;
        var startTime = Date.now();
        function startTimer() {
            startTime = Date.now();
            timer = setTimeout(function () {
                dismissToast(toast, 'timeout');
            }, remaining);
        }
        startTimer();

        if (cfg.pauseOnHover) {
            toast.addEventListener('mouseenter', function () {
                if (timer) {
                    clearTimeout(timer);
                    timer = null;
                }
                remaining -= (Date.now() - startTime);
                if (remaining < 0) remaining = 0;
                if (progressEl) progressEl.style.animationPlayState = 'paused';
            });
            toast.addEventListener('mouseleave', function () {
                if (!toast._dismissed) {
                    startTimer();
                    if (progressEl) progressEl.style.animationPlayState = 'running';
                }
            });
        }
    };
    window.showToast._uiFoundationEnhanced = true;

    document.addEventListener('DOMContentLoaded', function () {
        var container = document.getElementById('toastContainer');
        if (!container) return;
        if (container.dataset.uiFoundationFlashDispatched === '1') return;
        container.dataset.uiFoundationFlashDispatched = '1';
        [
            ['success', container.getAttribute('data-toast-success')],
            ['error', container.getAttribute('data-toast-error')],
            ['info', container.getAttribute('data-toast-info')]
        ].forEach(function (entry) {
            if (entry[1]) {
                window.showToast(entry[1], entry[0]);
            }
        });
    });
})();
