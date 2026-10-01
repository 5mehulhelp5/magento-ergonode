'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const moduleRoot = path.resolve(__dirname, '../..');
const coreRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi');
const boardSource = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/mapping-board.js'),
    'utf8'
);
const attributeSource = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/attribute-mapping.js'),
    'utf8'
);
const optionSource = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/option-mapping.js'),
    'utf8'
);
const mappingElements = {
    slotPayload: (slot) => slot ? slot.payload : null
};
const buttons = {
    isPressed: (button) => button.pressed
};
const visibilityToggle = {
    initialize: () => {},
    isVisible: (button) => Boolean(button && button.visible),
    setVisible: (button, visible) => {
        button.visible = visible;

        return visible;
    },
    toggle: (button) => {
        button.visible = !button.visible;

        return button.visible;
    }
};
let mappingBoard;

vm.runInNewContext(boardSource, {
    define: (dependencies, factory) => {
        assert.equal(dependencies.length, 6);
        mappingBoard = factory(
            {normalize: (value) => String(value || '').toLowerCase()},
            mappingElements,
            buttons,
            {},
            {},
            visibilityToggle
        );
    }
});

function slot(payload) {
    return {
        payload,
        getAttribute: (name) => name === 'data-code' && payload ? payload.code : ''
    };
}

function row(left, right) {
    return {
        querySelector: (selector) => selector.includes('data-side="ergo"') ? left : right
    };
}

test('serializes mappings once for attributes and preserves duplicates for options', () => {
    const left = slot({code: 'color', source: 'ergo'});
    const right = slot({code: 'color', source: 'magento'});
    const rows = [row(left, right), row(left, right)];
    const root = {
        querySelectorAll: (selector) => selector === '[data-role="mapping-row"]' ? rows : []
    };
    const unique = mappingBoard.collectMappings(root, true);
    const complete = mappingBoard.collectMappings(root, false);

    assert.equal(unique.length, 1);
    assert.equal(complete.length, 2);
    assert.deepEqual(
        Array.from(unique, (mapping) => [mapping.left.code, mapping.right.code]),
        [['color', 'color']]
    );
});

test('finds one complementary draft row from either side and avoids ambiguous pairing', () => {
    const leftDraft = row(slot({code: 'color', source: 'ergo'}), slot(null));
    const rightDraft = row(slot(null), slot({code: 'size', source: 'magento'}));
    const root = (rows) => ({
        querySelectorAll: (selector) => selector === '[data-role="mapping-row"]' ? rows : []
    });

    assert.equal(mappingBoard.findComplementaryRow(root([leftDraft]), 'magento'), leftDraft);
    assert.equal(mappingBoard.findComplementaryRow(root([rightDraft]), 'ergo'), rightDraft);
    assert.equal(
        mappingBoard.findComplementaryRow(root([leftDraft]), 'magento', () => false),
        null
    );
    assert.equal(
        mappingBoard.findComplementaryRow(root([leftDraft, leftDraft]), 'magento'),
        null
    );
});

test('owns the shared source and mapped drop contracts', () => {
    const panel = {
        getAttribute: (name) => name === 'data-source-panel' ? 'magento' : ''
    };

    assert.equal(mappingBoard.acceptsSourceDrop({code: 'color', source: 'ergo'}), true);
    assert.equal(mappingBoard.acceptsSourceDrop({code: 'color', source: 'other'}), false);
    assert.equal(mappingBoard.acceptsMappedDrop(panel, {
        origin: 'mapping',
        source: 'magento'
    }), true);
    assert.equal(mappingBoard.acceptsMappedDrop(panel, {
        origin: 'source',
        source: 'magento'
    }), false);
});

test('skips pending cards and keeps active visibility state', () => {
    const card = (source, code, active, pending) => ({
        querySelector: () => ({pressed: active}),
        getAttribute: (name) => ({
            'data-source': source,
            'data-code': code,
            'data-pending-create': pending ? '1' : '0'
        })[name] || ''
    });
    const root = {
        querySelectorAll: () => [
            card('ergo', 'color', true, false),
            card('magento', 'pending_color', true, true),
            card('magento', 'size', false, false)
        ]
    };
    const visibility = mappingBoard.collectVisibility(root);

    assert.deepEqual(
        Array.from(visibility, (item) => [item.source, item.code, item.active]),
        [['ergo', 'color', true], ['magento', 'size', false]]
    );
});

test('uses independent panel visibility controls and falls back to the workspace control', () => {
    const globalToggle = {visible: true};
    const leftToggle = {visible: false};
    const rightToggle = {visible: true};
    const root = {
        querySelector: (selector) => selector === '[data-role="visibility-toggle"]' ? globalToggle : null
    };
    const panel = (toggle) => ({
        querySelector: (selector) => selector === '[data-role="visibility-toggle"]'
            ? toggle
            : {value: ''}
    });
    const card = (active, owner) => ({
        classList: {toggle: () => {}},
        closest: () => owner,
        getAttribute: (name) => ({
            'data-mapped': '0',
            'data-search': 'color'
        })[name] || '',
        hidden: false,
        querySelector: () => ({pressed: active})
    });
    const leftPanel = panel(leftToggle);
    const rightPanel = panel(rightToggle);
    const activeCard = card(true, leftPanel);
    const leftOmittedCard = card(false, leftPanel);
    const rightOmittedCard = card(false, rightPanel);

    mappingBoard.updateCardVisibility(root, activeCard);
    mappingBoard.updateCardVisibility(root, leftOmittedCard);
    mappingBoard.updateCardVisibility(root, rightOmittedCard);
    assert.equal(activeCard.hidden, false);
    assert.equal(leftOmittedCard.hidden, true);
    assert.equal(rightOmittedCard.hidden, false);

    mappingBoard.toggleVisibility(leftToggle);
    mappingBoard.updateCardVisibility(root, leftOmittedCard);
    assert.equal(leftOmittedCard.hidden, false);
});

test('disables and resets a panel visibility action without excluded cards', () => {
    const toggle = {visible: true};
    const activeToggle = {pressed: true};
    const root = {};
    const panel = {
        closest: () => root,
        querySelector: () => toggle,
        querySelectorAll: (selector) => selector.includes('attribute-active-toggle')
            ? [activeToggle]
            : []
    };

    mappingBoard.updateSidePanel(panel);
    assert.equal(toggle.visible, false);
    assert.equal(toggle.disabled, true);

    activeToggle.pressed = false;
    mappingBoard.updateSidePanel(panel);
    assert.equal(toggle.disabled, false);
});

test('both mapping screens delegate shared board mechanics', () => {
    [attributeSource, optionSource].forEach((source) => {
        assert.match(source, /Ergonode_CoreAdminUi\/js\/mapping-board/);
        assert.match(source, /mappingBoard\.collectMappings\(/);
        assert.match(source, /mappingBoard\.collectVisibility\(/);
        assert.match(source, /mappingBoard\.findComplementaryRow\(/);
        assert.match(source, /row = row \|\| createMappingRowFromPayload\(root, payload\)/);
        assert.match(source, /row = addSourcePayloadToMapping\(element, payload, false\)/);
        assert.doesNotMatch(source, /function collectMappings\(/);
        assert.doesNotMatch(source, /function collectVisibility\(/);
        assert.doesNotMatch(source, /function getStatusTone\(/);
        assert.doesNotMatch(source, /function getSlotSide\(/);
        assert.match(source, /mappingBoard\.acceptsSourceDrop/);
        assert.match(source, /mappingBoard\.setMappedDragState/);
        assert.doesNotMatch(source, /function acceptsSourceDrop\(/);
        assert.doesNotMatch(source, /function setMappedDragState\(/);
    });
    assert.match(optionSource, /function normalizeRowAfterSlotRemoval\(row\)/);
    assert.doesNotMatch(optionSource, /normalizeRowAfterSlotRemoval\(root, row\)/);
});
