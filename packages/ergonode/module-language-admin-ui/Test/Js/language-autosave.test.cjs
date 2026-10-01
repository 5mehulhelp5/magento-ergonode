const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const modulePath = path.resolve(
    __dirname,
    '../../view/adminhtml/web/js/language-autosave.js'
);
const coreModulePath = path.resolve(
    __dirname,
    '../../../../../vendor/ergonode/module-core-admin-ui/view/adminhtml/web/js/autosave.js'
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
                'Ergonode_CoreAdminUi/js/request',
                'mage/translate'
            ]);
            autosave = factory(loadCoreModule(), request, (value) => value);
        },
        JSON,
        Promise,
        setTimeout,
        clearTimeout,
        window: {setTimeout, clearTimeout},
    }, { filename: modulePath });

    return autosave;
}

function fixture() {
    const attributes = {};
    const error = { hidden: true };
    const loader = { hidden: true };
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
                if (selector === '[data-role="autosave-region"]') {
                    return region;
                }
                return selector === '[data-role="language-loader"]' ? loader : null;
            },
            setAttribute(name, value) {
                attributes[name] = value;
            },
            removeAttribute(name) {
                delete attributes[name];
            },
        },
        loader,
    };
}

function deferred() {
    let resolve;
    let reject;
    const promise = new Promise((resolvePromise, rejectPromise) => {
        resolve = resolvePromise;
        reject = rejectPromise;
    });

    return { promise, reject, resolve };
}

async function settle() {
    await Promise.resolve();
    await new Promise((resolve) => setImmediate(resolve));
}

async function wait(milliseconds) {
    await new Promise((resolve) => setTimeout(resolve, milliseconds));
}

test('serializes saves and persists the latest snapshot after rapid changes', async () => {
    const view = fixture();
    const calls = [];
    let revision = 1;
    let saved = 0;
    const autosaveModule = loadModule({
        post(url, config, data) {
            const pending = deferred();

            calls.push({ data, pending, url });
            return pending.promise;
        },
    });
    const autosave = autosaveModule.create(view.root, {
        urls: { save: '/save' },
        revision: 'a'.repeat(64),
    }, {
        serialize: () => ({ edit: revision }),
        debounceMs: 10,
        onSaved: () => {
            saved += 1;
        },
    });

    autosave.schedule();
    revision = 2;
    autosave.schedule();

    assert.equal(calls.length, 0);
    await wait(15);
    assert.equal(calls.length, 1);
    assert.deepEqual(JSON.parse(calls[0].data.payload), { edit: 2, revision: 'a'.repeat(64) });
    assert.equal(view.attributes['data-autosave-state'], 'saving');
    assert.equal(view.attributes['aria-busy'], 'true');
    assert.equal(view.attributes.inert, '');
    assert.equal(view.loader.hidden, false);

    const flushed = autosave.flush();
    calls[0].pending.resolve({ success: true, revision: 'b'.repeat(64) });
    await settle();

    await flushed;

    assert.equal(saved, 1);
    assert.equal(view.attributes['data-autosave-state'], 'saved');
    assert.equal(view.attributes['aria-busy'], 'false');
    assert.equal(Object.hasOwn(view.attributes, 'inert'), false);
    assert.equal(view.loader.hidden, true);
    assert.equal(view.error.hidden, true);
});

test('keeps a failed snapshot pending and allows an explicit retry', async () => {
    const view = fixture();
    const error = new Error('save failed');
    let attempts = 0;
    let reportedError = null;
    const autosaveModule = loadModule({
        post() {
            attempts += 1;
            return attempts === 1 ? Promise.reject(error) : Promise.resolve({ success: true, revision: 'b'.repeat(64) });
        },
    });
    const autosave = autosaveModule.create(view.root, {
        urls: { save: '/save' },
        revision: 'a'.repeat(64),
    }, {
        serialize: () => ({ mappings: [] }),
        debounceMs: 10,
        onError: (failure) => {
            reportedError = failure;
        },
    });

    autosave.schedule();
    await wait(15);
    await settle();

    assert.equal(autosave.hasError(), true);
    assert.equal(view.attributes['data-autosave-state'], 'error');
    assert.equal(view.attributes['aria-busy'], 'false');
    assert.equal(Object.hasOwn(view.attributes, 'inert'), false);
    assert.equal(view.error.hidden, false);
    assert.equal(view.loader.hidden, true);
    assert.equal(reportedError, error);
    await assert.rejects(autosave.flush(), /save failed/);

    autosave.retry();
    await settle();

    assert.equal(attempts, 2);
    assert.equal(autosave.hasError(), false);
    assert.equal(view.attributes['data-autosave-state'], 'saved');
    assert.equal(view.error.hidden, true);
});


test('a conflict never adopts a newer revision or retries automatically', async () => {
    const view = fixture();
    const config = {urls: {save: '/save'}, revision: 'a'.repeat(64)};
    const calls = [];
    const autosave = loadModule({post(url, settings, data) {
        calls.push(JSON.parse(data.payload));
        return Promise.reject(new Error('Mappings changed. Reload the page.'));
    }}).create(view.root, config, {debounceMs: 10, serialize: () => ({mappings: []})});
    autosave.schedule();
    await wait(15);
    await settle();
    assert.equal(calls.length, 1);
    assert.equal(config.revision, 'a'.repeat(64));
    assert.equal(autosave.hasError(), true);
    autosave.retry();
    await settle();
    await settle();
    assert.equal(calls.length, 2);
    assert.equal(calls[1].revision, 'a'.repeat(64));
});

test('a response without a revision leaves the save in error', async () => {
    const view = fixture();
    const config = {urls: {save: '/save'}, revision: 'a'.repeat(64)};
    const autosave = loadModule({post: () => Promise.resolve({success: true})})
        .create(view.root, config, {debounceMs: 10, serialize: () => ({mappings: []})});
    autosave.schedule();
    await wait(15);
    await settle();
    assert.equal(autosave.hasError(), true);
    assert.equal(config.revision, 'a'.repeat(64));
});
