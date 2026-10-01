const assert = require('node:assert/strict');
const test = require('node:test');
const {fixture, element, settle, initialRevision, nextRevision} = require('./support/autosave-fixture.cjs');

function mappingWorkspace(canSave = true) {
    const view = fixture();
    view.config.canSave = canSave;
    const handlers = new Map();
    const messages = [];
    const panel = element();
    const button = element();
    button.disabled = false;
    button.closest = () => panel;
    // Minimal mapping DOM; serialization and the refresh handler run unchanged.
    const row = element();
    row.querySelector = (selector) => selector.includes('pair-slot')
        ? {getAttribute: () => selector.includes('ergo') ? view.state.code : '1'} : null;
    const cards = ['ergo', 'magento'].map((source) => ({
        getAttribute: (name) => name === 'data-source' ? source : source === 'ergo' ? view.state.code : '1',
        querySelector: () => null,
    }));
    view.root.querySelectorAll = (selector) => selector === '[data-role="mapping-row"]' ? [row]
        : selector === '[data-role="entity-card"]' ? cards : [];
    let autosave;
    let allowRemoval = true;
    const snapshotRemoval = view.load('CoreAdminUi/view/adminhtml/web/js/snapshot-removal.js', {
        'Ergonode_CoreAdminUi/js/request': view.request,
        'Ergonode_CoreAdminUi/js/buttons': view.load('CoreAdminUi/view/adminhtml/web/js/buttons.js'),
        'Ergonode_CoreAdminUi/js/entity-options': {bind() {}},
    });
    view.config.urls.delete_snapshot = '/delete';
    const initialize = view.load('LanguageAdminUi/view/adminhtml/web/js/language-mapping.js', {
        'Ergonode_CoreAdminUi/js/workspace': {mount(root, mount) {
            mount({
                delegate: (event, selector, handler) => handlers.set(`${event}:${selector}`, handler),
                listen() {}, cleanup() {}, claim: () => true,
            });
        }},
        'Ergonode_CoreAdminUi/js/text': {normalize: (value) => value.trim().toLowerCase()},
        'Ergonode_CoreAdminUi/js/workspace-context': {create: () => ({
            message: {
                show: (...args) => messages.push({kind: 'save', args}),
                error: (...args) => messages.push({kind: 'refresh', args}),
            },
            dirty: {capture: () => { view.state.captures++; }, isDirty: () => false},
        })},
        'Ergonode_CoreAdminUi/js/request': view.request,
        'Ergonode_CoreAdminUi/js/buttons': view.load('CoreAdminUi/view/adminhtml/web/js/buttons.js'),
        // Presentation collaborators do not participate in the tested save/refresh path.
        'Ergonode_CoreAdminUi/js/visibility-toggle': {initialize() {}},
        'Ergonode_CoreAdminUi/js/source-bulk-transfer': {bind() {}},
        'Ergonode_CoreAdminUi/js/entity-options': {bind() {}},
        'Ergonode_LanguageAdminUi/js/language-source-options': {initialize() {}, sync() {}},
        'Ergonode_LanguageAdminUi/js/language-autosave': {create(...args) {
            autosave = view.languageAutosave.create(...args);
            return autosave;
        }},
        'Ergonode_CoreAdminUi/js/mapping-requirements': {create: () => ({refresh() {}})},
        'Ergonode_CoreAdminUi/js/snapshot-removal': {bind(scope, root, config, options) {
            snapshotRemoval.bind(scope, root, config, {...options, confirm: () => allowRemoval});
        }},
    });
    initialize(view.config, view.root);
    return {...view, autosave, button, panel, messages, handlers,
        remove: () => {
            const target = element();
            target.setAttribute('data-code', 'pl_PL');
            handlers.get('click:[data-role="entity-delete-snapshot"]')({
                preventDefault() {}, stopImmediatePropagation() {},
            }, target);
        },
        cancelRemoval: () => { allowRemoval = false; },
        refresh: () => handlers.get('click:[data-role="refresh-ergonode"]')({}, button),
        retry: () => handlers.get('click:[data-role="retry-autosave"]')(),
    };
}

test('refresh flushes a debounced save and reloads only after the refresh succeeds', async () => {
    const view = mappingWorkspace();
    view.autosave.schedule();
    view.refresh();
    assert.equal(view.root.getAttribute('inert'), '', 'Refresh locks the entire editor before flushing');
    await settle();
    assert.deepEqual(view.calls.map((call) => call.url), ['/save']);
    assert.equal(view.calls[0].payload.mappings[0].left.code, 'pl_PL');
    assert.equal(view.button.disabled, true);
    assert.equal(view.panel.classList.contains('is-refreshing'), true);
    assert.equal(view.state.reloads, 0);
    view.refresh();
    await settle();
    assert.equal(view.calls.length, 1);

    view.calls[0].respond({success: true, revision: nextRevision});
    await settle();
    assert.deepEqual(view.calls.map((call) => call.url), ['/save', '/refresh']);
    assert.equal(view.region.getAttribute('inert'), null, 'Autosave has released its own region');
    assert.equal(view.root.getAttribute('inert'), '', 'The whole editor remains locked during refresh HTTP');
    assert.equal(view.state.reloads, 0);
    view.calls[1].respond({success: true, count: 2});
    await settle();
    assert.equal(view.state.reloads, 1);
    assert.equal(view.root.getAttribute('inert'), '', 'Do not reopen editing before navigation');
    assert.equal(view.button.disabled, false);
    assert.equal(view.config.revision, nextRevision);
    await view.time.advance(1000);
    assert.equal(view.calls.length, 2);
});

test('refresh waits for both the in-flight save and a newer queued revision', async () => {
    const view = mappingWorkspace();
    view.autosave.schedule();
    await view.time.advance(350);
    view.state.code = 'de_DE';
    view.autosave.schedule();
    view.refresh();
    await settle();
    view.calls[0].respond({success: true, revision: nextRevision});
    await settle();
    assert.deepEqual(view.calls.map((call) => call.url), ['/save', '/save']);
    assert.equal(view.calls[1].payload.revision, nextRevision);
    assert.equal(view.calls[1].payload.mappings[0].left.code, 'de_DE');
    assert.equal(view.state.reloads, 0);
    view.calls[1].respond({success: true, revision: 'c'.repeat(64)});
    await settle();
    assert.deepEqual(view.calls.map((call) => call.url), ['/save', '/save', '/refresh']);
    view.calls[2].respond({success: true, count: 2});
    await settle();
    assert.equal(view.state.reloads, 1);
});

for (const failure of ['network', 'conflict', 'invalid revision']) {
    test(`${failure} during save blocks refresh and keeps the unsaved mapping`, async () => {
        const view = mappingWorkspace();
        view.state.code = 'de_DE';
        view.autosave.schedule();
        view.refresh();
        await settle();
        if (failure === 'network') view.calls[0].reject(new Error('Offline'));
        else if (failure === 'conflict') {
            view.calls[0].respond({success: false, message: 'Reload the page.'}, 409);
        } else view.calls[0].respond({success: true});
        await settle();
        assert.equal(view.calls.length, 1);
        assert.equal(view.state.reloads, 0);
        assert.equal(view.state.code, 'de_DE');
        assert.equal(view.state.captures, 1, 'The unsaved state must not become the saved baseline');
        assert.equal(view.config.revision, initialRevision);
        assert.equal(view.autosave.hasError(), true);
        assert.equal(view.button.disabled, false);
        assert.equal(view.panel.classList.contains('is-refreshing'), false);
        assert.equal(view.root.getAttribute('inert'), null);
        assert.deepEqual(view.messages.map((message) => message.kind), ['save']);
        view.refresh();
        await settle();
        assert.equal(view.calls.length, 1, 'Another refresh must not bypass the failed save');
        assert.equal(view.state.reloads, 0);
    });
}

test('retrying a failed save enables a later refresh without losing the edited mapping', async () => {
    const view = mappingWorkspace();
    view.state.code = 'de_DE';
    view.autosave.schedule();
    view.refresh();
    await settle();
    view.calls[0].reject(new Error('Offline'));
    await settle();
    view.retry();
    await settle();
    assert.equal(view.calls[1].payload.revision, initialRevision);
    assert.equal(view.calls[1].payload.mappings[0].left.code, 'de_DE');
    view.calls[1].respond({success: true, revision: nextRevision});
    await settle();
    assert.equal(view.state.captures, 2);
    assert.equal(view.autosave.hasError(), false);
    view.refresh();
    await settle();
    assert.deepEqual(view.calls.map((call) => call.url), ['/save', '/save', '/refresh']);
    view.calls[2].respond({success: true, count: 2});
    await settle();
    assert.equal(view.state.reloads, 1);
});

test('refresh failure after a successful save preserves its revision and allows another refresh', async () => {
    const view = mappingWorkspace();
    view.autosave.schedule();
    view.refresh();
    await settle();
    view.calls[0].respond({success: true, revision: nextRevision});
    await settle();
    view.calls[1].respond({success: false, message: 'Ergonode unavailable'}, 500);
    await settle();
    assert.equal(view.state.reloads, 0);
    assert.equal(view.config.revision, nextRevision);
    assert.equal(view.autosave.hasError(), false);
    assert.equal(view.button.disabled, false);
    assert.equal(view.panel.classList.contains('is-refreshing'), false);
    assert.equal(view.root.getAttribute('inert'), null);
    assert.equal(view.messages.length, 1);
    assert.equal(view.messages[0].kind, 'refresh');
    assert.equal(view.messages[0].args[1].message, 'Ergonode unavailable');
    assert.equal(view.messages[0].args[3].url, '/configuration');
    view.refresh();
    await settle();
    assert.deepEqual(view.calls.map((call) => call.url), ['/save', '/refresh', '/refresh']);
    view.calls[2].respond({success: true, count: 2});
    await settle();
    assert.equal(view.state.reloads, 1);
});

test('viewer has browsing and refresh handlers but no mutation entrypoints', async () => {
    const view = mappingWorkspace(false);
    const bound = [...view.handlers.keys()];
    assert.deepEqual(bound.sort(), [
        'click:[data-role="refresh-ergonode"]',
        'click:[data-role="visibility-toggle"]',
        'input:[data-role="source-search"]',
    ]);
    view.refresh();
    await settle();
    assert.deepEqual(view.calls.map((call) => call.url), ['/refresh']);
    assert.equal(view.root.getAttribute('inert'), '');
    view.calls[0].respond({success: false, message: 'Offline'}, 500);
    await settle();
    assert.equal(view.root.getAttribute('inert'), null);
});

test('clean refresh locks the editor throughout the request, without creating a save', async () => {
    const view = mappingWorkspace();
    view.refresh();
    assert.equal(view.root.getAttribute('inert'), '');
    await settle();
    assert.deepEqual(view.calls.map((call) => call.url), ['/refresh']);
    assert.equal(view.root.getAttribute('inert'), '');
    view.calls[0].respond({success: true, count: 2});
    await settle();
    assert.equal(view.state.reloads, 1);
    assert.equal(view.root.getAttribute('inert'), '');
    await view.time.advance(1000);
    assert.equal(view.calls.length, 1);
});


test('snapshot deletion locks through its delayed reload and prevents concurrent refresh/removal', async () => {
    const view = mappingWorkspace();
    view.remove();
    assert.equal(view.root.getAttribute('inert'), '');
    view.refresh();
    view.remove();
    await settle();
    assert.deepEqual(view.calls.map((call) => call.url), ['/delete']);
    view.calls[0].respond({success: true});
    await settle();
    await view.time.advance(349);
    assert.equal(view.state.reloads, 0);
    assert.equal(view.root.getAttribute('inert'), '');
    await view.time.advance(1);
    assert.equal(view.state.reloads, 1);
    assert.equal(view.root.getAttribute('inert'), '');
});

test('cancelled deletion leaves editing enabled; failed deletion unlocks and allows refresh', async () => {
    const cancelled = mappingWorkspace();
    cancelled.cancelRemoval();
    cancelled.remove();
    await settle();
    assert.equal(cancelled.root.getAttribute('inert'), null);
    assert.equal(cancelled.calls.length, 0);
    const view = mappingWorkspace();
    view.remove();
    await settle();
    view.calls[0].reject(new Error('Offline'));
    await settle();
    assert.equal(view.root.getAttribute('inert'), null);
    assert.equal(view.state.reloads, 0);
    view.refresh();
    await settle();
    assert.deepEqual(view.calls.map((call) => call.url), ['/delete', '/refresh']);
});
