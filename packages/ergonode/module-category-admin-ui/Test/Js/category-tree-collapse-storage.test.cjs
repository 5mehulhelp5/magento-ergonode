'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

function loadCollapseStorage() {
    let exported;
    const fileName = path.resolve(
        __dirname,
        '../../view/adminhtml/web/js/category-tree-collapse-storage.js'
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

function createStorage() {
    const values = new Map();

    return {
        getItem(key) {
            return values.has(key) ? values.get(key) : null;
        },
        setItem(key, value) {
            values.set(key, value);
        }
    };
}

test('collapsed branches survive a save and are scoped to the category tree', () => {
    const collapseStorage = loadCollapseStorage();
    const storage = createStorage();

    assert.equal(collapseStorage.load(41, storage), null);
    assert.equal(collapseStorage.save(41, {
        source: {men: true, women: false},
        magento: {12: true}
    }, storage), true);
    assert.equal(collapseStorage.save(52, {
        source: {sale: true},
        magento: {}
    }, storage), true);

    const firstTree = collapseStorage.load(41, storage);
    const secondTree = collapseStorage.load(52, storage);

    assert.equal(firstTree.source.men, true);
    assert.equal(Object.hasOwn(firstTree.source, 'women'), false);
    assert.equal(firstTree.magento['12'], true);
    assert.equal(Object.hasOwn(firstTree.source, 'sale'), false);
    assert.equal(secondTree.source.sale, true);
    assert.equal(Object.keys(secondTree.magento).length, 0);
});

test('invalid or unavailable browser storage falls back without breaking the tree', () => {
    const collapseStorage = loadCollapseStorage();
    const brokenStorage = {
        getItem() {
            throw new Error('Storage is unavailable.');
        },
        setItem() {
            throw new Error('Storage is unavailable.');
        }
    };
    const corruptStorage = createStorage();

    corruptStorage.setItem('ergonode-category-tree-mapping:collapsed:v1:41', '{not-json');

    assert.equal(collapseStorage.load(41, brokenStorage), null);
    assert.equal(collapseStorage.save(41, {source: {}, magento: {}}, brokenStorage), false);
    assert.equal(collapseStorage.load(41, corruptStorage), null);
    assert.equal(collapseStorage.load(0, corruptStorage), null);
    assert.equal(collapseStorage.save(0, {source: {}, magento: {}}, corruptStorage), false);
});
