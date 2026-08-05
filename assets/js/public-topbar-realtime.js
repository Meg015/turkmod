(function () {
    "use strict";

    var reconnectDelay = 5000;
    var reconnectDelayMax = 30000;
    var fallbackIntervalMs = 30000;

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

    function init() {
        var menu = document.querySelector("[data-public-topbar-user-id]");
        var userId = menu ? Number(menu.getAttribute("data-public-topbar-user-id") || 0) : 0;
        var endpoint = menu ? menu.getAttribute("data-public-realtime-url") || "" : "";
        if (userId <= 0 || !window.WebSocket) {
            return;
        }

        var socket = null;
        var connected = false;
        var reconnectTimer = null;
        var stopped = false;
        var subscribers = [];

        function websocketUrl() {
            if (!endpoint) {
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

        function clearReconnectTimer() {
            if (reconnectTimer) {
                window.clearTimeout(reconnectTimer);
                reconnectTimer = null;
            }
        }

        function scheduleReconnect() {
            if (stopped || reconnectTimer) {
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
            if (stopped) {
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
                refreshAll();
            };

            socket.onmessage = function (event) {
                var payload;
                try {
                    payload = JSON.parse(event.data);
                } catch (error) {
                    return;
                }

                if (!payload || typeof payload.type !== "string") {
                    return;
                }

                emit(payload);

                if (payload.type === "new_message") {
                    refresh("messages");
                } else if (payload.type === "notification") {
                    refresh("notifications");
                }
            };

            socket.onerror = function () {
                connected = false;
                socket.close();
            };

            socket.onclose = function () {
                connected = false;
                scheduleReconnect();
            };
        }

        document.addEventListener("visibilitychange", function () {
            if (!document.hidden) {
                refreshAll();
                if (!connected && !reconnectTimer) {
                    connect();
                }
            }
        });

        window.addEventListener("pagehide", function () {
            stopped = true;
            clearReconnectTimer();
            connected = false;
            if (socket) {
                socket.onclose = null;
                socket.close();
            }
        });

        window.setInterval(function () {
            if (!connected && !document.hidden) {
                refreshAll();
            }
        }, fallbackIntervalMs);

        window.publicTopbarRealtime = {
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

        connect();
    }

    document.addEventListener("DOMContentLoaded", init);
})();
