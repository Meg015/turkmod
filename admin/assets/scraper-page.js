const adminScraperConfigNode = document.getElementById('adminScraperConfig');
const adminScraperConfig = adminScraperConfigNode ? JSON.parse(adminScraperConfigNode.textContent || '{}') : {};
const baseUri = adminScraperConfig.baseUri || '';
const botDefaultStatus = adminScraperConfig.botDefaultStatus || 'published';
const botBulkDefaultSelected = adminScraperConfig.botBulkDefaultSelected || '1';
const botBulkMaxTopicsPerPage = adminScraperConfig.botBulkMaxTopicsPerPage || '0';
const botBulkContinueOnError = adminScraperConfig.botBulkContinueOnError || '1';

// Bot Logları Filtreleme Sistemi
function filterLogs(status) {
    const table = document.querySelector('#tab-imports table tbody');
    if (!table) return;
    
    const rows = table.querySelectorAll('tr');
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
        // Boş satırı atla
        if (row.cells.length === 1) {
            row.style.display = status === 'all' ? '' : 'none';
            return;
        }
        
        // Durum badge'ini bul
        const statusBadge = row.querySelector('.admin-badge');
        if (!statusBadge) {
            row.style.display = '';
            return;
        }
        
        const rowStatus = statusBadge.classList.contains('admin-badge-success') ? 'imported' :
                         statusBadge.classList.contains('admin-badge-danger') ? 'failed' :
                         statusBadge.classList.contains('admin-badge-warning') ? 'preview' : 'other';
        
        if (status === 'all' || rowStatus === status) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
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
        const emptyRow = table.querySelector('tr[style*="display: none"]');
        if (emptyRow && emptyRow.cells.length === 1) {
            emptyRow.style.display = '';
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

// Sayfa yüklendiğinde tüm logları göster
document.addEventListener('DOMContentLoaded', function() {
    // İlk yüklemede "Tümü" seçili olsun
    const allBtn = document.querySelector('.log-filter-btn[data-filter="all"]');
    if (allBtn) {
        allBtn.classList.add('active');
    }
});
