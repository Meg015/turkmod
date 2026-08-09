import assert from "node:assert/strict";
import fs from "node:fs";
import vm from "node:vm";

const source = fs.readFileSync(new URL("../assets/js/public-topbar-realtime.js", import.meta.url), "utf8");
const uiSource = fs.readFileSync(new URL("../assets/js/ui-foundation.js", import.meta.url), "utf8");
const commentSource = fs.readFileSync(new URL("../assets/js/topic-comments.js", import.meta.url), "utf8");
const themeProfileSource = fs.readFileSync(new URL("../themes/turkmod/profile-sidebar.tpl", import.meta.url), "utf8");
const fallbackProfileSource = fs.readFileSync(new URL("../includes/partials/profile-sidebar.php", import.meta.url), "utf8");
const messagePageSource = fs.readFileSync(new URL("../includes/src/Modules/Messages/Http/messages-page-content.php", import.meta.url), "utf8");
const commentCss = fs.readFileSync(new URL("../assets/css/pro-comments.css", import.meta.url), "utf8");
const messageCss = fs.readFileSync(new URL("../assets/css/messages-page.css", import.meta.url), "utf8");
const themeCss = fs.readFileSync(new URL("../assets/css/theme.css", import.meta.url), "utf8");
const themeBundleCss = fs.readFileSync(new URL("../themes/turkmod/css/bundle.css", import.meta.url), "utf8");

function presenceMarkup(sourceText, marker) {
    const markerIndex = sourceText.indexOf(marker);
    assert.ok(markerIndex >= 0, `Expected presence marker: ${marker}`);
    const start = Math.max(0, sourceText.lastIndexOf("<span", markerIndex));
    const end = sourceText.indexOf("</span>", markerIndex);
    return sourceText.slice(start, end >= 0 ? end + 7 : markerIndex + marker.length);
}

function createRuntime(fetchImplementation) {
    const timers = new Map();
    const intervals = new Map();
    const documentListeners = new Map();
    const storage = new Map();
    let timerId = 0;

    const localStorage = {
        get length() { return storage.size; },
        key(index) { return Array.from(storage.keys())[index] ?? null; },
        getItem(key) { return storage.has(key) ? storage.get(key) : null; },
        setItem(key, value) { storage.set(String(key), String(value)); },
        removeItem(key) { storage.delete(String(key)); }
    };
    const document = {
        hidden: false,
        body: null,
        hasFocus() { return true; },
        querySelector(selector) {
            if (selector === 'meta[name="app-base-uri"]') {
                return { getAttribute() { return "/yenidosyalar"; } };
            }
            return null;
        },
        querySelectorAll() { return []; },
        addEventListener(type, callback) {
            documentListeners.set(type, callback);
        }
    };
    const windowListeners = new Map();
    const windowObject = {
        crypto: { randomUUID: (() => { let id = 0; return () => "tab-" + (++id); })() },
        localStorage,
        location: {
            origin: "http://localhost",
            protocol: "http:",
            href: "http://localhost/yenidosyalar/index.php",
            assign() {}
        },
        publicFetchJson: fetchImplementation,
        setTimeout(callback, delay) {
            timerId += 1;
            timers.set(timerId, { callback, delay: Number(delay || 0), cancelled: false });
            return timerId;
        },
        clearTimeout(id) {
            const timer = timers.get(id);
            if (timer) timer.cancelled = true;
        },
        setInterval(callback, delay) {
            timerId += 1;
            intervals.set(timerId, { callback, delay: Number(delay || 0), cancelled: false });
            return timerId;
        },
        clearInterval(id) {
            const interval = intervals.get(id);
            if (interval) interval.cancelled = true;
        },
        addEventListener(type, callback) { windowListeners.set(type, callback); },
        focus() {},
        publicTopbar: {}
    };
    windowObject.window = windowObject;

    const context = vm.createContext({
        window: windowObject,
        document,
        URL,
        JSON,
        Math,
        Date,
        Number,
        String,
        Array,
        Set,
        Map,
        Promise,
        console
    });
    vm.runInContext(source, context, { filename: "public-topbar-realtime.js" });
    assert.equal(typeof documentListeners.get("DOMContentLoaded"), "function");
    documentListeners.get("DOMContentLoaded")();

    return {
        window: windowObject,
        timers,
        intervals,
        runTimer(delay) {
            const entry = Array.from(timers.entries()).find(([, timer]) => !timer.cancelled && timer.delay === delay);
            assert.ok(entry, `Expected an active ${delay}ms timer`);
            entry[1].cancelled = true;
            entry[1].callback();
        },
        runInterval(delay) {
            const entry = Array.from(intervals.values()).find((interval) => !interval.cancelled && interval.delay === delay);
            assert.ok(entry, `Expected an active ${delay}ms interval`);
            entry.callback();
        }
    };
}

async function flushPromises() {
    for (let index = 0; index < 8; index += 1) {
        await Promise.resolve();
    }
}

const successCalls = [];
const successRuntime = createRuntime((url) => {
    successCalls.push(url);
    return Promise.resolve({
        success: true,
        users: { 42: { user_id: 42, visible: true, is_online: true, relative_label: "Şimdi çevrimiçi" } }
    });
});
let receivedState = null;
const stopWatching = successRuntime.window.publicTopbarRealtime.watchPresence([42, 42, -1], (userId, state) => {
    receivedState = { userId, state };
});
successRuntime.runTimer(25);
successRuntime.runTimer(0);
await flushPromises();
assert.equal(successCalls.length, 1);
assert.match(successCalls[0], /\/api\/user-presence\.php\?ids=42$/);
assert.equal(receivedState?.userId, 42);
assert.equal(receivedState?.state?.is_online, true);
assert.ok(Array.from(successRuntime.timers.values()).some((timer) => !timer.cancelled && timer.delay === 60000));
successRuntime.runInterval(15000);
successRuntime.runTimer(25);
assert.ok(Array.from(successRuntime.timers.values()).some((timer) => !timer.cancelled && timer.delay === 60000));
assert.equal(Array.from(successRuntime.timers.values()).some((timer) => !timer.cancelled && timer.delay === 0), false);
stopWatching();

const failureRuntime = createRuntime(() => Promise.reject(new Error("offline")));
failureRuntime.window.publicTopbarRealtime.watchPresence([7], () => {});
failureRuntime.runTimer(0);
await flushPromises();
assert.ok(Array.from(failureRuntime.timers.values()).some((timer) => !timer.cancelled && timer.delay === 120000));

assert.match(source, /presenceFailureCount === 1 \? 120000 : 300000/);
assert.match(source, /presence_subscribe/);
assert.match(source, /presence_unsubscribe/);
assert.match(source, /networkOwnerId/);
assert.match(source, /server_instance_id/);
assert.match(uiSource, /setTimeout\(closePresenceTooltip, 2500\)/);
assert.match(uiSource, /closest\('\[data-user-presence-dot\]'\)/);
assert.match(uiSource, /event\.preventDefault\(\)/);
assert.match(uiSource, /publicPresenceUI/);
assert.match(uiSource, /removeAttribute\('title'\)/);
assert.doesNotMatch(uiSource, /setAttribute\('title'/);

[
    presenceMarkup(commentSource, "data-user-presence-dot"),
    presenceMarkup(themeProfileSource, "data-user-presence-dot"),
    presenceMarkup(fallbackProfileSource, "data-user-presence-dot"),
    presenceMarkup(messagePageSource, "data-messages-thread-presence"),
    presenceMarkup(messagePageSource, "data-messages-active-presence")
].forEach((markup) => {
    assert.match(markup, /aria-label/);
    assert.match(markup, /data-presence-tooltip/);
    assert.doesNotMatch(markup, /\stitle\s*=/i);
});

assert.match(commentCss, /\.topic-comments \.ui-comment-presence-dot\s*\{[^}]*cursor:\s*default;/s);
assert.match(messageCss, /\.messages-presence-dot\s*\{[^}]*cursor:\s*default;/s);
assert.match(themeCss, /\.profile-page-shell \.profile-sidebar-meta-item--presence \.user-presence-dot\s*\{[^}]*cursor:\s*default;/s);
assert.match(themeBundleCss, /html\[data-public-theme="turkmod"\] \.profile-page-shell \.profile-sidebar-meta-item--presence \.user-presence-dot\s*\{[^}]*cursor:\s*default/s);

console.log("Public presence coordination verification passed.");
