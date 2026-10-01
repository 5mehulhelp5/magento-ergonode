const assert = require('node:assert/strict');
const test = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function fixture(options = {}, reject = false) {
    const calls = [];
    let click;
    let module;
    const button = {disabled: false, getAttribute: () => 'pl_PL',
        setAttribute() {}, removeAttribute() {}, classList: {toggle() {}}};
    const load = (file, dependencies) => {
        vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../view/adminhtml/web/js', file), 'utf8'), {
            window: {confirm: () => true, setTimeout: (callback) => { calls.push('reload scheduled'); }},
            define(names, factory) { module = factory(...names.map(name => name === 'mage/translate'
                ? value => value : dependencies[name])); },
        });
        return module;
    };
    const buttons = load('buttons.js', {});
    const removal = load('snapshot-removal.js', {
        'Ergonode_CoreAdminUi/js/buttons': buttons,
        'Ergonode_CoreAdminUi/js/entity-options': {bind() {}},
        'Ergonode_CoreAdminUi/js/request': {post() {
            calls.push('request');
            return reject ? Promise.reject(new Error('Offline')) : Promise.resolve({success: true});
        }},
    });
    removal.bind({claim: () => true, delegate(event, selector, handler) { click = handler; }}, {},
        {urls: {delete_snapshot: '/delete'}}, typeof options === 'function' ? options(calls) : options);
    return {calls, click: () => click({preventDefault() {}, stopImmediatePropagation() {}}, button)};
}
const settle = () => new Promise(resolve => setImmediate(resolve));

test('existing consumers retain the request and delayed reload without hooks', async () => {
    const view = fixture(); view.click(); await settle();
    assert.deepEqual(view.calls, ['request', 'reload scheduled']);
});
test('beforeRemove runs synchronously before HTTP and can decline removal', async () => {
    const view = fixture(calls => ({beforeRemove() { calls.push('start'); return false; }}));
    view.click(); assert.deepEqual(view.calls, ['start']); await settle();
    assert.deepEqual(view.calls, ['start']);
});
test('failure calls onError once after start, without scheduling reload', async () => {
    const view = fixture(calls => ({beforeRemove() { calls.push('start'); },
        onError(error) { calls.push(error.message); }}), true);
    view.click(); await settle();
    assert.deepEqual(view.calls, ['start', 'request', 'Offline']);
});
for (const options of [{confirm: () => false}, {isDirty: () => true}]) {
    test('cancellation and dirty state do not invoke lifecycle callbacks', async () => {
        const view = fixture(calls => ({...options, beforeRemove() { calls.push('start'); }}));
        view.click(); await settle(); assert.deepEqual(view.calls, []);
    });
}
