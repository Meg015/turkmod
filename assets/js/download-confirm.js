(function() {
    const card = document.querySelector('[data-download-confirm]');
    if (!card) return;

    const href = card.dataset.confirmHref || '';
    const countdownEl = document.getElementById('downloadConfirmCountdown');
    const primary = card.querySelector('[data-download-confirm-primary]');
    const primaryText = card.querySelector('[data-download-confirm-primary-text]');
    const autoRedirectEnabled = card.dataset.autoRedirectEnabled !== '0';
    const primaryLabel = card.dataset.primaryLabel || (primaryText ? primaryText.textContent : 'Hedefe Git');
    const countdownLabel = card.dataset.primaryCountdownLabel || 'Hedefe Git ({{seconds}})';
    const redirectingLabel = card.dataset.redirectingLabel || 'Yönlendiriliyor...';
    let remaining = Math.max(0, parseInt(card.dataset.autoRedirectSeconds || '0', 10) || 0);
    let redirected = false;

    const formatLabel = function(template, seconds) {
        return String(template || '')
            .replace(/\{+\s*missing:\s*seconds\s*\}+/gi, '{{seconds}}')
            .replace(/\(\(\s*missing:\s*seconds\s*\)\)/gi, '({{seconds}})')
            .replace(/\(\s*missing:\s*seconds\s*\)/gi, '{{seconds}}')
            .replace(/\bmissing:\s*seconds\b/gi, '{{seconds}}')
            .replace(/\{\{\{\s*seconds\s*\}\}\}/g, String(seconds))
            .replace(/\{\{\s*\}\}/g, String(seconds))
            .replace(/\{\{\s*seconds\s*\}\}/g, String(seconds))
            .replace(/\{\s*seconds\s*\}/g, String(seconds))
            .replace(/\{+\s*seconds\s*\}+/g, String(seconds))
            .replace(/\{+\s*\}+/g, String(seconds))
            .replace(/\{\s*\}/g, String(seconds))
            .replace(/\(\(\s*(\d+)\s*\)\)/g, '($1)');
    };

    const go = function() {
        if (redirected || href === '') return;
        redirected = true;
        window.location.href = href;
    };

    const update = function() {
        if (countdownEl) countdownEl.textContent = String(remaining);
        if (!primaryText) return;

        if (!autoRedirectEnabled) {
            primaryText.textContent = primaryLabel;
            return;
        }

        primaryText.textContent = remaining > 0 ? formatLabel(countdownLabel, remaining) : redirectingLabel;
    };

    if (primary) {
        primary.addEventListener('click', function() {
            redirected = true;
        });
    }

    update();
    if (!autoRedirectEnabled) {
        return;
    }

    if (remaining <= 0) {
        window.setTimeout(go, 150);
        return;
    }

    const timer = window.setInterval(function() {
        remaining -= 1;
        update();
        if (remaining <= 0) {
            window.clearInterval(timer);
            go();
        }
    }, 1000);
})();
