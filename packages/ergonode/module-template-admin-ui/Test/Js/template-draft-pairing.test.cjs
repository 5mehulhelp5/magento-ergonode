'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const moduleRoot = path.resolve(__dirname, '../..');
const source = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/web/js/template-draft-pairing.js'),
    'utf8'
);
let pairing;

vm.runInNewContext(source, {
    define: (dependencies, factory) => {
        assert.equal(dependencies.length, 0);
        pairing = factory();
    }
});

test('finds the only draft waiting for the opposite template side', () => {
    const leftDraft = {id: 1, templateCode: 'shoes', attributeSetId: ''};
    const rightDraft = {id: 2, templateCode: '', attributeSetId: '4'};

    assert.equal(pairing.findComplementary([leftDraft], 'attribute_set'), leftDraft);
    assert.equal(pairing.findComplementary([rightDraft], 'template'), rightDraft);
});

test('does not guess when more than one complementary draft exists', () => {
    const drafts = [
        {id: 1, templateCode: 'shoes', attributeSetId: ''},
        {id: 2, templateCode: 'bags', attributeSetId: ''}
    ];

    assert.equal(pairing.findComplementary(drafts, 'attribute_set'), null);
    assert.equal(pairing.findComplementary([], 'template'), null);
});
