import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

async function fixture(userId) {
    const documentListeners = {};
    const windowListeners = {};
    const timers = new Map();
    const intervals = new Map();
    const requests = [];
    const sockets = [];
    const storage = new Map();
    const broadcasts = [];
    let sequence = 0;
    let refreshes = 0;
    class Socket {
        static CONNECTING = 0;
        static OPEN = 1;
        static CLOSED = 3;
        readyState = 0;
        sent = [];
        constructor(url) { this.url = url; sockets.push(this); }
        send(value) { this.sent.push(JSON.parse(value)); }
        close() { this.readyState = Socket.CLOSED; this.onclose?.(); }
    }
    class Channel {
        postMessage(value) { broadcasts.push(value); }
        close() {}
    }
    const document = {
        hidden: false,
        hasFocus: () => true,
        body: {},
        querySelector(selector) {
            if (selector === '[data-public-topbar-user-id]') {
                return {getAttribute: name => name === 'data-public-topbar-user-id' ? String(userId) : 'ws://localhost/realtime'};
            }
            if (selector === 'meta[name="app-base-uri"]') { return {getAttribute: () => '/turkmod'}; }
            return null;
        },
        querySelectorAll: () => [],
        getElementById: () => null,
        addEventListener(name, handler) { documentListeners[name] = handler; },
    };
    const window = {
        location: {origin: 'http://localhost', protocol: 'http:', reload() {}},
        publicTopbar: {refreshMessages() { refreshes++; }, refreshNotifications() { refreshes++; }},
        WebSocket: Socket,
        BroadcastChannel: Channel,
        localStorage: {
            getItem: key => storage.get(key) ?? null,
            setItem: (key, value) => storage.set(key, value),
            removeItem: key => storage.delete(key),
        },
        publicFetchJson(url) {
            requests.push(url);
            return Promise.resolve(url.includes('auth-state')
                ? {success: true, auth: {logged_in: true}}
                : {success: true, users: {12: {user_id: 12, visible: true, is_online: true}}});
        },
        setTimeout(callback, delay) { const id = ++sequence; timers.set(id, {callback, delay}); return id; },
        clearTimeout(id) { timers.delete(id); },
        setInterval(callback, delay) { const id = ++sequence; intervals.set(id, {callback, delay}); return id; },
        clearInterval(id) { intervals.delete(id); },
        addEventListener(name, handler) { windowListeners[name] = handler; },
    };
    const context = vm.createContext({document, window, WebSocket: Socket, URL, console});
    vm.runInContext(fs.readFileSync(new URL('../assets/js/public-topbar-realtime.js', import.meta.url), 'utf8'), context);
    documentListeners.DOMContentLoaded();
    const settle = async () => { for (let i = 0; i < 12; i++) { await Promise.resolve(); } };
    await settle();
    const runTimers = async maxDelay => {
        const pending = [...timers].filter(([, timer]) => timer.delay <= maxDelay);
        for (const [id, timer] of pending) { if (timers.delete(id)) timer.callback(); }
        await settle();
    };
    return {window, timers, intervals, requests, sockets, storage, broadcasts, windowListeners, runTimers, settle, refreshes: () => refreshes};
}

test('authenticated presence uses WebSocket snapshots and never switches to HTTP after disconnection', async () => {
    const f = await fixture(5);
    await f.runTimers(25);
    const states = [];
    f.window.publicTopbarRealtime.watchPresence([12], (_id, state) => states.push(state));
    const socket = f.sockets[0];
    assert.ok(socket);
    socket.readyState = 1;
    socket.onopen();
    assert.equal(socket.sent.find(message => message.type === 'presence_subscribe')?.user_ids[0], 12);
    socket.onmessage({data: JSON.stringify({type: 'presence_snapshot', server_instance_id: 'test', sequence: 1, users: {12: {user_id: 12, visible: true, is_online: true}}})});
    assert.equal(states[0]?.is_online, true);
    const initialRefreshes = f.refreshes();
    socket.close();
    await f.runTimers(5000);
    assert.equal(f.requests.some(url => url.includes('user-presence')), false);
    assert.equal(f.refreshes(), initialRefreshes);
    assert.equal([...f.intervals.values()].some(timer => timer.delay === 5000), false);
    assert.equal([...f.storage.keys()].some(key => key.startsWith('public-realtime-event:')), false);
});

test('guest presence keeps its explicitly selected HTTP transport', async () => {
    const f = await fixture(0);
    const states = [];
    f.window.publicTopbarRealtime.watchPresence([12], (_id, state) => states.push(state));
    await f.runTimers(25);
    await f.runTimers(25);
    assert.equal(f.sockets.length, 0);
    assert.ok(f.requests.some(url => url.includes('user-presence.php?ids=12')));
    assert.equal(states[0]?.is_online, true);
});

test('ownership heartbeats do not create parallel sockets while the first connection is pending', async () => {
    const f = await fixture(5);
    await f.runTimers(25);
    for (const interval of f.intervals.values()) { interval.callback(); }
    await f.runTimers(25);
    assert.equal(f.sockets.length, 1);
});

test('browser back restores the channel and connection after pagehide cleanup', async () => {
    const f = await fixture(5);
    await f.runTimers(25);
    f.windowListeners.pagehide();
    assert.equal(f.intervals.size, 0);
    f.windowListeners.pageshow({persisted: true});
    await f.settle();
    await f.runTimers(25);
    assert.equal(f.sockets.length, 2);
    assert.equal([...f.intervals.values()].filter(timer => timer.delay === 10000).length, 1);
});
