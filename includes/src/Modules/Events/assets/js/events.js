(function () {
    'use strict';

    if (window.__uiEventsPublicRuntimeLoaded) {
        return;
    }
    window.__uiEventsPublicRuntimeLoaded = true;

    function readEventsJsonConfig(id) {
        var node = document.getElementById(id);
        if (!node) return {};

        try {
            var parsed = JSON.parse(node.textContent || '{}');
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (error) {
            return {};
        }
    }

    function initializeEventsRuntimeSettings() {
        var config = readEventsJsonConfig('eventsRuntimeSettings');
        window.eventsSettings = Object.assign({}, window.eventsSettings || {}, config);
    }

    initializeEventsRuntimeSettings();

    var eventsRuntimeStyleBuckets = {};

    function eventsCspNonce() {
        var node = document.querySelector('script[nonce], style[nonce]');
        return node ? (node.nonce || node.getAttribute('nonce') || '') : '';
    }

    function eventsCssString(value) {
        return String(value || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\n/g, '\\A ');
    }

    function eventsRuntimeStyleElement() {
        var style = document.getElementById('ui-events-runtime-style');
        if (style) return style;

        style = document.createElement('style');
        style.id = 'ui-events-runtime-style';
        var nonce = eventsCspNonce();
        if (nonce) {
            style.setAttribute('nonce', nonce);
        }
        document.head.appendChild(style);
        return style;
    }

    function setEventsRuntimeStyleBucket(name, rules) {
        eventsRuntimeStyleBuckets[name] = Array.isArray(rules) ? rules : [];
        var style = eventsRuntimeStyleElement();
        style.textContent = Object.keys(eventsRuntimeStyleBuckets).sort().map(function (key) {
            return eventsRuntimeStyleBuckets[key].join('\n');
        }).join('\n');
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') || '' : '';
    }

    function baseUri() {
        var meta = document.querySelector('meta[name="app-base-uri"]');
        if (meta && meta.getAttribute('content')) {
            return meta.getAttribute('content') || '';
        }

        var script = document.querySelector('script[src*="/events/assets/js/events.js"]');
        if (script && script.src) {
            try {
                var url = new URL(script.src, window.location.href);
                var marker = '/events/assets/js/events.js';
                var index = url.pathname.indexOf(marker);
                if (index >= 0) {
                    return url.origin + url.pathname.slice(0, index);
                }
            } catch (error) {}
        }

        var match = window.location.pathname.match(/^(.*?)(?:\/admin|\/events)(?:\/|$)/);
        return match ? window.location.origin + match[1] : '';
    }

    function showResult(target, message, tone) {
        if (!target) return;
        target.textContent = message;
        target.className = 'ui-events-result' + (tone ? ' ui-events-result-' + tone : '');
    }

    function appendText(parent, className, text) {
        var el = document.createElement('span');
        el.className = className;
        el.textContent = text || '';
        parent.appendChild(el);
        return el;
    }

    function renderInlineStatus(target, message, tone) {
        if (!target) return;
        target.textContent = message || '';
        target.classList.remove('ui-events-inline-status-info', 'ui-events-inline-status-success', 'ui-events-inline-status-error');
        target.classList.add('ui-events-inline-status', 'ui-events-inline-status-' + (tone || 'info'));
        target.hidden = false;
    }

    function inlineStatusForAction(button) {
        var scope = button.closest('.ui-events-list-item, .ui-events-raffle-card, .ui-events-task-card');
        if (!scope) return null;
        var status = scope.querySelector('[data-ui-events-inline-status]');
        if (status) return status;
        status = document.createElement('span');
        status.setAttribute('data-ui-events-inline-status', '');
        status.className = 'ui-events-inline-status';
        scope.appendChild(status);
        return status;
    }

    function wheelRewardTypeMeta(reward) {
        var type = String((reward && reward.type) || 'custom').toLowerCase();
        var premium = reward && (reward.is_premium === true || reward.is_premium === 1 || reward.is_premium === '1');
        var map = {
            points: {tone: 'points', label: 'Puan', icon: 'bi-coin'},
            wheel_spin: {tone: 'spin', label: 'Çark Hakkı', icon: 'bi-arrow-repeat'},
            raffle_entry: {tone: 'raffle', label: 'Çekiliş Hakkı', icon: 'bi-ticket-perforated'},
            coupon: {tone: 'coupon', label: 'Kupon', icon: 'bi-tag'},
            badge: {tone: 'badge', label: 'Rozet', icon: 'bi-award'},
            custom: {tone: 'custom', label: 'Özel Ödül', icon: 'bi-gift'}
        };
        var meta = map[type] || map.custom;
        if (premium) {
            return {
                tone: 'premium',
                label: meta.label + ' · Premium',
                icon: 'bi-stars'
            };
        }
        return meta;
    }

    function appendIconText(parent, className, icon, text) {
        var el = document.createElement('span');
        el.className = className;
        var iconEl = document.createElement('i');
        iconEl.className = 'bi ' + icon;
        iconEl.setAttribute('aria-hidden', 'true');
        el.appendChild(iconEl);
        el.appendChild(document.createTextNode(text || ''));
        parent.appendChild(el);
        return el;
    }

    function wheelUsageBool(value) {
        return value === true || value === 1 || value === '1' || value === 'true';
    }

    function wheelUsageNumber(value, fallback) {
        var parsed = parseInt(value, 10);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function formatWheelLimit(value) {
        if (value === null || value === undefined || value === '') {
            return 'Limitsiz';
        }

        return String(Math.max(0, wheelUsageNumber(value, 0)));
    }

    function formatEventsReadableDuration(seconds, zeroLabel) {
        var remaining = Math.max(0, wheelUsageNumber(seconds, 0));
        if (remaining <= 0) {
            return zeroLabel || '0 sn';
        }

        var units = [
            ['gün', 86400],
            ['saat', 3600],
            ['dk', 60],
            ['sn', 1]
        ];
        var parts = [];
        units.forEach(function(unit) {
            if (parts.length >= 2) return;
            var amount = Math.floor(remaining / unit[1]);
            if (amount <= 0) return;
            parts.push(amount + ' ' + unit[0]);
            remaining %= unit[1];
        });

        return parts.join(' ');
    }

    function formatWheelCooldown(seconds) {
        return formatEventsReadableDuration(seconds, 'Hazır');
    }

    function wheelUsageCanSpinNow(usage) {
        return !!usage && wheelUsageBool(usage.can_spin_now);
    }

    function applyWheelUsageState(usage, spinButton) {
        if (!usage || typeof usage !== 'object') return;

        var usagePanel = document.querySelector('[data-ui-events-wheel-usage]');
        var cooldownRemaining = Math.max(0, wheelUsageNumber(usage.cooldown_remaining, 0));
        var cooldownUntil = usage.next_spin_at_epoch
            ? wheelUsageNumber(usage.next_spin_at_epoch, 0) * 1000
            : (cooldownRemaining > 0 ? Date.now() + (cooldownRemaining * 1000) : '');
        var canSpinNow = wheelUsageCanSpinNow(usage);
        var limitBlocked = wheelUsageBool(usage.limit_blocked);

        if (usagePanel) {
            usagePanel.dataset.uiEventsLimitBlocked = limitBlocked ? '1' : '0';
            usagePanel.dataset.uiEventsCanSpinNow = canSpinNow ? '1' : '0';
            usagePanel.dataset.uiEventsCooldownUntil = cooldownUntil ? String(cooldownUntil) : '';

            var daily = usagePanel.querySelector('[data-ui-events-wheel-usage-daily]');
            var hourly = usagePanel.querySelector('[data-ui-events-wheel-usage-hourly]');
            var cooldown = usagePanel.querySelector('[data-ui-events-wheel-usage-cooldown]');
            if (daily) daily.textContent = formatWheelLimit(usage.remaining_daily);
            if (hourly) hourly.textContent = formatWheelLimit(usage.remaining_hourly);
            if (cooldown) cooldown.textContent = formatWheelCooldown(cooldownRemaining);
        }

        var heroCooldown = document.querySelector('[data-ui-events-wheel-usage-cooldown-hero]');
        if (heroCooldown) {
            heroCooldown.textContent = formatWheelCooldown(cooldownRemaining);
        }

        var badge = document.querySelector('[data-ui-events-wheel-ready-badge]');
        if (badge) {
            badge.textContent = cooldownRemaining > 0 ? 'Bekleme' : (canSpinNow ? 'Alınabilir' : 'Limit doldu');
            badge.classList.toggle('ui-events-badge-success', canSpinNow);
            badge.classList.toggle('ui-events-badge-available', canSpinNow);
            badge.classList.toggle('ui-events-badge-warning', !canSpinNow);
        }

        var button = spinButton || document.querySelector('[data-ui-events-spin]');
        if (button && button.dataset.uiEventsSpinPending !== '1') {
            button.disabled = !canSpinNow;
            var label = button.querySelector('.ui-events-wheel-spin-btn-text');
            if (label) {
                if (cooldownRemaining > 0) {
                    label.textContent = 'Bekle';
                } else if (wheelUsageBool(usage.can_spin_with_bonus) && wheelUsageNumber(usage.bonus_spin_count, 0) > 0) {
                    label.textContent = 'Bonus Hakla Çevir';
                } else if (wheelUsageBool(usage.can_spin_with_extra) && wheelUsageNumber(usage.extra_spin_cost, 0) > 0) {
                    label.textContent = usage.extra_spin_cost + ' Puanla Çevir';
                } else {
                    label.textContent = 'Çevir';
                }
            }
        }
    }

    function updateWheelUsageCountdowns() {
        document.querySelectorAll('[data-ui-events-wheel-usage]').forEach(function (usagePanel) {
            var until = wheelUsageNumber(usagePanel.dataset.uiEventsCooldownUntil, 0);
            if (!until) return;

            var remaining = Math.max(0, Math.ceil((until - Date.now()) / 1000));
            var cooldown = usagePanel.querySelector('[data-ui-events-wheel-usage-cooldown]');
            if (cooldown) cooldown.textContent = formatWheelCooldown(remaining);

            var heroCooldown = document.querySelector('[data-ui-events-wheel-usage-cooldown-hero]');
            if (heroCooldown) heroCooldown.textContent = formatWheelCooldown(remaining);

            if (remaining <= 0) {
                usagePanel.dataset.uiEventsCooldownUntil = '';
                if (usagePanel.dataset.uiEventsLimitBlocked !== '1') {
                    usagePanel.dataset.uiEventsCanSpinNow = '1';
                }
            }

            var button = document.querySelector('[data-ui-events-spin]');
            var canSpinNow = usagePanel.dataset.uiEventsCanSpinNow === '1' && remaining <= 0;
            if (button && button.dataset.uiEventsSpinPending !== '1') {
                button.disabled = !canSpinNow;
                var label = button.querySelector('.ui-events-wheel-spin-btn-text');
                if (label && remaining <= 0 && canSpinNow) {
                    label.textContent = 'Çevir';
                } else if (label && remaining > 0) {
                    label.textContent = 'Bekle';
                }
            }

            var badge = document.querySelector('[data-ui-events-wheel-ready-badge]');
            if (badge && remaining <= 0) {
                badge.textContent = canSpinNow ? 'Alınabilir' : 'Limit doldu';
                badge.classList.toggle('ui-events-badge-success', canSpinNow);
                badge.classList.toggle('ui-events-badge-available', canSpinNow);
                badge.classList.toggle('ui-events-badge-warning', !canSpinNow);
            }
        });
    }

    function renderWheelResultCard(target, reward, data) {
        if (!target) return;
        var remaining = data && data.remaining_spins ? data.remaining_spins : {};
        var usage = data && data.wheel_usage ? data.wheel_usage : null;
        var cooldownRemaining = usage ? wheelUsageNumber(usage.cooldown_remaining, 0) : 0;
        var rewardStatus = reward && reward.status === 'claimed' ? 'Teslim edildi' : 'Bekliyor';
        var nextSpinText = Number(remaining.daily || 0) > 0
            ? 'Bugün kalan hak: ' + remaining.daily
            : 'Günlük hak doldu';
        nextSpinText = cooldownRemaining > 0
            ? 'Tekrar çevirme: ' + formatWheelCooldown(cooldownRemaining)
            : 'Bugün kalan hak: ' + formatWheelLimit(remaining.daily);
        var rewardMeta = wheelRewardTypeMeta(reward);
        var rewardValue = reward && reward.value !== undefined && reward.value !== null && String(reward.value) !== ''
            ? String(reward.value)
            : '';

        target.innerHTML = '';
        target.className = 'ui-events-result ui-events-result-success ui-events-result-card ui-events-result-card-' + rewardMeta.tone;
        target.setAttribute('data-ui-events-result-card', '');

        var badge = document.createElement('span');
        badge.className = 'ui-events-result-kind';
        var badgeIcon = document.createElement('i');
        badgeIcon.className = 'bi ' + rewardMeta.icon;
        badgeIcon.setAttribute('aria-hidden', 'true');
        badge.appendChild(badgeIcon);
        badge.appendChild(document.createTextNode(rewardMeta.label));
        target.appendChild(badge);

        var title = document.createElement('strong');
        title.className = 'ui-events-result-title';
        title.textContent = 'Kazandın: ' + ((reward && reward.name) || 'Ödül');
        target.appendChild(title);

        var meta = document.createElement('div');
        meta.className = 'ui-events-result-meta';
        appendIconText(meta, 'ui-events-result-pill', 'bi-clock-history', nextSpinText);
        appendIconText(meta, 'ui-events-result-pill', 'bi-check2-circle', 'Ödül durumu: ' + rewardStatus);
        if (rewardValue !== '') {
            appendIconText(meta, 'ui-events-result-pill', 'bi-info-circle', 'Değer: ' + rewardValue);
        }
        if (data && data.bonus_spin_consumed) {
            appendIconText(meta, 'ui-events-result-pill', 'bi-lightning-charge', 'Bonus hak kullanıldı');
        }
        target.appendChild(meta);
    }

    var wheelNameTooltip = null;
    var wheelNameTooltipTarget = null;

    function ensureWheelNameTooltip(target) {
        var scope = target && target.closest('.ui-events-wheel-stage');
        if (!wheelNameTooltip) {
            wheelNameTooltip = document.createElement('div');
            wheelNameTooltip.className = 'ui-events-wheel-name-tooltip';
            wheelNameTooltip.setAttribute('role', 'tooltip');
            wheelNameTooltip.hidden = true;
        }

        var parent = scope || document.body;
        if (wheelNameTooltip.parentNode !== parent) {
            parent.appendChild(wheelNameTooltip);
        }

        return wheelNameTooltip;
    }

    function wheelLabelNeedsTooltip(target) {
        if (!target) return false;
        var label = target.getAttribute('data-ui-events-wheel-tooltip') || '';
        var span = target.querySelector('span');
        if (!label || !span) return false;
        return span.scrollWidth > span.clientWidth + 1;
    }

    function positionWheelNameTooltip(target) {
        if (!wheelNameTooltip || !target) return;
        wheelNameTooltip.classList.toggle('is-near-top', target.getBoundingClientRect().top < 140);
    }

    function showWheelNameTooltip(target) {
        if (!wheelLabelNeedsTooltip(target)) return;
        var tooltip = ensureWheelNameTooltip(target);
        wheelNameTooltipTarget = target;
        tooltip.textContent = target.getAttribute('data-ui-events-wheel-tooltip') || '';
        tooltip.hidden = false;
        tooltip.classList.add('is-visible');
        positionWheelNameTooltip(target);
    }

    function hideWheelNameTooltip() {
        if (!wheelNameTooltip) return;
        wheelNameTooltip.classList.remove('is-visible');
        wheelNameTooltip.hidden = true;
        wheelNameTooltipTarget = null;
    }

    function initializeWheelNameTooltips(root) {
        (root || document).querySelectorAll('[data-ui-events-wheel-tooltip]').forEach(function (label) {
            if (label.getAttribute('data-ui-events-wheel-tooltip-bound') === '1') return;
            label.setAttribute('data-ui-events-wheel-tooltip-bound', '1');

            var trigger = label.querySelector('span') || label;

            trigger.addEventListener('pointerenter', function () {
                showWheelNameTooltip(label);
            });

            trigger.addEventListener('pointerleave', hideWheelNameTooltip);

            trigger.addEventListener('pointermove', function () {
                if (wheelNameTooltipTarget === label) {
                    positionWheelNameTooltip(label);
                }
            });

            label.addEventListener('focus', function () {
                showWheelNameTooltip(label);
            });

            label.addEventListener('blur', hideWheelNameTooltip);

            label.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    hideWheelNameTooltip();
                    label.blur();
                }
            });
        });
    }

    function ensureEventsConfirmModal() {
        var modal = document.querySelector('[data-ui-events-confirm-modal]');
        if (modal) return modal;

        modal = document.createElement('div');
        modal.className = 'ui-events-confirm-modal';
        modal.setAttribute('data-ui-events-confirm-modal', '');
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'ui-events-confirm-title');
        modal.setAttribute('hidden', '');
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML =
            '<div class="ui-events-confirm-backdrop" data-ui-events-confirm-cancel data-ui-modal-close></div>' +
            '<div class="ui-events-confirm-dialog">' +
                '<div class="ui-events-confirm-icon"><i class="bi bi-exclamation-triangle"></i></div>' +
                '<div class="ui-events-confirm-copy"><h3 id="ui-events-confirm-title"></h3><p data-ui-events-confirm-message></p></div>' +
                '<div class="ui-events-confirm-actions">' +
                    '<button type="button" class="ui-events-btn ui-events-btn-secondary" data-ui-events-confirm-cancel>Vazgeç</button>' +
                    '<button type="button" class="ui-events-btn ui-events-btn-primary" data-ui-events-confirm-ok>Onayla</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(modal);
        return modal;
    }

    function showEventsConfirm(options) {
        var modal = ensureEventsConfirmModal();
        var title = modal.querySelector('#ui-events-confirm-title');
        var message = modal.querySelector('[data-ui-events-confirm-message]');
        var okButton = modal.querySelector('[data-ui-events-confirm-ok]');
        var cancelButtons = modal.querySelectorAll('[data-ui-events-confirm-cancel]');

        title.textContent = options.title || 'İşlemi onayla';
        message.textContent = options.message || 'Bu işlem uygulanacak.';
        okButton.textContent = options.confirmLabel || 'Onayla';
        okButton.className = 'ui-events-btn ' + (options.tone === 'danger' ? 'ui-events-btn-danger' : 'ui-events-btn-primary');
        cancelButtons.forEach(function (button) {
            if (button.tagName === 'BUTTON') button.textContent = options.cancelLabel || 'Vazgeç';
        });

        modal.classList.remove('is-danger', 'is-warning', 'is-success');
        if (options.tone) modal.classList.add('is-' + options.tone);

        return new Promise(function (resolve) {
            var settled = false;
            var controller = null;
            var cleanup = function (result) {
                if (settled) return;
                settled = true;
                okButton.removeEventListener('click', onOk);
                cancelButtons.forEach(function (button) { button.removeEventListener('click', onCancel); });
                document.removeEventListener('keydown', onKeydown);
                if (controller && modal._tmuiDialog) {
                    controller.close(true);
                } else {
                    modal.hidden = true;
                    modal.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('ui-events-confirm-open');
                }
                resolve(result);
            };
            var onOk = function () { cleanup(true); };
            var onCancel = function () { cleanup(false); };
            var onKeydown = function (event) {
                if (window.TMUI && typeof window.TMUI.openDialog === 'function') return;
                if (event.key === 'Escape') cleanup(false);
            };

            okButton.addEventListener('click', onOk);
            cancelButtons.forEach(function (button) { button.addEventListener('click', onCancel); });
            if (window.TMUI && typeof window.TMUI.openDialog === 'function') {
                controller = window.TMUI.openDialog(modal, {
                    bodyClass: 'ui-events-confirm-open',
                    initialFocus: '[data-ui-events-confirm-ok]',
                    returnFocus: document.activeElement,
                    onClose: function () {
                        cleanup(false);
                    }
                });
            } else {
                modal.hidden = false;
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('ui-events-confirm-open');
                okButton.focus();
                document.addEventListener('keydown', onKeydown);
            }
        });
    }

    function confirmElement(element) {
        if (!element || element.dataset.eventsConfirm === undefined) {
            return Promise.resolve(true);
        }
        return showEventsConfirm({
            title: element.dataset.eventsConfirmTitle || 'İşlemi onayla',
            message: element.dataset.eventsConfirm || 'Bu işlem uygulanacak.',
            confirmLabel: element.dataset.eventsConfirmOk || 'Onayla',
            cancelLabel: element.dataset.eventsConfirmCancel || 'Vazgeç',
            tone: element.dataset.eventsConfirmTone || 'primary'
        });
    }

    function eventsToast(message, type, duration) {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type || 'info', duration);
        }
    }

    async function postJson(url, payload) {
        var response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken()
            },
            body: JSON.stringify(payload || {})
        });
        var data = await response.json().catch(function () { return {}; });
        if (!response.ok || data.success === false) {
            var requestError = new Error(data.message || data.error || 'İşlem tamamlanamadı.');
            requestError.data = data;
            requestError.status = response.status;
            throw requestError;
        }
        return data;
    }

    function activateEventsTab(root, target) {
        root.querySelectorAll('[data-ui-events-tab]').forEach(function (button) {
            var active = button.getAttribute('data-ui-events-tab') === target;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        root.querySelectorAll('[data-ui-events-tab-panel]').forEach(function (panel) {
            var active = panel.getAttribute('data-ui-events-tab-panel') === target;
            panel.classList.toggle('is-active', active);
            panel.hidden = !active;
        });
    }

    function activateEventsTabFromQuery() {
        var requestedTab = '';

        try {
            requestedTab = new URLSearchParams(window.location.search).get('tab') || '';
        } catch (error) {
            requestedTab = '';
        }

        if (!requestedTab) return;

        document.querySelectorAll('[data-ui-events-tabs-root]').forEach(function (root) {
            var hasRequestedTab = false;
            root.querySelectorAll('[data-ui-events-tab]').forEach(function (button) {
                if (button.getAttribute('data-ui-events-tab') === requestedTab) {
                    hasRequestedTab = true;
                }
            });

            if (hasRequestedTab) {
                activateEventsTab(root, requestedTab);
            }
        });
    }

    function clampPercent(value) {
        var percent = parseInt(value, 10);
        if (Number.isNaN(percent)) return 0;
        return Math.max(0, Math.min(100, percent));
    }

    function initializeEventsProgressBars(root) {
        var rules = [];
        (root || document).querySelectorAll('[data-ui-events-progress]').forEach(function (bar, index) {
            var percent = clampPercent(bar.getAttribute('data-ui-events-progress') || '0');
            var progressId = bar.getAttribute('data-ui-events-progress-id');
            if (!progressId) {
                progressId = 'progress-' + index + '-' + Math.random().toString(36).slice(2, 8);
                bar.setAttribute('data-ui-events-progress-id', progressId);
            }
            rules.push('[data-ui-events-progress-id="' + eventsCssString(progressId) + '"]{width:' + percent + '%;}');
            bar.setAttribute('aria-valuenow', String(percent));
        });
        setEventsRuntimeStyleBucket('progress', rules);
    }

    function readEventFloat(element, name, fallback) {
        var value = parseFloat(element.getAttribute(name) || '');
        return Number.isFinite(value) ? value : fallback;
    }

    function initializeEventsWheelMarkup(root) {
        var colors = ['#0f766e', '#2563eb', '#dc2626', '#ca8a04', '#7c3aed', '#0891b2', '#be123c', '#16a34a', '#ea580c', '#4f46e5', '#0d9488', '#c026d3'];

        (root || document).querySelectorAll('[data-ui-events-wheel-inner]').forEach(function (inner) {
            if (inner.getAttribute('data-ui-events-css-rendered') === '1') {
                return;
            }

            var textSlices = inner.querySelectorAll('[data-ui-events-wheel-text]');
            var sliceCount = parseInt(inner.getAttribute('data-ui-events-slice-count') || String(textSlices.length), 10);
            sliceCount = Number.isFinite(sliceCount) && sliceCount > 0 ? sliceCount : Math.max(1, textSlices.length);

            var degPerSlice = readEventFloat(inner, 'data-ui-events-deg-per-slice', 360 / sliceCount);
            var offsetDeg = readEventFloat(inner, 'data-ui-events-offset-deg', -(degPerSlice / 2));
            var conicParts = [];

            for (var index = 0; index < sliceCount; index++) {
                conicParts.push(colors[index % colors.length] + ' ' + (index * degPerSlice).toFixed(4) + 'deg ' + ((index + 1) * degPerSlice).toFixed(4) + 'deg');
            }

            var runtimeId = inner.getAttribute('data-ui-events-runtime-style-id');
            if (!runtimeId) {
                runtimeId = 'wheel-' + Math.random().toString(36).slice(2, 9);
                inner.setAttribute('data-ui-events-runtime-style-id', runtimeId);
            }
            var runtimeSelector = '[data-ui-events-runtime-style-id="' + eventsCssString(runtimeId) + '"]';
            var wheelRules = [
                runtimeSelector + '{background:conic-gradient(from ' + offsetDeg.toFixed(4) + 'deg, ' + conicParts.join(', ') + ');}'
            ];

            inner.querySelectorAll('[data-ui-events-wheel-separator]').forEach(function (separator) {
                var sliceIndex = parseInt(separator.getAttribute('data-ui-events-wheel-index') || '0', 10) || 0;
                wheelRules.push(runtimeSelector + ' [data-ui-events-wheel-separator][data-ui-events-wheel-index="' + sliceIndex + '"]{transform:rotate(' + ((sliceIndex * degPerSlice) + offsetDeg).toFixed(4) + 'deg);}');
            });

            textSlices.forEach(function (text) {
                var sliceIndex = parseInt(text.getAttribute('data-ui-events-wheel-index') || '0', 10) || 0;
                wheelRules.push(runtimeSelector + ' [data-ui-events-wheel-text][data-ui-events-wheel-index="' + sliceIndex + '"]{transform:rotate(' + ((sliceIndex * degPerSlice) - 90).toFixed(4) + 'deg);}');
            });

            setEventsRuntimeStyleBucket('wheel-' + runtimeId, wheelRules);
        });
    }

    function initializeEventsTaskFilters() {
        var filters = document.querySelectorAll('[data-ui-events-task-filter]');
        if (filters.length === 0) return;

        filters.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var filterValue = this.getAttribute('data-ui-events-task-filter');
                
                filters.forEach(function (b) {
                    b.classList.remove('is-active');
                    b.setAttribute('aria-selected', 'false');
                });
                this.classList.add('is-active');
                this.setAttribute('aria-selected', 'true');

                document.querySelectorAll('[data-ui-events-task-group]').forEach(function (group) {
                    var groupKey = group.getAttribute('data-ui-events-task-group');
                    group.hidden = !(filterValue === 'all' || filterValue === groupKey);
                });
            });
        });
    }

    function initializeEventsTaskAccordions() {
        document.querySelectorAll('[data-ui-events-task-accordion]').forEach(function (card) {
            var trigger = card.querySelector('[data-ui-events-task-accordion-trigger]');
            var btn = card.querySelector('.ui-events-task-toggle-btn');
            if (!trigger) return;

            trigger.addEventListener('click', function (e) {
                if (e.target.closest('button') && !e.target.closest('.ui-events-task-toggle-btn')) return;
                
                var isOpen = card.classList.toggle('is-open');
                if (btn) {
                    btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                }
            });
        });
    }

    function animateCountUps() {
        document.querySelectorAll('[data-ui-events-countup]').forEach(function (el) {
            var target = parseInt(el.textContent.replace(/[^\d]/g, ''), 10);
            if (Number.isNaN(target)) return;

            var prefix = el.textContent.startsWith('+') ? '+' : '';
            var duration = prefersReducedMotion() ? 0 : 800;
            var startTime = null;

            var step = function (timestamp) {
                if (!startTime) startTime = timestamp;
                var progress = Math.min(1, (timestamp - startTime) / duration);
                var easedProgress = progress * (2 - progress);
                var current = Math.floor(easedProgress * target);
                
                el.textContent = prefix + current;

                if (progress < 1) {
                    window.requestAnimationFrame(step);
                } else {
                    el.textContent = prefix + target;
                }
            };

            if (duration > 0 && target > 0) {
                window.requestAnimationFrame(step);
            }
        });
    }

    function initializeEventsUi() {
        activateEventsTabFromQuery();
        initializeEventsProgressBars(document);
        initializeEventsWheelMarkup(document);
        initializeWheelNameTooltips(document);
        initializeEventsTaskFilters();
        initializeEventsTaskAccordions();
        animateCountUps();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeEventsUi);
    } else {
        initializeEventsUi();
    }

    window.addEventListener('resize', hideWheelNameTooltip);
    window.addEventListener('scroll', hideWheelNameTooltip, true);

    document.addEventListener('pointerdown', function (event) {
        if (!wheelNameTooltipTarget) return;
        if (event.target.closest('[data-ui-events-wheel-tooltip]')) return;
        hideWheelNameTooltip();
    });

    document.addEventListener('click', function (event) {
        var tabButton = event.target.closest('[data-ui-events-tab]');
        if (!tabButton) return;

        var root = tabButton.closest('[data-ui-events-tabs-root]');
        if (!root) return;

        event.preventDefault();
        activateEventsTab(root, tabButton.getAttribute('data-ui-events-tab'));
    });

    var eventsWheelAudioContext = null;

    function fireGuaranteedConfetti(isPremium) {
        var result = document.querySelector('[data-ui-events-result]');
        if (!result) {
            return;
        }

        result.classList.remove('is-celebrating', 'is-premium-celebrating');
        void result.offsetWidth;
        result.classList.add(isPremium ? 'is-premium-celebrating' : 'is-celebrating');
        if (isPremium) {
            window.setTimeout(function () {
                result.classList.remove('is-premium-celebrating');
            }, 1900);
            return;
        }

        window.setTimeout(function () {
            result.classList.remove('is-celebrating');
        }, 1500);
    }

    function prefersReducedMotion() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function eventsSettingNumber(key, fallback, min, max) {
        var value = window.eventsSettings && window.eventsSettings[key] !== undefined
            ? parseInt(window.eventsSettings[key], 10)
            : fallback;

        if (!Number.isFinite(value)) {
            value = fallback;
        }

        return Math.min(max, Math.max(min, value));
    }

    function eventsSettingBool(key, fallback) {
        if (!window.eventsSettings || window.eventsSettings[key] === undefined) {
            return fallback;
        }

        return window.eventsSettings[key] !== false && window.eventsSettings[key] !== 'false';
    }

    function mergeWheelSettings(settings) {
        if (!settings || typeof settings !== 'object') return;
        window.eventsSettings = window.eventsSettings || {};
        ['wheelSpinSpeedLevel', 'wheelSpinDurationMs', 'wheelSpinSpeedMultiplier', 'wheelSpinSoundEnabled', 'wheelSpinSoundVolume', 'wheelResultSoundEnabled', 'wheelResultSoundVolume'].forEach(function (key) {
            if (settings[key] !== undefined) {
                window.eventsSettings[key] = settings[key];
            }
        });
    }

    function wheelSpinSpeedLevel() {
        return eventsSettingNumber('wheelSpinSpeedLevel', 5, 1, 10);
    }

    function wheelSpinDurationMs() {
        if (window.eventsSettings && window.eventsSettings.wheelSpinDurationMs !== undefined) {
            return eventsSettingNumber('wheelSpinDurationMs', 7500, 4000, 10300);
        }

        var speed = wheelSpinSpeedMultiplier();
        var t = (speed - 1) / 99;
        return 7500 - (t * 5000);
    }

    function wheelSpinSpeedMultiplier() {
        return eventsSettingNumber('wheelSpinSpeedMultiplier', 1, 1, 100);
    }

    function remainingSpinDelay(startedAt, durationMs) {
        return Math.max(0, durationMs - (Date.now() - startedAt));
    }

    function easeOutQuart(progress) {
        var p = 1 - progress;
        return 1 - (p * p * p * p);
    }

    function updateWheelSpin(wheel) {
        if (!wheel) return { totalDegrees: 0, actualDurationMs: 5000 };

        if (wheel._eventsSpinFrame) {
            window.cancelAnimationFrame(wheel._eventsSpinFrame);
            wheel._eventsSpinFrame = null;
        }
        if (wheel._eventsSpinAnimation && typeof wheel._eventsSpinAnimation.cancel === 'function') {
            wheel._eventsSpinAnimation.cancel();
            wheel._eventsSpinAnimation = null;
        }
        if (wheel._eventsSpinFinishTimer) {
            window.clearTimeout(wheel._eventsSpinFinishTimer);
            wheel._eventsSpinFinishTimer = null;
        }
        if (wheel._eventsSpinAnimationEnd) {
            wheel.removeEventListener('animationend', wheel._eventsSpinAnimationEnd);
            wheel._eventsSpinAnimationEnd = null;
        }

        var speedLevel = wheelSpinSpeedLevel();
        var actualDurationMs = wheelSpinDurationMs();
        var totalDegrees = 4320;

        wheel.classList.remove('is-settled', 'is-spinning');
        for (var speedIndex = 1; speedIndex <= 10; speedIndex++) {
            wheel.classList.remove('ui-events-wheel-speed-' + speedIndex);
        }
        wheel.setAttribute('data-ui-events-rotation', String(totalDegrees));
        void wheel.offsetWidth;
        wheel.classList.add('ui-events-wheel-speed-' + speedLevel);
        wheel.classList.add('is-spinning');

        var spinFinished = false;
        var finishSpin = function () {
            if (spinFinished) return;
            spinFinished = true;
            wheel.classList.remove('is-spinning');
            for (var finishSpeedIndex = 1; finishSpeedIndex <= 10; finishSpeedIndex++) {
                wheel.classList.remove('ui-events-wheel-speed-' + finishSpeedIndex);
            }
            wheel.classList.add('is-settled');
            wheel._eventsSpinAnimation = null;
            if (wheel._eventsSpinAnimationEnd) {
                wheel.removeEventListener('animationend', wheel._eventsSpinAnimationEnd);
                wheel._eventsSpinAnimationEnd = null;
            }
            if (wheel._eventsSpinFinishTimer) {
                window.clearTimeout(wheel._eventsSpinFinishTimer);
            }
            wheel._eventsSpinFinishTimer = null;
        };

        wheel._eventsSpinAnimationEnd = function (event) {
            if (event.target === wheel) {
                finishSpin();
            }
        };
        wheel.addEventListener('animationend', wheel._eventsSpinAnimationEnd);
        wheel._eventsSpinFinishTimer = window.setTimeout(finishSpin, actualDurationMs + 120);
        
        return { totalDegrees: totalDegrees, actualDurationMs: actualDurationMs };
    }

    function getWheelAudioContext() {
        var AudioContextCtor = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextCtor) return null;

        if (!eventsWheelAudioContext) {
            eventsWheelAudioContext = new AudioContextCtor();
        }

        return eventsWheelAudioContext;
    }

    function unlockWheelAudioContext() {
        if (!eventsSettingBool('soundsEnabled', true)) return;
        if (!eventsSettingBool('wheelSpinSoundEnabled', true) && !eventsSettingBool('wheelResultSoundEnabled', true)) return;
        var context = getWheelAudioContext();
        if (!context || context.state !== 'suspended') return;
        context.resume().catch(function () {});
    }

    function scheduleWheelTick(context, output, tickTime, index, strength) {
        var osc = context.createOscillator();
        var clickOsc = context.createOscillator();
        var gain = context.createGain();
        var clickGain = context.createGain();

        // Main body of the tick (tiz/high-pitched sine)
        osc.type = 'sine';
        osc.frequency.setValueAtTime(1200, tickTime);
        osc.frequency.exponentialRampToValueAtTime(400, tickTime + 0.015);

        gain.gain.setValueAtTime(0, tickTime);
        gain.gain.linearRampToValueAtTime(strength * 0.9, tickTime + 0.002);
        gain.gain.exponentialRampToValueAtTime(0.001, tickTime + 0.02);

        // Sharp initial click (very high frequency snap)
        clickOsc.type = 'square';
        clickOsc.frequency.setValueAtTime(3500 + (index % 2) * 400, tickTime);
        clickOsc.frequency.exponentialRampToValueAtTime(1200, tickTime + 0.005);

        clickGain.gain.setValueAtTime(0, tickTime);
        clickGain.gain.linearRampToValueAtTime(strength * 0.5, tickTime + 0.001);
        clickGain.gain.exponentialRampToValueAtTime(0.001, tickTime + 0.008);

        var filter = context.createBiquadFilter();
        filter.type = 'bandpass';
        filter.frequency.setValueAtTime(2800, tickTime);
        filter.Q.setValueAtTime(1.5, tickTime);

        osc.connect(gain);
        clickOsc.connect(filter);
        filter.connect(clickGain);
        
        gain.connect(output);
        clickGain.connect(output);

        osc.start(tickTime);
        osc.stop(tickTime + 0.025);
        clickOsc.start(tickTime);
        clickOsc.stop(tickTime + 0.015);
    }

    function wheelTickDegrees(wheel) {
        var inner = wheel ? wheel.querySelector('[data-ui-events-wheel-inner]') : null;
        if (!inner) return 30;

        var configuredDegrees = readEventFloat(inner, 'data-ui-events-deg-per-slice', 30);
        if (!Number.isFinite(configuredDegrees) || configuredDegrees <= 0) return 30;

        return Math.max(8, Math.min(360, configuredDegrees));
    }

    function playWheelTrr(durationMs, totalDegrees, wheel) {
        if (!eventsSettingBool('soundsEnabled', true)) return;
        if (!eventsSettingBool('wheelSpinSoundEnabled', true)) return;
        var volume = eventsSettingNumber('wheelSpinSoundVolume', 55, 1, 100) / 100;
        if (volume <= 0) return;

        var context = getWheelAudioContext();
        if (!context) return;

        var schedule = function () {
            var master = context.createGain();
            var startTime = context.currentTime;
            var duration = durationMs / 1000;

            master.gain.setValueAtTime(0.0001, startTime);
            master.gain.exponentialRampToValueAtTime(0.9 * volume, startTime + 0.035);
            master.gain.setValueAtTime(0.9 * volume, Math.max(startTime + 0.035, startTime + duration - 0.1));
            master.gain.exponentialRampToValueAtTime(0.0001, startTime + duration);
            master.connect(context.destination);

            var degreesPerTick = wheelTickDegrees(wheel);
            var actualDegrees = totalDegrees || (durationMs * 3);
            var totalTicks = Math.floor(actualDegrees / degreesPerTick);
            
            var lastTickTime = -1;
            var minTickInterval = 0.025; // max 40 ticks per sec to prevent audio crash/freeze
            
            for (var i = 0; i < totalTicks; i++) {
                var currentDeg = i * degreesPerTick;
                var progress = currentDeg / actualDegrees;
                // Perfect sync with easeOutQuart: 1 - (1-p)^4
                var t = duration * (1 - Math.pow(Math.max(0, 1 - progress), 0.25));
                
                // Ensure late stage ticks are played precisely
                var isLateStage = progress > 0.85;
                
                if (t - lastTickTime >= minTickInterval || (isLateStage && (t - lastTickTime) >= 0.015)) {
                    var tickTime = startTime + t;
                    var hitStrength = (0.3 + (1 - progress) * 0.7) * volume;
                    scheduleWheelTick(context, master, tickTime, i, hitStrength);
                    lastTickTime = t;
                }
            }

            window.setTimeout(function () {
                master.disconnect();
            }, (duration * 1000) + 120);
        };

        if (context.state === 'suspended') {
            context.resume().then(schedule).catch(function () {});
            return;
        }

        schedule();
    }
    function playWheelResultSound() {
        if (!eventsSettingBool('soundsEnabled', true)) return;
        if (!eventsSettingBool('wheelResultSoundEnabled', true)) return;
        var volume = eventsSettingNumber('wheelResultSoundVolume', 70, 1, 100) / 100;
        var context = getWheelAudioContext();
        if (!context) return;
        var schedule = function () {
            var now = context.currentTime;

            var osc1 = context.createOscillator();
            var osc2 = context.createOscillator();
            var gain = context.createGain();

            osc1.type = 'triangle';
            osc2.type = 'sine';

            osc1.frequency.setValueAtTime(523.25, now);
            osc1.frequency.setValueAtTime(659.25, now + 0.15);
            osc1.frequency.setValueAtTime(783.99, now + 0.3);
            osc1.frequency.setValueAtTime(1046.50, now + 0.45);

            osc2.frequency.setValueAtTime(261.63, now);
            osc2.frequency.setValueAtTime(329.63, now + 0.15);
            osc2.frequency.setValueAtTime(392.00, now + 0.3);
            osc2.frequency.setValueAtTime(523.25, now + 0.45);

            gain.gain.setValueAtTime(0, now);
            gain.gain.linearRampToValueAtTime(0.28 * volume, now + 0.05);
            gain.gain.setValueAtTime(0.28 * volume, now + 0.6);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 1.2);

            osc1.connect(gain);
            osc2.connect(gain);
            gain.connect(context.destination);

            osc1.start(now);
            osc2.start(now);
            osc1.stop(now + 1.2);
            osc2.stop(now + 1.2);
        };

        if (context.state === 'suspended') {
            context.resume().then(schedule).catch(function () {});
            return;
        }

        schedule();
    }

    function playTaskClaimSuccessSound() {
        if (!eventsSettingBool('soundsEnabled', true)) return;
        var context = getWheelAudioContext();
        if (!context) return;
        var schedule = function () {
            var now = context.currentTime;
            var osc = context.createOscillator();
            var gain = context.createGain();

            osc.type = 'sine';
            osc.frequency.setValueAtTime(523.25, now); // C5
            osc.frequency.setValueAtTime(659.25, now + 0.08); // E5
            osc.frequency.setValueAtTime(783.99, now + 0.16); // G5
            osc.frequency.setValueAtTime(1046.50, now + 0.24); // C6

            gain.gain.setValueAtTime(0, now);
            gain.gain.linearRampToValueAtTime(0.25, now + 0.05);
            gain.gain.setValueAtTime(0.25, now + 0.28);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.45);

            osc.connect(gain);
            gain.connect(context.destination);

            osc.start(now);
            osc.stop(now + 0.5);
        };

        if (context.state === 'suspended') {
            context.resume().then(schedule).catch(function () {});
            return;
        }
        schedule();
    }

    var eventsWheelSpinInFlight = false;

    document.addEventListener('click', async function (event) {
        var spinButton = event.target.closest('[data-ui-events-spin]');
        if (!spinButton) return;

        event.preventDefault();
        if (eventsWheelSpinInFlight || spinButton.disabled || spinButton.dataset.uiEventsSpinPending === '1') {
            return;
        }

        var wheel = document.querySelector('[data-ui-events-wheel]');
        var result = document.querySelector('[data-ui-events-result]');
        var spinScope = spinButton.closest('.ui-events-wheel-stage') || document.documentElement;
        if (spinScope.dataset && spinScope.dataset.uiEventsSpinPending === '1') {
            return;
        }

        eventsWheelSpinInFlight = true;
        spinButton.dataset.uiEventsSpinPending = '1';
        if (spinScope.dataset) {
            spinScope.dataset.uiEventsSpinPending = '1';
        }
        spinButton.disabled = true;
        showResult(result, 'Çark hakkı kontrol ediliyor...', 'info');
        unlockWheelAudioContext();

        var releaseSpinLock = function (keepDisabled) {
            eventsWheelSpinInFlight = false;
            delete spinButton.dataset.uiEventsSpinPending;
            if (spinScope.dataset) {
                delete spinScope.dataset.uiEventsSpinPending;
            }
            spinButton.disabled = keepDisabled === true;
            updateWheelUsageCountdowns();
        };

        try {
            var data = await postJson(baseUri() + '/events/api/wheel-spin', {_token: csrfToken()});
            var reward = data.reward || {};
            mergeWheelSettings(data.wheel_settings);

            var spinStartedAt = Date.now();
            showResult(result, 'Çark dönüyor...', 'info');
            
            var spinInfo = updateWheelSpin(wheel);
            var totalDegrees = spinInfo.totalDegrees;
            var actualDurationMs = spinInfo.actualDurationMs;
            
            playWheelTrr(actualDurationMs, totalDegrees, wheel);

            setTimeout(function () {
                renderWheelResultCard(result, reward, data);
                applyWheelUsageState(data.wheel_usage, spinButton);
                releaseSpinLock(data.wheel_usage ? !wheelUsageCanSpinNow(data.wheel_usage) : false);

                playWheelResultSound();

                var isPremium = reward && (reward.is_premium === true || reward.is_premium === 1 || reward.is_premium === '1');
                fireGuaranteedConfetti(isPremium);
                eventsToast('Kazandınız: ' + (reward.name || 'Ödül'), 'success');
            }, remainingSpinDelay(spinStartedAt, actualDurationMs));
        } catch (error) {
            var errorMessage = error.message || '\u00C7ark \u00E7evrilemedi.';
            showResult(result, errorMessage, 'error');
            eventsToast(errorMessage, 'error');
            if (error.data && error.data.wheel_usage) {
                applyWheelUsageState(error.data.wheel_usage, spinButton);
            }
            releaseSpinLock(error.data && error.data.wheel_usage ? !wheelUsageCanSpinNow(error.data.wheel_usage) : false);
        }
    }, true);

    document.addEventListener('click', async function (event) {
        var joinButton = event.target.closest('[data-ui-events-raffle-join]');
        if (!joinButton) return;

        joinButton.disabled = true;
        try {
            var data = await postJson(baseUri() + '/events/api/raffle-join', {
                _token: csrfToken(),
                raffle_id: joinButton.getAttribute('data-ui-events-raffle-join')
            });
            joinButton.textContent = 'Katılım alındı';
            joinButton.classList.add('is-disabled');
            var raffleCard = joinButton.closest('.ui-events-raffle-card');
            if (raffleCard) {
                raffleCard.classList.add('is-just-joined');
                var participation = raffleCard.querySelector('.ui-events-raffle-participation-state');
                renderInlineStatus(participation, 'Katıldın', 'success');
                var entryCount = raffleCard.querySelector('[data-ui-events-raffle-entry-count]');
                if (entryCount) {
                    var currentCount = parseInt(entryCount.textContent, 10) || 0;
                    entryCount.textContent = String(currentCount + 1);
                }
                var remaining = data.entry && data.entry.remaining_entries !== undefined ? data.entry.remaining_entries : null;
                if (remaining !== null) {
                    var limit = raffleCard.querySelector('[data-ui-events-raffle-limit]');
                    if (limit) limit.textContent = 'Kalan hakkın: ' + remaining;
                }
            }
            eventsToast('Çekilişe başarıyla katıldınız.', 'success');
        } catch (error) {
            joinButton.disabled = false;
            renderInlineStatus(inlineStatusForAction(joinButton), error.message, 'error');
            eventsToast(error.message, 'error');
        }
    });

    document.addEventListener('click', async function (event) {
        var claimButton = event.target.closest('[data-ui-events-claim-reward]');
        if (!claimButton) return;

        var confirmed = await confirmElement(claimButton);
        if (!confirmed) return;

        claimButton.disabled = true;
        try {
            await postJson(baseUri() + '/events/api/claim-reward', {
                _token: csrfToken(),
                reward_id: claimButton.getAttribute('data-ui-events-claim-reward')
            });
            claimButton.textContent = 'Teslim edildi';
            claimButton.classList.add('is-disabled');
            renderInlineStatus(inlineStatusForAction(claimButton), 'Teslim edildi', 'success');
            eventsToast('Ödül teslim edildi.', 'success');
        } catch (error) {
            claimButton.disabled = false;
            renderInlineStatus(inlineStatusForAction(claimButton), error.message, 'error');
            eventsToast(error.message, 'error');
        }
    });

    document.addEventListener('click', async function (event) {
        var taskButton = event.target.closest('[data-ui-events-task-claim]');
        if (!taskButton) return;

        taskButton.disabled = true;
        try {
            await postJson(baseUri() + '/events/api/task-claim', {
                _token: csrfToken(),
                task_id: taskButton.getAttribute('data-ui-events-task-claim'),
                period_key: taskButton.getAttribute('data-ui-events-period') || ''
            });
            taskButton.textContent = 'Ödül alındı';
            taskButton.classList.add('is-disabled');
            renderInlineStatus(inlineStatusForAction(taskButton), 'Alındı', 'success');
            eventsToast('Görev ödülü alındı.', 'success');
            playTaskClaimSuccessSound();
            window.setTimeout(function () {
                window.location.reload();
            }, 1000);
        } catch (error) {
            taskButton.disabled = false;
            renderInlineStatus(inlineStatusForAction(taskButton), error.message, 'error');
            eventsToast(error.message, 'error');
        }
    });
    function pollToastNotifications() {
        if (typeof window.showToast !== 'function') return;

        fetch(baseUri() + '/events/api/notifications?toast_poll=1')
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data && data.success && data.payload && Array.isArray(data.payload.toasts)) {
                    data.payload.toasts.forEach(function(toast) {
                        var typeMap = {
                            'reward_claimed': 'success',
                            'raffle_win': 'success',
                            'wheel_win': 'success',
                            'task_completed': 'success',
                            'default': 'info'
                        };
                        var type = typeMap[toast.type] || 'success';
                        eventsToast(toast.message || toast.title, type);
                    });
                }
            })
            .catch(function(err) {});
    }

    // Start polling using dynamic interval
    if (typeof window.showToast === 'function') {
        var interval = window.eventsSettings && window.eventsSettings.pollingInterval ? window.eventsSettings.pollingInterval : 15000;
        setInterval(pollToastNotifications, interval);
        // Do an initial poll after a short delay
        setTimeout(pollToastNotifications, 2000);
    }

    // --- PUAN HISTORY FILTERING ---
    var filterButtons = document.querySelectorAll('.ui-events-filter-btn');
    var historyList = document.getElementById('ui-events-history-list');

    if (filterButtons.length > 0 && historyList) {
        filterButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var filter = this.getAttribute('data-filter');
                var now = new Date();
                var todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                var weekStart = new Date(now);
                weekStart.setDate(weekStart.getDate() - weekStart.getDay());
                var monthStart = new Date(now.getFullYear(), now.getMonth(), 1);

                // Update active button
                filterButtons.forEach(function(b) { b.classList.remove('is-active'); });
                btn.classList.add('is-active');

                // Filter history items
                var items = historyList.querySelectorAll('[data-history-date]');
                items.forEach(function(item) {
                    var dateStr = item.getAttribute('data-history-date');
                    var itemDate = new Date(dateStr);
                    var show = false;

                    if (filter === 'all') {
                        show = true;
                    } else if (filter === 'today') {
                        show = itemDate >= todayStart && itemDate < new Date(todayStart.getTime() + 86400000);
                    } else if (filter === 'week') {
                        var weekEnd = new Date(weekStart.getTime() + 604800000);
                        show = itemDate >= weekStart && itemDate < weekEnd;
                    } else if (filter === 'month') {
                        var monthEnd = new Date(now.getFullYear(), now.getMonth() + 1, 1);
                        show = itemDate >= monthStart && itemDate < monthEnd;
                    }

                    item.hidden = !show;
                });
            });
        });
    }

    // --- REALTIME COUNTDOWNS ---
    function updateRaffleCountdowns() {
        document.querySelectorAll('[data-ui-events-countdown]').forEach(function (el) {
            var dateStr = el.getAttribute('data-ui-events-countdown');
            if (!dateStr) return;

            var targetTime = new Date(dateStr.replace(' ', 'T')).getTime();
            if (Number.isNaN(targetTime)) return;

            var diff = targetTime - Date.now();
            var textEl = el.querySelector('.ui-events-countdown-text');
            if (!textEl) return;

            if (diff <= 0) {
                textEl.textContent = 'Süre doldu';
                el.classList.remove('is-urgent');
                el.classList.add('is-finished');
                return;
            }

            textEl.textContent = formatEventsReadableDuration(Math.ceil(diff / 1000), 'Süre doldu');

            if (diff < 6 * 60 * 60 * 1000) {
                el.classList.add('is-urgent');
            } else {
                el.classList.remove('is-urgent');
            }
            el.classList.remove('is-finished');
        });
    }

    if (document.querySelectorAll('[data-ui-events-countdown]').length > 0) {
        setInterval(updateRaffleCountdowns, 1000);
        updateRaffleCountdowns();
    }
    if (document.querySelectorAll('[data-ui-events-wheel-usage]').length > 0) {
        setInterval(updateWheelUsageCountdowns, 1000);
        updateWheelUsageCountdowns();
    }
})();
