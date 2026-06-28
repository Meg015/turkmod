document.querySelectorAll('.comments-subtab-btn').forEach(btn => {
                                    btn.addEventListener('click', function() {
                                        const tabId = this.getAttribute('data-comments-tab');
                                        document.querySelectorAll('.comments-subtab-panel').forEach(panel => {
                                            panel.classList.remove('is-active');
                                        });
                                        document.getElementById(tabId).classList.add('is-active');
                                        document.querySelectorAll('.comments-subtab-btn').forEach(b => {
                                            b.classList.remove('active');
                                        });
                                        this.classList.add('active');
                                    });
                                });
