document.querySelectorAll('.rate-limit-subtab-btn').forEach(btn => {
                                    btn.addEventListener('click', function() {
                                        const tabId = this.getAttribute('data-rate-tab');
                                        document.querySelectorAll('.rate-limit-subtab-panel').forEach(panel => {
                                            panel.classList.remove('is-active');
                                        });
                                        document.getElementById(tabId).classList.add('is-active');
                                        document.querySelectorAll('.rate-limit-subtab-btn').forEach(b => {
                                            b.classList.remove('active');
                                        });
                                        this.classList.add('active');
                                    });
                                });
