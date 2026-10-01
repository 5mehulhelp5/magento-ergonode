'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const source = fs.readFileSync(path.resolve(__dirname, '../../view/adminhtml/web/js/category-tree-mapping.js'), 'utf8');
const preview = source.slice(source.indexOf('        function previewCategories('),
    source.indexOf('        function formatAutoMapStats('));
const usable = source.slice(source.indexOf('        function hasUsableModels('), source.indexOf('        function collectPersistedMappings('));

function harness(options = {}) {
    const actions = [];
    let resolve;
    let reject;
    let pending = false;
    let completion;
    const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
    function deferred(promise) {
        return {
            then: callback => deferred(promise.then(callback)),
            fail: callback => deferred(promise.catch(callback)),
            always(callback) { completion = promise.then(callback, callback); return this; }
        };
    }
    const sandbox = {
        config: {urls: {auto_map: '/preview'}},
        isSourceBlocked: () => false,
        categoryTreeId: 7,
        categories: [{code: 'chairs'}],
        magentoCategories: [{id: 12}],
        $t: text => text,
        operationState: {
            start() {
                if (pending) { return false; }
                pending = true;
                actions.push('loader:start');
                return true;
            },
            finish() { pending = false; actions.push('loader:finish'); }
        },
        setBusy: (button, busy) => actions.push(`button:${busy}`),
        mappingDraft: () => ({draft_mappings: [{ergonode_code: 'chairs', magento_category_id: 12}]}),
        post(url, data) { actions.push(['post', url, data]); return deferred(promise); },
        showMessage: tone => actions.push(`message:${tone}`),
        markDirty: () => actions.push('dirty'),
        replaceModels() {
            if (options.renderError) { throw new Error('render failed'); }
            actions.push('replace');
        },
        subtreeMapping: {applyAssignments: () => { actions.push('subtree'); return {mapped: 1, unmatched: 0}; }},
        normalizeCategories: categories => categories,
        renderAll: () => actions.push('render'),
        formatAutoMapStats: text => text
    };
    vm.runInNewContext(usable + preview, sandbox);
    return {
        actions,
        start: scope => sandbox.previewCategories({}, 'Completed', scope),
        resolve: response => { resolve(response); return completion; },
        reject: () => { reject(new Error('network failed')); return completion; }
    };
}

const success = {success: true, categories: [{code: 'chairs'}], magento_categories: [{id: 12}]};

test('preview displays busy state before transport and ignores repeated activation until rendered', async () => {
    const run = harness();
    run.start();
    run.start();
    assert.deepEqual(run.actions.slice(0, 2), ['loader:start', 'button:true']);
    const requests = run.actions.filter(Array.isArray);
    assert.equal(requests.length, 1);
    assert.equal(JSON.parse(requests[0][2].payload).draft_mappings[0].magento_category_id, 12);
    assert.equal(run.actions.includes('replace'), false);
    await run.resolve(success);
    assert.deepEqual(run.actions.slice(-5), ['replace', 'message:success', 'dirty', 'button:false', 'loader:finish']);
});

for (const [label, response] of [
    ['backend error', {success: false, message: 'Failed'}],
    ['missing models', {success: true}],
    ['unexpected empty models', {success: true, categories: [], magento_categories: []}]
]) {
    test(`${label} preserves the existing draft and releases the loader`, async () => {
        const run = harness();
        run.start();
        await run.resolve(response);
        assert.equal(run.actions.includes('replace'), false);
        assert.equal(run.actions.includes('dirty'), false);
        assert.deepEqual(run.actions.slice(-3), ['message:error', 'button:false', 'loader:finish']);
    });
}

test('transport failure releases the loader and keeps the current models', async () => {
    const run = harness();
    run.start();
    await run.reject();
    assert.equal(run.actions.includes('replace'), false);
    assert.equal(run.actions.includes('dirty'), false);
    assert.deepEqual(run.actions.slice(-3), ['message:error', 'button:false', 'loader:finish']);
});

test('a render exception cannot leave the workspace blocked', async () => {
    const run = harness({renderError: true});
    run.start();
    await run.resolve(success);
    assert.deepEqual(run.actions.slice(-3), ['message:error', 'button:false', 'loader:finish']);
});

test('subtree preview applies only the scoped result before releasing the loader', async () => {
    const run = harness();
    run.start(['chairs']);
    await run.resolve(success);
    assert.equal(run.actions.includes('replace'), false);
    assert.deepEqual(run.actions.slice(-6), ['subtree', 'render', 'message:success', 'dirty', 'button:false', 'loader:finish']);
});
