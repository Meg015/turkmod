function initThemesPage() {
    var search = document.querySelector('[data-theme-search]');
    var cards = Array.prototype.slice.call(document.querySelectorAll('[data-theme-card]'));
    var setThemeVisibility = function (element, visible) {
        if (!element) return;
        if (window.adminVisibility && typeof window.adminVisibility.set === 'function') {
            window.adminVisibility.set(element, visible, { aria: false });
            return;
        }

        element.hidden = !visible;
    };

    if (search) {
        search.addEventListener('input', function () {
            var query = search.value.trim().toLocaleLowerCase('tr-TR');
            cards.forEach(function (card) {
                var haystack = (card.getAttribute('data-theme-search-text') || '').toLocaleLowerCase('tr-TR');
                setThemeVisibility(card, !(query !== '' && haystack.indexOf(query) === -1));
            });
        });
    }

    document.querySelectorAll('.theme-action-popover').forEach(function (details) {
        details.addEventListener('toggle', function () {
            if (!details.open) return;
            document.querySelectorAll('.theme-action-popover[open]').forEach(function (other) {
                if (other !== details) other.removeAttribute('open');
            });
        });
    });

    document.querySelectorAll('.ui-admin-tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            var targetId = tab.getAttribute('data-tab');
            var tabsGroup = tab.closest('.ui-admin-tabs');
            
            if (tabsGroup) {
                tabsGroup.querySelectorAll('.ui-admin-tab').forEach(function(t) {
                    t.classList.remove('is-active');
                });
                tab.classList.add('is-active');
            }
            
            var content = document.getElementById(targetId);
            if (content && content.parentElement) {
                var children = content.parentElement.children;
                for (var i = 0; i < children.length; i++) {
                    if (children[i].classList.contains('ui-admin-tab-content')) {
                        children[i].classList.remove('is-active');
                    }
                }
                content.classList.add('is-active');
            }
        });
    });

    var editor = document.querySelector('[data-code-editor]');
    if (editor) {
        var lineBadge = document.querySelector('[data-editor-lines]');
        var cursorBadge = document.querySelector('[data-editor-cursor]');
        var editorSearch = document.querySelector('[data-editor-search]');
        var searchStatus = document.querySelector('[data-editor-search-status]');

        var updateEditorStats = function () {
            var value = editor.value || '';
            var lineCount = value === '' ? 1 : value.split(/\r\n|\r|\n/).length;
            var cursor = editor.selectionStart || 0;
            var currentLine = value.slice(0, cursor).split(/\r\n|\r|\n/).length;
            if (lineBadge) lineBadge.textContent = lineCount.toLocaleString('tr-TR') + ' satır';
            if (cursorBadge) cursorBadge.textContent = 'Satır ' + currentLine.toLocaleString('tr-TR');
        };

        var runEditorSearch = function () {
            if (!editorSearch) return;
            var query = editorSearch.value;
            editor.classList.remove('is-search-hit');
            if (searchStatus) {
                setThemeVisibility(searchStatus, false);
                searchStatus.textContent = '';
            }
            if (!query) return;

            var haystack = editor.value.toLocaleLowerCase('tr-TR');
            var needle = query.toLocaleLowerCase('tr-TR');
            var index = haystack.indexOf(needle);
            if (index === -1) {
                if (searchStatus) {
                    setThemeVisibility(searchStatus, true);
                    searchStatus.textContent = 'Eşleşme bulunamadı.';
                }
                return;
            }

            editor.focus();
            editor.setSelectionRange(index, index + query.length);
            editor.classList.add('is-search-hit');
            var line = editor.value.slice(0, index).split(/\r\n|\r|\n/).length;
            if (searchStatus) {
                setThemeVisibility(searchStatus, true);
                searchStatus.textContent = 'İlk eşleşme satır ' + line.toLocaleString('tr-TR') + '.';
            }
            updateEditorStats();
        };

        editor.addEventListener('input', updateEditorStats);
        editor.addEventListener('click', updateEditorStats);
        editor.addEventListener('keyup', updateEditorStats);
        if (editorSearch) editorSearch.addEventListener('input', runEditorSearch);
        updateEditorStats();
    }
}

if (window.adminPage && typeof window.adminPage.register === 'function') {
    window.adminPage.register('themes', initThemesPage, { id: 'themes-page' });
}
