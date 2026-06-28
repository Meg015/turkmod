const leaderboardConfigNode = document.querySelector('[data-leaderboard-admin-config]');
const leaderboardConfig = leaderboardConfigNode ? JSON.parse(leaderboardConfigNode.getAttribute('data-leaderboard-admin-config') || '{}') : {};
const leaderboardApiBase = leaderboardConfig.apiBase || 'api';
const leaderboardPost = (endpoint, body) => window.adminFetchJson(`${leaderboardApiBase}/${endpoint}`, {
    method: 'POST',
    body: new URLSearchParams(body || {})
});

const activateLeaderboardTab = (targetId) => {
    document.querySelectorAll('.leaderboard-admin-tab').forEach(tab => {
        const isActive = tab.dataset.tabTarget === targetId;
        tab.classList.toggle('is-active', isActive);
        tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    document.querySelectorAll('.leaderboard-admin-tab-panel').forEach(panel => {
        const isActive = panel.id === targetId;
        panel.classList.toggle('is-active', isActive);
        panel.hidden = !isActive;
    });
};

document.querySelectorAll('.leaderboard-admin-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        activateLeaderboardTab(tab.dataset.tabTarget);
        history.replaceState(null, '', `#${tab.dataset.tabTarget}`);
    });
});

if (location.hash === '#leaderboard-settings') {
    activateLeaderboardTab('leaderboard-settings');
}

// Recalculate single entry
document.querySelectorAll('.recalculate-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const category = this.dataset.category;
        const period = this.dataset.period;

        if (!await adminConfirm(`${category} - ${period} için liderlik tablosunu yeniden hesaplamak istediğinize emin misiniz?`, {
            title: 'Yeniden hesapla',
            ok: 'Hesapla',
            tone: 'warning'
        })) {
            return;
        }

        this.disabled = true;
        this.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Hesaplanıyor...';

        try {
            const result = await leaderboardPost('leaderboard-recalculate.php', {
                category: category,
                period: period,
                force: 'true'
            });

            if (result.success) {
                adminAlert(`${result.affected_users} kullanıcı için hesaplama tamamlandı (${result.calculation_time_ms}ms)`, {
                    title: 'Başarılı',
                    ok: 'Tamam',
                    tone: 'success'
                }).then(() => {
                    location.reload();
                });
            } else {
                throw new Error(result.message || 'Bilinmeyen hata');
            }
        } catch (error) {
            adminAlert(error.message, {
                title: 'Hata',
                ok: 'Tamam',
                tone: 'danger'
            });
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-arrow-repeat"></i> Hesapla';
        }
    });
});

// Clear single cache
document.querySelectorAll('.clear-cache-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const category = this.dataset.category;
        const period = this.dataset.period;

        if (!await adminConfirm(`${category} - ${period} cache'ini temizlemek istediğinize emin misiniz?`, {
            title: 'Cache temizle',
            ok: 'Temizle',
            tone: 'danger'
        })) {
            return;
        }

        this.disabled = true;
        this.innerHTML = '<i class="bi bi-trash spin"></i> Temizleniyor...';

        try {
            const result = await leaderboardPost('leaderboard-clear-cache.php', {
                category: category,
                period: period
            });

            if (result.success) {
                adminAlert('Cache temizlendi', {
                    title: 'Başarılı',
                    ok: 'Tamam',
                    tone: 'success'
                }).then(() => {
                    location.reload();
                });
            } else {
                throw new Error(result.message || 'Bilinmeyen hata');
            }
        } catch (error) {
            adminAlert(error.message, {
                title: 'Hata',
                ok: 'Tamam',
                tone: 'danger'
            });
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-trash"></i> Temizle';
        }
    });
});

// Recalculate all
document.getElementById('recalculateAllBtn').addEventListener('click', async function() {
    if (!await adminConfirm('TÜM liderlik tablolarını yeniden hesaplamak istediğinize emin misiniz? Bu işlem uzun sürebilir.', {
        title: 'Tümünü yeniden hesapla',
        ok: 'Hesapla',
        tone: 'warning'
    })) {
        return;
    }

    this.disabled = true;
    this.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Hesaplanıyor...';

    const categories = Array.isArray(leaderboardConfig.categories) ? leaderboardConfig.categories : [];
    const periods = Array.isArray(leaderboardConfig.periods) ? leaderboardConfig.periods : [];

    let completed = 0;
    let failed = 0;

    for (const category of categories) {
        for (const period of periods) {
            try {
                const result = await leaderboardPost('leaderboard-recalculate.php', {
                    category: category,
                    period: period,
                    force: 'true'
                });
                if (result.success) {
                    completed++;
                } else {
                    failed++;
                }
            } catch (error) {
                failed++;
            }
        }
    }

    adminAlert(`Başarılı: ${completed}\nBaşarısız: ${failed}`, {
        title: 'Toplu Hesaplama Tamamlandı',
        ok: 'Tamam',
        tone: failed === 0 ? 'success' : 'warning'
    }).then(() => {
        location.reload();
    });
});

// Clear all cache
document.getElementById('clearAllCacheBtn').addEventListener('click', async function() {
    if (!await adminConfirm('TÜM cache kayıtlarını temizlemek istediğinize emin misiniz? Bu işlem geri alınamaz.', {
        title: 'Tüm cache temizlensin mi?',
        ok: 'Temizle',
        tone: 'danger'
    })) {
        return;
    }

    this.disabled = true;
    this.innerHTML = '<i class="bi bi-trash spin"></i> Temizleniyor...';

    try {
        const result = await leaderboardPost('leaderboard-clear-cache.php', {
            clear_all: 'true'
        });

        if (result.success) {
            adminAlert('Tüm cache temizlendi', {
                title: 'Başarılı',
                ok: 'Tamam',
                tone: 'success'
            }).then(() => {
                location.reload();
            });
        } else {
            throw new Error(result.message || 'Bilinmeyen hata');
        }
    } catch (error) {
        adminAlert(error.message, {
            title: 'Hata',
            ok: 'Tamam',
            tone: 'danger'
        });
        this.disabled = false;
        this.innerHTML = '<i class="bi bi-trash"></i> Tüm Cache\'i Temizle';
    }
});
