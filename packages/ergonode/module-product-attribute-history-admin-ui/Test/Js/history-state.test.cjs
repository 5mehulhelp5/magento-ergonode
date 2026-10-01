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


test('option changes are scoped by parent and never become attribute cards', () => {
    const change = {entity: 'option', side: 'target', attribute_code: 'color', code: 'option_1',
        actions: ['deleted'], before: attribute('option_1', 'red'), after: null};
    const state = {source: [attribute('colour', 'color')], target: [attribute('color', 'colour'), attribute('size')],
        options: {source: {colour: [attribute('red')]}, target: {color: [], size: [attribute('option_1')]}}, changes: [change]};
    assert.equal(history.rows(state, 'target').length, 2);
    const view = history.optionView(state, {side: 'target', code: 'color'});
    const row = history.rows(view, 'target')[0];
    assert.equal(row.code, 'option_1');
    assert.equal(row.deleted, true);
    assert.equal(row.linked.code, 'red');
    assert.equal(history.optionView(state, {side: 'target', code: 'size'}).changes.length, 0);
});

test('unmapping a parent retains its historical option counterpart and old states stay unavailable', () => {
    const change = {side: 'target', code: 'color', actions: ['disconnected'], before: attribute('color', 'colour'), after: attribute('color')};
    const state = {source: [attribute('colour')], target: [change.after], changes: [change]};
    const view = history.optionView(state, {side: 'target', code: 'color'});
    assert.equal(view.parents.source, 'colour');
    assert.equal(view.source.length, 0);
    assert.equal(state.options, undefined);
});


test('inline groups keep mapped source parents with visible options and separate repeated option codes', () => {
    const state = {source: [attribute('colour', 'color'), attribute('brand', 'manufacturer')],
        target: [attribute('color', 'colour'), attribute('manufacturer', 'brand')], changes: [],
        options: {source: {colour: [attribute('red')], brand: [attribute('acme', 'option_1')]},
            target: {color: [attribute('option_1')], manufacturer: [attribute('option_1', 'acme')]}}};
    const source = history.groups(state, 'source');
    assert.equal(source.length, 1);
    assert.equal(source[0].attribute.code, 'colour');
    assert.equal(source[0].options[0].code, 'red');
    const target = history.groups(state, 'target');
    assert.equal(target.length, 2);
    assert.equal(target[0].options[0].linked, null);
    assert.equal(target[1].options[0].linked.code, 'acme');
});

test('every option modification highlights its parent and mapped parent before options are loaded', () => {
    for (const [action, tone] of Object.entries({created: 'created', deleted: 'deleted', disconnected: 'disconnected',
        connected: 'connected', reconnected: 'connected', excluded: 'excluded', included: 'connected',
        renamed: 'changed', type_changed: 'changed', scope_changed: 'changed'})) {
        const state = {source: [attribute('colour', 'color')], target: [attribute('color', 'colour'), attribute('size')],
            options: {source: {}, target: {}}, changes: [{entity: 'option', side: 'source', attribute_code: 'colour',
                code: 'red', actions: [action], before: attribute('red'), after: attribute('red')}]};
        const parent = history.rows(state, 'target')[0];
        assert.equal(history.tone(parent), tone, action);
        assert.equal(parent.excluded, false);
        assert.equal(parent.actions.length, 0);
        assert.equal(history.tone(history.rows(state, 'source', true)[0]), tone);
        assert.equal(history.tone(history.rows(state, 'target')[1]), '');
    }
});

test('mixed option changes use generic change style and direct attribute changes take precedence', () => {
    const state = {source: [], target: [attribute('color')], options: {source: {}, target: {}}, changes:
        ['created', 'deleted'].map(action => ({entity: 'option', side: 'target', attribute_code: 'color', code: action,
            actions: [action], before: attribute(action), after: attribute(action)}))};
    assert.equal(history.tone(history.rows(state, 'target')[0]), 'changed');
    state.changes.push({side: 'target', code: 'color', actions: ['disconnected'], before: attribute('color'), after: attribute('color')});
    assert.equal(history.tone(history.rows(state, 'target')[0]), 'disconnected');
});

test('summary counts retain source parents whose unchanged options have not loaded', () => {
    const state = {source: [attribute('colour', 'color')], target: [], changes: [], options: {source: {}, target: {}},
        option_counts: {source: {colour: 3}, target: {}}};
    const group = history.groups(state, 'source')[0];
    assert.equal(group.attribute.code, 'colour');
    assert.equal(group.options.length, 0);
    assert.equal(group.count, 3);
});
