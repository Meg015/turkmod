/* ============================================================
   APP.JS - Core Application Logic
   Consolidated from: analytics.js, favorites.js, filters.js,
   download-handler.js, carousel.js
   ============================================================ */

/* esbuild imports — bundled into public.min.js */
import './ui.js';
import './ui-foundation.js';

/* ============================================================
   SECTION 1: ANALYTICS & TRACKING
   ============================================================ */

/**
 * Analytics and Event Tracking System
 * Tracks user behavior, conversions, and interactions
 */

class Analytics {
    constructor() {
        this.baseUri = document.querySelector('meta[name="app-base-uri"]')?.content || '';
        this.sessionId = this.getOrCreateSessionId();
        this.userId = this.getUserId();
        // Performance optimization (#19): Batch events instead of sending each immediately
        this.eventQueue = [];
        this.batchInterval = 5000; // Send batch every 5 seconds
        this.maxBatchSize = 20;
        this.initBatchSender();
        this.initTracking();
    }

    /**
     * Initialize batch sender - flushes queue periodically
     */
    initBatchSender() {
        setInterval(() => this.flushQueue(), this.batchInterval);
        // Also flush on page unload
        window.addEventListener('beforeunload', () => this.flushQueue(true));
    }

    /**
     * Flush event queue to server
     */
    flushQueue(useBeacon = false) {
        if (this.eventQueue.length === 0) return;

        const events = this.eventQueue.splice(0, this.maxBatchSize);
        const payload = JSON.stringify({ events, batch: true, timestamp: Date.now() });

        if (useBeacon && navigator.sendBeacon) {
            navigator.sendBeacon(this.baseUri + '/api/analytics/track.php', payload);
            return;
        }

        if (typeof window.publicFetchJson === 'function') {
            window.publicFetchJson(this.baseUri + '/api/analytics/track.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: payload,
                keepalive: true,
                notifyError: false,
                csrfRetry: false,
            }).catch(() => {});
        }
    }

    /**
     * Initialize tracking
     */
    initTracking() {
        // Track page view
        this.trackPageView();

        // Track outbound links
        this.trackOutboundLinks();

        // Track downloads
        this.trackDownloads();

        // Track search
        this.trackSearch();

        // Track time on page
        this.trackTimeOnPage();

        // Track scroll depth
        this.trackScrollDepth();
    }

    /**
     * Track custom event - queued for batch sending (performance #19)
     */
    track(event, data = {}) {
        const payload = {
            event,
            data,
            session_id: this.sessionId,
            user_id: this.userId,
            timestamp: Date.now(),
            url: window.location.href,
            referrer: document.referrer,
            user_agent: navigator.userAgent,
            screen_resolution: `${window.screen.width}x${window.screen.height}`,
            viewport_size: `${window.innerWidth}x${window.innerHeight}`,
        };

        // Send to Google Analytics if available (immediate)
        if (typeof gtag !== 'undefined') {
            gtag('event', event, data);
        }

        // Queue for batch sending instead of immediate fetch
        this.eventQueue.push(payload);

        // Flush if queue is full
        if (this.eventQueue.length >= this.maxBatchSize) {
            this.flushQueue();
        }
    }

    /**
     * Track page view
     */
    trackPageView() {
        this.track('page_view', {
            page_title: document.title,
            page_path: window.location.pathname,
        });
    }

    /**
     * Track download
     */
    trackDownload(topicId, topicTitle, downloadUrl) {
        this.track('download', {
            topic_id: topicId,
            topic_title: topicTitle,
            download_url: downloadUrl,
        });
    }

    /**
     * Track search
     */
    trackSearch() {
        const searchInput = document.querySelector('input[name="q"], .search-input');
        if (!searchInput) return;

        let searchTimeout;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const query = e.target.value.trim();
                if (query.length >= 2) {
                    this.track('search', {
                        query,
                        query_length: query.length,
                    });
                }
            }, 1000);
        });
    }

    /**
     * Track outbound links
     */
    trackOutboundLinks() {
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a');
            if (!link) return;

            const href = link.getAttribute('href');
            if (!href) return;

            // Check if external link
            if (href.startsWith('http') && !href.includes(window.location.hostname)) {
                this.track('outbound_link', {
                    url: href,
                    text: link.textContent.trim(),
                });
            }
        });
    }

    /**
     * Track downloads
     */
    trackDownloads() {
        document.addEventListener('click', (e) => {
            const downloadBtn = e.target.closest('[data-download-id]');
            if (!downloadBtn) return;

            const topicId = downloadBtn.dataset.downloadId;
            const topicTitle = downloadBtn.dataset.downloadTitle || 'Unknown';
            const downloadUrl = downloadBtn.href || downloadBtn.dataset.downloadUrl;

            this.trackDownload(topicId, topicTitle, downloadUrl);
        });
    }

    /**
     * Track time on page
     */
    trackTimeOnPage() {
        const startTime = Date.now();

        // Track on page unload
        window.addEventListener('beforeunload', () => {
            const timeSpent = Math.round((Date.now() - startTime) / 1000);

            // Use sendBeacon for reliable tracking on page unload
            const payload = JSON.stringify({
                event: 'time_on_page',
                data: {
                    seconds: timeSpent,
                    page_path: window.location.pathname,
                },
                session_id: this.sessionId,
                timestamp: Date.now(),
            });

            navigator.sendBeacon(this.baseUri + '/api/analytics/track.php', payload);
        });
    }

    /**
     * Track scroll depth
     */
    trackScrollDepth() {
        const depths = [25, 50, 75, 100];
        const tracked = new Set();

        const checkScroll = () => {
            const scrollPercent = Math.round(
                (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100
            );

            depths.forEach(depth => {
                if (scrollPercent >= depth && !tracked.has(depth)) {
                    tracked.add(depth);
                    this.track('scroll_depth', {
                        depth: depth,
                        page_path: window.location.pathname,
                    });
                }
            });
        };

        let scrollTimeout;
        window.addEventListener('scroll', () => {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(checkScroll, 100);
        }, { passive: true });
    }

    /**
     * Track form submission
     */
    trackFormSubmit(formName, formData = {}) {
        this.track('form_submit', {
            form_name: formName,
            ...formData,
        });
    }

    /**
     * Track error
     */
    trackError(error, context = {}) {
        this.track('error', {
            message: error.message || error,
            stack: error.stack,
            ...context,
        });
    }

    /**
     * Track conversion
     */
    trackConversion(conversionType, value = null) {
        this.track('conversion', {
            type: conversionType,
            value,
        });
    }

    /**
     * Track favorite toggle
     */
    trackFavorite(topicId, action) {
        this.track('favorite', {
            topic_id: topicId,
            action, // 'add' or 'remove'
        });
    }

    /**
     * Track comment
     */
    trackComment(topicId, commentLength) {
        this.track('comment', {
            topic_id: topicId,
            comment_length: commentLength,
        });
    }

    /**
     * Get or create session ID
     */
    getOrCreateSessionId() {
        let sessionId = sessionStorage.getItem('analytics_session_id');

        if (!sessionId) {
            sessionId = this.generateId();
            sessionStorage.setItem('analytics_session_id', sessionId);
        }

        return sessionId;
    }

    /**
     * Get user ID from cookie or localStorage
     */
    getUserId() {
        let userId = localStorage.getItem('analytics_user_id');

        if (!userId) {
            userId = this.generateId();
            localStorage.setItem('analytics_user_id', userId);
        }

        return userId;
    }

    /**
     * Generate unique ID
     */
    generateId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2);
    }

    /**
     * Check if in development mode
     */
    isDevelopment() {
        return window.location.hostname === 'localhost' ||
               window.location.hostname === '127.0.0.1';
    }
}

// Initialize analytics
const analytics = new Analytics();

// Make it globally available
window.analytics = analytics;

// Track JavaScript errors
window.addEventListener('error', (event) => {
    analytics.trackError(event.error || event.message, {
        filename: event.filename,
        lineno: event.lineno,
        colno: event.colno,
    });
});

// Track unhandled promise rejections
window.addEventListener('unhandledrejection', (event) => {
    analytics.trackError(event.reason, {
        type: 'unhandled_promise_rejection',
    });
});

/* ============================================================
   SECTION 2: FAVORITES MANAGEMENT
   ============================================================ */

(() => {
    'use strict';

    const baseUri = document.querySelector('meta[name="app-base-uri"]')?.content || '';
    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const optimistic = window.OptimisticUI || (window.OptimisticUI = {
        captureFavorite(button) {
            const countEl = button.querySelector('.ttb-favorite-count, .count, .action-badge');
            const rawCount = countEl ? countEl.textContent.replace(/[^\d]/g, '') : '0';
            return {
                active: button.classList.contains('is-active') || button.classList.contains('active') || Boolean(button.closest('.profile-favorite-remove-form')),
                count: Number(rawCount || 0),
            };
        },
        markPending(element, pending) {
            element.classList.toggle('is-optimistic-pending', pending);
            element.setAttribute('aria-busy', pending ? 'true' : 'false');
        },
        messageFor(error, fallback) {
            const messages = {
                csrf_failed: 'Güvenlik doğrulaması yenilenmeli. Sayfayı yenileyip tekrar deneyin.',
                invalid_topic: 'Konu bilgisi geçersiz.',
                topic_not_found: 'Bu konu artık yayında değil veya kaldırılmış.',
                rate_limited: 'Çok hızlı işlem yapıyorsunuz. Lütfen biraz sonra tekrar deneyin.',
                server_error: 'Sunucu tarafında bir sorun oluştu.',
                login_required: 'Bu işlem için giriş yapmalısınız.',
            };
            return messages[error] || fallback || 'İşlem tamamlanamadı.';
        },
    });

    const setButtonState = (button, isFavorited, count) => {
        if (button.dataset.originalHtml && button.classList.contains('is-submitting')) {
            button.innerHTML = button.dataset.originalHtml;
        }
        button.classList.remove('is-submitting');
        button.setAttribute('aria-busy', 'false');
        button.classList.toggle('is-active', isFavorited);
        button.classList.toggle('active', isFavorited);
        const icon = button.querySelector('i');
        if (icon) icon.className = `bi ${isFavorited ? 'bi-heart-fill' : 'bi-heart'}`;
        const countEl = button.querySelector('.ttb-favorite-count, .count, .action-badge');
        if (countEl) countEl.textContent = new Intl.NumberFormat('tr-TR').format(count);
        const labelEl = button.querySelector('.action-text, .ttb-favorite-label, [data-favorite-label]');
        if (labelEl) labelEl.textContent = isFavorited ? 'Favorilerden Kaldır' : 'Favorilere Ekle';
        const textNode = [...button.childNodes].find((node) => node.nodeType === Node.TEXT_NODE && node.textContent.trim());
        if (textNode) textNode.textContent = isFavorited ? ' Favorilerden Kaldır ' : ' Favorilere Ekle ';
        button.title = isFavorited ? 'Favorilerden kaldır' : 'Favorilere ekle';
    };

    const getFavoriteTopicId = (button) => button?.dataset.favoriteTopicId || button?.dataset.topicId || button?.closest('.ttb-favorite-form')?.dataset.topicId || '';

    const setTopicFavoriteState = (topicId, isFavorited, count) => {
        if (!topicId) return;
        document.querySelectorAll(`.ttb-favorite-form[data-topic-id="${topicId}"] button, [data-favorite-topic-id="${topicId}"], [data-topic-id="${topicId}"].ttb-favorite-btn`).forEach((button) => {
            setButtonState(button, isFavorited, count);
        });
    };

    class FavoriteManager {
        constructor() {
            document.addEventListener('submit', (event) => this.handleSubmit(event));
            document.addEventListener('click', (event) => this.handleClick(event));
        }

        handleSubmit(event) {
            const form = event.target.closest('.ttb-favorite-form');
            if (!form) return;
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            const topicId = form.dataset.topicId || button?.dataset.topicId;
            const slug = form.action;
            this.toggle({ button, topicId, fallbackUrl: slug });
        }

        handleClick(event) {
            const button = event.target.closest('[data-favorite-topic-id]');
            if (!button) return;
            event.preventDefault();
            this.toggle({ button, topicId: button.dataset.favoriteTopicId });
        }

        async toggle({ button, topicId, fallbackUrl }) {
            if (!button) return;
            topicId = String(topicId || getFavoriteTopicId(button) || '');
            const previous = optimistic.captureFavorite(button);
            const optimisticState = !previous.active;
            const optimisticCount = Math.max(0, previous.count + (optimisticState ? 1 : -1));
            setTopicFavoriteState(topicId, optimisticState, optimisticCount);
            optimistic.markPending(button, true);
            button.disabled = true;

            try {
                const requestOptions = {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ topic_id: Number(topicId || button.dataset.topicId || 0) }),
                };
                if (!window.publicFetchJson) {
                    throw new Error('Public API helper yuklenemedi.');
                }
                const payload = await window.publicFetchJson(`${baseUri}/api/favorites/toggle.php`, Object.assign({}, requestOptions, {
                    notifyError: false
                }));
                if (!payload.success) throw new Error(payload.error || 'favorite_failed');
                setTopicFavoriteState(topicId, Boolean(payload.favorited), Number(payload.count || 0));
                if (!payload.favorited && button.closest('.profile-favorite-remove-form')) {
                    const card = button.closest('.profile-topic-item');
                    if (card) {
                        card.classList.add('is-removing');
                        window.setTimeout(() => {
                            const list = card.closest('.profile-favorites-list');
                            card.remove();
                            if (list && !list.querySelector('.profile-topic-item')) {
                                list.innerHTML = `
                                    <div class="profile-empty-cta profile-empty-ajax">
                                        <i class="bi bi-heart" aria-hidden="true"></i>
                                        <h3>Henüz favori içeriğiniz yok</h3>
                                        <p>Beğendiğiniz konuları favorilere ekleyerek daha sonra kolayca erişebilirsiniz.</p>
                                        <a href="${baseUri}/index.php" class="ui-admin-btn ui-admin-btn-warning fw-bold">
                                            <i class="bi bi-compass me-1" aria-hidden="true"></i>İçerikleri Keşfet
                                        </a>
                                    </div>
                                `;
                            }
                        }, 180);
                    }
                }
                window.showToast?.(payload.favorited ? 'Favorilere eklendi' : 'Favorilerden kaldırıldı', 'success');
                window.analytics?.trackFavorite?.(payload.topic_id, payload.favorited ? 'add' : 'remove');
            } catch (error) {
                if (Number(error && error.status) === 401) {
                    window.location.href = `${baseUri}/giris`;
                    return;
                }
                setTopicFavoriteState(topicId, previous.active, previous.count);
                window.showToast?.(optimistic.messageFor(error.message, 'Favori işlemi tamamlanamadı.'), 'error');
            } finally {
                button.disabled = false;
                optimistic.markPending(button, false);
            }
        }
    }

    const init = () => new FavoriteManager();
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();

/* ============================================================
   SECTION 3: FILTER MANAGEMENT
   ============================================================ */

(() => {
    'use strict';

    const baseUri = document.querySelector('meta[name="app-base-uri"]')?.content || '';

    class FilterManager {
        constructor() {
            this.container = document.querySelector('[data-topic-list-container]');
            this.sortInsight = document.querySelector('[data-sort-insight] span');
            this.filters = new URLSearchParams(window.location.search);
            if (!this.container) return;
            this.bind();
        }

        sortMessages() {
            return {
                newest: 'Yeni içerikler yayın tarihine göre en güncelden eskiye sıralanır.',
                popular: 'Popüler sıralama görüntülenme ve indirme ilgisine göre listelenir.',
                downloads: 'Trend içerikler en çok indirilen modlara göre öne çıkar.',
                comments: 'En çok konuşulan içerikler yorum sayısına göre sıralanır.',
            };
        }

        renderSkeleton() {
            const skeletons = Array.from({ length: 4 }).map(() => `
                <article class="feed-card--list topic-list-skeleton" aria-hidden="true">
                    <span class="topic-skeleton-thumb"></span>
                    <span class="topic-skeleton-body">
                        <span class="topic-skeleton-line sk-w-45"></span>
                        <span class="topic-skeleton-line sk-w-85"></span>
                        <span class="topic-skeleton-line sk-w-70"></span>
                        <span class="topic-skeleton-actions">
                            <span></span><span></span><span></span>
                        </span>
                    </span>
                </article>
            `).join('');
            this.container.innerHTML = skeletons;
        }

        bind() {
            document.querySelectorAll('[data-filter]').forEach((element) => {
                const eventName = element.matches('select,input') ? 'change' : 'click';
                element.addEventListener(eventName, (event) => this.handleChange(event, element));
            });
            window.addEventListener('popstate', () => this.loadContent(false));
        }

        handleChange(event, element) {
            event.preventDefault();
            const key = element.dataset.filter;
            const value = element.dataset.value ?? element.value ?? '';

            if (value) this.filters.set(key, value);
            else this.filters.delete(key);
            this.filters.delete('page');

            this.updateActiveControls(key, value);
            this.updateSortInsight();
            this.loadContent(true);
        }

        updateActiveControls(key, value) {
            document.querySelectorAll(`[data-filter="${key}"]`).forEach((control) => {
                if (control.matches('button')) control.classList.toggle('active', (control.dataset.value || '') === value);
                if (control.matches('select')) control.value = value;
            });
        }

        updateSortInsight() {
            if (!this.sortInsight) return;
            const sort = this.filters.get('sort') || 'newest';
            this.sortInsight.textContent = this.sortMessages()[sort] || this.sortMessages().newest;
        }

        async loadContent(pushState = true) {
            const query = this.filters.toString();
            const pageUrl = `${window.location.pathname}${query ? `?${query}` : ''}`;
            const apiUrl = `${baseUri}/api/topics.php${query ? `?${query}` : ''}`;

            this.container.setAttribute('aria-busy', 'true');
            this.container.classList.add('is-loading');
            this.renderSkeleton();

            try {
                if (!window.publicFetchJson) {
                    throw new Error('Public API helper yuklenemedi.');
                }
                const payload = await window.publicFetchJson(apiUrl, { headers: { 'Accept': 'application/json' } });
                this.container.innerHTML = payload.html || '';
                if (pushState) history.pushState({}, '', pageUrl);
                this.container.scrollIntoView({ behavior: 'smooth', block: 'start' });
                window.analytics?.track?.('filter_apply', Object.fromEntries(this.filters.entries()));
            } catch (error) {
                if (pushState) window.location.href = pageUrl;
            } finally {
                this.container.removeAttribute('aria-busy');
                this.container.classList.remove('is-loading');
            }
        }
    }

    const init = () => new FilterManager();
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();

/* ============================================================
   SECTION 4: DOWNLOAD HANDLER
   ============================================================ */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const section = document.querySelector('.topic-dl-section');
        if (!section) return;

        const cards = section.querySelectorAll('.topic-dl-card');
        const countdownSeconds = Math.max(0, parseInt(section.dataset.countdownSeconds || '5', 10) || 0);
        const waitText = section.dataset.waitText || 'İndirme linkiniz kontrol ediliyor, lütfen bekleyiniz';
        const doneText = section.dataset.doneText || 'İndirme linkiniz hazır, indirmek için tıklayın';

        cards.forEach(function(card) {
            if (card.dataset.downloadHandlerBound === '1') return;
            card.dataset.downloadHandlerBound = '1';
            card.addEventListener('click', function(event) {
                event.preventDefault();

                // Zaten hazırsa direkt aç
                if (card.dataset.ready === '1') {
                    window.open(card.href, '_blank', 'noopener');
                    return;
                }

                // Countdown devam ediyorsa bekle
                if (card.dataset.counting === '1') return;

                card.dataset.counting = '1';
                const action = card.querySelector('.topic-dl-action');
                const button = card.querySelector('.topic-dl-button');
                let remaining = countdownSeconds;

                card.classList.add('is-counting');
                card.setAttribute('aria-busy', 'true');
                if (button) button.setAttribute('aria-live', 'polite');

                if (action) {
                    action.textContent = remaining > 0
                        ? waitText + '... ' + remaining
                        : waitText + '...';
                }

                // Countdown yoksa direkt hazır
                if (remaining <= 0) {
                    finishCountdown(card, action, doneText);
                    return;
                }

                // Countdown başlat
                const timer = setInterval(function() {
                    remaining -= 1;

                    if (remaining > 0) {
                        if (action) action.textContent = waitText + '... ' + remaining;
                        return;
                    }

                    clearInterval(timer);
                    finishCountdown(card, action, doneText);
                }, 1000);
            });
        });

        function finishCountdown(card, action, doneText) {
            card.dataset.ready = '1';
            card.dataset.counting = '0';
            card.removeAttribute('aria-busy');
            card.classList.remove('is-counting');
            card.classList.add('is-ready');
            if (action) action.textContent = doneText;
        }
    });
})();
