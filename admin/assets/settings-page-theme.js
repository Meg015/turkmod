document.querySelectorAll('.cron-subtab-btn').forEach(btn => {
                                    btn.addEventListener('click', function() {
                                        const tabId = this.getAttribute('data-cron-tab');
                                        if(!tabId) return;
                                        document.querySelectorAll('.cron-subtab-panel').forEach(panel => {
                                            panel.classList.remove('is-active');
                                        });
                                        document.getElementById(tabId).classList.add('is-active');
                                        document.querySelectorAll('.cron-subtab-btn').forEach(b => {
                                            b.classList.remove('active');
                                        });
                                        this.classList.add('active');
                                    });
                                });
