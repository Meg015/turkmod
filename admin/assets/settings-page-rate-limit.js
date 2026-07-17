(function () {
    'use strict';

    function numberValue(input) {
        if (!input) {
            return 0;
        }

        var value = parseInt(String(input.value || '').trim(), 10);
        return Number.isFinite(value) ? value : 0;
    }

    function minuteText(minutes) {
        return minutes === 1 ? '1 dakika' : minutes + ' dakika';
    }

    function setSummary(rule, text) {
        var summary = rule.querySelector('[data-rate-summary-text]');
        if (summary) {
            summary.textContent = text;
        }
    }

    function updatePairRule(rule) {
        var limit = numberValue(rule.querySelector('[data-rate-role="limit"]'));
        var windowMinutes = numberValue(rule.querySelector('[data-rate-role="window"]'));
        var action = rule.getAttribute('data-rate-action') || 'işlem';
        var zeroSummary = rule.getAttribute('data-rate-zero-summary') || '';

        if (limit <= 0 && zeroSummary !== '') {
            setSummary(rule, zeroSummary);
            return;
        }

        if (windowMinutes <= 0) {
            setSummary(rule, 'Süre 1 dakika veya daha yüksek olmalı.');
            return;
        }

        setSummary(rule, minuteText(windowMinutes) + ' içinde en fazla ' + limit + ' ' + action + '.');
    }

    function updateFixedRule(rule) {
        var limit = numberValue(rule.querySelector('[data-rate-role="single"]'));
        var action = rule.getAttribute('data-rate-action') || 'işlem';
        var windowLabel = rule.getAttribute('data-rate-window-label') || '';
        var zeroSummary = rule.getAttribute('data-rate-zero-summary') || '';

        if (limit <= 0 && zeroSummary !== '') {
            setSummary(rule, zeroSummary);
            return;
        }

        if (windowLabel !== '') {
            setSummary(rule, windowLabel + ' içinde en fazla ' + limit + ' ' + action + '.');
            return;
        }

        setSummary(rule, 'Limit değeri: ' + limit + '.');
    }

    function updateCooldownRule(rule) {
        var minutes = numberValue(rule.querySelector('[data-rate-role="single"]'));
        var zeroSummary = rule.getAttribute('data-rate-zero-summary') || '';

        if (minutes <= 0 && zeroSummary !== '') {
            setSummary(rule, zeroSummary);
            return;
        }

        setSummary(rule, 'Her yeni mesajdan önce ' + minuteText(minutes) + ' bekleme uygulanır.');
    }

    function updateSwitchRule(rule) {
        var input = rule.querySelector('[data-rate-role="switch"]');
        var onSummary = rule.getAttribute('data-rate-on-summary') || 'Ayar açık.';
        var offSummary = rule.getAttribute('data-rate-off-summary') || 'Ayar kapalı.';
        setSummary(rule, input && input.checked ? onSummary : offSummary);
    }

    function updateRule(rule) {
        var mode = rule.getAttribute('data-rate-summary-mode') || 'fixed';
        if (mode === 'pair') {
            updatePairRule(rule);
        } else if (mode === 'cooldown') {
            updateCooldownRule(rule);
        } else if (mode === 'switch') {
            updateSwitchRule(rule);
        } else {
            updateFixedRule(rule);
        }
    }

    document.querySelectorAll('.rate-limit-subtab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tabId = this.getAttribute('data-rate-tab');
            document.querySelectorAll('.rate-limit-subtab-panel').forEach(function (panel) {
                panel.classList.remove('is-active');
            });
            var target = document.getElementById(tabId);
            if (target) {
                target.classList.add('is-active');
            }
            document.querySelectorAll('.rate-limit-subtab-btn').forEach(function (item) {
                item.classList.remove('active');
            });
            this.classList.add('active');
        });
    });

    document.querySelectorAll('.rate-limit-rule').forEach(function (rule) {
        rule.querySelectorAll('input').forEach(function (input) {
            input.addEventListener('input', function () { updateRule(rule); });
            input.addEventListener('change', function () { updateRule(rule); });
        });
        updateRule(rule);
    });
})();
