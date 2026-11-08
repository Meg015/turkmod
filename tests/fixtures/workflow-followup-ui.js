async (page) => {
    page.setDefaultTimeout(5000);
    const base = 'http://localhost/turkmod';
    const results = [];
    const errors = [];
    const check = (ok, label) => { if (!ok) throw new Error(label); results.push(label); };
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/__workflow_followup*', route => route.fulfill({contentType: 'text/html', body: '<!doctype html><meta charset="utf-8"><body></body>'}));
    await page.goto(base + '/__workflow_followup?messages');
    await page.setContent(`<div data-messages-root data-messages-api-url="${base}/api/messages.php" data-active-thread-id="12" data-current-user-id="5">
      <aside class="messages-sidebar"><span data-messages-thread-count></span><span data-messages-unread-count></span><input data-messages-sidebar-search-input><div data-messages-thread-list></div></aside>
      <div data-messages-stream style="height:200px;overflow:auto"></div>
      <form data-messages-send-form><input type="hidden" name="action" value="send"><input type="hidden" name="thread_id" value="12"><textarea name="body" class="messages-composer-textarea"></textarea><button type="submit">Gönder</button></form></div>`);
    await page.evaluate(() => {
        const date = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 19).replace('T', ' ');
        window.messages = Array.from({length: 350}, (_, i) => ({id: i + 1, thread_id: 12, sender_user_id: 5, is_mine: true, is_deleted: false, body: 'Message ' + (i + 1), created_at: date, created_at_label: '12:00'}));
        window.threads = [12, 13].map(id => ({thread_id: id, with_user_id: id, with_user_name: 'User ' + id, thread_url: '/turkmod/mesajlar?thread=' + id, last_message_preview: 'Preview', unread_count: 0}));
        window.toasts = []; window.pending = {}; window.defer = {}; window.calls = []; window.failHistory = true;
        window.showToast = (...args) => window.toasts.push(args);
        window.TMUI = {confirm: () => Promise.resolve(true)};
        window.appConfirm = () => Promise.resolve(true);
        window.appPrompt = () => Promise.resolve('Edited history message');
        window.publicTopbar = {setMessageBadgeCount() {}, refreshMessages() {}};
        window.publicTopbarRealtime = {subscribe(fn) { window.realtime = fn; }};
        window.publicFetchJson = (url, options = {}) => {
            const query = new URL(url).searchParams;
            const action = options.body?.get('action') || query.get('action');
            window.calls.push(action);
            let response;
            if (action === 'send') {
                const msg = {id: 351, thread_id: 12, sender_user_id: 5, is_mine: true, body: options.body.get('body'), created_at: date};
                window.messages.push(msg);
                response = {ok: true, thread_id: 12, message_id: 351};
            } else if (action === 'edit' || action === 'delete') {
                const id = Number(options.body.get('message_id'));
                const msg = window.messages.find(msg => msg.id === id);
                msg.body = action === 'edit' ? options.body.get('body') : 'Bu mesaj silindi';
                msg.is_deleted = action === 'delete';
                response = {ok: true, updated_message: {id, body: msg.body, is_deleted: msg.is_deleted}};
            } else if (action === 'delete_thread') {
                const id = Number(options.body.get('thread_id'));
                window.threads = window.threads.filter(thread => thread.thread_id !== id);
                response = {ok: true, unread_count: 0};
            } else if (action === 'history') {
                if (window.failHistory) { window.failHistory = false; return Promise.reject(new Error('History connection failed')); }
                response = {ok: true, messages: window.messages.filter(msg => msg.id < Number(query.get('before_id'))).slice(-50)};
            } else if (action === 'list') response = {ok: true, threads: window.threads, unread_count: 0};
            else response = {ok: true, thread: {with_user_id: 9, with_user_name: 'User 9'}, messages: window.messages.slice(-300), unread_count: 0};
            const snapshot = structuredClone(response);
            if (window.defer[action]) {
                window.defer[action] = false;
                return new Promise((resolve, reject) => {
                    window.pending[action] = () => { delete window.pending[action]; resolve(snapshot); };
                    options.signal?.addEventListener('abort', () => { delete window.pending[action]; reject(new Error('timeout')); });
                });
            }
            return Promise.resolve(snapshot);
        };
    });
    await page.addScriptTag({url: base + '/assets/js/messages-page.js'});
    await page.locator('[data-message-id="350"]').waitFor();

    await page.evaluate(() => { window.defer.send = true; });
    await page.locator('textarea[name="body"]').fill('Sent while typing');
    await page.getByRole('button', {name: 'Gönder', exact: true}).click();
    await page.waitForFunction(() => window.pending.send);
    await page.evaluate(() => document.querySelector('[data-messages-send-form]').requestSubmit());
    check(await page.evaluate(() => window.calls.filter(action => action === 'send').length === 1), 'a pending send cannot be submitted twice');
    await page.locator('textarea[name="body"]').fill('Next unsent draft');
    await page.evaluate(() => window.pending.send());
    await page.locator('[data-message-id="351"]').waitFor();
    check(await page.locator('textarea[name="body"]').inputValue() === 'Next unsent draft', 'sending preserves the next draft typed before the response');

    await page.evaluate(() => { const stream = document.querySelector('[data-messages-stream]'); stream.scrollTop = 0; stream.dispatchEvent(new Event('scroll')); });
    await page.waitForFunction(() => window.calls.includes('history'));
    await page.waitForTimeout(150);
    check(errors.length === 0, 'failed history request produces no unhandled rejection');
    await page.evaluate(() => { const stream = document.querySelector('[data-messages-stream]'); stream.scrollTop = 0; stream.dispatchEvent(new Event('scroll')); });
    await page.locator('[data-message-id="1"]').waitFor();
    check(true, 'history can be retried after a failed request');
    await page.locator('[data-message-id="1"] [data-msg-action="edit"]').click();
    await page.waitForFunction(() => document.querySelector('[data-message-id="1"] .msg-body')?.textContent === 'Edited history message');
    check(true, 'editing an older loaded message updates it beyond the latest 300');

    await page.evaluate(() => { window.defer.thread = true; window.dispatchEvent(new Event('online')); });
    await page.waitForFunction(() => window.pending.thread);
    await page.locator('[data-message-id="100"] [data-msg-action="delete"]').click();
    await page.waitForFunction(() => document.querySelector('[data-message-id="100"]')?.classList.contains('is-deleted'));
    await page.evaluate(() => window.pending.thread());
    await page.waitForTimeout(150);
    check(await page.locator('[data-message-id="100"]').getAttribute('class').then(value => value.includes('is-deleted')), 'late thread snapshot does not restore a deleted message');

    await page.evaluate(() => window.realtime({type: 'edit_message', thread_id: '12', message_id: 1, body: 'Remote history edit'}));
    await page.waitForFunction(() => document.querySelector('[data-message-id="1"] .msg-body')?.textContent === 'Remote history edit');
    check(true, 'realtime edit updates older loaded history');

    await page.evaluate(() => { window.defer.list = true; window.dispatchEvent(new Event('online')); });
    await page.waitForFunction(() => window.pending.list);
    await page.locator('[data-delete-thread-id="13"]').click();
    await page.locator('[data-thread-id="13"]').waitFor({state: 'detached'});
    await page.evaluate(() => window.pending.list());
    await page.waitForTimeout(150);
    check(await page.locator('[data-thread-id="13"]').count() === 0, 'late inbox snapshot cannot resurrect a deleted conversation');
    await page.evaluate(() => { window.threads = []; window.dispatchEvent(new Event('online')); });
    await page.waitForFunction(() => !document.querySelector('[data-thread-id="12"]'));
    check(true, 'inbox removes conversations missing from the authoritative list');

    await page.goto(base + '/__workflow_followup?admin');
    await page.addScriptTag({url: base + '/admin/assets/admin-ui.js'});
    await page.evaluate(() => {
        window.oldPrompt = 'pending';
        window.adminPrompt('First prompt', {inputAttributes: {maxlength: '3', required: true}}).then(value => { window.oldPrompt = value; });
        window.newPrompt = window.adminPrompt('Second prompt');
    });
    check(await page.evaluate(() => window.oldPrompt === null), 'opening another prompt settles the previous prompt as cancelled');
    check(await page.locator('[data-ui-admin-native-input]').getAttribute('maxlength') === null && await page.locator('[data-ui-admin-native-input]').getAttribute('required') === null, 'prompt attributes do not leak to the next prompt');
    await page.locator('[data-ui-admin-native-input]').fill('Second result');
    await page.locator('[data-ui-admin-native-ok]').click();
    check(await page.evaluate(() => window.newPrompt) === 'Second result', 'only the current prompt receives the accepted value');
    await page.evaluate(() => {
        const modal = document.createElement('div'); modal.id = 'managedFixture'; modal.className = 'ui-admin-modal-overlay'; modal.innerHTML = '<div role="dialog"><button>Parent</button></div>'; document.body.appendChild(modal);
        window.parentModal = modal;
        window.adminModal.open(modal);
        window.adminModal.close(modal, {delay: 160});
        window.adminModal.open(modal);
    });
    await page.waitForTimeout(250);
    check(await page.locator('#managedFixture').isVisible(), 'an earlier close timer cannot hide a reopened admin modal');
    await page.evaluate(() => { window.adminAlert('Parent error', {tone: 'danger'}); });
    await page.locator('[data-ui-admin-native-ok]').click();
    check(await page.evaluate(() => document.body.classList.contains('ui-admin-dialog-open')), 'closing a nested alert preserves the parent modal scroll lock');

    const uploadHtml = await (await page.request.get(base + '/output/playwright/upload-form-fixture.html')).text();
    for (const mode of ['standalone', 'theme']) {
    const scriptUrl = mode === 'theme' ? '/turkmod/themes/turkmod/js/bundle.js' : '/turkmod/assets/js/upload-topic-form.js';
    await page.route('**/__workflow_followup?upload-' + mode, route => route.fulfill({contentType: 'text/html', body: '<!doctype html><meta charset="utf-8">' + uploadHtml + '<script>window.TMUI={registerAction(){}};window.showToast=()=>{};</script><script src="' + scriptUrl + '" defer></script>'}));
    await page.goto(base + '/__workflow_followup?upload-' + mode);
    await page.locator('input[name="title"]').fill('Previous upload');
    await page.locator('[data-step-target="3"]').click();
    await page.locator('textarea[name="content"]').fill('Previous description');
    await page.locator('[data-step-target="6"]').click();
    await page.locator('input[name="dl_url[]"]').fill('https://previous.test/download');
    await page.goto(base + '/__workflow_followup?away');
    await page.goBack();
    await page.waitForFunction(() => document.querySelector('input[name="title"]')?.dataset.liveRuleBound === '1');
    await page.waitForTimeout(200);
    check(await page.locator('input[name="title"]').inputValue() === '' && await page.locator('textarea[name="content"]').inputValue() === '' && await page.locator('input[name="dl_url[]"]').inputValue() === '', mode + ': history navigation opens a clean upload form without restoring previous fields');
    }
    check(errors.length === 0, 'no JavaScript errors in follow-up scenarios');
    return {checks: results.length, results};
}
