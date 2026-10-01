const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
let history;
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../view/adminhtml/web/js/history-state.js'), 'utf8'), {
    define: (_, factory) => { history = factory(); }
});
const attribute = (code, mapped_code = null, active = true) => ({code, label: code, type: 'text', scope: 'global', mapped_code, active});

test('drafts remain visible without inventing a connection and older states remain distinguishable', () => {
    const draft = {...attribute('description'), is_draft: true};
    const change = {side: 'target', code: draft.code, actions: ['draft_added'], before: {...draft, is_draft: false}, after: draft};
    const state = {source: [], target: [draft], changes: [change]};
    const row = history.rows(state, 'target')[0];
    assert.equal(row.is_draft, true);
    assert.equal(row.linked, null);
    assert.equal(history.tone(row), 'changed');
    assert.equal(history.lacksDraftStatus(state), false);
    assert.equal(history.lacksDraftStatus({source: [], target: [attribute('description')]}), true);
    assert.equal(history.lacksDraftStatus(null), false);
});

test('left column contains only unmapped and excluded source attributes without duplicating valid mappings', () => {
    const state = {source: [attribute('mapped', 'target'), attribute('unmapped'), attribute('excluded', 'hidden', false)], target: [], changes: []};
    assert.deepEqual(Array.from(history.rows(state, 'source'), row => row.code), ['excluded', 'unmapped']);
    assert.equal(history.rows(state, 'source')[0].active, false);
});

test('disconnected target retains previous source label and has striped tone', () => {
    const before = attribute('color', 'colour');
    const after = attribute('color');
    const state = {source: [attribute('colour')], target: [after], changes: [{side: 'target', code: 'color', actions: ['disconnected'], before, after}]};
    const row = history.rows(state, 'target')[0];
    assert.equal(row.linked.code, 'colour');
    assert.equal(history.tone(row), 'disconnected');
});

test('excluded source affects its mapped target and show action locates hidden mapped sources in Magento', () => {
    const change = {side: 'source', code: 'material', actions: ['excluded'], before: attribute('material', 'fabric'), after: attribute('material', 'fabric', false)};
    const state = {source: [change.after, attribute('brand', 'manufacturer')], target: [attribute('fabric', 'material'), attribute('manufacturer', 'brand')], changes: [change]};
    assert.equal(history.tone(history.rows(state, 'target')[0]), 'excluded');
    assert.equal(history.findVisibleChange(state, change).side, 'source');
    assert.equal(history.findVisibleChange(state, {side: 'source', code: 'brand', after: state.source[1]}).code, 'manufacturer');
});

test('deleted snapshots remain discoverable and do not mutate stored state', () => {
    const original = attribute('old', 'old_target');
    const state = {source: [], target: [], changes: [{side: 'source', code: 'old', actions: ['deleted'], before: original, after: null}]};
    const row = history.rows(state, 'source')[0];
    assert.equal(row.deleted, true);
    assert.equal(row.active, false);
    assert.equal(history.tone(row), 'deleted');
    assert.equal(original.active, true);
    assert.equal(state.source.length, 0);
});
