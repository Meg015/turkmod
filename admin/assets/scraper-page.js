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
    
    const rows = table.querySelectorAll('tr');
    let visibleCount = 0;
    let emptyRow = null;
    
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
        // Boş satırı atla
        if (row.cells.length === 1) {
            emptyRow = row;
            setScraperLogVisibility(row, status === 'all');
            return;
        }
        
        // Durum badge'ini bul
        const statusBadge = row.querySelector('.admin-badge');
        if (!statusBadge) {
            setScraperLogVisibility(row, true);
            return;
        }
        
        const rowStatus = statusBadge.classList.contains('admin-badge-success') ? 'imported' :
                         statusBadge.classList.contains('admin-badge-danger') ? 'failed' :
                         statusBadge.classList.contains('admin-badge-warning') ? 'preview' : 'other';
        
        if (status === 'all' || rowStatus === status) {
            setScraperLogVisibility(row, true);
            visibleCount++;
        } else {
            setScraperLogVisibility(row, false);
        }
    });
    
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
    
    // Sonuç mesajı göster
    if (visibleCount === 0 && status !== 'all') {
        if (emptyRow && emptyRow.cells.length === 1) {
            setScraperLogVisibility(emptyRow, true);
            const cell = emptyRow.cells[0];
            const statusText = status === 'imported' ? 'başarılı' :
                              status === 'failed' ? 'hatalı' :
                              status === 'preview' ? 'önizleme' : '';
            cell.innerHTML = `<div class="scraper-empty-table">
                <i class="bi bi-filter-circle"></i>
                <strong>Filtreye uygun log bulunamadı</strong>
                <span>${statusText} durumunda içerik yok</span>
            </div>`;
        }
    }
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
