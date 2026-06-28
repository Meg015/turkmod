document.querySelectorAll('.route-filter-subtab-btn').forEach(btn => {
                                    btn.addEventListener('click', function() {
                                        const tabId = this.getAttribute('data-route-tab');
                                        if(!tabId) return;
                                        document.querySelectorAll('.route-filter-subtab-panel').forEach(panel => {
                                            if(!panel.classList.contains('cron-subtab-panel')) {
                                                panel.classList.remove('is-active');
                                            }
                                        });
                                        document.getElementById(tabId).classList.add('is-active');
                                        document.querySelectorAll('.route-filter-subtab-btn').forEach(b => {
                                            if(!b.classList.contains('cron-subtab-btn')) {
                                                b.classList.remove('active');
                                            }
                                        });
                                        this.classList.add('active');
                                    });
                                });
