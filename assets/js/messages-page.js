(function () {
    "use strict";

    function debounce(fn, wait) {
        var timer = null;
        return function () {
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(null, args);
            }, wait);
        };
    }

    function createSearchItem(user, onSelect) {
        var button = document.createElement("button");
        button.type = "button";
        button.className = "messages-search-item";
        button.setAttribute("data-user-id", String(user.id || 0));

        var avatar = document.createElement("img");
        avatar.src = user.avatar || "";
        avatar.alt = user.name || "Kullanici";
        avatar.width = 30;
        avatar.height = 30;
        avatar.loading = "lazy";
        avatar.setAttribute("data-ui-avatar-img", "");

        var name = document.createElement("span");
        name.textContent = user.name || "Kullanici";

        button.appendChild(avatar);
        button.appendChild(name);
        button.addEventListener("click", function () {
            onSelect(user);
        });

        return button;
    }

    function initMessagesPage(root) {
        if (!root || root.dataset.messagesPageReady === "1") {
            return;
        }
        root.dataset.messagesPageReady = "1";

        var apiUrl = root.getAttribute("data-messages-api-url") || "";
        var startForm = root.querySelector("[data-messages-start-form]");
        var searchInput = root.querySelector("[data-messages-user-search]");
        var targetInput = root.querySelector("[data-messages-target-user-id]");
        var results = root.querySelector("[data-messages-search-results]");
        var stream = root.querySelector("[data-messages-stream]");

        if (stream) {
            stream.scrollTop = stream.scrollHeight;
        }

        if (!startForm || !searchInput || !targetInput || !results || !apiUrl) {
            return;
        }

        function clearResults() {
            results.innerHTML = "";
            results.hidden = true;
        }

        function clearSearchInvalid() {
            searchInput.classList.remove("is-invalid");
            searchInput.removeAttribute("aria-invalid");
        }

        function setSelectedUser(user) {
            var userId = Number(user && user.id ? user.id : 0);
            targetInput.value = userId > 0 ? String(userId) : "";
            searchInput.value = user && user.name ? String(user.name) : "";
            clearSearchInvalid();
            clearResults();
        }

        function renderResults(users) {
            results.innerHTML = "";
            if (!Array.isArray(users) || users.length === 0) {
                var empty = document.createElement("div");
                empty.className = "messages-search-empty";
                empty.textContent = "Sonuc bulunamadi.";
                results.appendChild(empty);
                results.hidden = false;
                return;
            }

            users.forEach(function (user) {
                results.appendChild(createSearchItem(user, setSelectedUser));
            });
            results.hidden = false;
        }

        function fetchUsers(query) {
            var value = String(query || "").trim();
            if (value.length < 2) {
                clearResults();
                return;
            }

            var url = new URL(apiUrl, window.location.origin);
            url.searchParams.set("action", "search");
            url.searchParams.set("q", value);
            url.searchParams.set("limit", "8");

            fetch(url.toString(), {
                headers: { "X-Requested-With": "XMLHttpRequest" }
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    var users = Array.isArray(data.users) ? data.users : [];
                    renderResults(users);
                })
                .catch(function () {
                    clearResults();
                });
        }

        var searchUsers = debounce(fetchUsers, 180);
        searchInput.addEventListener("input", function () {
            targetInput.value = "";
            clearSearchInvalid();
            searchUsers(searchInput.value);
        });

        searchInput.addEventListener("focus", function () {
            if (results.innerHTML.trim() !== "") {
                results.hidden = false;
            }
        });

        document.addEventListener("click", function (event) {
            if (!results.contains(event.target) && event.target !== searchInput) {
                results.hidden = true;
            }
        });

        startForm.addEventListener("submit", function (event) {
            var userId = Number(targetInput.value || 0);
            if (userId > 0) {
                return;
            }

            event.preventDefault();
            searchInput.classList.add("is-invalid");
            searchInput.setAttribute("aria-invalid", "true");
            if (typeof window.showToast === "function") {
                window.showToast("Lütfen listeden bir kullanıcı seçin.", "warning");
            }
            searchInput.focus();
        });
    }

    document.addEventListener("DOMContentLoaded", function () {
        initMessagesPage(document.querySelector("[data-messages-root]"));
    });
})();
