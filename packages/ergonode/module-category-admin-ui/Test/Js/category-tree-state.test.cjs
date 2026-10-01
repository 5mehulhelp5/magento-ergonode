'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

function loadTreeState() {
    let exported;
    const fileName = path.resolve(
        __dirname,
        '../../view/adminhtml/web/js/category-tree-state.js'
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

test('tree branches start expanded and toggle independently', () => {
    const state = loadTreeState();
    const source = {};
    const magento = {};

    assert.equal(state.isExpanded(source, 'chairs', false), true);
    assert.equal(state.toggle(source, 'chairs'), false);
    assert.equal(state.isExpanded(source, 'chairs', false), false);
    assert.equal(state.isExpanded(magento, 12, false), true);
    assert.equal(state.toggle(source, 'chairs'), true);
    assert.equal(state.isExpanded(source, 'chairs', false), true);
});

test('search forces matching paths open without clearing collapsed state', () => {
    const state = loadTreeState();
    const collapsed = {};

    state.toggle(collapsed, 'chairs');

    assert.equal(state.isExpanded(collapsed, 'chairs', true), true);
    assert.equal(state.isExpanded(collapsed, 'chairs', false), false);
});

test('collapsing a branch also collapses every descendant for the next reopen', () => {
    const state = loadTreeState();
    const collapsed = {};
    const children = {
        2: [3, 6],
        3: [4],
        4: [5]
    };

    state.collapseBranch(collapsed, 2, (categoryId) => children[categoryId] || []);

    assert.deepEqual(collapsed, {'2': true, '3': true, '4': true, '5': true, '6': true});

    state.toggle(collapsed, 2);

    assert.equal(state.isExpanded(collapsed, 2, false), true);
    assert.equal(state.isExpanded(collapsed, 3, false), false);
    assert.equal(state.isExpanded(collapsed, 4, false), false);
});

test('empty branch keys do not alter state', () => {
    const state = loadTreeState();
    const collapsed = {};

    assert.equal(state.toggle(collapsed, ''), true);
    assert.deepEqual(collapsed, {});
});
