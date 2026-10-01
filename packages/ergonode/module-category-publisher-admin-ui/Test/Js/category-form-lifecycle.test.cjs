'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const source = fs.readFileSync(path.resolve(__dirname,
    '../../view/adminhtml/web/js/form/element/category-create.js'), 'utf8');
const flush = () => new Promise(resolve => setImmediate(resolve));
const observable = value => function (next) { if (arguments.length) value = next; return value; };
const deferred = () => {
    let resolve;
    const promise = new Promise(yes => { resolve = yes; });
    return {resolve, promise};
};

function fixture() {
    let component;
    let clock = 0;
    let sequence = 0;
    let destroyed = 0;
    const requests = [], writes = [], timers = new Map();
    vm.runInNewContext(source, {
        Promise, Date: {now: () => clock},
        window: {
            setTimeout(callback, delay) { const id = ++sequence; timers.set(id, {callback, delay}); return id; },
            clearTimeout(id) { timers.delete(id); }
        },
        define(_deps, init) {
            component = init({}, {extend: definition => definition}, text => text, {
                post(_url, _config, payload) {
                    const result = deferred();
                    requests.push({...result, payload});
                    return result.promise;
                }
            }, null, null);
        }
    });
    const instance = Object.assign(Object.create(component), {
        urls: {create: '/create'}, categoryId: observable(101), categoryTreeId: observable(7),
        categoryCode: observable(''), writeReady: observable(true), busy: observable(false),
        message: observable(''), messageType: observable(''), visible: observable(true),
        readiness: {ensure: () => Promise.resolve(true)},
        auth: {ensure: () => Promise.resolve(true), destroy: () => destroyed++},
        source: {set: (name, value) => writes.push([name, value])}, _super() { return this; }
    });
    instance.syncContext();
    return {
        instance, requests, writes, timers, destroyed: () => destroyed,
        change(id, treeId = 7) { instance.categoryId(id); instance.categoryTreeId(treeId); instance.syncContext(); },
        respond(response) { requests.at(-1).resolve(response); },
        tick() {
            const [id, timer] = timers.entries().next().value;
            timers.delete(id); clock += timer.delay;
            timer.callback();
        },
        advance(seconds) { clock += seconds * 1000; }
    };
}

const retry = seconds => ({success: false, failure_type: 'retryable', retry_after_seconds: seconds, message: 'Throttled'});

test('form stops after five attempts, preserves Retry-After and releases Publish for manual resume', async () => {
    const f = fixture();
    const done = f.instance.create();
    await flush();
    for (let attempt = 1; attempt <= 5; attempt++) {
        assert.equal(f.requests.length, attempt);
        f.respond(retry(30));
        await flush();
        if (attempt < 5) {
            assert.equal([...f.timers.values()][0].delay, 30000);
            f.tick();
            await flush();
        }
    }
    await done;
    assert.equal(f.timers.size, 0);
    assert.equal(f.instance.busy(), false);
    assert.match(f.instance.message(), /Throttled.*Publication progress was retained/);
    assert.equal(f.writes.length, 0);
    const resumed = f.instance.create();
    await flush();
    f.respond({success: true, code: '0'});
    await resumed;
    assert.deepEqual(f.writes, [['data.ergonode_category_code', '0']]);
});

for (const [delay, elapsed] of [[121, 0], [30, 100]]) {
    test(`form time budget does not shorten Retry-After ${delay}s after ${elapsed}s`, async () => {
        const f = fixture();
        const done = f.instance.create();
        await flush();
        f.advance(elapsed);
        f.respond(retry(delay));
        await done;
        assert.equal(f.requests.length, 1);
        assert.equal(f.timers.size, 0);
        assert.equal(f.instance.busy(), false);
    });
}

for (const context of ['category', 'tree', 'destroy']) {
    test(`${context} change cancels retry and settles the old operation`, async () => {
        const f = fixture();
        const done = f.instance.create();
        await flush();
        f.respond(retry(2));
        await flush();
        assert.equal(f.timers.size, 1);
        if (context === 'destroy') f.instance.destroy();
        else if (context === 'tree') f.change(101, 8);
        else f.change(202);
        await done;
        assert.equal(f.timers.size, 0);
        assert.equal(f.requests.length, 1);
        assert.equal(f.instance.busy(), false);
        assert.equal(f.writes.length, 0);
        assert.equal(f.instance.message(), '');
        assert.equal(f.destroyed(), context === 'destroy' ? 1 : 0);
    });
}

test('late response cannot update another category or unlock its newer operation', async () => {
    const f = fixture();
    const first = f.instance.create();
    await flush();
    f.change(202);
    const second = f.instance.create();
    await flush();
    assert.deepEqual(f.requests.map(request => request.payload.category_id), [101, 202]);
    f.requests[0].resolve({success: true, code: 'old'});
    await first;
    assert.deepEqual(f.writes, []);
    assert.equal(f.instance.busy(), true);
    f.requests[1].resolve({success: true, code: 'new'});
    await second;
    assert.deepEqual(f.writes, [['data.ergonode_category_code', 'new']]);
    assert.equal(f.instance.busy(), false);
});

test('disposing an in-flight request ignores its result and destroys auth', async () => {
    const f = fixture();
    const done = f.instance.create();
    await flush();
    f.instance.destroy();
    f.respond({success: true, code: 'late'});
    await done;
    assert.equal(f.writes.length, 0);
    assert.equal(f.destroyed(), 1);
    f.instance.create();
    await flush();
    assert.equal(f.requests.length, 1);
});

test('a context change while login is pending cannot publish the newly selected category', async () => {
    const f = fixture();
    const login = deferred();
    f.instance.auth.ensure = () => login.promise;
    const done = f.instance.create();
    await flush();
    f.change(202);
    login.resolve(true);
    await done;
    assert.equal(f.requests.length, 0);
    assert.equal(f.instance.busy(), false);
});

test('rejected readiness releases busy and reports the error', async () => {
    const f = fixture();
    f.instance.readiness.ensure = () => Promise.reject(new Error('Readiness failed'));
    await f.instance.create();
    assert.equal(f.instance.message(), 'Readiness failed');
    assert.equal(f.instance.busy(), false);
    assert.equal(f.requests.length, 0);
});
