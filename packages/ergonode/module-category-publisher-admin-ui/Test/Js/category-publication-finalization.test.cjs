'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const categoryAdminUiRoot = path.resolve(__dirname, '../../../module-category-admin-ui');
const deferred = require(path.join(categoryAdminUiRoot, 'Test/Js/helpers/deferred.cjs'));
const publisher = fs.readFileSync(path.resolve(__dirname,
    '../../view/adminhtml/web/js/ergonode-category-publisher-mapping.js'), 'utf8');
const consumer = fs.readFileSync(path.join(categoryAdminUiRoot,
    'view/adminhtml/web/js/category-tree-mapping.js'), 'utf8');

function productionFunction(source, name) {
    const match = source.match(new RegExp('        function ' + name + '\\([^]*?\\n        \\}'));
    assert.ok(match, `Missing production function ${name}`);
    return match[0];
}

const flush = () => new Promise((resolve) => setImmediate(resolve));

function fixture(count = 50, excludedCodes = []) {
    const categories = Array.from({length: count}, (_, index) => ({
        code: `code-${index}`, parent_code: null, sort_order: index,
        extension_data: {to_ergonode: {pending_create: true, remote_prepared: true}}
    }));
    const requests = [];
    const renders = [];
    const waits = [];
    const failures = [];
    const completed = [];
    const events = [];
    const context = vm.createContext({
        pendingSave: null, operationState: {start: () => true, finish() {}},
        Promise, categories, dirty: true, persistedMappings: {}, categoryTreeId: 7,
        destroyed: false, cancelPause: null, assertMounted() {},
        publishInProgress: true, $saveButton: {}, config: {urls: {save: '/save'}},
        $t: (text) => text, $: {Deferred: deferred, extend: (deep, target, value) => structuredClone(value)},
        $root: {trigger: (name) => events.push([name, renders.length])},
        saveRecoveryHandler: null, saveWithRecovery: (send) => send(),
        collectVisibility: () => [], collectPersistedMappings: (items) => structuredClone(items),
        renderAll: () => renders.push(structuredClone(categories)),
        setBusy() {}, updateButtons() {}, showMessage() {}, setProcessBusy() {},
        sortPublishItems: (items) => items, requiresCategoryApi: () => true,
        processCategoryQueue: () => Promise.resolve({
            preparedCodes: categories.map((category) => category.code), excludedCodes
        }),
        progress: {
            open() {}, finalizing() {}, complete: (partial) => completed.push(partial),
            fail: (message) => failures.push(message),
            wait: (seconds) => { waits.push(seconds); return Promise.resolve(); }
        },
        post: (url, payload) => {
            const request = deferred();
            requests.push({payload: JSON.parse(payload.payload), ...request});
            return request;
        }
    });
    vm.runInContext([
        productionFunction(consumer, 'saveLayout'),
        productionFunction(consumer, 'markCategoryPublished'),
        productionFunction(publisher, 'boundedRetry'),
        productionFunction(publisher, 'validateLayoutBeforePublication'),
        productionFunction(publisher, 'saveLayoutWithRetry'),
        productionFunction(publisher, 'runPublishProcess'),
        'var api = {getCategories: () => categories, saveLayout, markCategoryPublished,',
        'validateLayout: () => Promise.resolve({success: true}),',
        'render: renderAll, markDirty: () => { dirty = true; }};'
    ].join('\n'), context);
    return {
        categories, requests, renders, waits, failures, completed, events, context,
        start: () => vm.runInContext('runPublishProcess({})', context)
    };
}

test('50 published categories render once with completed flags before the saved event', async () => {
    const f = fixture();
    const done = f.start();
    await flush();
    assert.equal(f.requests.length, 1);
    f.requests[0].resolve({success: true});
    await done;
    assert.equal(f.renders.length, 1);
    assert.equal(f.renders[0].length, 50);
    assert.ok(f.renders[0].every((category) => !category.extension_data.to_ergonode.pending_create));
    assert.ok(f.renders[0].every((category) => !category.extension_data.to_ergonode.remote_prepared));
    assert.deepEqual(f.events, [['ergonode:category-mapping:saved', 1]]);
    assert.equal(f.context.dirty, false);
    assert.deepEqual(f.completed, [false]);
});

test('partial publication leaves excluded categories pending and the workspace dirty', async () => {
    const f = fixture(2, ['code-1']);
    const done = f.start();
    await flush();
    assert.deepEqual(f.requests[0].payload.categories.map((item) => item.code), ['code-0']);
    f.requests[0].resolve({success: true});
    await done;
    assert.equal(f.renders.length, 1);
    assert.equal(f.renders[0][0].extension_data.to_ergonode.pending_create, undefined);
    assert.equal(f.renders[0][1].extension_data.to_ergonode.pending_create, true);
    assert.equal(f.context.dirty, true);
    assert.deepEqual(f.completed, [true]);
});

test('a rate limit does not mark or render categories before the successful retry', async () => {
    const f = fixture();
    const done = f.start();
    await flush();
    f.requests[0].resolve({success: false, failure_type: 'retryable', retry_after_seconds: 2});
    await flush();
    assert.equal(f.renders.length, 0);
    assert.ok(f.categories.every((category) => category.extension_data.to_ergonode.pending_create));
    assert.deepEqual(f.waits, [2]);
    assert.equal(f.requests.length, 2);
    f.requests[1].resolve({success: true});
    await done;
    assert.equal(f.renders.length, 1);
});

test('authorization and transport failures retain pending flags without a final render', async () => {
    for (const transport of [false, true]) {
        const f = fixture();
        const done = f.start();
        await flush();
        if (transport) f.requests[0].reject();
        else f.requests[0].resolve({success: false, failure_type: 'authorization', message: 'Denied'});
        await done;
        assert.equal(f.renders.length, 0);
        assert.ok(f.categories.every((category) => category.extension_data.to_ergonode.pending_create));
        assert.equal(f.context.dirty, true);
        assert.equal(f.failures.length, 1);
        assert.deepEqual(f.waits, []);
    }
});

test('ordinary layout saving still renders without an extension callback', () => {
    const f = fixture(1);
    vm.runInContext('saveLayout()', f.context);
    f.requests[0].resolve({success: true});
    assert.equal(f.renders.length, 1);
    assert.equal(f.context.dirty, false);
});

test('individual publication also completes its flags before the single save render', async () => {
    const f = fixture(1);
    Object.assign(f.context, {
        element: {querySelector: () => null},
        addPendingCategory: () => f.categories[0],
        updateBulkControls() {}, setIndividualButtonBusy() {}, notify() {}, waitForRetry() {},
        sendSingleCategoryWithRetry: () => Promise.resolve({status: 'synchronized', remote_id: 'remote-id'})
    });
    f.context.api.markCategoryRemotePrepared = () => true;
    vm.runInContext(productionFunction(publisher, 'createCategoryImmediately'), f.context);
    vm.runInContext('createCategoryImmediately({}, 123)', f.context);
    await flush();
    f.requests[0].resolve({success: true});
    await flush();
    assert.equal(f.renders.length, 1);
    assert.equal(f.renders[0][0].extension_data.to_ergonode.pending_create, undefined);
    assert.equal(f.context.publishInProgress, false);
});


test('persistent REST invisibility stops after five attempts and preserves drafts for manual save', async () => {
    const f = fixture(1);
    const done = f.start();
    for (let attempt = 0; attempt < 5; attempt++) {
        await flush();
        assert.equal(f.requests.length, attempt + 1);
        f.requests[attempt].resolve({success: false, failure_type: 'retryable', retry_after_seconds: 2});
    }
    await done;
    assert.equal(f.requests.length, 5);
    assert.equal(f.waits.length, 4);
    assert.equal(f.failures.length, 1);
    assert.match(f.failures[0], /Zachowano postęp/);
    assert.equal(f.categories[0].extension_data.to_ergonode.pending_create, true);
    assert.equal(f.context.dirty, true);
    assert.equal(f.context.publishInProgress, false);
});

test('Retry-After exceeding the automatic time budget stops without shortening the server delay', async () => {
    const f = fixture(1);
    const done = f.start();
    await flush();
    f.requests[0].resolve({success: false, failure_type: 'retryable', retry_after_seconds: 121});
    await done;
    assert.equal(f.requests.length, 1);
    assert.deepEqual(f.waits, []);
    assert.equal(f.failures.length, 1);
});


test('individual creation retains an unresolved draft when the retry limit is reached', async () => {
    const f = fixture(1);
    const messages = [];
    let removed = 0;
    const error = Object.assign(new Error('Retry limit'), {retainDraft: true});
    Object.assign(f.context, {
        element: {querySelector: () => null}, addPendingCategory: () => f.categories[0],
        updateBulkControls() {}, setIndividualButtonBusy() {}, notify: (...args) => messages.push(args),
        sendSingleCategoryWithRetry: () => Promise.reject(error)
    });
    f.context.api.removeCategory = () => removed++;
    vm.runInContext(productionFunction(publisher, 'createCategoryImmediately'), f.context);
    vm.runInContext('createCategoryImmediately({}, 123)', f.context);
    await flush();
    assert.equal(removed, 0);
    assert.equal(f.context.publishInProgress, false);
    assert.deepEqual(messages, [['error', 'Retry limit']]);
});


test('invalid complete draft is rejected before any category publication or tree save', async () => {
    const f = fixture(51);
    let batches = 0;
    f.context.processCategoryQueue = () => { batches++; return Promise.resolve({}); };
    f.context.api.validateLayout = () => Promise.resolve({success: false, message: 'Duplicate Magento ID'});
    await f.start();
    assert.equal(batches, 0);
    assert.equal(f.requests.length, 0);
    assert.deepEqual(f.failures, ['Duplicate Magento ID']);
    assert.equal(f.context.publishInProgress, false);
});

test('failed preflight transport releases Save without sending publication requests', async () => {
    const f = fixture(1);
    let batches = 0;
    f.context.processCategoryQueue = () => { batches++; return Promise.resolve({}); };
    f.context.api.validateLayout = () => Promise.reject(new Error('Validation unavailable'));
    await f.start();
    assert.equal(batches, 0);
    assert.equal(f.requests.length, 0);
    assert.deepEqual(f.failures, ['Validation unavailable']);
    assert.equal(f.context.publishInProgress, false);
});

for (const scenario of ['success', 'partial', 'failure']) {
    test(`prototype and zero category codes survive publication finalization: ${scenario}`, async () => {
        const codes = ['constructor', '__proto__', '0', 'regular'];
        const excluded = scenario === 'partial' ? ['__proto__'] : [];
        const f = fixture(codes.length, excluded);
        codes.forEach((code, index) => { f.categories[index].code = code; });
        const done = f.start();
        await flush();
        assert.deepEqual(f.requests[0].payload.categories.map(item => item.code),
            codes.filter(code => !excluded.includes(code)));
        f.requests[0].resolve({success: scenario !== 'failure', message: 'Save result'});
        await done;
        for (const category of f.categories) {
            assert.equal(!!category.extension_data.to_ergonode.pending_create,
                scenario === 'failure' || excluded.includes(category.code));
        }
        assert.equal(f.context.dirty, scenario !== 'success');
    });
}
