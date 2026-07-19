const topicReportFocusSelector = 'a[href], button:not([disabled]), textarea:not([disabled]), select:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])';
        let topicReportLastTrigger = null;
        let topicReportController = null;

        function openTopicReportModal(modal, trigger) {
            topicReportLastTrigger = trigger || document.activeElement;
            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
            if (window.TMUI && typeof window.TMUI.openDialog === 'function') {
                topicReportController = window.TMUI.openDialog(modal, {
                    bodyClass: 'topic-report-modal-open',
                    initialFocus: 'input[name="reporter_name"], select[name="reason"]',
                    returnFocus: topicReportLastTrigger,
                    onClose: function () {
                        topicReportController = null;
                    }
                });
                return;
            }
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('topic-report-modal-open');
            const first = modal.querySelector(topicReportFocusSelector);
            if (first) first.focus();
        }

        function closeTopicReportModal(modal) {
            if (topicReportController && typeof topicReportController.close === 'function') {
                topicReportController.close(true);
                return;
            }
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('topic-report-modal-open');
            if (topicReportLastTrigger && typeof topicReportLastTrigger.focus === 'function') {
                topicReportLastTrigger.focus();
            }
        }

        document.addEventListener('click', function(event) {
            const modal = document.getElementById('topicReportModal');
            if (!modal) return;
            const opener = event.target.closest('[data-report-modal-open]');
            if (opener) {
                openTopicReportModal(modal, opener);
            }
            if (event.target.closest('[data-report-modal-close]')) {
                closeTopicReportModal(modal);
            }
});
        document.addEventListener('keydown', function(event) {
            if (window.TMUI) return;
            const modal = document.getElementById('topicReportModal');
            if (!modal || modal.hidden) return;

            if (event.key === 'Escape') {
                closeTopicReportModal(modal);
                return;
            }

            if (event.key !== 'Tab') return;
            const focusables = Array.from(modal.querySelectorAll(topicReportFocusSelector)).filter(function(el) {
                return el.offsetParent !== null || el === document.activeElement;
            });
            if (!focusables.length) return;
            const first = focusables[0];
            const last = focusables[focusables.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });

        document.addEventListener('submit', function(event) {
            const form = event.target.closest('.topic-report-form');
            if (!form) return;
            event.preventDefault();
            const feedback = form.querySelector('.topic-report-feedback');
            const button = form.querySelector('button[type="submit"]');
            const original = button.innerHTML;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            const loadingLabel = button.getAttribute('data-loading-label') || 'Gönderiliyor...';
            button.innerHTML = '<i class="bi bi-hourglass-split" aria-hidden="true"></i> ' + loadingLabel;
            const body = Object.fromEntries(new FormData(form).entries());
            const endpoint = form.getAttribute('action');
            const requestOptions = {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                body: body
            };
            (window.publicFetchJson
                ? window.publicFetchJson(endpoint, Object.assign({}, requestOptions, { notifyError: false })).then(function(payload) {
                    return {ok: true, payload: payload};
                })
                : Promise.reject(new Error('Public API helper yuklenemedi.'))
            ).then(function(result) {
                const isSuccess = !!(result.ok && result.payload.success);
                const message = result.payload.message || (isSuccess ? 'Rapor gönderildi.' : 'Rapor gönderilemedi.');
                feedback.textContent = message;
                feedback.className = 'topic-report-feedback ' + (isSuccess ? 'is-success' : 'is-error');
                if (isSuccess) {
                    form.reset();
                    const modal = document.getElementById('topicReportModal');
                    if (modal) {
                        closeTopicReportModal(modal);
                    }
                    if (window.showToast) {
                        window.showToast(message, 'success', {
                            detail: 'İnceleme kuyruğuna alındı.'
                        });
                    }
                    return;
                }
                if (window.showToast) {
                    window.showToast(message, 'error', {
                        solution: 'Lütfen alanları kontrol edip tekrar deneyin.'
                    });
                }
            }).catch(function(error) {
                feedback.textContent = error && error.message ? error.message : 'Bağlantı hatası. Lütfen tekrar deneyin.';
                feedback.className = 'topic-report-feedback is-error';
                if (window.showToast) {
                    window.showToast('Rapor gönderilemedi.', 'error', {
                        solution: 'Bağlantınızı kontrol edip tekrar deneyin. Sorun sürerse sayfayı yenileyin.'
                    });
                }
            }).finally(function() {
                button.disabled = false;
                button.removeAttribute('aria-busy');
                button.innerHTML = original;
            });
        });
