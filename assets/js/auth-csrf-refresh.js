(function () {
    "use strict";

    function baseUri() {
        var meta = document.querySelector('meta[name="app-base-uri"]');
        return meta ? (meta.getAttribute("content") || "").replace(/\/+$/, "") : "";
    }

    function updateTokens(token) {
        if (!token) {
            return;
        }

        document.querySelectorAll('input[name="_token"], input[name="csrf_token"]').forEach(function (input) {
            input.value = token;
        });

        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
            meta.setAttribute("content", token);
        }
    }

    function nativeSubmit(form) {
        HTMLFormElement.prototype.submit.call(form);
    }

    function bindAuthForm(form) {
        if (!form || form.dataset.authCsrfRefreshBound === "1") {
            return;
        }

        var tokenInput = form.querySelector('input[name="_token"], input[name="csrf_token"]');
        if (!tokenInput) {
            return;
        }

        form.dataset.authCsrfRefreshBound = "1";
        form.addEventListener("submit", function (event) {
            if (form.dataset.authCsrfSubmitting === "1") {
                return;
            }

            event.preventDefault();
            form.dataset.authCsrfSubmitting = "1";
            form.setAttribute("aria-busy", "true");

            fetch(baseUri() + "/api/csrf-token.php", {
                method: "GET",
                credentials: "same-origin",
                cache: "no-store",
                headers: { "Accept": "application/json" }
            })
                .then(function (response) {
                    return response.ok ? response.json() : null;
                })
                .then(function (data) {
                    if (data && (data._token || data.csrf_token)) {
                        updateTokens(data._token || data.csrf_token);
                    }
                    nativeSubmit(form);
                })
                .catch(function () {
                    nativeSubmit(form);
                });
        });
    }

    function init() {
        document.querySelectorAll("form[data-auth-csrf-refresh]").forEach(bindAuthForm);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }

    window.addEventListener("pageshow", function (event) {
        if (!event.persisted) {
            return;
        }

        fetch(baseUri() + "/api/csrf-token.php", {
            method: "GET",
            credentials: "same-origin",
            cache: "no-store",
            headers: { "Accept": "application/json" }
        })
            .then(function (response) {
                return response.ok ? response.json() : null;
            })
            .then(function (data) {
                if (data && (data._token || data.csrf_token)) {
                    updateTokens(data._token || data.csrf_token);
                }
            })
            .catch(function () {});
    });
})();
