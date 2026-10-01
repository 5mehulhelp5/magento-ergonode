'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const Deferred = require('./helpers/deferred.cjs');
const source = fs.readFileSync(path.resolve(__dirname, '../../view/adminhtml/web/js/category-tree-mapping.js'), 'utf8');
const refresh = source.slice(source.indexOf('        function refreshCategories('), source.indexOf('        function post('));

function harness() {
    const request = Deferred();
    const feedback = [];
    const models = [];
    const state = {
        categoryTreeId: 7, extensionOperationPending: false, sourceRefreshPending: false, pendingSave: null, dirty: true,
        $t: value => value, config: {urls: {refresh: '/refresh'}}, element: {querySelector: () => null},
        window: {confirm: () => true}, setBusy() {}, showMessage() {}, updateButtons() {},
        updateSourceIssues: value => feedback.push(value),
        hasUsableModels: () => true, replaceModels: value => models.push(value), $root: {trigger() {}},
        post: () => request
    };
    vm.runInNewContext(refresh, state);
    state.refreshCategories({prop: () => false});
    return {state, request, feedback, models};
}

test('a failed source check refreshes attention without discarding the dirty draft', () => {
    const run = harness();
    run.request.resolve({success: false, source_issues: {status: 'missing'}});
    assert.equal(run.feedback[0].source_issues.status, 'missing');
    assert.equal(run.state.dirty, true);
    assert.equal(run.models.length, 0);
    assert.equal(run.state.sourceRefreshPending, false);
});

test('a response for the previous tree cannot replace the newly selected mapping', () => {
    const run = harness();
    run.state.categoryTreeId = 8;
    run.request.resolve({success: true, source_issues: {status: 'missing'}, categories: []});
    assert.equal(run.feedback.length, 0);
    assert.equal(run.models.length, 0);
    assert.equal(run.state.dirty, true);
    assert.equal(run.state.sourceRefreshPending, false);
});

for (const [renderName, side] of [['renderErgonodeList', 'ergo'], ['renderMagentoList', 'magento']]) {
    test(`${renderName} skips tree construction and cancels queued search for blocked sources`, () => {
        const start = source.indexOf(`        function ${renderName}()`);
        const render = source.slice(start, source.indexOf('\n        function ', start + 1));
        const guardStart = source.indexOf('        function isSourceBlocked()');
        const guard = source.slice(guardStart, source.indexOf('\n        function ', guardStart + 1));
        for (const issues of [{status: 'missing'}, {status: 'unavailable'}, {status: 'available', requires_refresh: true}]) {
            const cleared = [];
            const state = {
                config: {source_issues: issues}, searchTimers: {[side]: 42},
                window: {clearTimeout: id => cleared.push(id)}
            };
            // No tree dependencies: any attempt to project or build the tree fails this test.
            vm.runInNewContext(guard + render + `\n${renderName}();`, state);
            assert.deepEqual(cleared, [42]);
        }
    });
}
