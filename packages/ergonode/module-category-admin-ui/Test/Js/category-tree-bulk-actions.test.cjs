'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

function loadBulkActions() {
    let exported;
    const fileName = path.resolve(
        __dirname,
        '../../view/adminhtml/web/js/category-tree-bulk-actions.js'
    );
    const sandbox = {
        define(names, factory) {
            assert.deepEqual(Array.from(names), []);
            exported = factory();
        }
    };

    vm.runInNewContext(fs.readFileSync(fileName, 'utf8'), sandbox, {filename: fileName});

    return exported;
}

test('no category action is available without a valid selection', () => {
    const actions = loadBulkActions();
    const availability = actions.resolve('ergo', [], [], []);

    assert.deepEqual({...availability}, {disconnect: false});
});

test('unmapped Ergonode category cannot be disconnected', () => {
    const actions = loadBulkActions();
    const availability = actions.resolve('ergo', ['tables'], [
        {code: 'tables', active: true, magento_category_id: null}
    ], []);

    assert.equal(availability.disconnect, false);
});

test('mapped Ergonode category can be disconnected regardless of visibility', () => {
    const actions = loadBulkActions();
    const availability = actions.resolve('ergo', ['chairs'], [
        {code: 'chairs', active: false, magento_category_id: 12}
    ], []);

    assert.equal(availability.disconnect, true);
});

test('mixed Ergonode selection can disconnect any mapped category', () => {
    const actions = loadBulkActions();
    const availability = actions.resolve('ergo', ['chairs', 'tables'], [
        {code: 'chairs', active: false, magento_category_id: 12},
        {code: 'tables', active: true, magento_category_id: null}
    ], []);

    assert.equal(availability.disconnect, true);
});

test('Magento selection derives disconnect availability from both trees', () => {
    const actions = loadBulkActions();
    const availability = actions.resolve('magento', ['12', '13'], [
        {code: 'chairs', magento_category_id: 12}
    ], [
        {id: 12, active: true},
        {id: 13, active: false}
    ]);

    assert.equal(availability.disconnect, true);
});

test('unknown Magento selection cannot disconnect a stale mapping', () => {
    const actions = loadBulkActions();
    const availability = actions.resolve('magento', ['12'], [
        {code: 'chairs', magento_category_id: 12}
    ], []);

    assert.equal(availability.disconnect, false);
});


test('selection normalizes numeric IDs and never matches invalid IDs or inherited object keys', () => {
    const actions = loadBulkActions();
    assert.equal(actions.resolve('magento', ['0012'], [{magento_category_id: '12'}], [{id: 12}]).disconnect, true);
    assert.equal(actions.resolve('magento', [null, 0, -1, 'NaN'], [{magento_category_id: 0}], [{id: 0}]).disconnect, false);
    assert.equal(actions.resolve('ergo', ['__proto__'], [{code: '__proto__', magento_category_id: 12}], []).disconnect, true);
    assert.equal(actions.resolve('ergo', ['constructor'], [{code: 'other', magento_category_id: 12}], []).disconnect, false);
    assert.equal(actions.resolve('unknown', ['12'], [{magento_category_id: 12}], [{id: 12}]).disconnect, false);
});

test('availability preserves the first matching source row when codes repeat', () => {
    const actions = loadBulkActions();
    assert.equal(actions.resolve('ergo', ['chairs'], [
        {code: 'chairs', magento_category_id: null}, {code: 'chairs', magento_category_id: 12}
    ], []).disconnect, false);
});

test('large selections scan each tree at most once and stop at the first available action', () => {
    const actions = loadBulkActions();
    const size = 10000;
    let magentoReads = 0;
    let mappingReads = 0;
    const ids = Array.from({length: size}, (_, index) => index + 1);
    const magento = ids.map(id => ({get id() { magentoReads++; return id; }}));
    const categories = ids.map(id => ({code: String(id), get magento_category_id() { mappingReads++; return id; }}));
    assert.equal(actions.resolve('magento', [], categories, magento).disconnect, false);
    assert.equal(magentoReads + mappingReads, 0);
    assert.equal(actions.resolve('magento', ids, categories, magento).disconnect, true);
    assert.equal(magentoReads, size);
    assert.equal(mappingReads, 1);
    assert.equal(actions.resolve('ergo', ['missing'], categories, magento).disconnect, false);
    assert.equal(mappingReads, size + 1);
});
