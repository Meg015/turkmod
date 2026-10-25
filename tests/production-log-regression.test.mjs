import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const root = new URL('../', import.meta.url);
const commentsSource = await readFile(new URL('assets/js/topic-comments.js', root), 'utf8');
const escape = value => String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');

class Element {
    dataset = {};
    listeners = {};
    children = [];
    isConnected = true;
    disabled = false;
    _html = '';
    _text = '';
    classList = {
        add() {}, remove() {}, contains() { return false; }, toggle() {},
    };
    set textContent(value) { this._text = String(value ?? ''); this._html = escape(value); }
    get textContent() { return this._text; }
    set innerHTML(value) { this._html = value; }
    get innerHTML() { return this._html; }
    addEventListener(name, callback) { this.listeners[name] = callback; }
    querySelectorAll() { return []; }
    querySelector() { return null; }
    setAttribute() {}
    scrollIntoView() {}
    focus() {}
    replaceChildren(...children) { this.children = children; this._html = children.map(child => child.innerHTML).join(''); }
    insertAdjacentHTML(where, html) { this._html += html; }
}

async function commentHarness(responses, { optional = false, listPresent = true, pollSeconds = 0, hash = '' } = {}) {
    const section = new Element();
    section.dataset = { api: '/api/comments.php', topicId: '1', loggedIn: '0', poll: String(pollSeconds) };
    const list = new Element();
    const sort = new Element();
    const more = new Element();
    const nodes = new Map([['tcSort', sort], ['tcLoadMore', more]]);
    if (listPresent) nodes.set('tcList', list);
    if (optional) {
        for (const id of ['tcLoading', 'tcCount', 'tcLoadMoreWrap', 'tcPaginationInfo']) nodes.set(id, new Element());
    }
    const calls = [];
    const errors = [];
    const toasts = [];
    const intervals = [];
    const timers = [];
    const windowListeners = {};
    const location = new URL('https://turkmod.net/konu/test/' + hash);
    const window = {
        location, addEventListener: (name, callback) => { windowListeners[name] = callback; }, matchMedia: () => ({ matches: false }),
        setTimeout: (callback, delay) => { timers.push({ callback, delay }); return timers.length; },
        showToast: (...args) => toasts.push(args),
        publicFetchJson: url => {
            calls.push(url);
            const response = responses.shift();
            return response instanceof Error ? Promise.reject(response) : Promise.resolve(response);
        },
    };
    const document = {
        querySelector: selector => selector === '.topic-comments' ? section : null,
        getElementById: id => nodes.get(id) || (id.startsWith('comment-') && list.innerHTML.includes(`id="${id}"`) ? new Element() : null),
        createElement: () => new Element(), addEventListener() {},
    };
    vm.runInNewContext(commentsSource, {
        window, document, URL, URLSearchParams, setTimeout, clearTimeout,
        setInterval: callback => { intervals.push(callback); return intervals.length; }, clearInterval() {},
        console: { error: (...args) => errors.push(args) }, history: { replaceState() {} }, navigator: {},
    });
    const flush = async () => { await new Promise(resolve => setImmediate(resolve)); };
    await flush();
    return { list, sort, more, nodes, calls, errors, toasts, intervals, timers, windowListeners, location, flush };
}

const comment = (id, body = 'Hello') => ({ id, author: 'Test', body, time_ago: 'now', replies: [] });

test('comments load without optional loader/count/pagination markup', async () => {
    const app = await commentHarness([{ comments: [comment(1)], total: 1, pages: 1 }]);
    assert.equal(app.errors.length, 0);
    assert.match(app.list.innerHTML, /Hello/);
});

test('failed initial request renders escaped error without another rejected promise', async () => {
    const app = await commentHarness([new Error('<img src=x onerror=alert(1)>')]);
    assert.equal(app.errors.length, 1);
    assert.equal(app.list.children.length, 1);
    assert.match(app.list.children[0].textContent, /Yorumlar yüklenemedi/);
    assert.match(app.list.innerHTML, /&lt;img/);
    assert.doesNotMatch(app.list.innerHTML, /<img/);
});

test('failed refresh keeps existing comments and reports the original error', async () => {
    const app = await commentHarness([
        { comments: [comment(1, 'Original comment')], total: 1, pages: 1 },
        new Error('Network unavailable'),
    ], { optional: true });
    app.sort.value = 'desc';
    app.sort.listeners.change.call(app.sort);
    await app.flush();
    assert.match(app.list.innerHTML, /Original comment/);
    assert.match(app.toasts[0][0], /Network unavailable/);
    assert.equal(app.errors.length, 1);
});

test('failed load-more retries the same page and retains the existing list', async () => {
    const app = await commentHarness([
        { comments: [comment(1, 'First comment')], total: 3, pages: 3 },
        new Error('Temporary failure'),
        { comments: [comment(2, 'Second comment')], total: 3, pages: 3 },
    ]);
    app.more.listeners.click.call(app.more);
    await app.flush();
    assert.match(app.list.innerHTML, /First comment/);
    assert.equal(app.more.disabled, false);
    app.more.listeners.click.call(app.more);
    await app.flush();
    assert.deepEqual(app.calls.map(url => new URL(url, 'https://turkmod.net').searchParams.get('page')), ['1', '2', '2']);
    assert.match(app.list.innerHTML, /First comment/);
    assert.match(app.list.innerHTML, /Second comment/);
});

test('missing comments list exits without requests', async () => {
    const app = await commentHarness([], { listPresent: false });
    assert.equal(app.calls.length, 0);
});

test('failed sort change restores the loaded sort and keeps the last loaded page', async () => {
    const app = await commentHarness([
        { comments: [comment(1, 'First page')], total: 3, pages: 3 },
        { comments: [comment(2, 'Second page')], total: 3, pages: 3 },
        new Error('Sort request failed'),
        { comments: [comment(3, 'Third page')], total: 3, pages: 3 },
    ]);
    app.more.listeners.click.call(app.more);
    await app.flush();
    app.sort.value = 'desc';
    app.sort.listeners.change.call(app.sort);
    await app.flush();
    assert.equal(app.sort.value, 'asc');
    app.more.listeners.click.call(app.more);
    await app.flush();
    const request = new URL(app.calls.at(-1), 'https://turkmod.net');
    assert.equal(request.searchParams.get('page'), '3');
    assert.equal(request.searchParams.get('sort'), 'asc');
    assert.match(app.list.innerHTML, /First page/);
    assert.match(app.list.innerHTML, /Second page/);
    assert.match(app.list.innerHTML, /Third page/);
});

test('late load-more response cannot append old-order comments after a sort change', async () => {
    let resolveAppend;
    const delayed = new Promise(resolve => { resolveAppend = resolve; });
    const app = await commentHarness([
        { comments: [comment(1, 'Old sort first page')], total: 3, pages: 3 },
        delayed,
        { comments: [comment(3, 'New sort first page')], total: 3, pages: 3 },
    ]);
    app.more.listeners.click.call(app.more);
    app.sort.value = 'desc';
    app.sort.listeners.change.call(app.sort);
    await app.flush();
    resolveAppend({ comments: [comment(2, 'Old sort second page')], total: 3, pages: 3 });
    await app.flush();
    assert.match(app.list.innerHTML, /New sort first page/);
    assert.doesNotMatch(app.list.innerHTML, /Old sort/);
    assert.equal(app.errors.length, 0);
});

test('late failure from an old sort cannot roll back a newer successful sort', async () => {
    let rejectOldSort;
    const delayed = new Promise((resolve, reject) => { rejectOldSort = reject; });
    const app = await commentHarness([
        { comments: [comment(1)], total: 1, pages: 1 }, delayed,
        { comments: [comment(2, 'Popular sort')], total: 1, pages: 1 },
    ]);
    app.sort.value = 'desc';
    app.sort.listeners.change.call(app.sort);
    app.sort.value = 'popular';
    app.sort.listeners.change.call(app.sort);
    await app.flush();
    rejectOldSort(new Error('Obsolete request failed'));
    await app.flush();
    assert.equal(app.sort.value, 'popular');
    assert.match(app.list.innerHTML, /Popular sort/);
    assert.equal(app.errors.length, 0);
    assert.equal(app.toasts.length, 0);
});

test('polling response from the old sort cannot replace a newly sorted list', async () => {
    let resolvePoll;
    const delayedPoll = new Promise(resolve => { resolvePoll = resolve; });
    const app = await commentHarness([
        { comments: [comment(1, 'Original sort')], total: 1, pages: 1 }, delayedPoll,
        { comments: [comment(2, 'New selected sort')], total: 1, pages: 1 },
    ], { pollSeconds: 1 });
    app.intervals[0]();
    app.sort.value = 'desc';
    app.sort.listeners.change.call(app.sort);
    await app.flush();
    resolvePoll({ comments: [comment(3, 'Obsolete poll')], total: 1, pages: 1 });
    await app.flush();
    assert.match(app.list.innerHTML, /New selected sort/);
    assert.doesNotMatch(app.list.innerHTML, /Obsolete poll/);
    assert.equal(app.errors.length, 0);
});

test('deep-link auto-loading resumes in the new sort while the old hash request is pending', async () => {
    let resolveOldPage;
    const delayed = new Promise(resolve => { resolveOldPage = resolve; });
    const app = await commentHarness([
        { comments: [comment(1, 'Old order first page')], total: 3, pages: 3 }, delayed,
        { comments: [comment(2, 'New order first page')], total: 3, pages: 3 },
        { comments: [comment(42, 'Linked target')], total: 3, pages: 3 },
    ], { hash: '#comment-42' });
    assert.equal(app.calls.length, 2);
    app.sort.value = 'desc';
    app.sort.listeners.change.call(app.sort);
    await app.flush();
    assert.deepEqual(app.calls.map(url => {
        const params = new URL(url, 'https://turkmod.net').searchParams;
        return [params.get('sort'), params.get('page')];
    }), [['asc', '1'], ['asc', '2'], ['desc', '1'], ['desc', '2']]);
    resolveOldPage({ comments: [comment(3, 'Obsolete hash page')], total: 3, pages: 3 });
    await app.flush();
    for (const timer of app.timers.splice(0)) if (timer.delay === 40) timer.callback();
    await app.flush();
    assert.match(app.list.innerHTML, /Linked target/);
    assert.doesNotMatch(app.list.innerHTML, /Obsolete hash page/);
    assert.equal(app.calls.length, 4);
    assert.equal(app.errors.length, 0);
});

test('failed sort change resumes the cancelled deep-link lookup in the previous order', async () => {
    let resolveOldPage;
    const delayed = new Promise(resolve => { resolveOldPage = resolve; });
    const app = await commentHarness([
        { comments: [comment(1)], total: 3, pages: 3 }, delayed, new Error('Sort failed'),
        { comments: [comment(42, 'Target after rollback')], total: 3, pages: 3 },
    ], { hash: '#comment-42' });
    app.sort.value = 'desc';
    app.sort.listeners.change.call(app.sort);
    await app.flush();
    assert.equal(app.sort.value, 'asc');
    assert.match(app.list.innerHTML, /Target after rollback/);
    resolveOldPage({ comments: [comment(2)], total: 3, pages: 3 });
    await app.flush();
    for (const timer of app.timers.splice(0)) if (timer.delay === 40) timer.callback();
    await app.flush();
    assert.equal(app.calls.length, 4);
});

test('hash change waits for a pending sort before loading additional pages', async () => {
    let resolveSort;
    const delayed = new Promise(resolve => { resolveSort = resolve; });
    const app = await commentHarness([
        { comments: [comment(1, 'Original first page')], total: 3, pages: 3 }, delayed,
        { comments: [comment(42, 'Linked comment')], total: 3, pages: 3 },
    ]);
    app.sort.value = 'desc';
    app.sort.listeners.change.call(app.sort);
    app.location.hash = '#comment-42';
    app.windowListeners.hashchange();
    await app.flush();
    assert.equal(app.calls.length, 2);
    resolveSort({ comments: [comment(2, 'Descending first page')], total: 3, pages: 3 });
    await app.flush();
    assert.equal(app.calls.length, 3);
    assert.match(app.list.innerHTML, /Descending first page/);
    assert.match(app.list.innerHTML, /Linked comment/);
    assert.doesNotMatch(app.list.innerHTML, /Original first page/);
});

test('changing the hash during an auto-load continues lookup for the new target', async () => {
    let resolveSecondPage;
    const delayed = new Promise(resolve => { resolveSecondPage = resolve; });
    const app = await commentHarness([
        { comments: [comment(1)], total: 3, pages: 3 }, delayed,
        { comments: [comment(43, 'New hash target')], total: 3, pages: 3 },
    ], { hash: '#comment-42' });
    app.location.hash = '#comment-43';
    app.windowListeners.hashchange();
    resolveSecondPage({ comments: [comment(2)], total: 3, pages: 3 });
    await app.flush();
    assert.equal(app.calls.length, 3);
    assert.match(app.list.innerHTML, /New hash target/);
    assert.equal(app.errors.length, 0);
});

test('Markdown preserves formatting and escapes HTML without regex lookbehind', async () => {
    const app = await commentHarness([{
        comments: [comment(1, '*one* *two* **bold** ***both*** <script>alert(1)</script>')], total: 1, pages: 1,
    }]);
    assert.equal(app.errors.length, 0);
    assert.match(app.list.innerHTML, /<em>one<\/em> <em>two<\/em>/);
    assert.match(app.list.innerHTML, /<strong>bold<\/strong>/);
    assert.match(app.list.innerHTML, /<strong><em>both<\/em><\/strong>/);
    assert.match(app.list.innerHTML, /&lt;script&gt;/);
    assert.doesNotMatch(commentsSource, /\(\?<[/=!]/);
});

for (const filename of ['assets/js/app.js', 'themes/turkmod/js/bundle.js']) {
    test(`${filename}: retain genuine errors; filter only identified external/cancelled events`, async () => {
        const source = await readFile(new URL(filename, root), 'utf8');
        const start = source.indexOf('class Analytics {');
        const end = source.indexOf('// Initialize analytics', start);
        assert.ok(start >= 0 && end > start);
        const Analytics = vm.runInNewContext(source.slice(start, end) + '\nAnalytics');
        const records = [];
        const analytics = Object.create(Analytics.prototype);
        analytics.track = (...args) => records.push(args);
        const external = { message: "Cannot read properties of undefined (reading 'M_ID')", stack: 'at chrome-extension://id/executors/200.js:1:761' };
        analytics.trackError(external, { type: 'unhandled_promise_rejection' });
        analytics.trackError('External script failed', { filename: 'moz-extension://id/script.js' });
        for (const message of [
            'Skipping view transition because skipTransition() was called.',
            'Skipping view transition because viewport size changed.',
            'Transition was skipped. Navigation aborted', 'Transition was skipped',
            'Transition was aborted because of invalid state',
            'Transition was aborted because of invalid state. Document hidden',
            'Transition was aborted because of invalid state. ViewTransition opt-in disabled',
        ]) analytics.trackError(message, { type: 'unhandled_promise_rejection' });
        assert.equal(records.length, 0);
        analytics.trackError(null, { type: 'unhandled_promise_rejection' });
        analytics.trackError({ message: 'Application failed', stack: 'at https://turkmod.net/assets/app.js:1:2' });
        analytics.trackError({ message: 'Transition was skipped', stack: 'at https://turkmod.net/assets/app.js:1:2' }, { type: 'unhandled_promise_rejection' });
        analytics.trackError('Unknown transition failure', { type: 'unhandled_promise_rejection' });
        analytics.trackError('Script error.', { filename: '' });
        analytics.trackError({ message: 'Application callback failed', stack: 'Error: Application callback failed\n at https://turkmod.net/assets/app.js:1:2\n at chrome-extension://id/caller.js:1:2' });
        assert.equal(records.length, 6);
        assert.equal(records[0][1].message, 'Unknown error');
    });
}
