function getLeaderboardConfig() {
    const node = document.querySelector('[data-leaderboard-admin-config]');
    if (!node) {
        return {};
    }
    try {
        return JSON.parse(node.getAttribute('data-leaderboard-admin-config') || '{}') || {};
    } catch (error) {
        return {};
    }
}

function createLeaderboardPost(apiBase) {
    return (endpoint, body) => window.adminFetchJson(`${apiBase}/${endpoint}`, {
        method: 'POST',
        body: new URLSearchParams(body || {})
    });
}

function activateLeaderboardTab(targetId) {
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
}

function initLeaderboardPage() {
    const leaderboardConfig = getLeaderboardConfig();
    const leaderboardApiBase = leaderboardConfig.apiBase || 'api';
    const leaderboardPost = createLeaderboardPost(leaderboardApiBase);

    document.querySelectorAll('.leaderboard-admin-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            activateLeaderboardTab(tab.dataset.tabTarget);
            history.replaceState(null, '', `#${tab.dataset.tabTarget}`);
        });
    });

    if (location.hash === '#leaderboard-settings') {
        activateLeaderboardTab('leaderboard-settings');
    }

    document.querySelectorAll('.recalculate-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const category = this.dataset.category;
            const period = this.dataset.period;

            if (!await adminConfirm(`${category} - ${period} icin liderlik tablosunu yeniden hesaplamak istediginize emin misiniz?`, {
                title: 'Yeniden hesapla',
                ok: 'Hesapla',
                tone: 'warning'
            })) {
                return;
            }

            const buttonState = window.adminAsync ? window.adminAsync.setButtonLoading(this, {
                loadingHtml: '<i class="bi bi-arrow-repeat spin"></i> Hesaplaniyor...'
            }) : null;

            try {
                const result = await leaderboardPost('leaderboard-recalculate.php', {
                    category: category,
                    period: period,
                    force: 'true'
                });

                if (result.success) {
                    adminAlert(`${result.affected_users} kullanici icin hesaplama tamamlandi (${result.calculation_time_ms}ms)`, {
                        title: 'Basarili',
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
                if (window.adminAsync) window.adminAsync.restoreButton(buttonState);
            }
        });
    });

    document.querySelectorAll('.clear-cache-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const category = this.dataset.category;
            const period = this.dataset.period;

            if (!await adminConfirm(`${category} - ${period} cache'ini temizlemek istediginize emin misiniz?`, {
                title: 'Cache temizle',
                ok: 'Temizle',
                tone: 'danger'
            })) {
                return;
            }

            const buttonState = window.adminAsync ? window.adminAsync.setButtonLoading(this, {
                loadingHtml: '<i class="bi bi-trash spin"></i> Temizleniyor...'
            }) : null;

            try {
                const result = await leaderboardPost('leaderboard-clear-cache.php', {
                    category: category,
                    period: period
                });

                if (result.success) {
                    adminAlert('Cache temizlendi', {
                        title: 'Basarili',
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
                if (window.adminAsync) window.adminAsync.restoreButton(buttonState);
            }
        });
    });

    document.getElementById('recalculateAllBtn')?.addEventListener('click', async function() {
        if (!await adminConfirm('TUM liderlik tablolarini yeniden hesaplamak istediginize emin misiniz? Bu islem uzun surebilir.', {
            title: 'Tumunu yeniden hesapla',
            ok: 'Hesapla',
            tone: 'warning'
        })) {
            return;
        }

        window.adminAsync?.setButtonLoading(this, {
            loadingHtml: '<i class="bi bi-arrow-repeat spin"></i> Hesaplaniyor...'
        });

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

        adminAlert(`Basarili: ${completed}\nBasarisiz: ${failed}`, {
            title: 'Toplu Hesaplama Tamamlandi',
            ok: 'Tamam',
            tone: failed === 0 ? 'success' : 'warning'
        }).then(() => {
            location.reload();
        });
    });

    document.getElementById('clearAllCacheBtn')?.addEventListener('click', async function() {
        if (!await adminConfirm('TUM cache kayitlarini temizlemek istediginize emin misiniz? Bu islem geri alinamaz.', {
            title: 'Tum cache temizlensin mi?',
            ok: 'Temizle',
            tone: 'danger'
        })) {
            return;
        }

        const buttonState = window.adminAsync ? window.adminAsync.setButtonLoading(this, {
            loadingHtml: '<i class="bi bi-trash spin"></i> Temizleniyor...'
        }) : null;

        try {
            const result = await leaderboardPost('leaderboard-clear-cache.php', {
                clear_all: 'true'
            });

            if (result.success) {
                adminAlert('Tum cache temizlendi', {
                    title: 'Basarili',
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
            if (window.adminAsync) window.adminAsync.restoreButton(buttonState);
        }
    });
}

window.adminPage.register('*', initLeaderboardPage, {
    id: 'leaderboard-page',
    selector: '[data-leaderboard-admin-config]'
});
