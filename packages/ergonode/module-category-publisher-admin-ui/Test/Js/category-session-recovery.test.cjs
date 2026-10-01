'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const categoryAdminUiRoot = path.resolve(__dirname, '../../../module-category-admin-ui');
const deferred = require(path.join(categoryAdminUiRoot, 'Test/Js/helpers/deferred.cjs'));
const moduleRoot = path.resolve(__dirname, '../..');
const consumer = fs.readFileSync(path.join(categoryAdminUiRoot,
    'view/adminhtml/web/js/category-tree-mapping.js'), 'utf8');
const publisher = fs.readFileSync(path.join(moduleRoot,
    'view/adminhtml/web/js/ergonode-category-publisher-mapping.js'), 'utf8');
const recovery = fs.readFileSync(path.join(categoryAdminUiRoot,
    'view/adminhtml/web/js/save-recovery.js'), 'utf8');
const flush = () => new Promise((resolve) => setImmediate(resolve));
function productionFunction(source, name) {
    const match = source.match(new RegExp('        function ' + name + '\\([^]*?\\n        \\}'));
    assert.ok(match, `Missing production function ${name}`);
    return match[0];
}

function fixture() {
    const requests = [];
    const renders = [];
    const busy = [];
    const login = [];
    const $ = {Deferred: deferred, extend: (deep, target, value) => structuredClone(value)};
    let saveWithRecovery;
    vm.runInNewContext(recovery, {Promise, define: (deps, factory) => { saveWithRecovery = factory($); }});
    const categories = [{code: 'chairs', parent_code: null, sort_order: 40,
        magento_category_id: null, ergonode_category_id: 'remote-chairs', extension_data: {to_ergonode: {}}}];
    const context = vm.createContext({
        pendingSave: null, operationState: {start: () => true, finish() {}},
        Promise, $, categories, categoryTreeId: 7, dirty: true, persistedMappings: {chairs: 22},
        config: {urls: {save: '/save'}}, $saveButton: {}, $t: (message) => message,
        saveWithRecovery, saveRecoveryHandler: null,
        api: {getCategories: () => categories},
        auth: {ensure: () => new Promise((resolve, reject) => login.push({resolve, reject}))},
        collectVisibility: () => [], collectPersistedMappings: () => ({}),
        renderAll: () => renders.push(structuredClone(categories)),
        updateButtons() {}, showMessage() {}, $root: {trigger() {}},
        setBusy: (button, value) => busy.push(value),
        post: (url, data) => {
            const request = deferred();
            requests.push({request, data: structuredClone(data)});
            return request.promise();
        }
    });
    vm.runInContext([
        productionFunction(consumer, 'saveLayout'), productionFunction(publisher, 'recoverSave'),
        productionFunction(publisher, 'requiresManualApi'), productionFunction(publisher, 'requiresCategoryApi'),
        'saveRecoveryHandler = recoverSave;'
    ].join('\n'), context);
    let response;
    const start = () => vm.runInContext('saveLayout()', context).done((value) => { response = value; });
    return {context, requests, renders, busy, login, start, response: () => response};
}

const expired = {success: false, failure_type: 'authentication_required', message: 'Session expired'};

for (const identity of [undefined, null, '', 'remote-chairs']) {
    for (const magentoId of [null, 22]) {
        test(`mapping-only save skips publication with identity ${String(identity)} and Magento ID ${magentoId}`, async () => {
            const f = fixture();
            f.context.categories[0].ergonode_category_id = identity;
            f.context.categories[0].magento_category_id = magentoId;
            assert.equal(vm.runInContext('requiresManualApi()', f.context), false);
            f.start();
            f.requests[0].request.resolve({success: true});
            await flush();
            assert.equal(f.login.length, 0);
            assert.equal(JSON.parse(f.requests[0].data.payload).categories[0].magento_category_id, magentoId);
            assert.equal(f.context.dirty, false);
            assert.equal(f.renders.length, 1);
        });
    }
}

test('only explicit creation drafts enter the publication queue, including prepared drafts awaiting final save', () => {
    const f = fixture();
    const existing = {code: 'existing', ergonode_category_id: null};
    const draft = {code: 'draft', extension_data: {to_ergonode: {pending_create: true}}};
    const prepared = {code: 'prepared', ergonode_category_id: 'remote-prepared',
        extension_data: {to_ergonode: {pending_create: true, remote_prepared: true}}};
    f.context.categories.splice(0, 1, existing, draft, prepared);
    assert.equal(vm.runInContext('requiresManualApi()', f.context), true);
    assert.deepEqual(Array.from(vm.runInContext('categories.filter(requiresCategoryApi)', f.context), item => item.code),
        ['draft', 'prepared']);
});

test('expired tree-save session keeps edits until login and retries the identical payload once', async () => {
    const f = fixture();
    f.start();
    f.requests[0].request.resolve(expired);
    await flush();
    assert.equal(f.login.length, 1);
    assert.equal(f.context.dirty, true);
    assert.equal(f.renders.length, 0);
    assert.deepEqual(f.busy, [true]);
    assert.equal(f.response(), undefined);
    f.login[0].resolve(true);
    await flush();
    assert.equal(f.requests.length, 2);
    assert.deepEqual(f.requests[0].data, f.requests[1].data);
    f.requests[1].request.resolve({success: true});
    assert.equal(f.context.dirty, false);
    assert.equal(f.renders.length, 1);
    assert.deepEqual(f.busy, [true, false]);
});

test('cancelled or failed login retains dirty state and releases Save without retry', async () => {
    for (const rejected of [false, true]) {
        const f = fixture();
        f.start();
        f.requests[0].request.resolve(expired);
        await flush();
        if (rejected) f.login[0].reject(new Error('Unavailable'));
        else f.login[0].resolve(false);
        await flush();
        assert.equal(f.requests.length, 1);
        assert.equal(f.context.dirty, true);
        assert.equal(f.renders.length, 0);
        assert.equal(f.response(), expired);
        assert.deepEqual(f.busy, [true, false]);
    }
});

test('another authentication failure after login stops without a retry loop', async () => {
    const f = fixture();
    f.start();
    f.requests[0].request.resolve(expired);
    await flush();
    f.login[0].resolve(true);
    await flush();
    f.requests[1].request.resolve(expired);
    await flush();
    assert.equal(f.login.length, 1);
    assert.equal(f.requests.length, 2);
    assert.equal(f.context.dirty, true);
    assert.equal(f.renders.length, 0);
    assert.equal(f.response(), expired);
});

test('permission denial and rate limits do not trigger manual login', async () => {
    for (const failure_type of ['authorization', 'retryable']) {
        const f = fixture();
        const failure = {success: false, failure_type, retry_after_seconds: 30};
        f.start();
        f.requests[0].request.resolve(failure);
        await flush();
        assert.equal(f.requests.length, 1);
        assert.equal(f.login.length, 0);
        assert.equal(f.response(), failure);
        assert.equal(f.context.dirty, true);
    }
});

test('transport errors retain edits and never replay a possibly completed request', async () => {
    const f = fixture();
    let failed = false;
    f.start().fail(() => { failed = true; });
    f.requests[0].request.reject({status: 0});
    await flush();
    assert.equal(failed, true);
    assert.equal(f.login.length, 0);
    assert.equal(f.requests.length, 1);
    assert.equal(f.context.dirty, true);
    assert.deepEqual(f.busy, [true, false]);
});


test('normalizing a saved layout preserves zero positions outside the first row', () => {
    const context = vm.createContext({$: {extend: (deep, target, value) => structuredClone(value)}});
    vm.runInContext(productionFunction(consumer, 'normalizeCategories'), context);
    context.items = [{code: 'root', sort_order: 0}, {code: 'child', sort_order: 0, source_sort_order: 0}];
    const normalized = vm.runInContext('normalizeCategories(items)', context);
    assert.equal(normalized[1].sort_order, 0);
    assert.equal(normalized[1].source_sort_order, 0);
});
