const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const source = fs.readFileSync(path.resolve(__dirname,
    '../../view/adminhtml/web/js/form/element/category-create.js'), 'utf8');
const flush = () => new Promise((resolve) => setImmediate(resolve));
const observable = (value) => function (next) { if (arguments.length) value = next; return value; };

function fixture(ready, responses = []) {
    let component;
    let logins = 0;
    let sends = 0;
    let checks = 0;
    let timers = 0;
    vm.runInNewContext(source, {Promise, window: {setTimeout(callback) { timers++; callback(); }},
        define(_deps, init) {
            component = init({}, {extend: (definition) => definition}, (s) => s,
                {post: () => { sends++; return Promise.resolve(responses.shift()); }}, () => {}, () => {});
        }
    });
    const instance = Object.assign(Object.create(component), {
        urls: {create: '/create'}, categoryId: observable(12), categoryTreeId: observable(7),
        categoryCode: observable(''), writeReady: observable(true), busy: observable(false),
        message: observable(''), messageType: observable(''), visible: observable(true),
        readiness: {ensure: () => { checks++; return Promise.resolve(ready); }},
        auth: {ensure: () => { logins++; return Promise.resolve(true); }},
        source: {set() {}}
    });
    return {instance, counts: () => ({logins, sends, checks, timers})};
}

test('stale category form stops before login or creation when configuration was disabled', async () => {
    const f = fixture(false);
    f.instance.create();
    await flush();
    assert.deepEqual(f.counts(), {logins: 0, sends: 0, checks: 1, timers: 0});
    assert.equal(f.instance.busy(), false);
});

test('single category authorization failure never schedules another request', async () => {
    const f = fixture(true, [{success: false, failure_type: 'authorization',
        retry_after_seconds: 5, message: 'Write key rejected'}]);
    f.instance.create();
    await flush();
    assert.deepEqual(f.counts(), {logins: 1, sends: 1, checks: 1, timers: 0});
    assert.equal(f.instance.message(), 'Write key rejected');
    assert.equal(f.instance.busy(), false);
});

test('single category rate limiting retains safe retry and then completes', async () => {
    const f = fixture(true, [{success: false, failure_type: 'retryable', retry_after_seconds: 1},
        {success: true, code: 'chairs'}]);
    f.instance.create();
    await flush();
    assert.deepEqual(f.counts(), {logins: 1, sends: 2, checks: 1, timers: 1});
    assert.equal(f.instance.messageType(), 'success');
});
