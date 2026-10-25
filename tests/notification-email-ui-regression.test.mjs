import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

function fixture() {
    const listeners = {};
    const button = {
        name: 'action', value: 'send_email_test', tagName: 'BUTTON', type: 'submit',
        disabled: false, dataset: {}, innerHTML: 'Test E-Postası', attributes: {},
        setAttribute(name, value) { this.attributes[name] = value; },
        removeAttribute(name) { delete this.attributes[name]; },
    };
    const disabledButton = { ...button, disabled: true, dataset: {}, attributes: {} };
    const form = {
        dataset: {}, elements: [button, disabledButton],
        closest: () => true,
        addEventListener(name, handler) { listeners['form:' + name] = handler; },
        querySelector() { return this.elements.find(el => el.type === 'hidden' && el.name === 'action') ?? null; },
        querySelectorAll() { return [button, disabledButton]; },
        appendChild(element) { this.elements.push(element); element.remove = () => { this.elements.splice(this.elements.indexOf(element), 1); }; },
    };
    const document = {
        documentElement: { dataset: {} },
        createElement: () => ({ dataset: {} }),
        querySelectorAll(selector) {
            if (selector === 'form') return [form];
            if (selector === '[data-notification-busy-submit]') return form.elements.filter(el => el.dataset.notificationBusySubmit);
            if (selector === '[data-notification-submit-action]') return form.elements.filter(el => el.dataset.notificationSubmitAction);
            return [];
        },
    };
    const context = vm.createContext({ document, window: { adminPage: { register() {} }, addEventListener(name, handler) { listeners['window:' + name] = handler; } } });
    vm.runInContext(fs.readFileSync(new URL('../admin/assets/notifications-page.js', import.meta.url), 'utf8'), context);
    vm.runInContext('initNotificationSubmissionState()', context);
    return { form, button, disabledButton, listeners };
}

test('native form keeps the clicked action when submit buttons enter busy state', () => {
    const { form, button, listeners } = fixture();
    listeners['form:submit']({ defaultPrevented: false, submitter: button });
    assert.equal(button.disabled, true);
    assert.equal(form.elements.find(el => el.type === 'hidden')?.value, 'send_email_test');
});

test('browser back restores busy controls and removes the stale transient action', () => {
    const { form, button, disabledButton, listeners } = fixture();
    listeners['form:submit']({ defaultPrevented: false, submitter: button });
    listeners['window:pageshow']?.({ persisted: true });
    assert.equal(button.disabled, false);
    assert.equal(button.innerHTML, 'Test E-Postası');
    assert.equal(button.attributes['aria-busy'], undefined);
    assert.equal(disabledButton.disabled, true);
    assert.equal(form.elements.filter(el => el.type === 'hidden').length, 0);
});

test('validation cancellation does not disable the test button or add an action', () => {
    const { form, button, listeners } = fixture();
    listeners['form:submit']({ defaultPrevented: true, submitter: button });
    assert.equal(button.disabled, false);
    assert.equal(form.elements.length, 2);
});
