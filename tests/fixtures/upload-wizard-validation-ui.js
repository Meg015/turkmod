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
    await page.route('**/__upload_validation_fixture*', route => route.fulfill({
        contentType: 'text/html', body: '<!doctype html><meta charset="utf-8"><body></body>',
    }));
    const response = await page.request.get(base + '/output/playwright/upload-form-fixture.html');
    check(response.ok(), 'rendered upload form fixture is available');
    const formHtml = await response.text();

    for (const mode of ['standalone', 'theme', 'theme-minified']) {
        await page.goto(base + '/__upload_validation_fixture?' + mode);
        await page.setContent('<!doctype html><meta charset="utf-8">' + formHtml);
        await page.evaluate(mode => {
            const form = document.getElementById('uploadForm');
            Object.assign(form.dataset, {
                allowStepSkip: '0', requireAuthor: '1', requireVersion: '1',
                requireDownloadLink: '1', allowVideoUrl: '1', allowedVideoHosts: 'youtube.com',
                minTitleLength: '5', minContentLength: '10',
            });
            if (mode === 'standalone') form.dataset.uploadTopicStandalone = '1';
            form.querySelector('[name="title"]').minLength = 5;
            form.querySelector('[name="content"]').dataset.minLength = '10';
            for (const name of ['author_topic', 'topic_version', 'dl_url[]']) {
                form.elements.namedItem(name).required = true;
            }
            window.TMUI = { registerAction() {} };
        }, mode);
        await page.addScriptTag({url: base + (mode === 'standalone'
            ? '/assets/js/upload-topic-form.js'
            : '/themes/turkmod/js/bundle' + (mode === 'theme-minified' ? '.min' : '') + '.js')});
        await page.evaluate(() => {
            window.fixtureToasts = [];
            window.fixtureRequests = [];
            window.showToast = (message, type) => window.fixtureToasts.push({message, type});
            window.publicFetchJson = async (url, options) => {
                window.fixtureRequests.push({url, links: options.body.get('download_lines')});
                return {success: true};
            };
        });

        const next = page.locator('[data-wizard-next]');
        const activeStep = () => page.locator('.upload-wizard-panel:not([hidden])').getAttribute('data-step');
        const clearToasts = () => page.evaluate(() => { window.fixtureToasts.length = 0; });
        const singleToastFor = async key => page.evaluate(key => {
            const hint = document.querySelector('[data-live-hint="' + key + '"]');
            return window.fixtureToasts.length === 1 && window.fixtureToasts[0].message === hint.textContent;
        }, key);
        const noToasts = () => page.evaluate(() => window.fixtureToasts.length === 0);

        await page.locator('[name="title"]').fill('Test');
        await next.click();
        check(await activeStep() === '1' && await singleToastFor('title'), mode + ': short title reports only its own error');
        await page.locator('[name="title"]').fill('Test mod title');
        await page.locator('[name="category_id"]').selectOption('1');
        await clearToasts();
        await next.click();
        check(await activeStep() === '2', mode + ': valid basics advance with later required fields empty');
        await next.click();
        await page.locator('[name="content"]').fill('A sufficiently long mod description.');
        await next.click();
        check(await activeStep() === '4', mode + ': description advances to gallery and video');

        await page.locator('[name="topic_video_url"]').fill('https://disallowed.test/video');
        await next.click();
        check(await activeStep() === '4' && await singleToastFor('video'), mode + ': video is checked in step 4 with one toast');
        await page.locator('[name="topic_video_url"]').fill('');
        await clearToasts();
        await next.click();
        check(await activeStep() === '5' && await noToasts(), mode + ': gallery advances without checking author or downloads');

        await next.click();
        check(await activeStep() === '5' && await singleToastFor('author'), mode + ': missing author reports one specific error');
        await page.locator('[name="author_topic"]').fill('Test author');
        await clearToasts();
        await next.click();
        check(await activeStep() === '5' && await singleToastFor('version'), mode + ': missing version still blocks step 5');
        await page.locator('[name="topic_version"]').fill('1.50');
        await clearToasts();
        await next.click();
        check(await activeStep() === '6' && await noToasts(), mode + ': step 5 advances to empty required download sources');

        await next.click();
        check(await activeStep() === '6' && await singleToastFor('download'), mode + ': empty required downloads block step 6 with one toast');
        await page.locator('[name="dl_url[]"]').fill('invalid-url');
        await clearToasts();
        await next.click();
        check(await activeStep() === '6' && await singleToastFor('download'), mode + ': invalid required downloads cannot advance');
        await page.locator('[name="dl_url[]"]').fill('https://example.test/download');
        await clearToasts();
        await next.click();
        check(await activeStep() === '7' && await noToasts(), mode + ': valid download advances to review');

        await page.locator('[data-step-target="5"]').click();
        await page.locator('[data-step-target="6"]').click();
        check(await activeStep() === '6' && await noToasts(), mode + ': step headers use the same scoped validation');
        await page.evaluate(() => {
            const form = document.getElementById('uploadForm');
            form.dataset.requireDownloadLink = '0';
            const url = form.elements.namedItem('dl_url[]');
            url.required = false;
            url.value = '';
        });
        await next.click();
        check(await activeStep() === '7' && await noToasts(), mode + ': optional downloads may remain empty');

        // Dispatch directly so the custom full-form check is exercised before native browser validation.
        // All network submissions are intercepted by fixture publicFetchJson; no topic is created.
        await page.evaluate(() => {
            const form = document.getElementById('uploadForm');
            form.dataset.requireDownloadLink = '1';
            form.elements.namedItem('author_topic').value = '   ';
            form.elements.namedItem('topic_version').value = '   ';
            form.elements.namedItem('topic_video_url').value = 'https://disallowed.test/video';
            form.dispatchEvent(new Event('submit', {bubbles: true, cancelable: true}));
        });
        check(await singleToastFor('video') && await activeStep() === '4', mode + ': multiple full-form errors produce one toast and reveal the earliest invalid step');
        check(await page.evaluate(() => ['author', 'version', 'video', 'download'].every(key =>
            document.querySelector('[data-live-hint="' + key + '"]').classList.contains('is-error'))),
        mode + ': final submission continues to validate every field group');
        check(await page.evaluate(() => window.fixtureRequests.length === 0), mode + ': invalid submission sends no request');

        await clearToasts();
        await page.evaluate(() => {
            const form = document.getElementById('uploadForm');
            form.elements.namedItem('author_topic').value = 'Test author';
            form.elements.namedItem('topic_version').value = '1.50';
            form.elements.namedItem('topic_video_url').value = '';
            const url = form.elements.namedItem('dl_url[]');
            url.required = true;
            url.value = 'https://example.test/download';
        });
        check(await page.locator('#uploadForm').evaluate(form => form.checkValidity()), mode + ': completed form passes native validation');
        await page.locator('#uploadForm').evaluate(form => {
            form.dispatchEvent(new Event('submit', {bubbles: true, cancelable: true}));
        });
        await page.waitForFunction(() => window.fixtureRequests.length === 1);
        check(await page.evaluate(() => window.fixtureRequests[0].links === 'Link|https://example.test/download'),
            mode + ': valid submission serializes the download link');
        check(await page.evaluate(() => window.fixtureToasts.length === 1 && window.fixtureToasts[0].type === 'success'),
            mode + ': valid submission shows one success toast');
    }
    check(errors.length === 0, 'all upload implementations run without JavaScript errors: ' + errors.join('; '));
    return {checks: results.length, results};
}
