document.querySelectorAll('.topic-dl-section .topic-dl-card').forEach(function (card) {
    if (card.dataset.downloadHandlerBound === '1') return;
    card.dataset.downloadHandlerBound = '1';
    card.addEventListener('click', function (event) {
        event.preventDefault();
        if (card.dataset.ready === '1') {
            window.open(card.href, '_blank', 'noopener');
            return;
        }
        if (card.dataset.counting === '1') return;
        card.dataset.counting = '1';
        const section = card.closest('.topic-dl-section');
        const seconds = Math.max(0, parseInt(section?.dataset.countdownSeconds || '5', 10) || 0);
        const waitText = section?.dataset.waitText || 'İndirme linkiniz kontrol ediliyor, lütfen bekleyiniz';
        const doneText = section?.dataset.doneText || 'İndirme linkiniz hazır, indirmek için tıklayın';
        const action = card.querySelector('.topic-dl-action');
        const button = card.querySelector('.topic-dl-button');
        let remaining = seconds;

        card.classList.add('is-counting');
        card.setAttribute('aria-busy', 'true');
        if (button) button.setAttribute('aria-live', 'polite');
        if (action) action.textContent = waitText + (remaining > 0 ? '... ' + remaining : '...');

        if (remaining <= 0) {
            finishCountdown(card, action, doneText);
            return;
        }

        const timer = setInterval(function () {
            remaining -= 1;
            if (remaining > 0) {
                if (action) action.textContent = waitText + '... ' + remaining;
                return;
            }
            clearInterval(timer);
            finishCountdown(card, action, doneText);
        }, 1000);
    });
});

function finishCountdown(card, action, doneText) {
    card.dataset.ready = '1';
    card.dataset.counting = '0';
    card.removeAttribute('aria-busy');
    card.classList.remove('is-counting');
    card.classList.add('is-ready');
    if (action) action.textContent = doneText;
}
