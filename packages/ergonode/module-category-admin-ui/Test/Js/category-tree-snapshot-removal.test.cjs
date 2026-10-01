'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(path.resolve(__dirname, '../../view/adminhtml/web/js/category-tree-mapping.js'), 'utf8');
const removal = source.slice(source.indexOf('        function deleteSnapshotCategory('),
    source.indexOf('        function mappingDraft('));

function harness(response, dirty = false) {
    const actions = [];
    const sandbox = {
        dirty,
        categoryTreeId: 7,
        config: {urls: {delete_snapshot: '/remove'}},
        $t: value => value,
        $root: {trigger: (name, values) => actions.push(['event', name, values[0]])},
        setBusy: (button, busy) => actions.push(['busy', busy]),
        updateButtons: () => actions.push(['buttons']),
        replaceModels: result => actions.push(['models', result]),
        showMessage: type => actions.push(['message', type]),
        post(url, data) {
            actions.push(['post', url, {...data}]);
            const request = {
                done(callback) { callback(response); return request; },
                fail() { return request; },
                always(callback) { callback(); return request; }
            };
            return request;
        }
    };
    vm.runInNewContext(removal, sandbox);
    sandbox.deleteSnapshotCategory('bottoms', {});
    return actions;
}

test('successful snapshot removal notifies history only after the visible models are replaced', () => {
    const response = {success: true, categories: []};
    const actions = harness(response);
    const eventIndex = actions.findIndex(action => action[0] === 'event');

    assert.deepEqual(actions.find(action => action[0] === 'post'),
        ['post', '/remove', {category_tree_id: 7, ergonode_code: 'bottoms'}]);
    assert.ok(eventIndex > actions.findIndex(action => action[0] === 'models'));
    assert.deepEqual(actions.filter(action => action[0] === 'event'),
        [['event', 'ergonode:category-mapping:snapshot-removed', response]]);
    assert.deepEqual(actions.at(-1), ['busy', false]);
});

test('failed removal leaves the models and history untouched', () => {
    const actions = harness({success: false});

    assert.equal(actions.some(action => ['models', 'event'].includes(action[0])), false);
    assert.deepEqual(actions.at(-1), ['busy', false]);
});

test('unsaved mapping changes prevent removal and history refresh', () => {
    assert.deepEqual(harness({success: true}, true), [['message', 'error']]);
});
