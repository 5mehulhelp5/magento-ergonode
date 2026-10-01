'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const {backendRoot} = require('./module-paths.cjs');

const modulePath = path.resolve(
    __dirname,
    '../../view/adminhtml/web/js/template-autosave.js'
);
const coreModulePath = path.resolve(
    backendRoot,
    'vendor/ergonode/module-core-admin-ui/view/adminhtml/web/js/autosave.js'
);

function loadCoreModule() {
    let coreAutosave;

    vm.runInNewContext(fs.readFileSync(coreModulePath, 'utf8'), {
        define(dependencies, factory) {
            assert.deepEqual(Array.from(dependencies), []);
            coreAutosave = factory();
        },
        Error,
        Promise,
    }, {filename: coreModulePath});

    return coreAutosave;
}

function loadModule(request) {
    let autosave;

    vm.runInNewContext(fs.readFileSync(modulePath, 'utf8'), {
        define(dependencies, factory) {
            assert.deepEqual(Array.from(dependencies), [
                'Ergonode_CoreAdminUi/js/autosave',
                'Ergonode_CoreAdminUi/js/request'
            ]);
            autosave = factory(loadCoreModule(), request);
        },
        JSON,
        Promise,
    }, {filename: modulePath});

    return autosave;
}

function fixture() {
    const attributes = {};
    const error = {hidden: true};
    const region = {
        querySelector(selector) {
            return selector === '[data-role="autosave-error"]' ? error : null;
        },
        setAttribute(name, value) {
            attributes[name] = value;
        },
        removeAttribute(name) {
            delete attributes[name];
        },
    };

    return {
        attributes,
        error,
        root: {
            querySelector(selector) {
                return selector === '[data-role="autosave-region"]' ? region : null;
            },
        },
    };
}

function deferred() {
    let resolve;
    let reject;
    const promise = new Promise((resolvePromise, rejectPromise) => {
        resolve = resolvePromise;
        reject = rejectPromise;
    });

    return {promise, reject, resolve};
}

async function settle() {
    await Promise.resolve();
    await new Promise((resolve) => setImmediate(resolve));
}

test('serializes saves and persists the latest template snapshot after rapid changes', async () => {
    const view = fixture();
    const calls = [];
    const savedSnapshots = [];
    let revision = 1;
    const autosaveModule = loadModule({
        post(url, config, data) {
            const pending = deferred();

            calls.push({data, pending, url});
            return pending.promise;
        },
    });
    const autosave = autosaveModule.create(view.root, {
        urls: {save_mapping: '/save'},
    }, {
        serialize: () => ({
            mappings: {bag: revision},
            drafts: [{attribute_set_id: null, template_code: `draft_${revision}`}],
            visibility: [{active: true, code: 'bag', source: 'ergo'}],
            revision,
        }),
        onSaved: (response, snapshot) => {
            savedSnapshots.push(snapshot);
        },
    });

    autosave.schedule();
    revision = 2;
    autosave.schedule();

    assert.equal(calls.length, 1);
    assert.deepEqual(JSON.parse(calls[0].data.mappings), {bag: 1});
    assert.equal(Object.hasOwn(calls[0].data, 'drafts'), false);
    assert.deepEqual(JSON.parse(calls[0].data.visibility), [
        {active: true, code: 'bag', source: 'ergo'}
    ]);
    assert.equal(view.attributes['data-autosave-state'], 'saving');
    assert.equal(view.attributes['aria-busy'], 'true');
    assert.equal(view.attributes.inert, '');

    const flushed = autosave.flush();
    calls[0].pending.resolve({success: true});
    await settle();

    assert.equal(calls.length, 2);
    assert.deepEqual(JSON.parse(calls[1].data.mappings), {bag: 2});
    assert.equal(Object.hasOwn(calls[1].data, 'drafts'), false);
    calls[1].pending.resolve({success: true});
    await flushed;

    assert.equal(savedSnapshots.length, 1);
    assert.equal(savedSnapshots[0].revision, 2);
    assert.equal(view.attributes['data-autosave-state'], 'saved');
    assert.equal(view.attributes['aria-busy'], 'false');
    assert.equal(Object.hasOwn(view.attributes, 'inert'), false);
    assert.equal(view.error.hidden, true);
});

test('keeps a failed template snapshot pending and allows an explicit retry', async () => {
    const view = fixture();
    const error = new Error('save failed');
    let attempts = 0;
    const autosaveModule = loadModule({
        post() {
            attempts += 1;
            return attempts === 1 ? Promise.reject(error) : Promise.resolve({success: true});
        },
    });
    const autosave = autosaveModule.create(view.root, {
        urls: {save_mapping: '/save'},
    }, {
        serialize: () => ({drafts: [], mappings: {}, visibility: []}),
    });

    autosave.schedule();
    await settle();

    assert.equal(autosave.hasError(), true);
    assert.equal(view.attributes['data-autosave-state'], 'error');
    assert.equal(view.attributes['aria-busy'], 'false');
    assert.equal(Object.hasOwn(view.attributes, 'inert'), false);
    assert.equal(view.error.hidden, false);
    await assert.rejects(autosave.flush(), /save failed/);

    autosave.retry();
    await settle();

    assert.equal(attempts, 2);
    assert.equal(autosave.hasError(), false);
    assert.equal(view.attributes['data-autosave-state'], 'saved');
    assert.equal(view.error.hidden, true);
});
