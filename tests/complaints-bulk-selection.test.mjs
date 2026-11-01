import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

function element() {
    const classes = new Set();
    return {
        checked: false, disabled: false, value: '', listeners: {}, attributes: {},
        classList: {toggle(name, active) { active ? classes.add(name) : classes.delete(name); }, contains(name) { return classes.has(name); }},
        addEventListener(name, callback) { this.listeners[name] = callback; },
        setAttribute(name, value) { this.attributes[name] = value; },
        focus() { this.focused = true; },
        reportValidity() { this.validityReported = true; },
    };
}
function fixture() {
    const ids = Object.fromEntries(['selectAllReports', 'selectedReportCount', 'bulkReportsForm', 'bulkReportOperation', 'bulkReportNote', 'bulkReportSubmit'].map(id => [id, element()]));
    const icon = {};
    const label = {};
    ids.bulkReportSubmit.querySelector = selector => selector === 'i' ? icon : label;
    const rows = [element(), element(), element()];
    rows.forEach(row => { row.card = element(); row.closest = () => row.card; });
    const window = {listeners: {}, addEventListener(name, callback) { this.listeners[name] = callback; }, adminPage: {register(_name, init) { init(); }}};
    const document = {
        getElementById: id => ids[id] ?? null,
        querySelectorAll: selector => selector === '.complaints-row-checkbox' ? rows : [],
        querySelector: () => null,
        addEventListener() {},
    };
    vm.runInNewContext(fs.readFileSync(new URL('../admin/assets/complaints-reports-page.js', import.meta.url), 'utf8'), {window, document});
    const change = (id, value) => { ids[id].value = value; ids[id].listeners.change(); };
    const check = (index, checked) => { rows[index].checked = checked; rows[index].listeners.change(); };
    const submit = () => { const event = {preventDefault() { this.prevented = true; }}; ids.bulkReportsForm.listeners.submit(event); return event; };
    return {ids, rows, window, icon, label, change, check, submit};
}

test('empty selection disables apply and rejects submission even with an operation', () => {
    const f = fixture();
    assert.equal(f.ids.bulkReportSubmit.disabled, true);
    f.change('bulkReportOperation', 'delete');
    assert.equal(f.ids.bulkReportSubmit.disabled, true);
    assert.equal(f.submit().prevented, true);
    assert.equal(f.rows[0].focused, true);
});

test('individual and select-all selections keep count, row state, and mixed state in sync', () => {
    const f = fixture();
    f.check(1, true);
    assert.equal(f.ids.selectedReportCount.textContent, '1');
    assert.equal(f.rows[1].card.classList.contains('is-selected'), true);
    assert.equal(f.ids.selectAllReports.indeterminate, true);
    f.ids.selectAllReports.checked = true;
    f.ids.selectAllReports.listeners.change();
    assert.equal(f.ids.selectedReportCount.textContent, '3');
    assert.equal(f.ids.selectAllReports.indeterminate, false);
    f.ids.selectAllReports.checked = false;
    f.ids.selectAllReports.listeners.change();
    assert.equal(f.ids.selectedReportCount.textContent, '0');
    assert.equal(f.rows.some(row => row.card.classList.contains('is-selected')), false);
});

test('delete disables the note and uses danger action; switching back preserves the draft note', () => {
    const f = fixture();
    f.check(0, true);
    assert.equal(f.ids.bulkReportSubmit.disabled, true);
    assert.equal(f.submit().prevented, true);
    assert.equal(f.ids.bulkReportOperation.validityReported, true);
    f.change('bulkReportOperation', 'resolved');
    f.ids.bulkReportNote.value = 'İncelendi';
    assert.equal(f.ids.bulkReportNote.disabled, false);
    assert.equal(f.ids.bulkReportSubmit.disabled, false);
    f.change('bulkReportOperation', 'delete');
    assert.equal(f.ids.bulkReportNote.disabled, true);
    assert.equal(f.ids.bulkReportSubmit.classList.contains('ui-admin-btn-danger'), true);
    assert.equal(f.icon.className, 'bi bi-trash3');
    assert.equal(f.label.textContent, 'Sil');
    assert.equal(f.ids.bulkReportSubmit.attributes['aria-label'], 'Seçilen 1 raporu sil');
    f.change('bulkReportOperation', 'reviewing');
    assert.equal(f.ids.bulkReportNote.value, 'İncelendi');
    assert.equal(f.ids.bulkReportNote.disabled, false);
    assert.equal(f.ids.bulkReportSubmit.classList.contains('ui-admin-btn-primary'), true);
    assert.equal(f.label.textContent, 'Uygula');
});

test('a valid submission prevents duplicates and browser back restores usable controls', () => {
    const f = fixture();
    f.check(0, true);
    f.change('bulkReportOperation', 'delete');
    assert.equal(f.submit().prevented, undefined);
    assert.equal(f.ids.bulkReportSubmit.disabled, true);
    assert.equal(f.label.textContent, 'İşleniyor');
    assert.equal(f.ids.bulkReportsForm.attributes['aria-busy'], 'true');
    assert.equal(f.submit().prevented, true);
    f.window.listeners.pageshow();
    assert.equal(f.ids.bulkReportSubmit.disabled, false);
    assert.equal(f.label.textContent, 'Sil');
    assert.equal(f.ids.bulkReportsForm.attributes['aria-busy'], 'false');
    f.check(0, false);
    assert.equal(f.ids.bulkReportSubmit.disabled, true);
    assert.equal(f.ids.bulkReportsForm.classList.contains('is-active'), false);
});
