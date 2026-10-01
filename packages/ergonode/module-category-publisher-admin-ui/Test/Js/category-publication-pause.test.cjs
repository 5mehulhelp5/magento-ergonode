'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const source = fs.readFileSync(path.resolve(
    __dirname, '../../view/adminhtml/web/js/ergonode-category-publisher-mapping.js'
), 'utf8');

function deferred() {
    let resolve;
    let reject;
    const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
    return {promise, resolve, reject};
}

const flush = () => new Promise((resolve) => setImmediate(resolve));
const category = (code, parentCode = null) => ({
    code, label: code, parent_code: parentCode, extension_data: {to_ergonode: {pending_create: true}}
});

function fixture(categories = [category('a'), category('b'), category('c')], batchSize = 1) {
    const sends = [];
    const prepared = [];
    const published = [];
    const applied = [];
    const saves = [];
    const waits = [];
    const states = [];
    const busy = [];
    const completed = [];
    const errors = [];
    let controls;
    let finalizations = 0;
    let dirty = 0;
    const names = [
        'assertMounted', 'validateLayoutBeforePublication', 'boundedRetry', 'runPublishProcess', 'processCategoryQueue', 'requiresCategoryApi',
        'sortPublishItems', 'categoryDepth', 'hasBlockedAncestor', 'blockedResult'
    ];
    const functions = names.map((name) => {
        const match = source.match(new RegExp('        function ' + name + '\\([^]*?\\n        \\}'));
        assert.ok(match, `Missing production function ${name}`);
        return match[0];
    }).join('\n');
    const context = vm.createContext({
        Promise,
        $t: (value) => value,
        config: {category_batch_size: batchSize},
        destroyed: false, cancelPause: null,
        publishInProgress: true,
        api: {
            validateLayout: () => Promise.resolve({success: true}),
            getCategories: () => structuredClone(categories),
            markCategoryRemotePrepared: (code, id) => prepared.push([code, id]),
            markCategoryPublished: (code) => published.push(code),
            render() {}, markDirty: () => dirty++
        },
        progress: {
            open: (total, callbacks) => { controls = callbacks; },
            setPauseState: (state) => states.push(state),
            showBatch() {},
            applyBatch: (results) => applied.push(...results),
            wait: (seconds) => {
                const wait = deferred();
                waits.push({seconds, ...wait});
                return wait.promise;
            },
            finalizing: () => finalizations++, complete: (partial) => completed.push(partial),
            fail: (message) => errors.push(message)
        },
        sendCategoryBatch: (items) => {
            const response = deferred();
            sends.push({items, ...response});
            return response.promise;
        },
        saveLayoutWithRetry: (excludedCodes, wait, message, beforeRender) => {
            saves.push(Array.from(excludedCodes));
            if (beforeRender) beforeRender();
            return Promise.resolve();
        },
        cancelPendingCategory() {},
        setProcessBusy: (button, value) => busy.push(value)
    });
    vm.runInContext(functions, context);
    return {
        sends, prepared, published, applied, saves, waits, states, busy, completed, errors,
        dispose: () => { context.destroyed = true; context.cancelPause?.(); },
        start: () => vm.runInContext('runPublishProcess({})', context),
        pause: () => controls.pause(), resume: () => controls.resume(),
        result(index, overrides = {}) {
            sends[index].resolve({success: true, items: sends[index].items.map((item) => ({
                code: item.code, status: 'synchronized', remote_id: `id-${item.code}`, ...overrides
            }))});
        },
        finalizations: () => finalizations,
        dirty: () => dirty,
        inProgress: () => context.publishInProgress
    };
}

test('in-flight batch completes before pause; repeated resume never replays completed batches', async () => {
    const f = fixture();
    const done = f.start();
    await flush();
    f.pause();
    assert.equal(f.sends.length, 1);
    assert.equal(f.states.length, 0);
    f.result(0);
    await flush();
    assert.deepEqual(f.states, ['paused']);
    assert.deepEqual(f.prepared, [['a', 'id-a']]);
    assert.equal(f.sends.length, 1);
    assert.equal(f.inProgress(), true);
    assert.deepEqual(f.busy, []);
    f.resume();
    f.resume();
    await flush();
    assert.equal(f.sends.length, 2);
    f.pause();
    f.result(1);
    await flush();
    assert.equal(f.sends.length, 2);
    f.resume();
    await flush();
    f.result(2);
    await done;
    assert.deepEqual(f.sends.map((send) => send.items[0].code), ['a', 'b', 'c']);
    assert.deepEqual(f.published, ['a', 'b', 'c']);
    assert.deepEqual(f.saves, [[]]);
    assert.deepEqual(f.completed, [false]);
    assert.deepEqual(f.busy, [false]);
});

test('pause on the last batch also defers tree and mapping finalization', async () => {
    const f = fixture([category('a')]);
    const done = f.start();
    await flush();
    f.pause();
    f.result(0);
    await flush();
    assert.equal(f.finalizations(), 0);
    assert.deepEqual(f.saves, []);
    assert.deepEqual(f.published, []);
    f.resume();
    await done;
    assert.equal(f.finalizations(), 1);
    assert.deepEqual(f.published, ['a']);
});

test('pause during rate limiting waits for Retry-After and resume retries only the pending batch', async () => {
    const f = fixture([category('a')]);
    const done = f.start();
    await flush();
    f.sends[0].resolve({success: false, failure_type: 'retryable', retry_after_seconds: 30});
    await flush();
    f.pause();
    assert.equal(f.waits[0].seconds, 30);
    assert.equal(f.states.length, 0);
    assert.equal(f.sends.length, 1);
    f.waits[0].resolve();
    await flush();
    assert.deepEqual(f.states, ['paused']);
    assert.equal(f.sends.length, 1);
    f.resume();
    await flush();
    assert.equal(f.sends[1].items[0].code, 'a');
    f.result(1);
    await done;
    assert.equal(f.applied.length, 1);
});

test('failures and blocked descendants survive pause and resume', async () => {
    const f = fixture([category('a'), category('b', 'a'), category('c')]);
    const done = f.start();
    await flush();
    f.pause();
    f.result(0, {status: 'failed', remote_id: null});
    await flush();
    f.resume();
    await flush();
    assert.equal(f.sends[1].items[0].code, 'c');
    f.result(1);
    await done;
    assert.deepEqual(f.saves, [['a', 'b']]);
    assert.deepEqual(f.published, ['c']);
    assert.deepEqual(f.completed, [true]);
    assert.equal(f.dirty(), 1);
    assert.equal(f.applied.find((item) => item.code === 'b').status, 'blocked');
});

test('a transport failure while pausing ends the process and releases Save', async () => {
    const f = fixture();
    const done = f.start();
    await flush();
    f.pause();
    f.sends[0].reject(new Error('Transport failed'));
    await done;
    assert.deepEqual(f.errors, ['Transport failed']);
    assert.equal(f.inProgress(), false);
    assert.deepEqual(f.busy, [false]);
    assert.deepEqual(f.saves, []);
});

test('authorization failure stops a 49-category batch without retrying or saving mappings', async () => {
    const f = fixture(Array.from({length: 49}, (_, i) => category(`category-${i}`)), 49);
    const done = f.start();
    await flush();
    assert.equal(f.sends[0].items.length, 49);
    f.sends[0].resolve({success: false, failure_type: 'authorization',
        message: 'Ergonode updates are disabled.', retry_after_seconds: 5});
    await done;
    assert.equal(f.sends.length, 1);
    assert.deepEqual(f.waits, []);
    assert.deepEqual(f.saves, []);
    assert.deepEqual(f.errors, ['Ergonode updates are disabled.']);
    assert.equal(f.inProgress(), false);
});


test('disposing a paused publication releases its promise without another request or final save', async () => {
    const f = fixture();
    const done = f.start();
    await flush();
    f.pause();
    f.result(0);
    await flush();
    f.dispose();
    await done;
    assert.equal(f.sends.length, 1);
    assert.deepEqual(f.saves, []);
    assert.match(f.errors[0], /disposed/);
});

test('disposing during an in-flight batch does not dispatch the next batch or finalize', async () => {
    const f = fixture();
    const done = f.start();
    await flush();
    f.dispose();
    f.result(0);
    await done;
    assert.equal(f.sends.length, 1);
    assert.deepEqual(f.saves, []);
    assert.deepEqual(f.prepared, []);
});

for (const parentCode of ['constructor', '__proto__']) {
    test(`failed ${parentCode} parent blocks its child and preserves parent-first order`, async () => {
        const f = fixture([category('child', parentCode), category(parentCode)]);
        const done = f.start();
        await flush();
        assert.equal(f.sends[0].items[0].code, parentCode);
        f.result(0, {status: 'failed', remote_id: null});
        await done;
        assert.equal(f.sends.length, 1);
        assert.deepEqual(f.saves, [[parentCode, 'child']]);
        assert.deepEqual(f.applied.map(item => item.status), ['failed', 'blocked']);
    });

    test(`missing response for ${parentCode} cannot become an inherited success`, async () => {
        const f = fixture([category(parentCode)]);
        const done = f.start();
        await flush();
        f.sends[0].resolve({success: true, items: []});
        await done;
        assert.deepEqual(f.saves, [[parentCode]]);
        assert.equal(f.applied[0].status, 'failed');
    });
}
