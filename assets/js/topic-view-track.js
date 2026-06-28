(function () {
    'use strict';

    function csrfToken() {
        var meta = document.querySelector('meta[name=\"csrf-token\"]');
        return meta && meta.content ? meta.content : '';
    }

    function trackTopicViews(root) {
        var scope = root || document;
        scope.querySelectorAll('[data-topic-view-id][data-topic-view-url]').forEach(function (element) {
            if (element.getAttribute('data-topic-view-tracked') === '1') {
                return;
            }

            var topicId = element.getAttribute('data-topic-view-id') || '';
            var endpoint = element.getAttribute('data-topic-view-url') || '';
            if (!topicId || !endpoint || typeof window.fetch !== 'function' || typeof window.FormData !== 'function') {
                return;
            }

            element.setAttribute('data-topic-view-tracked', '1');
            window.setTimeout(function () {
                var formData = new FormData();
                var token = element.getAttribute('data-csrf') || csrfToken();
                formData.append('id', topicId);
                formData.append('_token', token);
                window.fetch(endpoint, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                }).catch(function () {});
            }, 1000);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            trackTopicViews(document);
        });
    } else {
        trackTopicViews(document);
    }
})();
