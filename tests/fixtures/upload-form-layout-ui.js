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
        for (const order of ['page-last', 'theme-last']) {
            const themeStyles = ['/themes/turkmod/css/bundle.min.css', '/themes/turkmod/css/mobile-fixes.css', '/themes/turkmod/css/layout-grid-fix.css', '/themes/turkmod/css/static-page.css'];
            const styles = ['/assets/css/roboto-local.css', '/assets/dist/public.min.css', '/assets/css/ui-foundation.css',
                ...(order === 'page-last' ? [...themeStyles, '/assets/css/public-upload.css'] : ['/assets/css/public-upload.css', ...themeStyles])];
            const url = base + '/__upload_layout_fixture?' + mode + '-' + order;
            await page.route('**/__upload_layout_fixture?' + mode + '-' + order, route => route.fulfill({
                contentType: 'text/html', body: '<!doctype html><html lang="tr" data-public-theme="turkmod" data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
                    + styles.map(src => '<link rel="stylesheet" href="' + base + src + '">').join('')
                    + '</head><body class="public-theme-layout public-theme-layout-active public-page-' + (mode === 'edit' ? 'edit-topic' : 'upload-topic') + '">'
                    + '<main id="main-content" class="site-main site-main--focus"><div class="container page-wrap ui-container"><div class="layout grid grid--focus"><section class="content content-area vstack gap-4">'
                    + html + '</section></div></div></main><div id="toastContainer"></div><script src="' + base + '/themes/turkmod/js/bundle.min.js" defer></script></body></html>',
            }));
            await page.setViewportSize({width:1440, height:1000});
            await page.goto(url);
            await page.evaluate(() => document.fonts.ready);
            const prefix = mode + '/' + order;
            check(await page.locator('.public-dropzone-trigger').evaluateAll(elements => elements.length === 2 && elements.every(el => el.tagName === 'BUTTON' && el.type === 'button')),
                prefix + ': media pickers are keyboard-accessible buttons that cannot submit the form');
            const dimensions = await page.evaluate(() => {
                const form = document.querySelector('.upload-composer-layout');
                const fields = document.querySelector('.upload-form-fields');
                const title = document.querySelector('[name="title"]');
                const category = document.querySelector('[name="category_id"]');
                return {form:form.getBoundingClientRect().width, fields:fields.getBoundingClientRect().width,
                    titleTop:title.getBoundingClientRect().top, categoryTop:category.getBoundingClientRect().top};
            });
            check(Math.abs(dimensions.form - dimensions.fields) < 2, prefix + ': fields occupy the full composer width');
            check(Math.abs(dimensions.titleTop - dimensions.categoryTop) < 2, prefix + ': title and category share a desktop row');
            await page.locator('[data-step-target="5"]').click();
            const authorRow = await page.evaluate(() => {
                const author = document.querySelector('[name="author_topic"]').getBoundingClientRect();
                const version = document.querySelector('[name="topic_version"]').getBoundingClientRect();
                return {top:Math.abs(author.top - version.top),width:Math.abs(author.width - version.width)};
            });
            check(authorRow.top < 2 && authorRow.width < 2, prefix + ': author and version use equal desktop columns');
            for (const theme of ['light', 'dark']) {
                await page.evaluate(theme => { document.documentElement.dataset.theme = theme; }, theme);
                for (const width of [1920, 1024, 768, 390, 320]) {
                    await page.setViewportSize({width, height:1000});
                    for (const step of [1, 2, 3, 4, 5, 6, 7]) {
                        await page.locator('[data-step-target="' + step + '"]').click();
                        const layout = await page.evaluate(() => {
                            const active = document.querySelector('.upload-wizard-panel.is-active');
                            const fields = document.querySelector('.upload-form-fields');
                            const composer = document.querySelector('.upload-composer-layout');
                            const nav = document.querySelector('.upload-wizard-progress');
                            const a = active.getBoundingClientRect(), f = fields.getBoundingClientRect(), c = composer.getBoundingClientRect();
                            return {full:Math.abs(c.width-f.width)<2 && Math.abs(a.right-f.right)<2,
                                overflow:document.documentElement.scrollWidth>innerWidth+1 || nav.scrollWidth>nav.clientWidth+1,
                                columns:getComputedStyle(nav).gridTemplateColumns.split(' ').length,
                                navHeight:nav.getBoundingClientRect().height,
                                controlsRow:getComputedStyle(document.querySelector('.upload-wizard-controls')).flexDirection==='row',
                                activePanels:[...document.querySelectorAll('.upload-wizard-panel')].filter(el=>getComputedStyle(el).display!=='none').length};
                        });
                        check(layout.full && !layout.overflow && layout.activePanels === 1 && layout.columns === 7 && layout.navHeight <= 72 && layout.controlsRow,
                            prefix + '/' + theme + '/' + width + '/step-' + step + ': full width, one panel, no overflow');
                    }
                }
            }
            if (mode === 'edit') {
                check(await page.locator('input[name="keep_media[]"]:checked').count() === 2, prefix + ': existing cover and gallery stay selected');
                check(await page.locator('[name="title"]').inputValue() === 'ETS2 - Scania S Özel Kabin Paketi', prefix + ': edit content survives navigation');
            }
            await page.setViewportSize({width:1440, height:1000});
            await page.evaluate(() => { document.documentElement.dataset.theme = 'light'; });
            await page.locator('[data-step-target="1"]').click();
            await page.waitForFunction(() => getComputedStyle(document.querySelector('.upload-wizard-panel[data-step="1"]')).opacity === '1');
            await page.waitForTimeout(350);
            if (order === 'page-last') await page.screenshot({path:'output/playwright/' + mode + '-form-after.png',fullPage:true});
        }
    }
    check(errors.length === 0, 'upload/edit layout has no JavaScript errors: ' + errors.join('; '));
    return {checks:results.length,coverage:'upload/edit; both stylesheet orders; light/dark; 1920/1024/768/390/320px; all 7 steps',errors};
}
