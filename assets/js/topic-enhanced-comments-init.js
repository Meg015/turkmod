(function () {
    'use strict';

    function enabled(section, name, fallback) {
        if (!section || !section.dataset || section.dataset[name] === undefined) {
            return fallback;
        }
        return section.dataset[name] === '1';
    }

    function initEnhancedComments() {
        document.body.classList.add('topic-detail-page');
        var section = document.querySelector('.topic-comments');
        if (!section || !window.EnhancedComments) {
            return;
        }

        window.EnhancedComments.init({
            reactionsEnabled: enabled(section, 'reactionsEnabled', true),
            markdownEnabled: enabled(section, 'commentsMarkdownEnabled', true),
            mentionsEnabled: enabled(section, 'commentsMentionsEnabled', true),
            editHistoryEnabled: enabled(section, 'commentsEditHistoryEnabled', true)
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initEnhancedComments);
    } else {
        initEnhancedComments();
    }
})();
