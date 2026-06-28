(function () {
    'use strict';

    function parseSlides(carousel) {
        try {
            var rawSlides = carousel.getAttribute('data-topic-carousel-slides') || carousel.getAttribute('data-carousel-slides') || '[]';
            var parsed = JSON.parse(rawSlides);
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function clear(element) {
        while (element && element.firstChild) {
            element.removeChild(element.firstChild);
        }
    }

    function mediaElement(slide) {
        var type = slide && slide.type ? String(slide.type) : '';
        var element;

        if (type === 'youtube') {
            element = document.createElement('iframe');
            element.className = 'topic-carousel-media';
            element.src = 'https://www.youtube.com/embed/' + encodeURIComponent(slide.id || '') + '?autoplay=0';
            element.width = 1600;
            element.height = 900;
            element.loading = 'lazy';
            element.allowFullscreen = true;
            return element;
        }

        if (type === 'vimeo') {
            element = document.createElement('iframe');
            element.className = 'topic-carousel-media';
            element.src = 'https://player.vimeo.com/video/' + encodeURIComponent(slide.id || '');
            element.width = 1600;
            element.height = 900;
            element.loading = 'lazy';
            element.allowFullscreen = true;
            return element;
        }

        if (type === 'video') {
            element = document.createElement('video');
            element.className = 'topic-carousel-media';
            element.controls = true;
            element.preload = 'metadata';
            element.width = 1600;
            element.height = 900;
            element.src = String(slide.url || '');
            return element;
        }

        element = document.createElement('img');
        element.className = 'topic-carousel-media';
        element.src = String((slide && slide.url) || '');
        element.alt = '';
        element.width = 1600;
        element.height = 900;
        element.loading = 'lazy';
        element.decoding = 'async';
        return element;
    }

    function initCarousel(carousel) {
        var slides = parseSlides(carousel);
        var currentIndex = 0;
        var content = carousel.querySelector('#ui-comment-content');
        var counter = carousel.querySelector('#tcCounter');
        var prev = carousel.querySelector('#ui-comment-prev');
        var next = carousel.querySelector('#ui-comment-next');
        var thumbs = Array.prototype.slice.call(carousel.querySelectorAll('.ui-comment-thumb'));

        function render(index) {
            if (!slides.length || !content) {
                return;
            }

            currentIndex = index;
            clear(content);
            content.appendChild(mediaElement(slides[currentIndex]));

            thumbs.forEach(function (thumb, thumbIndex) {
                var isActive = thumbIndex === currentIndex;
                thumb.classList.toggle('active', isActive);
                if (isActive) {
                    thumb.setAttribute('aria-current', 'true');
                } else {
                    thumb.removeAttribute('aria-current');
                }
            });

            if (counter) {
                counter.textContent = (currentIndex + 1) + ' / ' + slides.length;
            }
        }

        if (prev) {
            prev.addEventListener('click', function () {
                render(currentIndex > 0 ? currentIndex - 1 : slides.length - 1);
            });
        }

        if (next) {
            next.addEventListener('click', function () {
                render(currentIndex < slides.length - 1 ? currentIndex + 1 : 0);
            });
        }

        thumbs.forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                render(parseInt(thumb.getAttribute('data-idx') || '0', 10) || 0);
            });
        });

        if (slides.length <= 1) {
            if (prev) {
                prev.classList.add('is-hidden');
                prev.hidden = true;
                prev.style.setProperty('display', 'none', 'important');
            }
            if (next) {
                next.classList.add('is-hidden');
                next.hidden = true;
                next.style.setProperty('display', 'none', 'important');
            }
        } else {
            if (prev) {
                prev.classList.remove('is-hidden');
                prev.hidden = false;
                prev.style.removeProperty('display');
            }
            if (next) {
                next.classList.remove('is-hidden');
                next.hidden = false;
                next.style.removeProperty('display');
            }
        }

        render(0);
    }

    function initAll() {
        document.querySelectorAll('[data-topic-carousel-slides], [data-carousel-slides]').forEach(initCarousel);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
