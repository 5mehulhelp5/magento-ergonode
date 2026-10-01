'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const jsRoot = path.resolve(__dirname, '../../view/adminhtml/web/js');

function fixture(controls = {}) {
    let options, now = 0;
    const timers = new Map();
    const actions = [];
    function element(tagName = 'div') {
        const children = new Map(), attributes = new Map(), listeners = {};
        let content = '';
        return {
            tagName, hidden: false, style: {}, childNodes: [],
            get textContent() { return content + this.childNodes.map((child) => child.textContent).join(''); },
            set textContent(value) { content = String(value); this.childNodes = []; },
            set innerHTML(html) {
                for (const match of html.matchAll(/<[^>]*data-role="([^"]+)"[^>]*>/g)) {
                    const child = element();
                    child.hidden = /\bhidden\b/.test(match[0]);
                    children.set(`[data-role="${match[1]}"]`, child);
                    this.childNodes.push(child);
                }
            },
            querySelector: (selector) => children.get(selector),
            setAttribute: (key, value) => attributes.set(key, value),
            getAttribute: (key) => attributes.get(key),
            removeAttribute: (key) => attributes.delete(key),
            addEventListener: (key, callback) => { listeners[key] = callback; },
            click: () => listeners.click?.(),
            appendChild(child) { this.childNodes.push(child); return child; },
            remove() {}, focus() {}
        };
    }
    const dependencies = {
        jquery: () => ({modal: (action) => actions.push(action), closest: () => ({remove() {}})}),
        'Magento_Ui/js/modal/modal': (value) => { options = value; },
        'mage/translate': (text) => text,
        'text!ui/template/modal/modal-popup.html': '<aside role="dialog"></aside>'
    };
    function load(file) {
        let exported;
        vm.runInNewContext(fs.readFileSync(path.join(jsRoot, file + '.js'), 'utf8'), {
            define: (names, callback) => { exported = callback(...names.map((name) =>
                dependencies[name] || load(name.split('/').at(-1)))); },
            document: {createElement: element, body: element()},
            Date: {now: () => now},
            window: {
                setInterval: (callback) => { const id = Symbol(); timers.set(id, callback); return id; },
                clearInterval: (id) => timers.delete(id)
            }
        });
        return exported;
    }
    const progress = load('category-sync-progress')(controls);
    return {
        progress, actions, timers,
        node: (role) => progress.element.querySelector(`[data-role="sync-progress-${role}"]`),
        escape: () => options.keyEventHandlers.escapeKey(),
        advance: (ms) => { now += ms; timers.forEach((tick) => tick()); }
    };
}

test('running dialog has no percentage, measures elapsed time and cannot close early', () => {
    const f = fixture();
    f.progress.open('tree', true);
    assert.match(f.node('scope').textContent, /Sync \(force\).*All active/);
    assert.equal(f.node('status').textContent, 'Synchronizing category trees…');
    assert.equal(f.node('bar').getAttribute('aria-valuenow'), undefined);
    assert.equal(f.node('close').hidden, true);
    f.advance(65000);
    assert.equal(f.node('time').textContent, 'Elapsed time: 1:05');
    f.escape();
    f.node('close').click();
    assert.deepEqual(f.actions, ['openModal']);
});

test('success, conflicts and failure stop the timer and allow closing; reopening resets state', () => {
    const f = fixture();
    for (const outcome of ['success', 'warning', 'error']) {
        f.progress.open('data', false);
        assert.equal(f.node('time').textContent, 'Elapsed time: 0:00');
        assert.equal(f.node('bar').hidden, false);
        assert.equal(f.timers.size, 1);
        f.advance(2000);
        f.progress.finish(outcome, '<script>operator message</script>');
        assert.equal(f.progress.element.getAttribute('data-state'), outcome);
        assert.equal(f.node('status').textContent, '<script>operator message</script>');
        assert.equal(f.timers.size, 0);
        assert.equal(f.node('bar').hidden, true);
        assert.equal(f.node('close').hidden, false);
        f.advance(60000);
        assert.equal(f.node('time').textContent, 'Elapsed time: 0:02');
        f.escape();
        assert.equal(f.actions.at(-1), 'closeModal');
    }
    f.progress.open('all', false);
    f.progress.destroy();
    assert.equal(f.timers.size, 0);
    assert.equal(f.actions.at(-1), 'destroy');
});

function synchronizationFixture({dirty = false, confirm = true, disabled = false} = {}) {
    const source = fs.readFileSync(path.join(jsRoot, 'category-mapping-consumer.js'), 'utf8');
    const syncFunctions = source.slice(source.indexOf('        function synchronizeCategoryTrees('),
        source.indexOf('        function resetCategoryTreeSyncCursor('));
    const requests = [], messages = [], applied = [], progress = [], events = [];
    let busy = false, confirmations = 0;
    const button = {
        prop: () => disabled,
        attr: (key) => key === 'data-synchronization-scope' ? 'tree' : 'false'
    };
    const scheduled = new Map();
    let counter = 0, createRun;
    const browser = {
        confirm: () => { confirmations++; return confirm; },
        crypto: {getRandomValues: (bytes) => bytes.fill(++counter)},
        setTimeout: (callback) => { const key = Symbol(); scheduled.set(key, callback); return key; },
        clearTimeout: (key) => scheduled.delete(key)
    };
    vm.runInNewContext(fs.readFileSync(path.join(jsRoot, 'category-sync-run.js'), 'utf8'), {
        window: browser, Uint8Array,
        define: (_names, callback) => { createRun = callback((text) => text); }
    });
    const context = {
        api: {isDirty: () => dirty, setOperationPending() {}},
        dirty, synchronizationPending: false, syncProgress: null, syncRun: null, syncButton: null,
        categoryTreeId: 7, config: {urls: {sync: '/sync', sync_status: '/status', sync_pause: '/pause'}},
        $root: {trigger: (event) => events.push(event)},
        createSyncRun: createRun,
        $t: (text) => text,
        window: browser,
        createSyncProgress: () => ({open: (...args) => progress.push(['open', ...args]),
            finish: (...args) => progress.push(['finish', ...args]),
            update: (value) => progress.push(['update', value]), requestPause() {}}),
        setBusy: (_button, value) => { busy = value; },
        applyCategoryTreeConfig: (value) => applied.push(value),
        showMessage: (...args) => messages.push(args),
        post: (url, data, timeout) => {
            const handlers = {};
            const promise = Object.fromEntries(['done', 'fail', 'always'].map((key) =>
                [key, (callback) => { handlers[key] = callback; return promise; }]));
            requests.push({url, data, timeout,
                resolve: (value) => { handlers.done?.(value); handlers.always?.(); },
                reject: () => { handlers.fail?.(); handlers.always?.(); }});
            return promise;
        }
    };
    vm.createContext(context);
    vm.runInContext(syncFunctions, context);
    return {requests, messages, applied, progress, events, busy: () => busy, confirmations: () => confirmations,
        run: (force = false) => context.synchronizeCategoryTrees(button, force),
        pause: () => context.syncRun.pause(), resume: () => context.syncRun.resume(),
        tick: () => { const callbacks = [...scheduled.values()]; scheduled.clear(); callbacks.forEach((tick) => tick()); }};
}

test('workspace opens progress before the request, blocks duplicate sync, and applies completed models', () => {
    const f = synchronizationFixture();
    f.run(true);
    assert.equal(f.confirmations(), 1);
    assert.deepEqual(f.progress, [['open', 'tree', true]]);
    assert.equal(f.busy(), true);
    assert.equal(f.requests[0].data.synchronization_action, 'reset-cursor-and-sync');
    f.run();
    assert.equal(f.requests.length, 1);
    const config = {category_tree_id: 3};
    f.requests[0].resolve({state: 'warning', success: false, completed: true, config, message: 'Completed with 2 conflicts.'});
    assert.deepEqual(f.applied, [config]);
    assert.deepEqual(f.progress.at(-1), ['finish', 'warning', 'Completed with 2 conflicts.',
        {state: 'warning', success: false, completed: true, config, message: 'Completed with 2 conflicts.'}]);
    assert.equal(f.busy(), false);
    f.run();
    f.requests[1].resolve({state: 'success', success: true, completed: true, config, message: 'No new changes in Ergonode.'});
    assert.deepEqual(f.progress.at(-1), ['finish', 'success', 'No new changes in Ergonode.',
        {state: 'success', success: true, completed: true, config, message: 'No new changes in Ergonode.'}]);
});

test('cancelled or disabled actions neither open a popup nor send a request', () => {
    for (const options of [{dirty: true, confirm: false}, {confirm: false}, {disabled: true}]) {
        const f = synchronizationFixture(options);
        f.run(true);
        assert.equal(f.requests.length, 0);
        assert.equal(f.progress.length, 0);
    }
});

test('errors and completed configuration failures finish feedback and release busy state', () => {
    for (const response of [{state: 'error', message: 'API unavailable'},
        {state: 'success', config: {status: {error: 'Could not load tree'}}}]) {
        const f = synchronizationFixture();
        f.run();
        f.requests[0].resolve(response);
        assert.equal(f.progress.at(-1)[1], 'error');
        assert.equal(f.busy(), false);
    }
});

test('a lost execution response is recovered by status polling without starting another synchronization', () => {
    const f = synchronizationFixture();
    f.run();
    assert.equal(f.requests[0].timeout, 0);
    f.requests[0].reject();
    f.tick();
    assert.equal(f.requests[1].url, '/status');
    f.requests[1].resolve({state: 'running', stage: 'moving_category', processed: 42, total: 115});
    assert.equal(f.busy(), true);
    assert.equal(f.progress.at(-1)[0], 'update');
    f.run();
    assert.equal(f.requests.filter((r) => r.url === '/sync').length, 1);
    f.tick();
    f.requests[2].resolve({state: 'success', message: 'Done', config: {category_tree_id: 7}});
    assert.equal(f.busy(), false);
    assert.deepEqual(f.progress.at(-1), ['finish', 'success', 'Done',
        {state: 'success', message: 'Done', config: {category_tree_id: 7}}]);
    f.requests[0].resolve({state: 'error', message: 'Late response'});
    assert.deepEqual(f.progress.at(-1), ['finish', 'success', 'Done',
        {state: 'success', message: 'Done', config: {category_tree_id: 7}}]);
});

test('pause is acknowledged by the backend and resume uses the same run and scope only on demand', () => {
    const f = synchronizationFixture();
    f.run(true);
    const original = f.requests[0].data;
    f.pause();
    assert.equal(f.requests[1].url, '/pause');
    assert.equal(f.requests[1].data.run_id, original.run_id);
    f.requests[1].resolve({success: true});
    assert.equal(f.busy(), true);
    f.tick();
    f.requests[2].resolve({state: 'paused', message: 'Paused'});
    assert.equal(f.busy(), false);
    f.resume();
    assert.equal(f.busy(), true);
    assert.equal(f.requests[3].data.run_id, original.run_id);
    assert.equal(f.requests[3].data.resume, 1);
    assert.equal(f.requests[3].data.synchronization_action, 'reset-cursor-and-sync');
    f.requests[0].resolve({state: 'paused', message: 'Late pause response'});
    assert.equal(f.busy(), true);
});

test('repeated unavailable or invalid status responses never report a false completion', () => {
    for (const invalid of [false, true]) {
        const f = synchronizationFixture();
        f.run();
        f.requests[0].reject();
        for (let i = 0; i < 3; i++) {
            f.tick();
            if (invalid) f.requests.at(-1).resolve(null);
            else f.requests.at(-1).reject();
        }
        assert.match(f.progress.at(-1)[2], /may still be running/);
        assert.equal(f.busy(), false);
    }
});

test('popup displays real counts, confirms pause before enabling resume, and keeps the text safe', () => {
    let pause = 0, resume = 0;
    const f = fixture({pause: () => pause++, resume: () => resume++});
    f.progress.open('tree', false);
    f.progress.update({stage: 'moving_category', processed: 42, total: 115, item: '<b>code</b>'});
    assert.equal(f.node('status').textContent, 'Updating category order and product indexes…');
    assert.equal(f.node('count').textContent, 'Processed: 42 of 115');
    assert.equal(f.node('percent').textContent, '36%');
    f.node('pause').click();
    assert.equal(pause, 1);
    f.progress.requestPause(true);
    assert.equal(f.node('pause').disabled, true);
    assert.equal(f.node('resume').hidden, true);
    f.progress.finish('paused', 'Paused');
    assert.equal(f.node('resume').hidden, false);
    assert.equal(f.node('close').hidden, false);
    f.node('resume').click();
    assert.equal(resume, 1);
});

test('ordinary requests retain a timeout while a sync can wait longer than a minute', () => {
    const source = fs.readFileSync(path.join(require('./project-paths.cjs').moduleRoot('CategoryAdminUi', 'module-category-admin-ui'), 'view/adminhtml/web/js/category-tree-mapping.js'), 'utf8');
    const post = source.slice(source.indexOf('        function post('), source.indexOf('        function getSourceRoots('));
    const calls = [];
    const context = {config: {form_key: 'csrf'}, $: {ajax: (options) => calls.push(options)}};
    vm.createContext(context);
    vm.runInContext(post, context);
    context.post('/save', {});
    context.post('/sync', {}, 0);
    assert.equal(calls[0].timeout, 60000);
    assert.equal(calls[1].timeout, 0);
    assert.equal(calls[1].data.form_key, 'csrf');
});


test('download progress shows real counts without an invented total, and resets on restart', () => {
    const f = fixture();
    f.progress.open('tree', true);
    f.progress.update({stage: 'fetching_tree', tree_code: 'tghome_1', tree_number: 3,
        tree_total: 3, pages: 2, downloaded: 1400, total: null});
    assert.equal(f.node('tree').textContent, 'Tree 3 of 3: tghome_1');
    assert.equal(f.node('download').textContent, 'Downloaded: 1400 categories · 2 pages');
    assert.equal(f.node('bar').getAttribute('aria-valuenow'), undefined);
    f.progress.finish('error', 'Missing mapping: is_active.');
    assert.equal(f.node('status').textContent, 'Missing mapping: is_active.');
    f.progress.open('tree', false);
    assert.equal(f.node('download').textContent, '');
    assert.equal(f.node('tree').textContent, '');
});

test('ETA uses samples within a stage, stops on pause, and is relearned after resume', () => {
    const f = fixture();
    const point = (processed) => ({stage: 'creating_category', tree_code: 'tghome_1', processed, total: 100});
    f.progress.open('all', false);
    f.progress.update(point(10));
    assert.equal(f.node('eta').textContent, 'Estimating remaining time…');
    f.advance(5000);
    f.progress.update(point(20));
    assert.equal(f.node('eta').textContent, 'About 40 sec remaining');
    f.progress.requestPause(true);
    assert.equal(f.node('eta').textContent, 'Waiting for a safe pause…');
    f.progress.finish('paused', 'Paused', point(21));
    assert.equal(f.node('bar').hidden, false);
    assert.equal(f.node('bar').getAttribute('aria-valuenow'), '21');
    assert.equal(f.node('eta').textContent, 'Estimate paused');
    f.advance(120000);
    f.progress.open('all', false);
    f.progress.update(point(21));
    assert.equal(f.node('eta').textContent, 'Estimating remaining time…');
});

test('global data progress does not inherit the last structural tree or its download counter', () => {
    const f = fixture();
    const base = {tree_code: 'tghome_1', tree_number: 3, tree_total: 3, pages: 3, downloaded: 1871};
    f.progress.open('all', false);
    f.progress.update({...base, stage: 'creating_category', processed: 50, total: 1871});
    f.progress.update({...base, stage: 'fetching_category_data', processed: 2, total: 10, item: '<b>code</b>'});
    assert.equal(f.node('tree').textContent, 'All mapped categories');
    assert.equal(f.node('download').hidden, true);
    assert.equal(f.node('item').textContent, 'Current category: <b>code</b>');
    assert.equal(f.node('bar').getAttribute('aria-valuenow'), '2');
    assert.equal(f.node('eta').textContent, 'Estimating remaining time…');
    f.advance(31000);
    assert.equal(f.node('eta').textContent, 'Waiting for progress…');
    assert.equal(f.node('percent').textContent, '20%');
});

function descendants(node, tag) {
    return node.childNodes.flatMap((child) => [ ...(child.tagName === tag ? [child] : []), ...descendants(child, tag) ]);
}

test('completion selects the conflicted tree, groups samples, and never shows terminal 0 of 0', () => {
    const f = fixture();
    f.progress.open('all', false);
    const conflicts = ['Stored mapping for "sztucce" points outside the configured Magento root.',
        'Stored mapping for "szklo" points outside the configured Magento root.',
        'Unable to reconcile category "dekoracje": Could not save category: URL key for specified store already exists.'];
    const response = {stage: 'saving_tree_cursor', processed: 0, total: 0, stats: {results: {tree: {tree_results: [
        {tree_code: 'default', conflict_count: 0},
        {tree_code: 'tghome_1', conflict_count: 1867, conflicts, stats: {created: 16, unmatched: 1871}}
    ]}}}};
    f.progress.finish('warning', 'Completed with conflicts', response);
    const host = f.node('results');
    const buttons = descendants(host, 'button');
    assert.equal(buttons[1].getAttribute('aria-pressed'), 'true');
    assert.equal(descendants(host, 'details').length, 2);
    assert.match(host.textContent, /Examples shown: 2/);
    assert.match(host.textContent, /Showing 3 of 1867 conflicts/);
    assert.equal(f.node('metrics').hidden, true);
    assert.equal(f.node('count').textContent, '');
    buttons[0].click();
    assert.equal(buttons[0].getAttribute('aria-pressed'), 'true');
    assert.match(host.textContent, /No conflicts\./);
    buttons[1].click();
    const group = descendants(host, 'details')[0];
    f.progress.update(response);
    assert.equal(descendants(host, 'details')[0], group);
});


test('committed operation totals remain visible after completion and reset for a new run', () => {
    const f = fixture();
    f.progress.open('tree', false);
    f.progress.update({stage: 'creating_category', processed: 30, total: 100,
        operations: {created: 28, moved: 12, deleted: 3}});
    assert.equal(f.node('operations').hidden, false);
    assert.equal(f.node('created').textContent, '28');
    assert.equal(f.node('moved').textContent, '12');
    assert.equal(f.node('deleted').textContent, '3');
    f.progress.finish('paused', 'Paused', {operations: {created: 30, moved: 12, deleted: 3}});
    assert.equal(f.node('created').textContent, '30');
    f.progress.open('tree', false);
    assert.equal(f.node('created').textContent, '0');
    f.progress.finish('success', 'Done', {operations: {created: 32, moved: 14, deleted: 4}});
    assert.equal(f.node('created').textContent, '32');
    assert.equal(f.node('operations').hidden, false);
    f.progress.open('data', false);
    assert.equal(f.node('operations').hidden, true);
});

test('completion recovered by polling refreshes the tree and history underneath the popup once', () => {
    const f = synchronizationFixture();
    f.run();
    f.tick();
    const response = {state: 'success', config: {category_tree_id: 7,
        categories: [{code: 'new', magento_category_id: 42}], magento_categories: [{id: 42}]}};
    f.requests[1].resolve(response);
    f.requests[0].resolve(response);
    assert.deepEqual(f.applied, [response.config]);
    assert.deepEqual(f.events, ['ergonode:category-mapping:refreshed']);
    assert.equal(f.progress.at(-1)[0], 'finish');
});
