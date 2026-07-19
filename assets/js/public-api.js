(function () {
    "use strict";

    function csrfMeta() {
        return document.querySelector('meta[name="csrf-token"]');
    }

    function csrfToken() {
        var meta = csrfMeta();
        return meta ? (meta.getAttribute("content") || "") : "";
    }

    function appBaseUri() {
        var meta = document.querySelector('meta[name="app-base-uri"]');
        return meta && meta.getAttribute("content") ? meta.getAttribute("content").replace(/\/+$/, "") : "";
    }

    function updateCsrfToken(token) {
        if (!token || typeof token !== "string") {
            return;
        }

        var meta = csrfMeta();
        if (meta) {
            meta.setAttribute("content", token);
        }
        document.querySelectorAll('input[name="_token"], input[name="csrf_token"]').forEach(function (input) {
            input.value = token;
        });
        document.querySelectorAll("[data-csrf-token], [data-notifications-csrf]").forEach(function (node) {
            if (node.hasAttribute("data-csrf-token")) {
                node.setAttribute("data-csrf-token", token);
            }
            if (node.hasAttribute("data-notifications-csrf")) {
                node.setAttribute("data-notifications-csrf", token);
            }
        });
    }

    function compactResponseText(text) {
        return String(text || "")
            .replace(/^\uFEFF/, "")
            .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, " ")
            .replace(/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/gi, " ")
            .replace(/<[^>]*>/g, " ")
            .replace(/\s+/g, " ")
            .trim()
            .slice(0, 240);
    }

    function responseError(response, data, rawText, defaultMessage) {
        var status = response && response.status ? Number(response.status) : 0;
        var code = data && (data.code || data.error) ? String(data.code || data.error).toLowerCase() : "";
        var rawMessage = compactResponseText(rawText);
        var rawBody = String(rawText || "");
        var rawLooksLikeHtml = /<\s*(?:!doctype|html|head|body|main|section|div|form|script|style|title)\b/i.test(rawBody);
        var message = data && data.message ? String(data.message) : "";

        if (!message && rawMessage && !rawLooksLikeHtml && rawMessage.length <= 160) {
            message = rawMessage;
        }

        if (status === 419 || code === "csrf_token_invalid" || code === "csrf_failed" || code === "csrf_invalid" || code === "csrf_refresh_required") {
            message = message || "Guvenlik dogrulamasi yenilendi. Lutfen islemi tekrar deneyin.";
        } else if (!message && (status === 401 || status === 403 || code === "forbidden")) {
            message = "Oturumunuz yenilenmis olabilir. Lutfen sayfayi yenileyip tekrar deneyin.";
        } else if (!message) {
            message = defaultMessage || (status > 0 ? "Sunucu yaniti okunamadi. HTTP " + status : "Sunucudan beklenen JSON cevabi alinamadi.");
        }

        var error = new Error(message);
        error.status = status;
        error.code = code;
        error.data = data || null;
        error.rawText = rawText || "";
        return error;
    }

    function applyResponse(data) {
        if (data && typeof data === "object") {
            updateCsrfToken(data.csrfToken || data._token || data.csrf_token || "");
        }
        return data;
    }

    function parseJsonResponse(response, options) {
        var opts = options || {};
        return response.text().then(function (text) {
            var rawText = String(text || "").replace(/^\uFEFF/, "");
            var data = {};

            if (rawText.trim() !== "") {
                try {
                    data = JSON.parse(rawText);
                } catch (parseError) {
                    throw responseError(response, null, rawText, opts.invalidJsonMessage || "Sunucudan gecersiz cevap alindi.");
                }
            }

            applyResponse(data);
            if (!response.ok || data.ok === false || data.success === false) {
                throw responseError(response, data, rawText, opts.errorMessage || "Islem tamamlanamadi.");
            }

            return data || {};
        });
    }

    function notifyError(error, defaultMessage) {
        var message = error && error.message ? error.message : (defaultMessage || "Islem tamamlanamadi.");
        if (typeof window.showToast === "function") {
            window.showToast(message, "error");
        }
    }

    function isCsrfError(error) {
        if (!error) {
            return false;
        }

        var code = String(error.code || "").toLowerCase();
        return Number(error.status || 0) === 419
            || code === "csrf_token_invalid"
            || code === "csrf_failed"
            || code === "csrf_invalid"
            || code === "csrf_refresh_required";
    }

    function refreshCsrfToken() {
        var endpoint = appBaseUri() + "/api/csrf-token.php";
        return fetch(endpoint, {
            method: "GET",
            credentials: "same-origin",
            cache: "no-store",
            headers: {
                "Accept": "application/json",
                "X-Requested-With": "XMLHttpRequest"
            }
        }).then(function (response) {
            if (!response.ok) {
                return null;
            }

            return response.text().then(function (text) {
                if (!String(text || "").trim()) {
                    return null;
                }

                try {
                    return JSON.parse(text);
                } catch (error) {
                    return null;
                }
            });
        }).then(function (data) {
            var token = data && (data.csrfToken || data._token || data.csrf_token);
            if (!token) {
                return false;
            }

            updateCsrfToken(token);
            return true;
        }).catch(function () {
            return false;
        });
    }

    function fetchJson(url, options) {
        var opts = Object.assign({}, options || {});
        var retriedCsrf = !!opts._csrfRetried;
        delete opts._csrfRetried;
        var headers = new Headers(opts.headers || {});
        var token = csrfToken();

        if (!headers.has("Accept")) {
            headers.set("Accept", "application/json");
        }
        if (!headers.has("X-Requested-With")) {
            headers.set("X-Requested-With", "XMLHttpRequest");
        }
        if (token && !headers.has("X-CSRF-Token")) {
            headers.set("X-CSRF-Token", token);
        }

        if (opts.body instanceof FormData) {
            if (token) {
                if (typeof opts.body.set === "function") {
                    opts.body.set("_token", token);
                } else if (!opts.body.has("_token") && !opts.body.has("csrf_token")) {
                    opts.body.append("_token", token);
                }
            }
        } else if (opts.body instanceof URLSearchParams) {
            if (token) {
                opts.body.set("_token", token);
            }
            if (!headers.has("Content-Type")) {
                headers.set("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
            }
        } else if (opts.body && typeof opts.body === "object") {
            if (token) {
                opts.body._token = token;
            } else {
                opts.body._token = opts.body._token || opts.body.csrf_token || "";
            }
            if (!headers.has("Content-Type")) {
                headers.set("Content-Type", "application/json; charset=UTF-8");
            }
            opts.body = JSON.stringify(opts.body);
        }

        opts.credentials = opts.credentials || "same-origin";
        opts.headers = headers;

        return fetch(url, opts).then(function (response) {
            return parseJsonResponse(response, opts).catch(function (error) {
                if (!retriedCsrf && opts.csrfRetry !== false && isCsrfError(error)) {
                    return refreshCsrfToken().then(function (refreshed) {
                        if (!refreshed) {
                            throw error;
                        }

                        return fetchJson(url, Object.assign({}, options || {}, { _csrfRetried: true }));
                    });
                }
                if (opts.notifyError) {
                    notifyError(error, opts.errorMessage);
                }
                throw error;
            });
        });
    }

    window.publicApi = Object.assign(window.publicApi || {}, {
        csrfToken: csrfToken,
        updateCsrfToken: updateCsrfToken,
        applyResponse: applyResponse,
        compactResponseText: compactResponseText,
        parseJsonResponse: parseJsonResponse,
        fetchJson: fetchJson,
        notifyError: notifyError,
        isCsrfError: isCsrfError,
        refreshCsrfToken: refreshCsrfToken
    });
    window.publicParseJsonResponse = parseJsonResponse;
    window.publicFetchJson = fetchJson;
})();
