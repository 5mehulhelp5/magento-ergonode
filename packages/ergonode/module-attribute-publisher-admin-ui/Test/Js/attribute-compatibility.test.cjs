'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const test = require('node:test');
const source = fs.readFileSync(path.join(__dirname, '../../view/adminhtml/web/js/ergonode-attribute-publisher-mapping.js'), 'utf8');
let rules;
const instrumented = source.slice(0, source.indexOf('    return function (config, element) {')) +
    '    return { allowedErgonodeAttributeTypes };\n});';
vm.runInNewContext(instrumented, {
    define: (_dependencies, factory) => { rules = factory(); }
});

test('picker uses supplied compatibility rules and keeps the publication capability filter', () => {
    assert.deepEqual(Array.from(rules.allowedErgonodeAttributeTypes('TEXT', {
        text: ['text'], textarea: ['text'], gallery: ['text']
    })), ['text', 'textarea']);
    assert.deepEqual(Array.from(rules.allowedErgonodeAttributeTypes('text', {
        textarea: ['text']
    })), ['textarea']);
});

test('missing rules fail closed and independent configurations do not leak into each other', () => {
    assert.deepEqual(Array.from(rules.allowedErgonodeAttributeTypes('text')), []);
    assert.deepEqual(Array.from(rules.allowedErgonodeAttributeTypes('text', {text: ['text']})), ['text']);
    assert.deepEqual(Array.from(rules.allowedErgonodeAttributeTypes('text', {})), []);
});
