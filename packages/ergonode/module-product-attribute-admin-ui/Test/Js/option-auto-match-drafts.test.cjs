'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const moduleRoot = path.resolve(__dirname, '../..');
const coreRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi');
const mappingSource = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/option-mapping.js'),
    'utf8'
);

function functionSource(name, nextName) {
    return mappingSource.slice(
        mappingSource.indexOf(`function ${name}(`),
        mappingSource.indexOf(`function ${nextName}(`)
    );
}

function loadDraftFunctions(overrides = {}) {
    const context = {
        buttons: {isPressed: (button) => button.pressed},
        getOppositeSlot: (slot) => slot.opposite,
        hasSlotOption: (slot) => Boolean(slot && slot.payload),
        mappingElements: {
            cardPayload: (card) => card.payload,
            markNewMapping: () => {},
            slotPayload: (slot) => slot ? slot.payload : null
        },
        refreshRowAfterEdit: () => {},
        setOptionMapped: () => {},
        setSlotFilled: (slot, payload) => {
            slot.payload = payload;
        },
        ...overrides
    };
    const source = [
        functionSource('getAutoMatchPayloads', 'createMatchedMappingRow'),
        functionSource('findDraftRowByPayload', 'applyAutoMatches'),
        'result = {getAutoMatchPayloads, completeAutoMatchedDraft};'
    ].join('\n');

    vm.runInNewContext(source, context);

    return context.result;
}

function mappingRow(leftPayload, rightPayload) {
    const left = {payload: leftPayload};
    const right = {payload: rightPayload};
    const row = {
        removed: false,
        remove() {
            this.removed = true;
        },
        querySelector(selector) {
            return selector.includes('data-side="ergo"') ? left : right;
        }
    };

    left.opposite = right;
    right.opposite = left;

    return {left, right, row};
}

test('auto match payload includes the filled side of an incomplete mapping row', () => {
    const rightPayload = {code: 'option_59', label: 'White', source: 'magento'};
    const draft = mappingRow(null, rightPayload);
    const sourceCard = {
        payload: {code: 'option_60', label: 'Yellow', source: 'magento'},
        querySelector: () => ({pressed: true}),
        getAttribute: (name) => name === 'data-mapped' ? '0' : ''
    };
    const root = {
        querySelectorAll(selector) {
            return selector.includes('entity-card') ? [sourceCard] : [draft.row];
        }
    };
    const {getAutoMatchPayloads} = loadDraftFunctions();

    assert.deepEqual(
        Array.from(getAutoMatchPayloads(root, 'magento'), (payload) => payload.code),
        ['option_60', 'option_59']
    );
});

test('auto match fills an existing right-only draft instead of duplicating its Magento option', () => {
    const leftPayload = {code: 'white', label: 'White', source: 'ergo'};
    const rightPayload = {code: 'option_59', label: 'White', source: 'magento'};
    const draft = mappingRow(null, rightPayload);
    const mapped = [];
    const marked = [];
    const {completeAutoMatchedDraft} = loadDraftFunctions({
        mappingElements: {
            cardPayload: (card) => card.payload,
            markNewMapping: (row, source) => marked.push([row, source]),
            slotPayload: (slot) => slot ? slot.payload : null
        },
        setOptionMapped: (root, source, code) => mapped.push([source, code])
    });
    const root = {querySelectorAll: () => [draft.row]};

    assert.equal(completeAutoMatchedDraft(root, leftPayload, rightPayload), draft.row);
    assert.equal(draft.left.payload.code, 'white');
    assert.equal(draft.row.removed, false);
    assert.deepEqual(mapped, [['ergo', 'white'], ['magento', 'option_59']]);
    assert.deepEqual(marked, [[draft.row, 'ergo']]);
});
