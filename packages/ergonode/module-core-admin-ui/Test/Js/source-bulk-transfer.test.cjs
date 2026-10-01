'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const modulePath = path.resolve(
    __dirname,
    '../../view/adminhtml/web/js/source-bulk-transfer.js'
);
let sourceBulkTransfer;

vm.runInNewContext(fs.readFileSync(modulePath, 'utf8'), {
    define(dependencies, factory) {
        assert.deepEqual(Array.from(dependencies), [
            'Ergonode_CoreAdminUi/js/entity-options',
            'mage/translate'
        ]);
        sourceBulkTransfer = factory({}, (value) => value);
    }
}, {filename: modulePath});

function card(attributes = {}, classes = [], pressed = null) {
    return {
        classList: {
            contains(value) {
                return classes.includes(value);
            }
        },
        getAttribute(name) {
            return Object.hasOwn(attributes, name) ? attributes[name] : null;
        },
        querySelector() {
            return pressed === null ? null : {
                getAttribute(name) {
                    return name === 'aria-pressed' ? pressed : null;
                }
            };
        }
    };
}

test('partial selection selects the remaining visible cards and complete selection clears them', () => {
    assert.deepEqual(
        {...sourceBulkTransfer.resolveSelectionState(3, 1, 1)},
        {allChecked: false, disabled: false}
    );
    assert.deepEqual(
        {...sourceBulkTransfer.resolveSelectionState(3, 3, 3)},
        {allChecked: true, disabled: false}
    );
    assert.deepEqual(
        {...sourceBulkTransfer.resolveSelectionState(0, 0, 0)},
        {allChecked: false, disabled: true}
    );
});

test('only active and unmapped source cards can be selected', () => {
    assert.equal(sourceBulkTransfer.isSelectable(card()), true);
    assert.equal(sourceBulkTransfer.isSelectable(card({'data-mapped': '1'})), false);
    assert.equal(sourceBulkTransfer.isSelectable(card({'aria-disabled': 'true'})), false);
    assert.equal(sourceBulkTransfer.isSelectable(card({}, ['is-mapped'])), false);
    assert.equal(sourceBulkTransfer.isSelectable(card({}, [], 'false')), false);
    assert.equal(sourceBulkTransfer.isSelectable(card({}, [], 'true')), true);
});

test('consumer eligibility can further restrict selection', () => {
    const candidate = card();

    assert.equal(sourceBulkTransfer.isSelectable(candidate, {isSelectable: () => false}), false);
    assert.equal(sourceBulkTransfer.isSelectable(candidate, {isSelectable: () => true}), true);
});
