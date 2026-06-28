const topicReportFocusSelector = 'a[href], button:not([disabled]), textarea:not([disabled]), select:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])';
        let topicReportLastTrigger = null;
        let topicReportController = null;

        function openTopicReportModal(modal, trigger) {
            topicReportLastTrigger = trigger || document.activeElement;
            if (window.TMUI && typeof window.TMUI.openDialog === 'function') {
                topicReportController = window.TMUI.openDialog(modal, {
                    bodyClass: 'topic-report-modal-open',
                    initialFocus: 'select[name="reason"]',
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
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> Gönderiliyor...';
            const body = Object.fromEntries(new FormData(form).entries());
            const endpoint = form.getAttribute('action');
            fetch(endpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                body: JSON.stringify(body)
            }).then(function(response) {
                return response.json().then(function(payload) {
                    return {ok: response.ok, payload: payload};
                });
            }).then(function(result) {
                feedback.textContent = result.payload.message || (result.ok ? 'Rapor gönderildi.' : 'Rapor gönderilemedi.');
                feedback.className = 'topic-report-feedback ' + (result.ok && result.payload.success ? 'is-success' : 'is-error');
                if (result.ok && result.payload.success) {
                    form.reset();
                    const modal = document.getElementById('topicReportModal');
                    if (modal) {
                        closeTopicReportModal(modal);
                    }
                    if (window.showToast) {
                        window.showToast(result.payload.message || 'Rapor gönderildi.', 'success', {
                            detail: 'İnceleme kuyruğuna alındı.'
                        });
                    }
                }
            }).catch(function() {
                feedback.textContent = 'Bağlantı hatası. Lütfen tekrar deneyin.';
                feedback.className = 'topic-report-feedback is-error';
                if (window.showToast) {
                    window.showToast('Rapor gönderilemedi.', 'error', {
                        solution: 'Bağlantınızı kontrol edip tekrar deneyin. Sorun sürerse sayfayı yenileyin.'
                    });
                }
            }).finally(function() {
                button.disabled = false;
                button.innerHTML = original;
            });
        });
