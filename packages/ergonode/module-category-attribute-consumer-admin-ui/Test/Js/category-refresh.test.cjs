'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const test = require('node:test');

const source = fs.readFileSync(path.resolve(__dirname,
    '../../view/adminhtml/web/js/form/element/category-refresh.js'), 'utf8');

function observable(initial) {
    let value = initial;
    return function (next) {
        if (arguments.length) value = next;
        return value;
    };
}

function fixture() {
    let definition, resolve, reject, requests = 0, reloads = 0, dirty = false;
    const provider = 'category_form.category_form_data_source';
    const fields = [{provider, hasChanged: () => dirty},
        {provider: 'another_form', hasChanged: () => true}];
    const jquery = {ajax: () => {
        requests++;
        return {done(fn) { resolve = fn; return this; }, fail(fn) { reject = fn; return this; }};
    }};
    const dependencies = {
        jquery,
        uiRegistry: {filter: (predicate) => fields.filter(predicate)},
        'Magento_Ui/js/form/element/abstract': {extend: (value) => value},
        'mage/translate': (value) => value
    };
    vm.runInNewContext(source, {
        define: (names, factory) => { definition = factory(...names.map((name) => dependencies[name])); },
        window: {FORM_KEY: 'test', location: {reload: () => { reloads++; }}}, Promise, Error
    });
    const instance = Object.assign(Object.create(definition), {
        provider, urls: {refresh: '/refresh'}, categoryId: observable(12), categoryCode: observable('chairs'),
        busy: observable(false), message: observable(''), messageType: observable(''), visible: observable(true)
    });
    return {
        instance, dirty: () => { dirty = true; }, requests: () => requests, reloads: () => reloads,
        finish: async (success = true) => {
            if (success) resolve({success: true, reload: true});
            else reject({responseJSON: {message: 'Download failed'}});
            await new Promise(setImmediate);
        }
    };
}

test('dirty category form blocks refresh without losing its edits', () => {
    const f = fixture();
    f.dirty();
    f.instance.refresh();
    assert.equal(f.requests(), 0);
    assert.match(f.instance.message(), /Save.*changes/i);
    assert.equal(f.instance.busy(), false);
});

test('clean category ignores other forms and reloads after successful refresh', async () => {
    const f = fixture();
    f.instance.refresh();
    f.instance.refresh();
    assert.equal(f.requests(), 1);
    await f.finish();
    assert.equal(f.reloads(), 1);
    assert.equal(f.instance.busy(), false);
});

test('edits made during refresh prevent automatic reload', async () => {
    const f = fixture();
    f.instance.refresh();
    f.dirty();
    await f.finish();
    assert.equal(f.reloads(), 0);
    assert.match(f.instance.message(), /changes/i);
    assert.equal(f.instance.busy(), false);
});

for (const success of [true, false]) {
    test(`late ${success ? 'success' : 'failure'} cannot update another category`, async () => {
        const f = fixture();
        f.instance.refresh();
        f.instance.categoryId(24);
        f.instance.categoryCode('tables');
        await f.finish(success);
        assert.equal(f.reloads(), 0);
        assert.equal(f.instance.message(), '');
        assert.equal(f.instance.busy(), false);
    });
}

test('transport failure preserves edits and releases the refresh button', async () => {
    const f = fixture();
    f.instance.refresh();
    f.dirty();
    await f.finish(false);
    assert.equal(f.reloads(), 0);
    assert.equal(f.instance.message(), 'Download failed');
    assert.equal(f.instance.busy(), false);
});
