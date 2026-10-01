'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const moduleRoot = path.resolve(__dirname, '../..');
const source = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/web/js/category-code-generator.js'),
    'utf8'
);
const cases = JSON.parse(fs.readFileSync(
    path.join(moduleRoot, 'Test/Fixtures/category-code-cases.json'),
    'utf8'
));
let factory;

vm.runInNewContext(source, {
    define(dependencies, candidate) {
        assert.deepEqual(Array.from(dependencies), []);
        factory = candidate;
    }
});
const generator = factory();

test('generates codes from the shared PHP and JavaScript parity cases', () => {
    cases.forEach(({labels, expected}) => {
        assert.equal(generator.fromPathLabels(labels), expected);
    });
});

test('limits generated code to the Ergonode maximum length', () => {
    assert.equal(generator.fromPathLabels(['a'.repeat(150)]).length, 128);
});
