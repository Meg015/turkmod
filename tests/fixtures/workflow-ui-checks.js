async (page) => {
    page.setDefaultTimeout(5000);
    const base = 'http://localhost/turkmod';
    const results = [];
    const check = (ok, name) => { if (!ok) throw new Error(name); results.push(name); };
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/__workflow_fixture*', route => route.fulfill({contentType: 'text/html', body: '<!doctype html><html lang="tr"><head><meta charset="utf-8"></head><body></body></html>'}));

    await page.goto(base + '/__workflow_fixture?messages');
    await page.setContent(`<div data-messages-root data-messages-api-url="${base}/api/messages.php" data-messages-url="${base}/mesajlar" data-active-thread-id="12" data-current-user-id="5">
      <aside class="messages-sidebar"><span data-messages-thread-count></span><span data-messages-unread-count></span><input data-messages-sidebar-search-input><div class="messages-thread-list" data-messages-thread-list></div></aside>
      <div data-messages-stream style="height:250px;overflow:auto"></div>
      <form data-messages-send-form><input name="action" value="send" type="hidden"><input name="thread_id" value="12" type="hidden"><textarea class="messages-composer-textarea" name="body"></textarea><button type="submit">Gönder</button></form></div>`);
    await page.evaluate(() => {
        window.fixtureMessages = [{id: 1, sender_user_id: 9, body: 'İlk mesaj', created_at: '2026-11-08 17:00:00', created_at_label: '17:00'}];
        window.fixtureThreads = [{thread_id: 12, with_user_id: 9, with_user_name: 'Üye', last_message_preview: 'İlk mesaj', unread_count: 0, thread_url: '/turkmod/mesajlar?thread=12'}];
        window.fixtureRequests = []; window.fixtureToasts = []; window.failSend = false; window.hangNextThread = false;
        window.showToast = (...args) => window.fixtureToasts.push(args);
        window.publicTopbar = {setMessageBadgeCount() {}, refreshMessages() {}};
        window.publicTopbarRealtime = {subscribe(fn) { window.realtimeConsumer = fn; }};
        window.publicFetchJson = async (url, options = {}) => {
            const action = options.body?.get('action') || new URL(url).searchParams.get('action');
            window.fixtureRequests.push({action, cache: options.cache});
            if (action === 'send') {
                if (window.failSend) throw new Error('Gönderim başarısız');
                window.fixtureMessages.push({id: 3, sender_user_id: 5, is_mine: true, body: options.body.get('body'), created_at: '2026-11-08 17:00:00'});
                return {ok: true, message_id: 3, thread_id: 12};
            }
            if (action === 'thread' && window.hangNextThread) {
                window.hangNextThread = false;
                return new Promise((_, reject) => options.signal.addEventListener('abort', () => reject(new Error('aborted'))));
            }
            if (action === 'list') return {ok: true, threads: window.fixtureThreads, unread_count: 0};
            return {ok: true, thread: {with_user_name: 'Üye', with_user_id: 9}, messages: window.fixtureMessages, unread_count: 0};
        };
    });
    await page.addScriptTag({url: base + '/assets/js/messages-page.js'});
    await page.getByText('İlk mesaj', {exact: true}).first().waitFor();
    check(await page.locator('[data-messages-root]').getAttribute('data-messages-page-ready') === '1', 'late-loaded message script initializes');
    await page.evaluate(() => window.fixtureMessages.push({id: 2, sender_user_id: 9, body: 'Yenilemeden gelen mesaj', created_at: '2026-11-08 17:01:00'}));
    await page.getByText('Yenilemeden gelen mesaj', {exact: true}).waitFor({timeout: 5000});
    check(true, 'HTTP fallback displays incoming message without refresh or WebSocket');
    await page.locator('textarea[name="body"]').fill('Yanıtım');
    await page.getByRole('button', {name: 'Gönder', exact: true}).click();
    await page.getByText('Yanıtım', {exact: true}).waitFor();
    check(await page.evaluate(() => window.fixtureToasts.length === 0), 'successful message send creates no toast');
    await page.evaluate(() => { window.failSend = true; });
    await page.locator('textarea[name="body"]').fill('Hatalı gönderim');
    await page.getByRole('button', {name: 'Gönder', exact: true}).click();
    await page.waitForFunction(() => window.fixtureToasts.length === 1);
    check(await page.evaluate(() => window.fixtureToasts[0][0] === 'Gönderim başarısız'), 'message send errors remain visible');
    await page.evaluate(() => { window.fixtureThreads.push({thread_id: 13, with_user_id: 11, with_user_name: 'Yeni Üye', last_message_preview: 'Yeni sohbet', unread_count: 1, thread_url: '/turkmod/mesajlar?thread=13'}); window.realtimeConsumer({type: 'new_message', thread_id: '13'}); });
    await page.locator('[data-thread-id="13"]').waitFor();
    check(true, 'new conversation arrives in inbox without page reload');
    await page.evaluate(() => { window.fixtureMessages.push({id: 4, sender_user_id: 9, body: 'Odak sonrası mesaj', created_at: '2026-11-08 17:02:00'}); window.dispatchEvent(new Event('online')); });
    await page.getByText('Odak sonrası mesaj', {exact: true}).waitFor({timeout: 1000});
    check(await page.evaluate(() => window.fixtureRequests.filter(r => ['list', 'thread'].includes(r.action)).every(r => r.cache === 'no-store')), 'inbox and thread polling bypass cache');
    await page.evaluate(() => { window.hangNextThread = true; window.dispatchEvent(new Event('online')); });
    await page.evaluate(() => window.fixtureMessages.push({id: 5, sender_user_id: 9, body: 'Zaman aşımı sonrası mesaj', created_at: '2026-11-08 17:03:00'}));
    await page.getByText('Zaman aşımı sonrası mesaj', {exact: true}).waitFor({timeout: 15000});
    check(true, 'stalled request times out and polling recovers');

    await page.goto(base + '/__workflow_fixture?admin');
    await page.addScriptTag({url: base + '/admin/assets/admin-ui.js'});
    await page.evaluate(() => { window.fixtureAdminToasts = []; window.adminToast.success = m => window.fixtureAdminToasts.push(m); });
    await page.evaluate(() => window.adminAlert('', {title: 'Başarılı', tone: 'success'}));
    check(await page.locator('#ui-admin-native-dialog').count() === 0, 'empty admin alert creates no popup');
    await page.evaluate(() => window.adminAlert('Kullanıcı banlandı.', {tone: 'success'}));
    check(await page.locator('#ui-admin-native-dialog').count() === 0 && await page.evaluate(() => window.fixtureAdminToasts.length === 1), 'successful admin action uses toast without confirmation popup');
    await page.evaluate(() => { window.pendingAlert = window.adminAlert('İşlem başarısız.', {tone: 'danger'}); });
    await page.locator('#ui-admin-native-dialog').waitFor({state: 'visible'});
    check(await page.locator('[data-ui-admin-native-input]').isHidden() && await page.locator('[data-ui-admin-native-textarea]').isHidden(), 'admin error has no stray input');
    await page.locator('[data-ui-admin-native-ok]').click();
    await page.evaluate(() => { window.pendingPrompt = window.adminPrompt('Sebep', {input: 'textarea'}); });
    await page.locator('[data-ui-admin-native-textarea]').fill('Fixture sebep');
    await page.locator('[data-ui-admin-native-ok]').click();
    check(await page.evaluate(() => window.pendingPrompt) === 'Fixture sebep', 'admin prompt retains functional text input');

    const modal = await (await page.request.get(base + '/output/playwright/revision-modal-fixture.html')).text();
    await page.evaluate(html => { document.body.insertAdjacentHTML('beforeend', '<form id="revisionFixture" class="topic-row-checkbox" data-moderation-note-form data-moderation-note-required="1" data-moderation-note-title="Revizyon notu"><input name="decision" value="revision"><input name="moderation_note"><input name="notify_in_app" value="1"><input name="notify_email" value="0"><input name="notify_message" value="0"><button type="submit">Revizyon iste</button></form>' + html); document.getElementById('revisionFixture').submit = function() { window.revisionPayload = Object.fromEntries(new FormData(this)); }; }, modal);
    await page.addStyleTag({url: base + '/admin/assets/admin.css'});
    await page.addStyleTag({url: base + '/admin/assets/topics-page.css'});
    await page.addScriptTag({url: base + '/admin/assets/topics-page.js'});
    await page.evaluate(() => initTopicsPage());
    await page.getByRole('button', {name: 'Revizyon iste', exact: true}).click();
    await page.locator('#moderationActionNoteSubmit').click();
    check(await page.locator('#moderationActionNoteError').isVisible() && !await page.evaluate(() => window.revisionPayload), 'empty revision note blocks submission');
    await page.locator('#moderationActionNoteText').fill('Eksik bilgileri tamamlayın.');
    check(await page.locator('[data-revision-channel="in_app"]').isChecked() && !await page.locator('[data-revision-channel="email"]').isChecked(), 'revision popup defaults to in-app only');
    await page.locator('[data-revision-channel="in_app"]').uncheck();
    await page.locator('[data-revision-channel="email"]').check();
    await page.locator('[data-revision-channel="message"]').check();
    await page.screenshot({path: 'output/playwright/revision-modal.png'});
    await page.locator('#moderationActionNoteSubmit').click();
    const revision = await page.evaluate(() => window.revisionPayload);
    check(revision.notify_email === '1' && revision.notify_message === '1' && revision.notify_in_app === '0' && revision.moderation_note === 'Eksik bilgileri tamamlayın.', 'revision checkboxes serialize the selected channels and note');
    await page.getByRole('button', {name: 'İptal', exact: true}).click();
    await page.locator('#moderationActionNoteModal').waitFor({state: 'hidden'});
    await page.getByRole('button', {name: 'Revizyon iste', exact: true}).click();
    await page.locator('[data-revision-channel="email"]').uncheck();
    await page.locator('[data-revision-channel="message"]').uncheck();
    await page.locator('#moderationActionNoteSubmit').click();
    check(await page.evaluate(() => ['in_app', 'email', 'message'].every(channel => window.revisionPayload['notify_' + channel] === '0')), 'revision can be saved without sending a channel');

    const formHtml = await (await page.request.get(base + '/output/playwright/upload-form-fixture.html')).text();
    for (const mode of ['theme', 'standalone']) {
        await page.goto(base + '/__workflow_fixture?upload-' + mode);
        await page.setContent(formHtml);
        await page.evaluate(mode => {
            localStorage.setItem('mod2.uploadTopicDraft.v1', JSON.stringify({title: 'Eski başlık', content: 'Eski açıklama', links: [{url: 'https://old.test'}]}));
            localStorage.setItem('turkmod.uploadTopicDraft.v2:create', JSON.stringify({title: 'Eski başlık', content: 'Eski açıklama', links: [{url: 'https://old.test'}]}));
            if (mode === 'standalone') document.getElementById('uploadForm').dataset.uploadTopicStandalone = '1';
            window.showToast = () => {};
            window.TMUI = {registerAction() {}};
        }, mode);
        await page.addScriptTag({url: 'https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js'});
        if (mode === 'theme') await page.addScriptTag({url: base + '/themes/turkmod/js/bundle.js'});
        else {
            await page.addScriptTag({url: base + '/assets/js/upload-topic-rich-editor.js'});
            await page.addScriptTag({url: base + '/assets/js/upload-topic-form.js'});
        }
        const empty = await page.evaluate(() => {
            const form = document.getElementById('uploadForm');
            return [...form.querySelectorAll('input[name="title"], textarea[name="content"], input[name="author_topic"], input[name="topic_version"], input[name="topic_video_url"], input[name="dl_url[]"], input[name="dl_name[]"]')].every(el => el.value === '')
                && form.querySelector('textarea.rich-editor').quillInstance.getText().trim() === ''
                && !localStorage.getItem('mod2.uploadTopicDraft.v1') && !localStorage.getItem('turkmod.uploadTopicDraft.v2:create')
                && form.querySelector('[name="_token"]').value === 'fixture-csrf' && form.querySelector('[name="upload_submit_token"]').value === 'fixture-submit';
        });
        check(empty, mode + ': fresh form clears both old drafts, editor and links while preserving tokens');
        await page.locator('input[name="title"]').fill('Yeni mod');
        await page.locator('select[name="category_id"]').selectOption('1');
        await page.locator('[data-step-target="3"]').click();
        await page.locator('.ql-editor').fill('Yeni mod açıklaması');
        await page.locator('[data-step-target="6"]').click();
        await page.locator('input[name="dl_url[]"]').fill('https://new.test/download');
        await page.locator('[data-step-target="1"]').click();
        await page.locator('[data-step-target="3"]').click();
        check(await page.locator('.ql-editor').innerText() === 'Yeni mod açıklaması', mode + ': step navigation retains current description');
        await page.locator('[data-step-target="6"]').click();
        check(await page.locator('input[name="dl_url[]"]').inputValue() === 'https://new.test/download', mode + ': step navigation retains current download link');
        check(await page.evaluate(() => !localStorage.getItem('mod2.uploadTopicDraft.v1') && !localStorage.getItem('turkmod.uploadTopicDraft.v2:create')), mode + ': inputs do not recreate persistent drafts');
    }
    await page.evaluate(() => { ['theme-mode', 'theme', 'data-theme'].forEach(key => localStorage.setItem(key, 'light')); });
    await page.setViewportSize({width: 1440, height: 1000});
    const missing = await page.goto(base + '/konu/codex-missing-topic-999999999');
    await page.locator('.not-found-card').waitFor();
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(350);
    check(missing.status() === 404 && (await missing.allHeaders())['x-robots-tag']?.includes('noindex'), 'missing topic returns HTTP 404 and noindex');
    check(await page.getByRole('heading', {name: 'Konu bulunamadı', exact: true}).isVisible(), 'topic missing state remains visible without a toast');
    check(await page.locator('.not-found-actions a').count() === 2, '404 offers home and content discovery routes');
    check(await page.locator('#main-content.site-main--focus').count() === 1, '404 is centered in the full content area without sidebars');
    await page.screenshot({path: 'output/playwright/missing-topic-desktop.png'});
    await page.setViewportSize({width: 390, height: 844});
    await page.waitForTimeout(350);
    check(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth), 'mobile 404 has no horizontal overflow');
    await page.screenshot({path: 'output/playwright/missing-topic-mobile-light.png'});
    await page.getByRole('button', {name: 'Tema Degistir', exact: true}).click();
    await page.waitForFunction(() => document.documentElement.getAttribute('data-bs-theme') === 'dark');
    await page.waitForTimeout(350);
    await page.screenshot({path: 'output/playwright/missing-topic-mobile-dark.png'});
    check(await page.locator('.not-found-card').isVisible(), 'dark mobile 404 remains readable');
    const missingPage = await page.goto(base + '/codex-unknown-page-999999999');
    check(missingPage.status() === 404 && await page.getByRole('heading', {name: 'Sayfa bulunamadı', exact: true}).isVisible(), 'general 404 shares the missing-state design');
    check(errors.length === 0, 'No JavaScript errors: ' + errors.join('; '));
    return {checks: results.length, results};
}
