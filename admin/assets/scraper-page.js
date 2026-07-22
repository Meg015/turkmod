function getAdminScraperConfig() {
    const node = document.getElementById('adminScraperConfig');
    if (!node) {
        return {};
    }
    try {
        return JSON.parse(node.textContent || '{}') || {};
    } catch (error) {
        return {};
    }
}

const adminScraperConfig = getAdminScraperConfig();
const baseUri = adminScraperConfig.baseUri || '';
const botDefaultStatus = adminScraperConfig.botDefaultStatus || 'published';
const botBulkDefaultSelected = adminScraperConfig.botBulkDefaultSelected || '1';
const botBulkMaxTopicsPerPage = adminScraperConfig.botBulkMaxTopicsPerPage || '0';
const botBulkContinueOnError = adminScraperConfig.botBulkContinueOnError || '1';

function setScraperLogVisibility(element, visible) {
    if (!element) return;
    if (window.adminVisibility && typeof window.adminVisibility.set === 'function') {
        window.adminVisibility.set(element, visible, { aria: false });
        return;
    }

    element.hidden = !visible;
}

// Bot Logları Filtreleme Sistemi
function filterLogs(status) {
    const table = document.querySelector('#tab-imports table tbody');
    if (!table) return;

    const rows = table.querySelectorAll('tr[data-log-status]');
    const filterEmptyRow = table.querySelector('tr[data-log-empty="filter"]');
    let visibleCount = 0;

    // Filtreleme butonlarını güncelle
    document.querySelectorAll('.log-filter-btn').forEach(btn => {
        btn.classList.remove('active', 'ui-admin-btn-primary');
        if (btn.dataset.filter === status) {
            btn.classList.add('active');
            if (status !== 'all') {
                btn.classList.add('ui-admin-btn-primary');
            }
        }
    });

    rows.forEach(row => {
        const rowStatus = row.dataset.logStatus || 'other';

        if (status === 'all' || rowStatus === status) {
            setScraperLogVisibility(row, true);
            visibleCount++;
        } else {
            setScraperLogVisibility(row, false);
        }
    });

    if (filterEmptyRow) {
        setScraperLogVisibility(filterEmptyRow, visibleCount === 0 && status !== 'all' && rows.length > 0);
        const statusLabel = filterEmptyRow.querySelector('[data-log-empty-label]');
        if (statusLabel) {
            const statusText = status === 'imported' ? 'yayınlanan' :
                              status === 'failed' ? 'hatalı' :
                              status === 'preview' ? 'önizleme' : 'seçili';
            statusLabel.textContent = `${statusText} durumunda içerik yok`;
        }
    }

    // İstatistik kartlarını güncelle (opsiyonel animasyon)
    const statsCards = document.querySelectorAll('#logStatsCards .admin-surface-card');
    statsCards.forEach((card, index) => {
        const shouldHighlight =
            (status === 'imported' && index === 0) ||
            (status === 'failed' && index === 1) ||
            (status === 'preview' && index === 2) ||
            (status === 'all' && index === 3);

        card.classList.toggle('is-highlighted', shouldHighlight);
    });
}

window.filterLogs = filterLogs;

function initScraperPageFilters() {
    // İlk yüklemede "Tümü" seçili olsun
    const allBtn = document.querySelector('.log-filter-btn[data-filter="all"]');
    if (allBtn) {
        allBtn.classList.add('active');
    }
}

window.adminPage.register('scraper', initScraperPageFilters, {
    id: 'scraper-page:filters',
    selector: '.log-filter-btn'
});
