import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../assets/js/google-analytics-init.js', import.meta.url), 'utf8');
const run = (attributes, existing = []) => {
    const window = { dataLayer: existing };
    const document = { currentScript: { getAttribute: key => attributes[key] ?? null } };
    vm.runInNewContext(source, { window, document });
    return window.dataLayer.map(entry => Array.from(entry));
};
assert.equal(run({}).length, 0);
const base = run({ 'data-ga-id': 'AW-753258530' });
assert.equal(base.length, 2);
assert.deepEqual(base[1], ['config', 'AW-753258530']);
const conversion = run({ 'data-ga-id': 'AW-753258530', 'data-ga-conversion': 'AW-753258530/test_label' }, [['existing']]);
assert.equal(conversion.length, 4);
assert.equal(conversion.filter(event => event[0] === 'event' && event[1] === 'conversion').length, 1);
assert.equal(conversion[3][2].send_to, 'AW-753258530/test_label');
console.log('PASS: initializer queues base configuration and one optional conversion');
