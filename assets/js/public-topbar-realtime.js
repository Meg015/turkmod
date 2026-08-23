(function () {
    "use strict";

    var reconnectDelay = 5000;
    var reconnectDelayMax = 30000;
    var fallbackIntervalMs = 5000;

    function refresh(kind) {
        if (document.hidden) {
            return;
        }

        var topbar = window.publicTopbar || {};
        var method = kind === "messages" ? topbar.refreshMessages : topbar.refreshNotifications;
        if (typeof method === "function") {
            method();
        }
    }

    function refreshAll() {
        refresh("messages");
        refresh("notifications");
    }

    function normalizeMessageBadgeCount(value) {
        var parsed = parseInt(String(value || "").replace(/\D/g, ""), 10);
        return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
    }

    function updateMessageBadgeDom(count) {
        var badge = document.getElementById("msgBadge") || document.querySelector("[data-messages-badge]");
        if (!badge) {
            return false;
        }

        badge.textContent = count > 99 ? "99+" : String(count);
        if (count > 0) {
            badge.classList.add("is-visible");
        } else {
            badge.classList.remove("is-visible");
        }

        return true;
    }

    window.publicTopbar = window.publicTopbar || {};
    window.publicTopbar.setMessageBadgeCount = function (count) {
        var nextCount = normalizeMessageBadgeCount(count);
        window.publicTopbar.messageBadgeCount = nextCount;
        updateMessageBadgeDom(nextCount);
        return nextCount;
    };

    function getPublicFetchJson() {
        var api = window.publicApi && typeof window.publicApi === "object" ? window.publicApi : null;

        if (typeof window.publicFetchJson !== "function" && api && typeof api.fetchJson === "function") {
            window.publicFetchJson = api.fetchJson.bind(api);
        }

        if (typeof window.publicFetchJson === "function") {
            return window.publicFetchJson;
        }

        return api && typeof api.fetchJson === "function"
            ? api.fetchJson.bind(api)
            : null;
    }

    function init() {
        var menu = document.querySelector("[data-public-topbar-user-id]");
        var userId = menu ? Number(menu.getAttribute("data-public-topbar-user-id") || 0) : 0;
        var endpoint = menu ? menu.getAttribute("data-public-realtime-url") || "" : "";
        var baseUriMeta = document.querySelector('meta[name="app-base-uri"]');
        var baseUri = baseUriMeta ? String(baseUriMeta.getAttribute("content") || "").replace(/\/$/, "") : "";
        var presenceEndpoint = baseUri + "/api/user-presence.php";
        var authStateRefreshPromise = null;
        var authStateReloadScheduled = false;
        var authStateLastCheckedAt = 0;
        var authStateMinRefreshIntervalMs = 15000;

        var socket = null;
        var connected = false;
        var reconnectTimer = null;
        var stopped = false;
        var subscribers = [];
        var messagePreferences = {
            enabled: true,
            sound_enabled: false,
            desktop_enabled: false
        };
        var seenMessageIds = new Set();
        var seenMessageOrder = [];
        var seenNotificationIds = new Set();
        var seenNotificationOrder = [];
        var pendingMessageIds = new Set();
        var pendingMessageGroups = new Map();
        // Keep direct-message websocket paths from producing duplicate toasts.
        var directMessageToastThreads = new Map();
        var directMessageToastWindowMs = 1500;
        var aggregationWindowMs = 2000;
        var seenTtlMs = 10 * 60 * 1000;
        var notificationSeenTtlMs = 10 * 60 * 1000;
        var tabStateTtlMs = 20 * 1000;
        var tabId = createTabId();
        var lastActiveAt = Date.now();
        var coordinationScope = userId > 0 ? "user-" + userId : "guest";
        var tabStatePrefix = "public-message-tab:" + coordinationScope + ":";
        var tabStateKey = tabStatePrefix + tabId;
        var seenStorageKey = "public-message-seen:" + coordinationScope;
        var notificationSeenStorageKey = "public-notification-seen:" + coordinationScope;
        var crossTabEventKey = "public-realtime-event:" + coordinationScope;
        var peerStates = new Map();
        var messageChannel = null;
        var heartbeatTimer = null;
        var audioContext = null;
        var presenceWatchers = new Map();
        var localPresenceIds = new Set();
        var presenceStates = new Map();
        var presenceWatcherSequence = 0;
        var socketSubscribedIds = new Set();
        var presenceFallbackTimer = null;
        var presenceFailureCount = 0;
        var presenceRequestSequence = 0;
        var presenceAppliedRequestSequence = 0;
        var presenceServerInstanceId = "";
        var presenceServerSequence = 0;
        var ownershipReconcileTimer = null;
        var hasNetworkOwnership = false;
        var networkPresenceKey = "";
        var domPresenceWatcherCleanup = null;
        var domPresenceObserver = null;
        var domPresenceScanTimer = null;

        if (typeof window.publicTopbar.messageBadgeCount === "number") {
            updateMessageBadgeDom(window.publicTopbar.messageBadgeCount);
        }

        function authStateIsLoggedIn(payload) {
            var state = payload && payload.auth && typeof payload.auth === "object"
                ? payload.auth
                : payload;

            if (!state || typeof state !== "object") {
                return false;
            }

            if (Object.prototype.hasOwnProperty.call(state, "logged_in")) {
                return state.logged_in === true || state.logged_in === 1 || state.logged_in === "1";
            }

            if (Object.prototype.hasOwnProperty.call(state, "authenticated")) {
                return state.authenticated === true || state.authenticated === 1 || state.authenticated === "1";
            }

            return false;
        }

        function scheduleAuthReload() {
            if (authStateReloadScheduled) {
                return;
            }

            authStateReloadScheduled = true;
            window.setTimeout(function () {
                window.location.reload();
            }, 0);
        }

        function refreshAuthState(force) {
            if (userId <= 0) {
                return Promise.resolve(true);
            }

            if (!force && document.hidden) {
                return Promise.resolve(true);
            }

            if (authStateRefreshPromise) {
                return authStateRefreshPromise;
            }

            var now = Date.now();
            if (!force && authStateLastCheckedAt > 0 && now - authStateLastCheckedAt < authStateMinRefreshIntervalMs) {
                return Promise.resolve(true);
            }

            authStateLastCheckedAt = now;
            try {
                var apiFetch = getPublicFetchJson();
                if (typeof apiFetch !== "function") {
                    return Promise.resolve(true);
                }

                authStateRefreshPromise = apiFetch(baseUri + "/api/auth-state.php", {
                    method: "GET",
                    cache: "no-store",
                    notifyError: false,
                    csrfRetry: false
                }).then(function (payload) {
                    authStateRefreshPromise = null;

                    if (!authStateIsLoggedIn(payload)) {
                        scheduleAuthReload();
                        return false;
                    }

                    return true;
                }).catch(function () {
                    authStateRefreshPromise = null;
                    return true;
                });
            } catch (error) {
                authStateRefreshPromise = null;
                return true;
            }

            return authStateRefreshPromise;
        }

        function createTabId() {
            try {
                if (window.crypto && typeof window.crypto.randomUUID === "function") {
                    return window.crypto.randomUUID();
                }
            } catch (error) {
                // Fall through to a non-cryptographic per-tab identifier.
            }
            return Date.now().toString(36) + "-" + Math.random().toString(36).slice(2);
        }

        function boolPreference(value, fallback) {
            if (typeof value === "undefined" || value === null || value === "") {
                return fallback;
            }
            if (typeof value === "boolean") {
                return value;
            }
            return ["1", "true", "yes", "on"].indexOf(String(value).toLowerCase()) !== -1;
        }

        function setMessagePreferences(preferences) {
            preferences = preferences || {};
            messagePreferences.enabled = boolPreference(preferences.enabled, true);
            messagePreferences.sound_enabled = messagePreferences.enabled && boolPreference(preferences.sound_enabled, false);
            messagePreferences.desktop_enabled = messagePreferences.enabled && boolPreference(preferences.desktop_enabled, false);

            if (!messagePreferences.enabled) {
                pendingMessageGroups.forEach(function (group) {
                    if (group.timer) {
                        window.clearTimeout(group.timer);
                    }
                    group.message_ids.forEach(function (messageId) {
                        pendingMessageIds.delete(messageId);
                    });
                });
                pendingMessageGroups.clear();
            }
        }

        setMessagePreferences(window.publicMessageNotificationPreferences || {});

        function safeStorageGet(key) {
            try {
                return window.localStorage ? window.localStorage.getItem(key) : null;
            } catch (error) {
                return null;
            }
        }

        function safeStorageSet(key, value) {
            try {
                if (!window.localStorage) {
                    return false;
                }
                window.localStorage.setItem(key, value);
                return true;
            } catch (error) {
                return false;
            }
        }

        function safeStorageRemove(key) {
            try {
                if (window.localStorage) {
                    window.localStorage.removeItem(key);
                }
            } catch (error) {
                // Storage is an optional cross-tab optimization.
            }
        }

        function pageHasFocus() {
            if (document.hidden) {
                return false;
            }
            return typeof document.hasFocus !== "function" || document.hasFocus();
        }

        function pageIsVisible() {
            return !document.hidden;
        }

        function currentTabState() {
            return {
                tab_id: tabId,
                visible: !document.hidden,
                focused: pageHasFocus(),
                last_active_at: lastActiveAt,
                updated_at: Date.now(),
                presence_user_ids: Array.from(localPresenceIds).slice(0, 500)
            };
        }

        function recordPeerState(state) {
            if (!state || !state.tab_id) {
                return;
            }
            peerStates.set(String(state.tab_id), state);
            scheduleOwnershipReconcile();
        }

        function broadcastChannelMessage(payload) {
            var channelDelivered = false;
            if (messageChannel) {
                try {
                    messageChannel.postMessage(payload);
                    channelDelivered = true;
                } catch (error) {
                    // localStorage remains available as a fallback where possible.
                }
            }
            if (!channelDelivered && payload && ["realtime_event", "presence_payload", "bye", "seen", "notification_seen"].indexOf(payload.type) !== -1) {
                safeStorageSet(crossTabEventKey, JSON.stringify({
                    sender_tab_id: tabId,
                    nonce: createTabId(),
                    payload: payload
                }));
            }
        }

        function broadcastMessageBadgeCount(count) {
            var nextCount = normalizeMessageBadgeCount(count);
            window.publicTopbar.setMessageBadgeCount(nextCount);
            broadcastChannelMessage({
                type: "realtime_event",
                payload: {
                    type: "message_badge_sync",
                    message_count: nextCount
                }
            });
            return nextCount;
        }

        function persistTabState(markActive) {
            if (markActive) {
                lastActiveAt = Date.now();
            }
            var state = currentTabState();
            recordPeerState(state);
            safeStorageSet(tabStateKey, JSON.stringify(state));
            broadcastChannelMessage({ type: "state", state: state });
        }

        function loadStoredTabStates() {
            var states = [];
            if (messageChannel !== null) {
                return states;
            }
            var now = Date.now();
            try {
                if (!window.localStorage) {
                    return states;
                }
                for (var index = window.localStorage.length - 1; index >= 0; index -= 1) {
                    var key = window.localStorage.key(index);
                    if (!key || key.indexOf(tabStatePrefix) !== 0) {
                        continue;
                    }
                    var value = window.localStorage.getItem(key);
                    var state = value ? JSON.parse(value) : null;
                    if (state && state.tab_id && now - Number(state.updated_at || 0) <= tabStateTtlMs) {
                        states.push(state);
                    } else {
                        safeStorageRemove(key);
                    }
                }
            } catch (error) {
                return states;
            }
            return states;
        }

        function liveTabStates() {
            var now = Date.now();
            var candidates = new Map(peerStates);
            loadStoredTabStates().forEach(function (state) {
                candidates.set(String(state.tab_id), state);
            });
            candidates.set(tabId, currentTabState());

            var liveStates = [];
            candidates.forEach(function (state, candidateId) {
                if (!state || now - Number(state.updated_at || 0) > tabStateTtlMs) {
                    peerStates.delete(candidateId);
                    return;
                }
                liveStates.push(state);
            });
            return liveStates;
        }

        function networkOwnerId() {
            var liveStates = liveTabStates();
            if (liveStates.length === 0) {
                return tabId;
            }
            var visibleStates = liveStates.filter(function (state) {
                return !!state.visible;
            });
            var focusedStates = liveStates.filter(function (state) {
                return !!state.visible && !!state.focused;
            });
            var pool = focusedStates.length > 0 ? focusedStates : (visibleStates.length > 0 ? visibleStates : liveStates);
            pool.sort(function (left, right) {
                var activeDifference = Number(right.last_active_at || 0) - Number(left.last_active_at || 0);
                if (activeDifference !== 0) {
                    return activeDifference;
                }
                var updateDifference = Number(right.updated_at || 0) - Number(left.updated_at || 0);
                if (updateDifference !== 0) {
                    return updateDifference;
                }
                return String(left.tab_id).localeCompare(String(right.tab_id));
            });
            return String(pool[0].tab_id || tabId);
        }

        function ownsNetworkSurface() {
            return networkOwnerId() === tabId;
        }

        function scheduleOwnershipReconcile() {
            if (stopped || ownershipReconcileTimer) {
                return;
            }
            ownershipReconcileTimer = window.setTimeout(function () {
                ownershipReconcileTimer = null;
                reconcileNetworkOwnership();
            }, 25);
        }

        function notificationOwnerId() {
            var now = Date.now();
            var candidates = new Map(peerStates);
            loadStoredTabStates().forEach(function (state) {
                candidates.set(String(state.tab_id), state);
            });
            candidates.set(tabId, currentTabState());

            var liveStates = [];
            candidates.forEach(function (state, candidateId) {
                if (!state || now - Number(state.updated_at || 0) > tabStateTtlMs) {
                    peerStates.delete(candidateId);
                    return;
                }
                liveStates.push(state);
            });
            if (liveStates.length === 0) {
                return tabId;
            }

            var visibleStates = liveStates.filter(function (state) {
                return !!state.visible;
            });
            var focusedStates = liveStates.filter(function (state) {
                return !!state.visible && !!state.focused;
            });
            var pool = focusedStates.length > 0 ? focusedStates : (visibleStates.length > 0 ? visibleStates : liveStates);
            pool.sort(function (left, right) {
                var activeDifference = Number(right.last_active_at || 0) - Number(left.last_active_at || 0);
                if (activeDifference !== 0) {
                    return activeDifference;
                }
                var updateDifference = Number(right.updated_at || 0) - Number(left.updated_at || 0);
                if (updateDifference !== 0) {
                    return updateDifference;
                }
                return String(left.tab_id).localeCompare(String(right.tab_id));
            });
            return String(pool[0].tab_id || tabId);
        }

        function ownsNotificationSurface() {
            return notificationOwnerId() === tabId;
        }

        function loadSeenMessages() {
            var now = Date.now();
            var stored = safeStorageGet(seenStorageKey);
            if (!stored) {
                return;
            }
            try {
                var entries = JSON.parse(stored);
                if (!Array.isArray(entries)) {
                    return;
                }
                entries.forEach(function (entry) {
                    var messageId = Number(entry && entry.id || 0);
                    var seenAt = Number(entry && entry.at || 0);
                    if (messageId > 0 && now - seenAt <= seenTtlMs && !seenMessageIds.has(messageId)) {
                        seenMessageIds.add(messageId);
                        seenMessageOrder.push(messageId);
                    }
                });
            } catch (error) {
                // Ignore malformed optional client cache data.
            }
        }

        function persistSeenMessages() {
            var now = Date.now();
            var entries = seenMessageOrder.slice(-200).map(function (messageId) {
                return { id: messageId, at: now };
            });
            safeStorageSet(seenStorageKey, JSON.stringify(entries));
        }

        function rememberMessage(messageId) {
            if (seenMessageIds.has(messageId)) {
                return false;
            }

            seenMessageIds.add(messageId);
            seenMessageOrder.push(messageId);
            while (seenMessageOrder.length > 200) {
                seenMessageIds.delete(seenMessageOrder.shift());
            }
            persistSeenMessages();
            broadcastChannelMessage({ type: "seen", message_ids: [messageId] });
            return true;
        }

        function rememberMessages(messageIds) {
            var added = false;
            messageIds.forEach(function (messageId) {
                if (messageId > 0 && !seenMessageIds.has(messageId)) {
                    seenMessageIds.add(messageId);
                    seenMessageOrder.push(messageId);
                    added = true;
                }
                pendingMessageIds.delete(messageId);
            });
            while (seenMessageOrder.length > 200) {
                seenMessageIds.delete(seenMessageOrder.shift());
            }
            if (added) {
                persistSeenMessages();
                broadcastChannelMessage({ type: "seen", message_ids: messageIds });
            }
        }

        function messageWasSeen(messageId) {
            loadSeenMessages();
            return seenMessageIds.has(messageId);
        }

        function loadSeenNotifications() {
            var now = Date.now();
            var stored = safeStorageGet(notificationSeenStorageKey);
            if (!stored) {
                return;
            }
            var nextSeenNotificationIds = new Set();
            var nextSeenNotificationOrder = [];
            try {
                var entries = JSON.parse(stored);
                if (!Array.isArray(entries)) {
                    return;
                }
                entries.forEach(function (entry) {
                    var notificationId = Number(entry && entry.id || 0);
                    var seenAt = Number(entry && entry.at || 0);
                    if (notificationId > 0 && now - seenAt <= notificationSeenTtlMs && !nextSeenNotificationIds.has(notificationId)) {
                        nextSeenNotificationIds.add(notificationId);
                        nextSeenNotificationOrder.push(notificationId);
                    }
                });
                seenNotificationIds = nextSeenNotificationIds;
                seenNotificationOrder = nextSeenNotificationOrder;
            } catch (error) {
                // Ignore malformed optional client cache data.
            }
        }

        function persistSeenNotifications() {
            var now = Date.now();
            var entries = seenNotificationOrder.slice(-200).map(function (notificationId) {
                return { id: notificationId, at: now };
            });
            safeStorageSet(notificationSeenStorageKey, JSON.stringify(entries));
        }

        function recordNotificationId(notificationId, options) {
            notificationId = Number(notificationId || 0);
            options = options || {};
            if (notificationId <= 0) {
                return true;
            }

            loadSeenNotifications();
            if (seenNotificationIds.has(notificationId)) {
                return false;
            }

            seenNotificationIds.add(notificationId);
            seenNotificationOrder.push(notificationId);
            while (seenNotificationOrder.length > 200) {
                seenNotificationIds.delete(seenNotificationOrder.shift());
            }
            persistSeenNotifications();
            if (options.broadcast !== false) {
                broadcastChannelMessage({ type: "notification_seen", notification_ids: [notificationId] });
            }
            return true;
        }

        function activeMessageThreadId() {
            var messagesRoot = document.querySelector("[data-messages-root]");
            return messagesRoot ? Number(messagesRoot.getAttribute("data-active-thread-id") || 0) : 0;
        }

        function safeThreadUrl(value) {
            try {
                var resolved = new URL(String(value || ""), window.location.href);
                if (resolved.origin !== window.location.origin) {
                    return "";
                }
                if (resolved.protocol !== "http:" && resolved.protocol !== "https:") {
                    return "";
                }
                return resolved.toString();
            } catch (error) {
                return "";
            }
        }

        function threadIdFromUrl(value) {
            var resolvedUrl = safeThreadUrl(value);
            if (!resolvedUrl) {
                return 0;
            }

            try {
                var parsed = new URL(resolvedUrl, window.location.href);
                return Math.max(0, Number(parsed.searchParams.get("thread") || 0));
            } catch (error) {
                return 0;
            }
        }

        function rememberDirectMessageToast(threadId) {
            threadId = Number(threadId || 0);
            if (threadId <= 0) {
                return;
            }
            directMessageToastThreads.set(threadId, Date.now());
        }

        function recentDirectMessageToast(threadId) {
            threadId = Number(threadId || 0);
            if (threadId <= 0) {
                return false;
            }

            var shownAt = directMessageToastThreads.get(threadId);
            if (typeof shownAt !== "number") {
                return false;
            }
            if (Date.now() - shownAt > directMessageToastWindowMs) {
                directMessageToastThreads.delete(threadId);
                return false;
            }

            return true;
        }

        function notificationText(group) {
            if (group.count > 1) {
                return group.sender_name + " tarafından " + group.count + " yeni mesaj gönderildi.";
            }
            return group.sender_name + " tarafından bir mesaj gönderildi.";
        }

        function normalizeNotificationToastType(value) {
            var type = String(value || "").trim().toLowerCase();
            if (type === "danger" || type === "failed") {
                return "error";
            }
            if (type === "warn") {
                return "warning";
            }
            if (type === "ok") {
                return "success";
            }
            if (type === "system") {
                return "info";
            }
            if (type === "error" || type === "warning" || type === "success" || type === "info") {
                return type;
            }
            return "info";
        }

        function notificationToastVisible() {
            return pageIsVisible();
        }

        function notificationToastOptions(payload) {
            var title = String(payload && (payload.title || payload.notification_title) || "").trim();
            var message = String(payload && (payload.message || payload.notification_message) || "").trim();
            var link = safeThreadUrl(payload && (payload.link || payload.notification_link) || "");
            var toastType = normalizeNotificationToastType(payload && (payload.notification_type || payload.level || payload.severity) || "info");
            var hasBody = title !== "" && message !== "";
            var body = hasBody ? title : (message || title || "Yeni bildiriminiz var.");
            var options = {
                type: toastType,
                message: body
            };

            if (hasBody) {
                options.title = "Bildirim";
                options.detail = message;
            }

            if (link) {
                options.clickUrl = link;
                options.clickLabel = "Bildirimi aç";
            }

            return options;
        }

        function showNotificationToast(payload) {
            if (!notificationToastVisible()) {
                return false;
            }
            var messageThreadId = threadIdFromUrl(payload && (payload.link || payload.notification_link) || "");
            if (recentDirectMessageToast(messageThreadId)) {
                return false;
            }
            window.showToast(notificationToastOptions(payload));
            rememberDirectMessageToast(messageThreadId);
            return true;
        }

        function showRealtimeDesktopNotification(payload) {
            if (!ownsNotificationSurface() || !("Notification" in window) || window.Notification.permission !== "granted") {
                return false;
            }

            var notificationId = Number(payload && payload.notification_id || 0);
            var title = String(payload && (payload.title || payload.notification_title) || "").trim();
            var message = String(payload && (payload.message || payload.notification_message) || "").trim();
            var link = safeThreadUrl(payload && (payload.link || payload.notification_link) || "");
            var messageThreadId = threadIdFromUrl(link);

            if (messageThreadId > 0) {
                rememberDirectMessageToast(messageThreadId);
                return false;
            }

            try {
                var desktopNotification = new window.Notification(title || "Yeni bildirim", {
                    body: message || "Yeni bildiriminiz var.",
                    tag: notificationId > 0 ? "site-notification-" + notificationId : "site-notification",
                    renotify: true,
                    silent: true
                });
                desktopNotification.onclick = function () {
                    try {
                        desktopNotification.close();
                    } catch (error) {
                        // Closing is optional.
                    }
                    window.focus();
                    if (link) {
                        window.location.assign(link);
                    }
                };
                rememberDirectMessageToast(messageThreadId);
                return true;
            } catch (error) {
                return false;
            }
        }

        function ensureAudioContext() {
            if (audioContext) {
                return audioContext;
            }
            var AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (!AudioContextClass) {
                return null;
            }
            try {
                audioContext = new AudioContextClass();
            } catch (error) {
                audioContext = null;
            }
            return audioContext;
        }

        function unlockMessageSound() {
            var context = ensureAudioContext();
            if (context && context.state === "suspended" && typeof context.resume === "function") {
                Promise.resolve(context.resume()).catch(function () {});
            }
        }

        function playMessageSound() {
            if (!messagePreferences.sound_enabled || !pageIsVisible()) {
                return;
            }
            var context = ensureAudioContext();
            if (!context || context.state !== "running") {
                return;
            }
            try {
                var now = context.currentTime;
                var oscillator = context.createOscillator();
                var gain = context.createGain();
                oscillator.type = "sine";
                oscillator.frequency.setValueAtTime(740, now);
                oscillator.frequency.exponentialRampToValueAtTime(980, now + 0.14);
                gain.gain.setValueAtTime(0.0001, now);
                gain.gain.exponentialRampToValueAtTime(0.045, now + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.28);
                oscillator.connect(gain);
                gain.connect(context.destination);
                oscillator.start(now);
                oscillator.stop(now + 0.3);
                oscillator.onended = function () {
                    oscillator.disconnect();
                    gain.disconnect();
                };
            } catch (error) {
                // Audio failure must never affect message delivery.
            }
        }

        function showDesktopNotification(group) {
            if (!messagePreferences.desktop_enabled || !("Notification" in window) || window.Notification.permission !== "granted") {
                return false;
            }
            try {
                var desktopNotification = new window.Notification(notificationText(group), {
                    body: "Yeni özel mesajınız var.",
                    tag: "direct-message-" + group.sender_user_id,
                    renotify: group.count > 1,
                    silent: true
                });
                desktopNotification.onclick = function () {
                    try {
                        desktopNotification.close();
                    } catch (error) {
                        // Closing is optional.
                    }
                    window.focus();
                    window.location.assign(group.thread_url);
                };
                return true;
            } catch (error) {
                return false;
            }
        }

        function clearPendingGroup(group) {
            if (!group) {
                return;
            }
            if (group.timer) {
                window.clearTimeout(group.timer);
            }
            group.message_ids.forEach(function (messageId) {
                pendingMessageIds.delete(messageId);
            });
            pendingMessageGroups.delete(group.sender_user_id);
        }

        function discardPendingMessages(messageIds) {
            var handled = new Set(messageIds.map(function (messageId) {
                return Number(messageId || 0);
            }));
            handled.forEach(function (messageId) {
                pendingMessageIds.delete(messageId);
            });
            pendingMessageGroups.forEach(function (group) {
                var previousLength = group.message_ids.length;
                var remaining = group.message_ids.filter(function (messageId) {
                    return !handled.has(messageId);
                });
                var removedCount = previousLength - remaining.length;
                if (removedCount <= 0) {
                    return;
                }
                if (remaining.length === 0) {
                    clearPendingGroup(group);
                    return;
                }
                group.message_ids = remaining;
                group.count = Math.max(remaining.length, group.count - removedCount);
            });
        }

        function flushMessageGroup(senderUserId) {
            var group = pendingMessageGroups.get(senderUserId);
            if (!group || stopped || !messagePreferences.enabled) {
                clearPendingGroup(group);
                return false;
            }

            group.timer = null;

            if (recentDirectMessageToast(group.thread_id)) {
                rememberMessages(group.message_ids);
                clearPendingGroup(group);
                return false;
            }

            if (pageIsVisible()) {
                window.showToast({
                    message: notificationText(group),
                    type: "info",
                    clickUrl: group.thread_url,
                    clickLabel: group.sender_name + " ile konuşmayı aç"
                });
                if (pageIsVisible()) {
                    playMessageSound();
                }
                rememberDirectMessageToast(group.thread_id);
                rememberMessages(group.message_ids);
                clearPendingGroup(group);
                return true;
            }

            if (showDesktopNotification(group)) {
                rememberDirectMessageToast(group.thread_id);
                rememberMessages(group.message_ids);
                clearPendingGroup(group);
                return true;
            }

            rememberMessages(group.message_ids);
            clearPendingGroup(group);
            return false;
        }

        function flushDeferredMessageGroups() {
            pendingMessageGroups.forEach(function (group) {
                if (group.deferred && !group.timer) {
                    flushMessageGroup(group.sender_user_id);
                }
            });
        }

        function queueMessageNotification(payload) {
            var senderUserId = payload.sender_user_id;
            var group = pendingMessageGroups.get(senderUserId);
            if (!group) {
                group = {
                    sender_user_id: senderUserId,
                    sender_name: payload.sender_name,
                    thread_id: payload.thread_id,
                    thread_url: payload.thread_url,
                    count: 0,
                    message_ids: [],
                    timer: null,
                    deferred: false
                };
                pendingMessageGroups.set(senderUserId, group);
            }

            group.sender_name = payload.sender_name;
            group.thread_id = payload.thread_id;
            group.thread_url = payload.thread_url;
            group.count += payload.message_count;
            group.message_ids.push(payload.message_id);
            group.deferred = false;
            pendingMessageIds.add(payload.message_id);

            if (!group.timer) {
                group.timer = window.setTimeout(function () {
                    flushMessageGroup(senderUserId);
                }, aggregationWindowMs);
            }
        }

        function notifyMessage(payload) {
            payload = payload || {};

            var messageId = Number(payload.message_id || payload.last_message_id || 0);
            var threadId = Number(payload.thread_id || 0);
            var senderUserId = Number(payload.sender_user_id || payload.last_sender_user_id || 0);
            var senderName = String(payload.sender_name || payload.with_user_name || "").trim();
            var threadUrl = safeThreadUrl(payload.thread_url);
            var messageCount = Math.max(1, Number(payload.message_count || 1));

            if (!messagePreferences.enabled || messageId <= 0 || threadId <= 0 || senderUserId <= 0 || senderUserId === userId) {
                return false;
            }
            if (!senderName || !threadUrl) {
                return false;
            }
            if (messageWasSeen(messageId) || pendingMessageIds.has(messageId)) {
                return false;
            }
            if (recentDirectMessageToast(threadId)) {
                rememberMessage(messageId);
                return false;
            }

            if (!pageIsVisible()) {
                rememberMessage(messageId);
                return false;
            }

            var toastMessage = notificationText({
                count: messageCount,
                sender_name: senderName.slice(0, 120)
            });

            if (typeof window.showToast === "function") {
                window.showToast({
                    message: toastMessage,
                    type: "info",
                    clickUrl: threadUrl,
                    clickLabel: senderName + " ile konuşmayı aç"
                });
            }

            rememberDirectMessageToast(threadId);
            rememberMessage(messageId);
            playMessageSound();
            return true;
        }

        function handleCrossTabMessage(data) {
            data = data || {};
            if (data.type === "hello") {
                persistTabState(false);
                return;
            }
            if (data.type === "state") {
                recordPeerState(data.state);
                flushDeferredMessageGroups();
                return;
            }
            if (data.type === "bye" && data.tab_id) {
                peerStates.delete(String(data.tab_id));
                flushDeferredMessageGroups();
                scheduleOwnershipReconcile();
                return;
            }
            if (data.type === "realtime_event" && data.payload) {
                handleRealtimePayload(data.payload, true);
                return;
            }
            if (data.type === "presence_payload" && data.payload) {
                applyPresencePayload(data.payload);
                return;
            }
            if (data.type === "notification_seen" && Array.isArray(data.notification_ids)) {
                data.notification_ids.forEach(function (notificationId) {
                    recordNotificationId(notificationId, { broadcast: false });
                });
                return;
            }
            if (data.type !== "seen" || !Array.isArray(data.message_ids)) {
                return;
            }

            data.message_ids.forEach(function (messageId) {
                messageId = Number(messageId || 0);
                if (messageId > 0 && !seenMessageIds.has(messageId)) {
                    seenMessageIds.add(messageId);
                    seenMessageOrder.push(messageId);
                    pendingMessageIds.delete(messageId);
                }
            });
            while (seenMessageOrder.length > 200) {
                seenMessageIds.delete(seenMessageOrder.shift());
            }
            discardPendingMessages(data.message_ids);
        }

        function initCrossTabCoordination() {
            loadSeenMessages();
            loadSeenNotifications();
            try {
                if (typeof window.BroadcastChannel === "function") {
                    messageChannel = new window.BroadcastChannel("public-message-notifications:" + userId);
                    messageChannel.onmessage = function (event) {
                        handleCrossTabMessage(event && event.data || {});
                    };
                    messageChannel.postMessage({ type: "hello", tab_id: tabId });
                }
            } catch (error) {
                messageChannel = null;
            }

            persistTabState(true);
            heartbeatTimer = window.setInterval(function () {
                persistTabState(false);
            }, 10000);
            scheduleOwnershipReconcile();
        }

        function normalizePresenceIds(userIds, limit) {
            var normalized = new Set();
            var values = Array.isArray(userIds) || userIds instanceof Set ? Array.from(userIds) : [userIds];
            values.forEach(function (value) {
                var id = Number(value || 0);
                if (Number.isInteger(id) && id > 0) {
                    normalized.add(id);
                }
            });
            return Array.from(normalized).sort(function (left, right) {
                return left - right;
            }).slice(0, Math.max(1, Number(limit || 500)));
        }

        function combinedPresenceIds() {
            var combined = new Set();
            liveTabStates().forEach(function (state) {
                normalizePresenceIds(state.presence_user_ids || [], 500).forEach(function (presenceUserId) {
                    if (combined.size < 500) {
                        combined.add(presenceUserId);
                    }
                });
            });
            return Array.from(combined).sort(function (left, right) {
                return left - right;
            });
        }

        function notifyPresenceWatchers(presenceUserId, state) {
            presenceWatchers.forEach(function (watcher) {
                if (!watcher.ids.has(presenceUserId)) {
                    return;
                }
                try {
                    watcher.callback(presenceUserId, Object.assign({}, state));
                } catch (error) {
                    // A surface must not disrupt the shared presence stream.
                }
            });
        }

        function applyPresenceUsers(users, source, preserveOnline) {
            if (!users || typeof users !== "object") {
                return;
            }
            Object.keys(users).forEach(function (key) {
                var incoming = users[key] || {};
                var presenceUserId = Number(incoming.user_id || key || 0);
                if (!Number.isInteger(presenceUserId) || presenceUserId <= 0) {
                    return;
                }

                var visible = incoming.visible === true;
                var state = {
                    user_id: presenceUserId,
                    visible: visible,
                    is_online: visible && incoming.is_online === true,
                    status_label: visible && incoming.is_online === true ? "Çevrimiçi" : "Çevrimdışı",
                    relative_label: visible ? String(incoming.relative_label || "Bilinmiyor") : "",
                    state_class: visible && incoming.is_online === true ? "is-online" : "is-offline",
                    _source: source
                };
                var previous = presenceStates.get(presenceUserId);
                if (source === "http" && preserveOnline && visible && previous && previous.visible && previous._source === "websocket") {
                    state.is_online = previous.is_online;
                    state.status_label = previous.status_label;
                    state.state_class = previous.state_class;
                    state._source = previous._source;
                }
                presenceStates.set(presenceUserId, state);
                notifyPresenceWatchers(presenceUserId, state);
            });
        }

        function applyPresencePayload(payload) {
            if (!payload || typeof payload !== "object") {
                return;
            }
            var source = payload.source === "http" ? "http" : "websocket";
            if (source === "websocket") {
                var instanceId = String(payload.server_instance_id || "");
                var sequence = Number(payload.sequence || 0);
                if (!instanceId || !Number.isFinite(sequence) || sequence <= 0) {
                    return;
                }
                if (presenceServerInstanceId !== instanceId) {
                    presenceServerInstanceId = instanceId;
                    presenceServerSequence = 0;
                }
                if (sequence <= presenceServerSequence) {
                    return;
                }
                presenceServerSequence = sequence;
            } else {
                var requestSequence = Number(payload.request_sequence || 0);
                if (requestSequence > 0 && requestSequence < presenceAppliedRequestSequence) {
                    return;
                }
                presenceAppliedRequestSequence = Math.max(presenceAppliedRequestSequence, requestSequence);
            }
            applyPresenceUsers(payload.users, source, payload.preserve_online === true);
        }

        function recomputeLocalPresenceIds() {
            var nextIds = new Set();
            presenceWatchers.forEach(function (watcher) {
                watcher.ids.forEach(function (presenceUserId) {
                    if (nextIds.size < 500) {
                        nextIds.add(presenceUserId);
                    }
                });
            });
            localPresenceIds = nextIds;
            persistTabState(false);
            reconcilePresenceSubscriptions();
            schedulePresenceRefresh(0);
        }

        function watchPresence(userIds, callback) {
            var ids = new Set(normalizePresenceIds(userIds, 500));
            if (typeof callback !== "function" || ids.size === 0) {
                return function () {};
            }
            presenceWatcherSequence += 1;
            var watcherId = "presence-watcher-" + presenceWatcherSequence;
            presenceWatchers.set(watcherId, { ids: ids, callback: callback });
            ids.forEach(function (presenceUserId) {
                if (presenceStates.has(presenceUserId)) {
                    callback(presenceUserId, Object.assign({}, presenceStates.get(presenceUserId)));
                }
            });
            recomputeLocalPresenceIds();

            return function () {
                if (presenceWatchers.delete(watcherId)) {
                    recomputeLocalPresenceIds();
                }
            };
        }

        function scanPresenceDom() {
            domPresenceScanTimer = null;
            var rootsByUser = new Map();
            Array.prototype.forEach.call(document.querySelectorAll("[data-presence-user-id]"), function (root) {
                var presenceUserId = Number(root.getAttribute("data-presence-user-id") || 0);
                if (!Number.isInteger(presenceUserId) || presenceUserId <= 0) {
                    return;
                }
                if (!rootsByUser.has(presenceUserId)) {
                    rootsByUser.set(presenceUserId, []);
                }
                rootsByUser.get(presenceUserId).push(root);
            });

            if (domPresenceWatcherCleanup) {
                domPresenceWatcherCleanup();
                domPresenceWatcherCleanup = null;
            }
            if (rootsByUser.size === 0) {
                return;
            }
            domPresenceWatcherCleanup = watchPresence(Array.from(rootsByUser.keys()), function (presenceUserId, state) {
                (rootsByUser.get(presenceUserId) || []).forEach(function (root) {
                    if (window.publicPresenceUI && typeof window.publicPresenceUI.apply === "function") {
                        window.publicPresenceUI.apply(root, state);
                    }
                });
            });
        }

        function schedulePresenceDomScan() {
            if (domPresenceScanTimer) {
                return;
            }
            domPresenceScanTimer = window.setTimeout(scanPresenceDom, 25);
        }

        function initPresenceDomObserver() {
            scanPresenceDom();
            if (typeof window.MutationObserver !== "function" || !document.body) {
                return;
            }
            domPresenceObserver = new window.MutationObserver(function (mutations) {
                var shouldScan = mutations.some(function (mutation) {
                    var changedNodes = Array.prototype.slice.call(mutation.addedNodes || [])
                        .concat(Array.prototype.slice.call(mutation.removedNodes || []));
                    return changedNodes.some(function (node) {
                        if (!node || node.nodeType !== 1) {
                            return false;
                        }
                        return (node.matches && node.matches("[data-presence-user-id]"))
                            || (node.querySelector && node.querySelector("[data-presence-user-id]"));
                    });
                });
                if (shouldScan) {
                    schedulePresenceDomScan();
                }
            });
            domPresenceObserver.observe(document.body, { childList: true, subtree: true });
        }

        function chunkIds(ids, size) {
            var chunks = [];
            for (var index = 0; index < ids.length; index += size) {
                chunks.push(ids.slice(index, index + size));
            }
            return chunks;
        }

        function socketSend(payload) {
            if (!socket || socket.readyState !== window.WebSocket.OPEN) {
                return false;
            }
            try {
                socket.send(JSON.stringify(payload));
                return true;
            } catch (error) {
                return false;
            }
        }

        function reconcilePresenceSubscriptions() {
            if (!ownsNetworkSurface() || !connected) {
                return;
            }
            var desired = new Set(combinedPresenceIds());
            var added = [];
            var removed = [];
            desired.forEach(function (presenceUserId) {
                if (!socketSubscribedIds.has(presenceUserId)) {
                    added.push(presenceUserId);
                }
            });
            socketSubscribedIds.forEach(function (presenceUserId) {
                if (!desired.has(presenceUserId)) {
                    removed.push(presenceUserId);
                }
            });

            chunkIds(added, 100).forEach(function (ids) {
                if (socketSend({ type: "presence_subscribe", user_ids: ids })) {
                    ids.forEach(function (presenceUserId) {
                        socketSubscribedIds.add(presenceUserId);
                    });
                }
            });
            chunkIds(removed, 100).forEach(function (ids) {
                if (socketSend({ type: "presence_unsubscribe", user_ids: ids })) {
                    ids.forEach(function (presenceUserId) {
                        socketSubscribedIds.delete(presenceUserId);
                    });
                }
            });
        }

        function clearPresenceFallbackTimer() {
            if (presenceFallbackTimer) {
                window.clearTimeout(presenceFallbackTimer);
                presenceFallbackTimer = null;
            }
        }

        function schedulePresenceRefresh(delay) {
            clearPresenceFallbackTimer();
            if (stopped || !ownsNetworkSurface() || document.hidden || combinedPresenceIds().length === 0) {
                return;
            }
            var resolvedDelay = typeof delay === "number" ? Math.max(0, delay) : (connected ? 300000 : 60000);
            presenceFallbackTimer = window.setTimeout(function () {
                presenceFallbackTimer = null;
                refreshPresenceFallback();
            }, resolvedDelay);
        }

        function refreshPresenceFallback() {
            if (stopped || !ownsNetworkSurface() || document.hidden) {
                schedulePresenceRefresh();
                return;
            }
            var ids = combinedPresenceIds();
            if (ids.length === 0) {
                return;
            }

            presenceRequestSequence += 1;
            var requestSequence = presenceRequestSequence;
            var users = {};
            var batches = chunkIds(ids, 100);
            var apiFetch = getPublicFetchJson();
            if (typeof apiFetch !== "function") {
                schedulePresenceRefresh(60000);
                return;
            }
            var chain = Promise.resolve();
            batches.forEach(function (batch) {
                chain = chain.then(function () {
                    var url = presenceEndpoint + "?ids=" + encodeURIComponent(batch.join(","));
                    return apiFetch(url, {
                        method: "GET",
                        credentials: "same-origin",
                        headers: { Accept: "application/json" },
                        notifyError: false,
                        csrfRetry: false
                    }).then(function (payload) {
                        if (!payload || payload.success === false || !payload.users) {
                            throw new Error("Invalid presence response");
                        }
                        Object.keys(payload.users).forEach(function (key) {
                            users[key] = payload.users[key];
                        });
                    });
                });
            });

            chain.then(function () {
                if (requestSequence < presenceRequestSequence) {
                    return;
                }
                presenceFailureCount = 0;
                var payload = {
                    type: "presence_snapshot",
                    source: "http",
                    request_sequence: requestSequence,
                    observed_at: Date.now(),
                    preserve_online: connected,
                    users: users
                };
                applyPresencePayload(payload);
                broadcastChannelMessage({ type: "presence_payload", payload: payload });
                schedulePresenceRefresh(connected ? 300000 : 60000);
            }).catch(function () {
                presenceFailureCount += 1;
                var nextDelay = presenceFailureCount === 1 ? 120000 : 300000;
                schedulePresenceRefresh(nextDelay);
            });
        }

        function closeSocketForOwnershipChange() {
            clearReconnectTimer();
            connected = false;
            socketSubscribedIds.clear();
            if (socket) {
                socket.onclose = null;
                try {
                    socket.close();
                } catch (error) {
                    // Socket teardown is best effort during leader handoff.
                }
                socket = null;
            }
        }

        function reconcileNetworkOwnership() {
            if (stopped) {
                return;
            }
            if (!ownsNetworkSurface()) {
                hasNetworkOwnership = false;
                networkPresenceKey = "";
                closeSocketForOwnershipChange();
                clearPresenceFallbackTimer();
                return;
            }
            var nextPresenceKey = combinedPresenceIds().join(",");
            var shouldRefreshImmediately = !hasNetworkOwnership || nextPresenceKey !== networkPresenceKey;
            hasNetworkOwnership = true;
            networkPresenceKey = nextPresenceKey;
            if (userId > 0 && !connected && !reconnectTimer) {
                connect();
            }
            reconcilePresenceSubscriptions();
            if (shouldRefreshImmediately) {
                schedulePresenceRefresh(0);
            } else if (!presenceFallbackTimer) {
                schedulePresenceRefresh();
            }
        }

        document.addEventListener("pointerdown", unlockMessageSound, { once: true, capture: true });
        document.addEventListener("keydown", unlockMessageSound, { once: true, capture: true });
        initCrossTabCoordination();

        function websocketUrl() {
            if (userId <= 0 || !endpoint) {
                return "";
            }

            var resolved;
            try {
                resolved = endpoint.charAt(0) === "/"
                    ? new URL(endpoint, window.location.origin)
                    : new URL(endpoint);
            } catch (error) {
                return "";
            }

            if (resolved.protocol === "http:") {
                resolved.protocol = "ws:";
            } else if (resolved.protocol === "https:") {
                resolved.protocol = "wss:";
            }
            if (resolved.protocol !== "ws:" && resolved.protocol !== "wss:") {
                return "";
            }
            if (window.location.protocol === "https:" && resolved.protocol !== "wss:") {
                return "";
            }

            resolved.searchParams.set("user_id", String(userId));
            return resolved.toString();
        }

        function emit(payload) {
            subscribers.slice().forEach(function (subscriber) {
                try {
                    subscriber(payload);
                } catch (error) {
                    // A consumer must not disrupt real-time badge updates.
                }
            });
        }

        function handleRealtimePayload(payload) {
            if (!payload || typeof payload.type !== "string") {
                return;
            }

            if (payload.type === "presence_snapshot" || payload.type === "presence_changed") {
                applyPresencePayload(payload);
            } else if (payload.type === "presence_error") {
                schedulePresenceRefresh(0);
            } else if (payload.type === "message_badge_sync") {
                window.publicTopbar.setMessageBadgeCount(payload.message_count || 0);
            } else if (payload.type === "new_message") {
                var topbar = window.publicTopbar || {};
                var messageId = Number(payload.message_id || 0);
                var threadId = Number(payload.thread_id || 0);
                var senderUserId = Number(payload.sender_user_id || 0);
                var shouldBumpBadge =
                    senderUserId > 0
                    && senderUserId !== userId
                    && messageId > 0
                    && threadId > 0
                    && !messageWasSeen(messageId)
                    && !pendingMessageIds.has(messageId)
                    && (document.hidden || activeMessageThreadId() !== threadId);

                if (shouldBumpBadge && typeof topbar.incrementMessageBadge === "function") {
                    topbar.incrementMessageBadge();
                }
                notifyMessage(payload);
                refresh("messages");
            } else if (payload.type === "notification") {
                var topbar = window.publicTopbar || {};
                var notificationId = Number(payload.notification_id || 0);
                var shouldToastNotification = recordNotificationId(notificationId);
                if (typeof topbar.incrementNotificationBadge === "function") {
                    topbar.incrementNotificationBadge();
                }
                if (shouldToastNotification) {
                    if (document.hidden) {
                        showRealtimeDesktopNotification(payload);
                    } else {
                        showNotificationToast(payload);
                    }
                }
                refresh("notifications");
            }

            emit(payload);
        }

        function clearReconnectTimer() {
            if (reconnectTimer) {
                window.clearTimeout(reconnectTimer);
                reconnectTimer = null;
            }
        }

        function scheduleReconnect() {
            if (stopped || reconnectTimer || !ownsNetworkSurface() || userId <= 0) {
                return;
            }

            reconnectTimer = window.setTimeout(function () {
                reconnectTimer = null;
                connect();
            }, reconnectDelay);
            reconnectDelay = Math.min(reconnectDelay * 2, reconnectDelayMax);
        }

        function connect() {
            clearReconnectTimer();
            if (stopped || !ownsNetworkSurface() || userId <= 0 || !window.WebSocket) {
                return;
            }
            var url = websocketUrl();
            if (!url) {
                return;
            }

            try {
                socket = new WebSocket(url);
            } catch (error) {
                connected = false;
                scheduleReconnect();
                return;
            }

            socket.onopen = function () {
                connected = true;
                reconnectDelay = 5000;
                socketSubscribedIds.clear();
                refreshAll();
                reconcilePresenceSubscriptions();
                schedulePresenceRefresh(300000);
            };

            socket.onmessage = function (event) {
                var payload;
                try {
                    payload = JSON.parse(event.data);
                } catch (error) {
                    return;
                }

                handleRealtimePayload(payload);
                broadcastChannelMessage({ type: "realtime_event", payload: payload });
            };

            socket.onerror = function () {
                connected = false;
                if (socket) {
                    socket.close();
                }
            };

            socket.onclose = function () {
                connected = false;
                socket = null;
                socketSubscribedIds.clear();
                scheduleReconnect();
                schedulePresenceRefresh(0);
            };
        }

        document.addEventListener("visibilitychange", function () {
            persistTabState(pageHasFocus());
            if (!document.hidden) {
                reconcileNetworkOwnership();
                refreshAuthState(true).then(function (isValid) {
                    if (!isValid) {
                        return;
                    }
                    refreshAll();
                    window.setTimeout(flushDeferredMessageGroups, 25);
                    reconcileNetworkOwnership();
                    schedulePresenceRefresh(0);
                });
            } else {
                clearPresenceFallbackTimer();
            }
        });

        window.addEventListener("focus", function () {
            persistTabState(true);
            reconcileNetworkOwnership();
            refreshAuthState(false);
            window.setTimeout(flushDeferredMessageGroups, 25);
        });

        window.addEventListener("blur", function () {
            persistTabState(false);
        });

        window.addEventListener("pageshow", function (event) {
            if (event.persisted) {
                reconcileNetworkOwnership();
                refreshAuthState(true);
            }
        });

        window.addEventListener("storage", function (event) {
            if (!event || !event.key) {
                return;
            }
            if (event.key === seenStorageKey) {
                loadSeenMessages();
                discardPendingMessages(Array.from(seenMessageIds));
                return;
            }
            if (event.key === crossTabEventKey && event.newValue) {
                try {
                    var crossTabEvent = JSON.parse(event.newValue);
                    if (crossTabEvent && crossTabEvent.sender_tab_id !== tabId) {
                        handleCrossTabMessage(crossTabEvent.payload || {});
                    }
                } catch (error) {
                    // Ignore malformed optional cross-tab events.
                }
                return;
            }
            if (event.key.indexOf(tabStatePrefix) === 0) {
                if (event.newValue) {
                    try {
                        recordPeerState(JSON.parse(event.newValue));
                    } catch (error) {
                        // Ignore malformed optional tab state.
                    }
                } else {
                    peerStates.delete(event.key.slice(tabStatePrefix.length));
                }
                flushDeferredMessageGroups();
                scheduleOwnershipReconcile();
            }
        });

        window.addEventListener("pagehide", function () {
            stopped = true;
            clearReconnectTimer();
            clearPresenceFallbackTimer();
            connected = false;
            if (ownershipReconcileTimer) {
                window.clearTimeout(ownershipReconcileTimer);
                ownershipReconcileTimer = null;
            }
            if (domPresenceScanTimer) {
                window.clearTimeout(domPresenceScanTimer);
                domPresenceScanTimer = null;
            }
            if (domPresenceObserver) {
                domPresenceObserver.disconnect();
                domPresenceObserver = null;
            }
            if (domPresenceWatcherCleanup) {
                domPresenceWatcherCleanup();
                domPresenceWatcherCleanup = null;
            }
            if (heartbeatTimer) {
                window.clearInterval(heartbeatTimer);
                heartbeatTimer = null;
            }
            pendingMessageGroups.forEach(function (group) {
                if (group.timer) {
                    window.clearTimeout(group.timer);
                }
            });
            safeStorageRemove(tabStateKey);
            broadcastChannelMessage({ type: "bye", tab_id: tabId });
            if (messageChannel) {
                try {
                    messageChannel.close();
                } catch (error) {
                    // Closing is optional during page teardown.
                }
                messageChannel = null;
            }
            if (socket) {
                socket.onclose = null;
                socket.close();
            }
        });

        window.setInterval(function () {
            if (!connected && !document.hidden) {
                reconcileNetworkOwnership();
            }
            if (!connected && !document.hidden) {
                refreshAll();
            }
        }, fallbackIntervalMs);

        window.publicTopbarRealtime = {
            notifyMessage: notifyMessage,
            setMessagePreferences: setMessagePreferences,
            flushMessageNotifications: flushDeferredMessageGroups,
            recordNotificationId: recordNotificationId,
            broadcastMessageBadgeCount: broadcastMessageBadgeCount,
            watchPresence: watchPresence,
            getPresenceState: function (presenceUserId) {
                var state = presenceStates.get(Number(presenceUserId || 0));
                return state ? Object.assign({}, state) : null;
            },
            refreshAuthState: refreshAuthState,
            subscribe: function (subscriber) {
                if (typeof subscriber !== "function") {
                    return function () {};
                }
                subscribers.push(subscriber);
                return function () {
                    subscribers = subscribers.filter(function (candidate) {
                        return candidate !== subscriber;
                    });
                };
            }
        };

        refreshAuthState(true).then(function (isValid) {
            if (!isValid) {
                return;
            }
            initPresenceDomObserver();
            reconcileNetworkOwnership();
        });
    }

    document.addEventListener("DOMContentLoaded", init);
})();
