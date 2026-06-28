document.querySelectorAll('.file-manager-subtab-btn').forEach(btn => {
                                btn.addEventListener('click', function() {
                                    const tabId = this.getAttribute('data-file-manager-tab');
                                    document.querySelectorAll('.file-manager-subtab-panel').forEach(panel => {
                                        panel.classList.remove('is-active');
                                    });
                                    document.getElementById(tabId)?.classList.add('is-active');
                                    document.querySelectorAll('.file-manager-subtab-btn').forEach(b => {
                                        b.classList.remove('active');
                                    });
                                    this.classList.add('active');
                                });
                            });
