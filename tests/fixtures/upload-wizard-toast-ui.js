async (page) => {
    page.setDefaultTimeout(5000);
    const base = 'http://localhost/turkmod';
    const results = [];
    const errors = [];
    const check = (ok, label) => {
        if (!ok) throw new Error(label);
        results.push(label);
    };
    page.on('pageerror', error => errors.push(error.message));
    const response = await page.request.get(base + '/output/playwright/upload-form-fixture.html');
    check(response.ok(), 'rendered upload form fixture is available');
    const formHtml = await response.text();

    for (const mode of ['standalone', 'theme', 'theme-minified']) {
        const script = mode === 'standalone' ? '/assets/js/upload-topic-form.js'
            : '/themes/turkmod/js/bundle' + (mode === 'theme-minified' ? '.min' : '') + '.js';
        const foundation = mode === 'standalone' ? '/assets/dist/public.min.js' : '/assets/js/ui-foundation.js';
        // Use the real toast renderer and bridge, loaded before DOMContentLoaded as in production.
        await page.route('**/__upload_toast_fixture?' + mode, route => route.fulfill({
            contentType: 'text/html', body:
                '<!doctype html><meta charset="utf-8"><body class="public-theme-layout public-page-upload-topic">' + formHtml
                + '<div id="toastContainer" data-toast-duration="60000"></div><script>'
                + 'document.getElementById("publicGalleryInput").required = true;'
                + (mode === 'standalone' ? 'document.getElementById("uploadForm").dataset.uploadTopicStandalone="1";' : '')
                + '</script><script src="' + base + foundation + '" defer></script>'
                + '<script src="' + base + script + '" defer></script>'
                + '<script src="' + base + '/assets/js/public-toast-bridge.js" defer></script></body>',
        }));
        await page.goto(base + '/__upload_toast_fixture?' + mode);
        const next = page.locator('[data-wizard-next]');
        const activeToasts = page.locator('#toastContainer .topic-toast:not(.toast-out)');
        await page.locator('[data-step-target="4"]').click();
        await next.click();
        check(await activeToasts.count() === 1, mode + ': missing gallery displays one real toast');
        const galleryMessage = await activeToasts.locator('.toast-message').innerText();
        const galleryToasts = activeToasts.filter({hasText: galleryMessage});
        await next.click();
        check(await galleryToasts.count() === 1, mode + ': repeated validation does not stack the same gallery toast');
        await next.dblclick();
        check(await galleryToasts.count() === 1, mode + ': double-clicking next still leaves one gallery toast');

        await page.evaluate(() => { document.getElementById('publicCoverInput').required = true; });
        await page.locator('[data-step-target="2"]').click();
        await next.click();
        check(await activeToasts.count() === 2, mode + ': a different required-field warning remains visible');
        await galleryToasts.locator('.toast-close-btn').click();
        await page.locator('[data-step-target="4"]').click();
        await next.click();
        check(await galleryToasts.count() === 1 && await activeToasts.count() === 2,
            mode + ': dismissing the gallery toast allows a fresh warning');

        await page.evaluate(() => { document.getElementById('publicGalleryInput').required = false; });
        await next.click();
        check(await page.locator('.upload-wizard-panel:not([hidden])').getAttribute('data-step') === '5',
            mode + ': removing the gallery requirement still allows navigation');
        await page.evaluate(() => {
            const form = document.getElementById('uploadForm');
            form.dataset.requireAuthor = '1';
            form.elements.namedItem('author_topic').required = true;
        });
        await next.click();
        check(await activeToasts.filter({has: page.locator('.toast-message')}).count() === 3
            && await page.locator('#toastContainer .toast-error:not(.toast-out)').count() === 1,
        mode + ': missing author displays one error toast alongside distinct warnings');
        await next.dblclick();
        check(await page.locator('#toastContainer .toast-error:not(.toast-out)').count() === 1,
            mode + ': repeated field-rule errors are also deduplicated');
        await page.evaluate(() => {
            window.showToast('Separate event', 'info');
            window.showToast('Separate event', 'info');
        });
        check(await activeToasts.filter({hasText: 'Separate event'}).count() === 2,
            mode + ': independent notifications keep their existing behavior');
    }
    check(errors.length === 0, 'toast integration has no JavaScript errors: ' + errors.join('; '));
    return {checks: results.length, results};
}
