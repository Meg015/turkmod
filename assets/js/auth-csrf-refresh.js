(function () {
    "use strict";

    function updateTokens(token) {
        if (!token) {
            return;
        }

        if (window.publicApi && typeof window.publicApi.updateCsrfToken === "function") {
            window.publicApi.updateCsrfToken(token);
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

    function refreshToken() {
        if (!window.publicApi || typeof window.publicApi.refreshCsrfToken !== "function") {
            return Promise.resolve(false);
        }

        return window.publicApi.refreshCsrfToken().then(function (refreshed) {
            if (refreshed && typeof window.publicApi.csrfToken === "function") {
                updateTokens(window.publicApi.csrfToken());
            }
            return refreshed;
        }).catch(function () {
            return false;
        });
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

            refreshToken()
                .then(function () {
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

        refreshToken();
    });
})();
