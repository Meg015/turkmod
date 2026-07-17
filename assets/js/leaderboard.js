/**
 * Leaderboard JavaScript
 * Handles AJAX loading, pagination, and search
 */

(function() {
    'use strict';

    // Widget period selector
    const widgetPeriodSelect = document.getElementById('leaderboard-period-widget');
    if (widgetPeriodSelect) {
        widgetPeriodSelect.addEventListener('change', function() {
            loadWidgetData(this.value);
        });
    }

    function getAppBaseUri() {
        const baseMeta = document.querySelector('meta[name="app-base-uri"]');
        return baseMeta ? (baseMeta.getAttribute('content') || '').replace(/\/+$/, '') : '';
    }

    function getBaseUri() {
        return `${window.location.origin}${getAppBaseUri()}`;
    }

    function normalizeAvatarUrl(value, fallbackUrl) {
        const avatar = String(value || '').trim();
        if (avatar === '') return fallbackUrl;
        if (/^(data|javascript|vbscript):/i.test(avatar)) return fallbackUrl;
        if (/^(https?:)?\/\//i.test(avatar)) return avatar;

        const appBase = getAppBaseUri();
        const cleanBase = appBase.replace(/^\/+/, '').replace(/\/+$/, '');
        let relativePath = avatar.replace(/^\/+/, '');
        if (cleanBase && relativePath.startsWith(cleanBase + '/')) {
            relativePath = relativePath.slice(cleanBase.length + 1);
        }

        return `${window.location.origin}${appBase}/${relativePath}`;
    }

    function slugifyProfileName(value) {
        const text = String(value || '').trim();
        if (text === '') return 'kullanici';

        const normalized = text
            .replace(/[ÇĞİÖŞÜçğıöşü]/g, (char) => ({
                'Ç': 'C',
                'Ğ': 'G',
                'İ': 'I',
                'Ö': 'O',
                'Ş': 'S',
                'Ü': 'U',
                'ç': 'c',
                'ğ': 'g',
                'ı': 'i',
                'ö': 'o',
                'ş': 's',
                'ü': 'u',
            }[char] || char))
            .normalize('NFKD')
            .replace(/[\u0300-\u036f]/g, '');

        const slug = normalized
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');

        return slug || 'kullanici';
    }

    function buildProfileUrl(user, baseUri) {
        const profileUrl = String(user && user.profile_url ? user.profile_url : '').trim();
        if (profileUrl !== '') return profileUrl;

        const userId = parseInt((user && (user.user_id ?? user.id)) || 0, 10);
        if (userId <= 0) return '#';

        const displayName = String((user && (user.username || user.name || user.author)) || '').trim();
        const slug = slugifyProfileName(displayName);

        return `${baseUri}/profil/${encodeURIComponent(`${slug}-${userId}`)}`;
    }

    function buildAvatarUrl(user, fallbackUrl) {
        const avatarValue = user && (user.avatar_url ?? user.avatar ?? user.avatar_path);
        return normalizeAvatarUrl(avatarValue, fallbackUrl);
    }

    /**
     * Load widget data via AJAX
     */
    function loadWidgetData(period) {
        const widgetList = document.getElementById('leaderboard-widget-list');
        if (!widgetList) return;

        // Show loading state
        widgetList.style.opacity = '0.5';
        widgetList.style.pointerEvents = 'none';

        const baseUri = getBaseUri();
        const fallbackAvatarUrl = getLocalAssetUrl('assets/images/noavatar-neon-helmet.svg');
        const apiUrl = `${baseUri}/api/leaderboard?category=daily_login&period=${period}&limit=5`;

        fetch(apiUrl)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    renderWidgetUsers(data.data, widgetList, fallbackAvatarUrl);
                } else {
                    showWidgetError(widgetList);
                }
            })
            .catch(error => {
                console.error('Leaderboard widget error:', error);
                showWidgetError(widgetList);
            })
            .finally(() => {
                widgetList.style.opacity = '1';
                widgetList.style.pointerEvents = 'auto';
            });
    }

    /**
     * Render widget users
     */
    function renderWidgetUsers(users, container, fallbackAvatarUrl) {
        const medals = ['🥇', '🥈', '🥉'];
        const baseUri = getBaseUri();

        container.innerHTML = users.map((user, index) => {
            const rank = index + 1;
            const username = escapeHtml(user.username || user.name || 'Anonim');
            const count = formatNumber(parseInt(user.count || 0, 10));
            const profileUrl = buildProfileUrl(user, baseUri);
            const avatarUrl = buildAvatarUrl(user, fallbackAvatarUrl);

            const rankHtml = rank <= 3
                ? `<span class="medal">${medals[rank - 1]}</span>`
                : `<span class="rank-number">${rank}</span>`;

            return `
                <div class="leaderboard-item">
                    <div class="leaderboard-rank">${rankHtml}</div>
                    <a href="${profileUrl}" class="leaderboard-avatar">
                        <img src="${avatarUrl}" alt="${username}" width="48" height="48" loading="lazy" decoding="async" data-ui-avatar-img data-ui-avatar-fallback="${fallbackAvatarUrl}">
                    </a>
                    <div class="leaderboard-info">
                        <a href="${profileUrl}" class="leaderboard-username">${username}</a>
                        <span class="leaderboard-score">${count} giriş</span>
                    </div>
                </div>
            `;
        }).join('');
    }

    function getLocalAssetUrl(relativePath) {
        const cleanPath = String(relativePath || '').replace(/^\/+/, '');
        return `${getBaseUri()}/${cleanPath}`;
    }

    /**
     * Show widget error
     */
    function showWidgetError(container) {
        container.innerHTML = `
            <div class="leaderboard-error">
                <i class="bi bi-exclamation-triangle"></i>
                <p>Veriler yüklenemedi</p>
            </div>
        `;
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Format number with thousands separator
     */
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    /**
     * Debounce function for search
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Search functionality with debounce
    const searchInput = document.querySelector('.leaderboard-search input[name="search"]');
    if (searchInput) {
        const debouncedSearch = debounce(function() {
            this.form.submit();
        }, 500);

        searchInput.addEventListener('input', debouncedSearch);
    }

    // Keyboard navigation for period buttons
    const periodButtons = document.querySelectorAll('.period-btn');
    periodButtons.forEach((button, index) => {
        button.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft' && index > 0) {
                periodButtons[index - 1].focus();
            } else if (e.key === 'ArrowRight' && index < periodButtons.length - 1) {
                periodButtons[index + 1].focus();
            }
        });
    });

    // Tab navigation for category tabs
    const categoryTabs = document.querySelectorAll('.leaderboard-tab');
    categoryTabs.forEach((tab, index) => {
        tab.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft' && index > 0) {
                categoryTabs[index - 1].focus();
            } else if (e.key === 'ArrowRight' && index < categoryTabs.length - 1) {
                categoryTabs[index + 1].focus();
            }
        });
    });

    // Smooth scroll to top when changing pages
    const paginationButtons = document.querySelectorAll('.pagination button');
    paginationButtons.forEach(button => {
        button.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });

    document.addEventListener('click', function(event) {
        const button = event.target.closest('[data-leaderboard-href]');
        if (!button || button.disabled) return;
        const href = button.getAttribute('data-leaderboard-href');
        if (href) {
            window.location.href = href;
        }
    });

    // Add loading animation to table when navigating
    const leaderboardTable = document.querySelector('.leaderboard-table-container');
    if (leaderboardTable) {
        window.addEventListener('beforeunload', function() {
            leaderboardTable.style.opacity = '0.5';
        });
    }

    // Highlight current user row with animation
    const currentUserRow = document.querySelector('.leaderboard-table tr.current-user');
    if (currentUserRow) {
        setTimeout(() => {
            currentUserRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            currentUserRow.style.animation = 'highlight-pulse 2s ease-in-out';
        }, 500);
    }

    // Add tooltips to metadata items
    const metadataItems = document.querySelectorAll('.metadata-item');
    metadataItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            const title = this.getAttribute('title');
            if (title) {
                this.setAttribute('data-tooltip', title);
            }
        });
    });

})();
