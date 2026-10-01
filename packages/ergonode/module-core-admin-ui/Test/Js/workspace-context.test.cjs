'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(
    path.resolve(__dirname, '../../view/adminhtml/web/js/workspace-context.js'),
    'utf8'
);

function load(messages, dirtyState) {
    let exported;

    vm.runInNewContext(source, {
        define: (dependencies, factory) => {
            assert.deepEqual(Array.from(dependencies), [
                'Ergonode_CoreAdminUi/js/messages',
                'Ergonode_CoreAdminUi/js/dirty-state',
                'mage/translate'
            ]);
            exported = factory(messages, dirtyState, (value) => value);
        }
    });

    return exported;
}

test('workspace context owns messages, dirty state and its published root contract', () => {
    const cleanups = [];
    const root = {};
    const message = {destroyed: false, destroy() { this.destroyed = true; }};
    const dirty = {capture() {}};
    const serialize = () => ({value: 1});
    const contextFactory = load(
        {create: (target, config) => {
            assert.equal(target, root);
            assert.equal(config.containerSelector, '[data-role="message"]');
            return message;
        }},
        {create: (callback) => {
            assert.equal(callback, serialize);
            return dirty;
        }}
    );
    const context = contextFactory.create({cleanup: (callback) => cleanups.push(callback)}, root, {
        message: {containerSelector: '[data-role="message"]'},
        serialize
    });

    assert.equal(context.message, message);
    assert.equal(context.dirty, dirty);
    assert.equal(context.serialize, serialize);
    assert.deepEqual(context.serialize(), {value: 1});
    assert.equal(root.veaContext, context);

    cleanups[0]();
    assert.equal(message.destroyed, true);
    assert.equal('veaContext' in root, false);
});

test('workspace context can stay private to a mounted controller', () => {
    const root = {};
    const contextFactory = load(
        {create: () => ({destroy() {}})},
        {create: () => ({})}
    );

    contextFactory.create({cleanup: () => {}}, root, {publish: false});

    assert.equal('veaContext' in root, false);
});
