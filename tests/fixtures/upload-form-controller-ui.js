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
    for (const mode of ['upload', 'edit']) {
        const html = await (await page.request.get(base + '/output/playwright/' + mode + '-form-fixture.html')).text();
        for (const order of ['theme-first', 'page-first', 'standalone']) {
            const pageScript = '/assets/js/' + (mode === 'upload' ? 'upload' : 'edit') + '-topic-form.js';
            const themeScript = '/themes/turkmod/js/bundle.min.js';
            const scripts = order === 'page-first' ? [pageScript, themeScript] : [themeScript, pageScript];
            const url = base + '/__upload_controller_fixture?' + mode + '-' + order;
            await page.route('**/__upload_controller_fixture?' + mode + '-' + order, route => route.fulfill({
                contentType: 'text/html', body: '<!doctype html><meta charset="utf-8"><body class="public-page-' + (mode === 'upload' ? 'upload-topic' : 'edit-topic') + '">'
                    + html + '<div id="toastContainer" data-toast-duration="60000"></div><script>'
                    + 'document.getElementById("publicCoverInput").required=true;document.getElementById("publicGalleryInput").required=true;'
                    + (order === 'standalone' ? 'document.getElementById("uploadForm").dataset.uploadTopicStandalone="1";' : '')
                    + '</script><script src="' + base + '/assets/js/ui-foundation.js" defer></script>'
                    + scripts.map(src => '<script src="' + base + src + '" defer></script>').join('') + '</body>',
            }));
            await page.goto(url);
            await page.evaluate(() => {
                window.fixtureToastCalls = [];
                window.fixtureRequests = [];
                window.fixturePickerClicks = 0;
                const show = window.showToast;
                window.showToast = (...args) => { window.fixtureToastCalls.push(args); return show(...args); };
                document.getElementById('publicCoverInput').addEventListener('click', event => {
                    event.preventDefault();
                    window.fixturePickerClicks++;
                });
                window.publicFetchJson = async () => { window.fixtureRequests.push('submit'); return {success:true}; };
            });
            const prefix = mode + '/' + order;
            if (mode === 'upload') check(await page.evaluate(() => typeof window.validateUploadFieldRules === 'function' && typeof window.validateUploadFieldRules(false) === 'boolean'),
                prefix + ': the rich editor retains its field-validation callback');
            const next = page.locator('[data-wizard-next]');
            await page.locator('[data-step-target="2"]').click();
            await next.click();
            check(await page.evaluate(() => window.fixtureToastCalls.length === 1), prefix + ': one click invokes one cover validation notification');
            check(await page.locator('#toastContainer .topic-toast:not(.toast-out)').count() === 1, prefix + ': exactly one cover toast is rendered');
            await next.dblclick();
            check(await page.evaluate(() => window.fixtureToastCalls.length === 3), prefix + ': double-click has one handler per click');
            check(await page.locator('#toastContainer .topic-toast:not(.toast-out)').count() === 1, prefix + ': repeated cover failures keep one visible toast');
            await page.locator('[data-open-input="publicCoverInput"]').click();
            check(await page.evaluate(() => window.fixturePickerClicks === 1), prefix + ': media picker opens once');

            // A script included again must neither rebind handlers nor redeclare global const bindings.
            await page.addScriptTag({url:base + pageScript});
            await page.evaluate(() => { window.fixtureToastCalls.length = 0; });
            await next.click();
            check(await page.evaluate(() => window.fixtureToastCalls.length === 1), prefix + ': repeated script inclusion is idempotent');
            await page.evaluate(() => { document.getElementById('publicCoverInput').required=false; });
            await next.click();
            check(await page.locator('.upload-wizard-panel:not([hidden])').getAttribute('data-step') === '3', prefix + ': navigation advances a single step');
            await page.locator('[data-step-target="4"]').click();
            await page.evaluate(() => { window.fixtureToastCalls.length = 0; });
            await next.click();
            check(await page.evaluate(() => window.fixtureToastCalls.length === 1), prefix + ': gallery validation also has one notification caller');

            await page.evaluate(() => {
                const form = document.getElementById('uploadForm');
                form.querySelector('[name="title"]').value='Controller regression example';
                form.querySelector('[name="category_id"]').value='1';
                form.querySelector('[name="content"]').value='A sufficiently long description for validating a single submission controller.';
                form.querySelector('[name="author_topic"]').value='Test author';
                form.querySelector('[name="topic_version"]').value='1.62';
                form.querySelector('[name="dl_url[]"]').value='https://example.test/controller';
                document.getElementById('publicGalleryInput').required=false;
                window.fixtureToastCalls.length=0;
            });
            await page.locator('[data-step-target="7"]').click();
            await page.locator('#uploadForm').evaluate(form => form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true})));
            await page.waitForFunction(() => window.fixtureRequests.length > 0);
            check(await page.evaluate(() => window.fixtureRequests.length === 1), prefix + ': one submission sends one request');
            check(await page.evaluate(() => window.fixtureToastCalls.length === 1 && window.fixtureToastCalls[0][1] === 'success'), prefix + ': one submission shows one success notification');
        }
    }
    check(errors.length === 0, 'combined form scripts produce no JavaScript errors: ' + errors.join('; '));
    return {checks:results.length,coverage:'upload/edit, both script orders and standalone forms, duplicate script inclusion, cover/gallery/picker/submission',errors};
}
