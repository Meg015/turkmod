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

(function() {
    const cards = document.querySelectorAll('[data-seo-public-page-card]');

    function syncCard(card) {
        const noindex = card.querySelector('[data-seo-public-page-noindex]');
        const sitemap = card.querySelector('[data-seo-public-page-sitemap]');
        const sitemapRow = card.querySelector('[data-seo-public-page-sitemap-row]');
        if (!noindex || !sitemap) {
            return;
        }

        const locked = !!noindex.disabled;
        const blocked = !!noindex.checked || locked;
        sitemap.disabled = blocked;
        sitemap.setAttribute('aria-disabled', blocked ? 'true' : 'false');
        if (blocked) {
            sitemap.checked = false;
        }
        if (sitemapRow) {
            sitemapRow.classList.toggle('is-disabled', blocked);
        }
        if (card) {
            card.classList.toggle('is-noindex', blocked);
        }
    }

    cards.forEach(function(card) {
        const noindex = card.querySelector('[data-seo-public-page-noindex]');
        if (noindex) {
            noindex.addEventListener('change', function() {
                syncCard(card);
            });
        }
        syncCard(card);
    });
})();
