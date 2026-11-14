async (page) => {
    page.setDefaultTimeout(5000);
    const base = 'http://localhost/turkmod';
    const results = [];
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const html = {};
    for (const mode of ['upload', 'edit']) {
        html[mode] = await (await page.request.get(base + '/output/playwright/' + mode + '-form-fixture.html')).text();
    }
    await page.route('**/__upload_edge_fixture*', route => route.fulfill({contentType: 'text/html', body: '<!doctype html><meta charset="utf-8"><body></body>'}));
    const activeStep = () => page.locator('.upload-wizard-panel:not([hidden])').getAttribute('data-step');
    const submit = () => page.evaluate(() => document.getElementById('uploadForm').requestSubmit());
    const settle = () => page.waitForTimeout(100);
    const counts = () => page.evaluate(() => ({requests: window.fixtureRequests.length, toasts: window.fixtureToasts.length}));
    const selectImages = (id, count = 1) => page.evaluate(({id, count}) => {
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 8;
        const data = atob(canvas.toDataURL('image/png').split(',')[1]);
        const files = new DataTransfer();
        for (let index = 0; index < count; index++) files.items.add(new File([Uint8Array.from(data, char => char.charCodeAt(0))], 'image-' + index + '.png', {type: 'image/png'}));
        document.getElementById(id).files = files.files;
    }, {id, count});
    for (const mode of ['upload', 'edit']) {
        for (const controller of ['theme', 'theme-minified', 'standalone']) {
            const prefix = mode + '/' + controller;
            const check = (ok, label) => results.push({ok, label: prefix + ': ' + label});
            const mount = async (settings = {}) => {
                await page.goto(base + '/__upload_edge_fixture?' + mode + '-' + controller);
                await page.setContent('<!doctype html><meta charset="utf-8">' + html[mode]);
                await page.evaluate(({controller, settings}) => {
                    const form = document.getElementById('uploadForm');
                    Object.assign(form.dataset, {allowStepSkip:'1', requireCover:'0', requireGallery:'0', requireAuthor:'0', requireVersion:'0', requireDownloadLink:'0', allowVideoUrl:'1', minContentLength:'10', minTitleLength:'5', maxTitleLength:'200', allowedImageExt:'png', maxImages:'10'}, settings);
                    if (controller === 'standalone') form.dataset.uploadTopicStandalone = '1';
                    form.querySelector('[name="content"]').dataset.minLength = '10';
                    window.TMUI = {registerAction(){}};
                }, {controller, settings});
                await page.addScriptTag({url:base + (controller === 'standalone' ? '/assets/js/' + (mode === 'upload' ? 'upload' : 'edit') + '-topic-form.js' : '/themes/turkmod/js/bundle' + (controller === 'theme-minified' ? '.min' : '') + '.js')});
                await page.evaluate(() => {
                    const form = document.getElementById('uploadForm');
                    form.querySelector('[name="title"]').value = 'Form validation test';
                    form.querySelector('[name="category_id"]').value = '1';
                    form.querySelector('[name="content"]').value = 'A sufficiently long description for the edge-case validation checks.';
                    form.querySelector('[name="dl_url[]"]').value = 'https://example.test/download';
                    window.fixtureToasts = [];
                    window.fixtureRequests = [];
                    window.fixtureFailNext = false;
                    window.showToast = (message, type) => window.fixtureToasts.push({message, type});
                    window.publicFetchJson = async () => {
                        window.fixtureRequests.push('submit');
                        if (window.fixtureFailNext) { window.fixtureFailNext = false; throw new Error('Fixture connection failed'); }
                        return {success:true};
                    };
                });
            };

            await mount({allowStepSkip:'0'});
            await page.evaluate(() => { document.getElementById('publicCoverInput').required = true; });
            await page.locator('[data-step-target="7"]').click();
            await settle();
            check(await activeStep() === '2' && (await counts()).toasts === 1, 'forward jump stops at the first invalid intermediate step');

            await mount();
            await page.locator('[data-step-target="7"]').click();
            await page.evaluate(() => { document.querySelector('[name="title"]').value = ''; });
            await submit(); await settle();
            check(await activeStep() === '1' && (await counts()).requests === 0 && (await counts()).toasts === 1, 'native review submission reveals hidden invalid basics with one notification');

            await mount({requireCover:'1'});
            await page.locator('[data-step-target="2"]').click();
            await page.evaluate(() => {
                for (const kept of document.querySelectorAll('[data-step="2"] [name="keep_media[]"]')) kept.checked = false;
            });
            await page.locator('[data-wizard-next]').click();
            await selectImages('publicCoverInput');
            await page.evaluate(() => document.getElementById('publicCoverInput').dispatchEvent(new Event('change', {bubbles:true})));
            await settle();
            check(await page.evaluate(() => {
                const input = document.getElementById('publicCoverInput');
                return input.files.length === 1 && !input.classList.contains('is-invalid') && input.getAttribute('aria-invalid') !== 'true';
            }), 'selecting a valid cover clears its previous invalid state');

            await mount();
            await page.locator('[data-step-target="7"]').click();
            await page.evaluate(() => { document.querySelector('[name="content"]').value = '<p><br></p>'; });
            await submit(); await settle();
            check(await activeStep() === '3' && (await counts()).requests === 0 && (await counts()).toasts === 1, 'visually empty rich text is rejected at the description step');

            await mount({requireDownloadLink:'1'});
            await page.locator('[data-step-target="7"]').click();
            await page.evaluate(() => {
                const row = document.querySelector('.dl-row').cloneNode(true);
                row.querySelector('[name="dl_url[]"]').value = '';
                row.querySelector('[name="dl_url[]"]').required = true;
                document.getElementById('dlRows').appendChild(row);
            });
            await submit(); await settle();
            check((await counts()).requests === 1 && (await counts()).toasts === 1, 'one valid download satisfies the rule despite another empty row');

            await mount();
            await page.locator('[data-step-target="7"]').click();
            await page.evaluate(() => { document.querySelector('[name="dl_url[]"]').value = 'ftp://example.test/download'; });
            await submit(); await settle();
            check(await activeStep() === '6' && (await counts()).requests === 0 && (await counts()).toasts === 1, 'non-HTTP download is rejected even when downloads are optional');

            await mount();
            await page.locator('[data-step-target="2"]').click();
            await selectImages('publicCoverInput');
            await page.evaluate(() => { const next = document.querySelector('[data-wizard-next]'); next.click(); next.click(); });
            await settle();
            check(await activeStep() === '3', 'pending image validation advances only once');

            if (controller !== 'standalone' || mode === 'upload') {
                await mount();
                await page.locator('[data-step-target="4"]').click();
                await selectImages('publicGalleryInput', 2);
                await page.evaluate(() => document.getElementById('publicGalleryInput').dispatchEvent(new Event('change', {bubbles:true})));
                await page.waitForFunction(() => document.querySelectorAll('#publicGalleryPreview .public-preview-item').length === 2);
                await page.evaluate(() => {
                    const transfer = new DataTransfer();
                    transfer.setData('text/plain', '0');
                    document.querySelectorAll('#publicGalleryPreview .public-preview-item')[1].dispatchEvent(new DragEvent('drop', {bubbles:true, cancelable:true, dataTransfer:transfer}));
                });
                await settle();
                check(await page.evaluate(() => Array.from(document.getElementById('publicGalleryInput').files, file => file.name).join(',') === 'image-1.png,image-0.png'), 'dragging gallery previews reorders files without clearing the selection');
            }

            await mount();
            await page.locator('[data-step-target="7"]').click();
            await selectImages('publicCoverInput');
            await page.evaluate(() => {
                const form = document.getElementById('uploadForm');
                form.dispatchEvent(new Event('submit', {bubbles:true, cancelable:true}));
                form.dispatchEvent(new Event('submit', {bubbles:true, cancelable:true}));
            });
            await settle();
            check((await counts()).requests === 1 && (await counts()).toasts === 1, 'concurrent submits during image validation send one request');

            await mount();
            await page.locator('[data-step-target="7"]').click();
            await page.evaluate(() => {
                const files = new DataTransfer();
                for (let index = 0; index < 2; index++) files.items.add(new File(['not an image'], 'bad-' + index + '.png', {type:'image/png'}));
                document.getElementById('publicGalleryInput').files = files.files;
            });
            await submit(); await settle();
            check(await activeStep() === '4' && (await counts()).requests === 0 && (await counts()).toasts === 1, 'multiple invalid gallery files produce one validation notification');

            await mount();
            await page.locator('[data-step-target="7"]').click();
            await page.evaluate(() => { window.fixtureFailNext = true; });
            await submit(); await settle();
            await submit(); await settle();
            check((await counts()).requests === 2 && await page.evaluate(() => window.fixtureToasts.at(-1).type === 'success'), 'failed request can be retried successfully');

            if (mode === 'upload') {
                await mount({lockAfterSubmit:'0'});
                await page.locator('[data-step-target="7"]').click();
                await submit(); await settle(); await submit(); await settle();
                check((await counts()).requests === 2, 'successful unlocked submission clears its pending state');
            } else {
                await mount({requireCover:'1', requireGallery:'1'});
                await page.locator('[data-step-target="7"]').click();
                await submit(); await settle();
                check((await counts()).requests === 1, 'retained cover and gallery satisfy required-media rules');
                await mount({requireCover:'1', requireGallery:'1'});
                await page.locator('[data-step-target="7"]').click();
                await page.evaluate(() => { document.querySelector('[data-step="2"] [name="keep_media[]"]').checked = false; });
                await submit(); await settle();
                check(await activeStep() === '2' && (await counts()).requests === 0 && (await counts()).toasts === 1, 'removing the required cover reveals the cover step');
                await mount({requireGallery:'1'});
                await page.locator('[data-step-target="7"]').click();
                await page.evaluate(() => { document.querySelector('[data-step="4"] [name="keep_media[]"]').checked = false; });
                await submit(); await settle();
                check(await activeStep() === '4' && (await counts()).requests === 0 && (await counts()).toasts === 1, 'removing every required gallery image reveals the gallery step');
                await mount({maxImages:'1'});
                await page.locator('[data-step-target="7"]').click();
                await selectImages('publicGalleryInput');
                await submit(); await settle();
                check(await activeStep() === '4' && (await counts()).requests === 0 && (await counts()).toasts === 1, 'gallery limit includes retained images and newly selected images');
            }
        }
    }
    return {checks:results.length, failures:results.filter(result => !result.ok).map(result => result.label), errors};
}
