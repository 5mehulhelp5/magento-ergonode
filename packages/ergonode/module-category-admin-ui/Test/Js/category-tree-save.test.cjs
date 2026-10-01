'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const Deferred = require('./helpers/deferred.cjs');
const jsRoot = path.resolve(__dirname, '../../view/adminhtml/web/js');
const source = fs.readFileSync(path.join(jsRoot, 'category-tree-mapping.js'), 'utf8');
const save = source.slice(source.indexOf('        function saveLayout('), source.indexOf('        function markCategoryRemotePrepared('));

function harness(options = {}) {
    const actions = [];
    const requests = [];
    let busy = options.previewBusy || false;
    const $ = {Deferred, extend: (deep, target, value) => structuredClone(value)};
    let saveWithRecovery;
    vm.runInNewContext(fs.readFileSync(path.join(jsRoot, 'save-recovery.js'), 'utf8'), {
        define: (deps, factory) => { saveWithRecovery = factory($); }, Promise
    });
    const state = {
        $, $t: value => value, pendingSave: null,
        persistedMappings: {old: 42}, dirty: true,
        categoryTreeId: 3, categories: [{code: 'chairs', magento_category_id: null}, {code: 'tables', magento_category_id: 43}],
        operationState: {
            start(operation) { if (busy) return false; busy = true; actions.push(`start:${operation}`); return true; },
            finish() { busy = false; actions.push('finish'); }
        },
        collectVisibility: () => [],
        collectPersistedMappings: categories => Object.fromEntries(categories.map(item => [item.code, item.magento_category_id])),
        config: {urls: {save: '/save'}},
        $saveButton: {},
        $root: {trigger: name => actions.push(name)},
        setBusy: (button, value) => actions.push(`button:${value}`),
        showMessage: tone => actions.push(`message:${tone}`),
        renderAll() {
            actions.push('render');
            if (options.renderError) throw new Error('render failed');
        },
        updateButtons: () => actions.push('buttons'),
        saveWithRecovery,
        saveRecoveryHandler: options.recover,
        post(url, data) {
            actions.push('post');
            if (options.sendError) throw new Error('send failed');
            const request = Deferred();
            requests.push({url, data, request});
            return request;
        }
    };
    vm.runInNewContext(save, state);
    return {state, actions, requests, busy: () => busy, start: (...args) => state.saveLayout(...args)};
}

test('save shows busy state before transport, shares a pending request and releases only after rendering', () => {
    const run = harness();
    const first = run.start(['tables'], () => run.actions.push('beforeRender'));
    assert.equal(run.start(), first);
    assert.equal(run.requests.length, 1);
    assert.deepEqual(run.actions, ['start:save', 'button:true', 'post']);
    const payload = JSON.parse(run.requests[0].data.payload);
    assert.deepEqual(payload.categories.map(item => item.code), ['chairs']);
    assert.equal(payload.categories[0].magento_category_id, null);
    let complete = false;
    first.done(() => { complete = true; assert.equal(run.busy(), false); });
    run.requests[0].request.resolve({success: true});
    assert.equal(complete, true);
    assert.equal(run.state.dirty, false);
    assert.deepEqual(run.actions.slice(-7), ['beforeRender', 'render', 'buttons', 'message:success',
        'ergonode:category-mapping:saved', 'button:false', 'finish']);
});

for (const scenario of ['backend', 'network', 'render', 'beforeRender', 'send']) {
    test(`${scenario} error unlocks the workspace and preserves the dirty draft for retry`, () => {
        const run = harness({renderError: scenario === 'render', sendError: scenario === 'send'});
        run.start([], () => { if (scenario === 'beforeRender') throw new Error('extension failed'); });
        if (scenario !== 'send') {
            const request = run.requests[0].request;
            if (scenario === 'network') request.reject(new Error('network failed'));
            else request.resolve({success: scenario !== 'backend'});
        }
        assert.equal(run.state.dirty, true);
        assert.equal(run.busy(), false);
        assert.equal(run.state.pendingSave, null);
        assert.deepEqual(run.state.persistedMappings, {old: 42});
        assert.deepEqual(run.actions.slice(-3), ['message:error', 'button:false', 'finish']);
    });
}

test('save waits through recovery and its retry without enabling edits in between', async () => {
    let recover;
    const run = harness({recover: () => new Promise(resolve => { recover = resolve; })});
    const first = run.start();
    run.requests[0].request.resolve({success: false, conflict: true});
    await new Promise(setImmediate);
    assert.equal(run.busy(), true);
    assert.equal(run.start(), first);
    recover(true);
    await new Promise(setImmediate);
    assert.equal(run.requests.length, 2);
    assert.equal(run.requests[0].data.payload, run.requests[1].data.payload);
    assert.equal(run.busy(), true);
    run.requests[1].request.resolve({success: true});
    assert.equal(run.busy(), false);
    assert.equal(run.state.dirty, false);
});

test('save cannot overlap a pending automatic mapping preview', () => {
    const run = harness({previewBusy: true});
    let rejected = false;
    run.start().fail(() => { rejected = true; });
    assert.equal(rejected, true);
    assert.equal(run.requests.length, 0);
    assert.equal(run.busy(), true);
    assert.equal(run.state.dirty, true);
});

for (const excludedCodes of [[], ['constructor', '__proto__']]) {
    test(`save preserves literal category codes and excludes only explicit keys: ${excludedCodes}`, () => {
        const run = harness();
        const codes = ['constructor', '__proto__', 'hasownproperty', '0', 'regular'];
        run.state.categories = codes.map((code, index) => ({code, magento_category_id: index + 10}));
        run.start(excludedCodes);
        const expected = codes.filter(code => !excludedCodes.includes(code));
        const payload = JSON.parse(run.requests[0].data.payload);
        assert.deepEqual(payload.categories.map(item => item.code), expected);
        run.requests[0].request.resolve({success: true});
        assert.deepEqual(Object.keys(run.state.persistedMappings).sort(), expected.slice().sort());
        assert.equal(run.state.dirty, false);
    });
}
