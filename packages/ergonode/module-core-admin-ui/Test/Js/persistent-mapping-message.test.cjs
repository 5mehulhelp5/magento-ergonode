'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(path.resolve(__dirname, '../../view/adminhtml/web/js/attribute-mapping.js'), 'utf8');
const refresh = source.slice(source.indexOf('    function refreshRowAfterEdit(row)'), source.indexOf('    function getDragPayload('));

function fixture() {
    const attributes = {
        'data-validation-message': 'Adapter unavailable. Attribute skipped.',
        'data-validation-code': 'category',
        'data-validation-tone': 'error'
    };
    const right = {code: 'category', getAttribute: () => right.code};
    const row = {
        querySelector: (selector) => selector.includes('magento') ? right : {},
        querySelectorAll: () => [],
        getAttribute: (name) => attributes[name]
    };
    const sandbox = {
        hasSlotAttribute: () => true,
        getSlotType: () => 'text',
        updateEmptySlotHint() {},
        updateRowSearchData() {},
        updateOptionMappingAction() {},
        canMapAttributeTypes: () => true,
        showRowMessage: (target, message) => {target.message = message;},
        hideRowMessage: (target) => {target.message = null;},
        setRowStatus: (target, tone) => {target.tone = tone;}
    };
    vm.runInNewContext(refresh, sandbox);
    return {row, right, refresh: sandbox.refreshRowAfterEdit};
}

test('editing a compatible mapping repeatedly preserves its backend error', () => {
    const state = fixture();
    state.refresh(state.row);
    state.refresh(state.row);
    assert.equal(state.row.tone, 'error');
    assert.equal(state.row.message, 'Adapter unavailable. Attribute skipped.');
});

test('replacing the target attribute removes the stale backend message', () => {
    const state = fixture();
    state.refresh(state.row);
    state.right.code = 'description';
    state.refresh(state.row);
    assert.equal(state.row.tone, 'manual');
    assert.equal(state.row.message, null);
});

test('save response applies current validation to new rows and can clear an old error', () => {
    const attributes = {'data-code': 'category'};
    const row = {
        querySelector: () => ({getAttribute: (name) => attributes[name]}),
        setAttribute: (name, value) => {attributes[name] = value;}
    };
    const root = {querySelectorAll: () => [row]};
    let refreshed = 0;
    const sandbox = {refreshRowAfterEdit: () => {refreshed += 1;}};
    vm.runInNewContext(source.slice(source.indexOf('    function applyValidationMessages('), source.indexOf('    function saveMappings(')), sandbox);
    sandbox.applyValidationMessages(root, {category: {message: 'Missing adapter', tone: 'error'}});
    assert.equal(attributes['data-validation-message'], 'Missing adapter');
    sandbox.applyValidationMessages(root, {category: {message: '', tone: ''}});
    assert.equal(attributes['data-validation-message'], '');
    assert.equal(refreshed, 2);
});
