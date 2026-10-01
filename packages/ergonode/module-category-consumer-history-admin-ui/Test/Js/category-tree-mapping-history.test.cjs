'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const source = fs.readFileSync(path.resolve(__dirname, '../../view/adminhtml/web/js/category-tree-mapping-history.js'), 'utf8');
const styles = fs.readFileSync(path.resolve(__dirname, '../../view/adminhtml/web/css/category-tree-history.css'), 'utf8');

function harness(initialTreeId) {
    const events = new Map();
    const requests = [];
    const renders = [];
    const workspace = {};
    const message = {
        hidden: true,
        children: [],
        replaceChildren() { this.children = []; },
        append(child) { this.children.push(child); }
    };
    const element = {
        closest: () => workspace,
        querySelector: (selector) => selector.includes('history-message') ? message : {},
        setAttribute() {}
    };
    if (initialTreeId) {
        workspace.veaCategoryMappingApi = {getCategoryTreeId: () => initialTreeId};
    }
    const jquery = () => ({on(names, callback) {
        names.split(' ').forEach((name) => events.set(name.split('.')[0], callback));
    }});
    jquery.ajax = (options) => {
        const handlers = {};
        const request = {
            options,
            aborted: false,
            done(callback) { handlers.done = callback; return request; },
            fail(callback) { handlers.fail = callback; return request; },
            always(callback) { handlers.always = callback; return request; },
            abort() { request.aborted = true; handlers.fail({}, 'abort'); handlers.always(); },
            resolve(page) { handlers.done({success: true, page}); handlers.always(); },
            reject() { handlers.fail({}, 'error'); handlers.always(); }
        };
        requests.push(request);
        return request;
    };
    let initialize;
    vm.runInNewContext(source, {
        URL,
        window: {location: {href: 'https://magento.test/mapping'}},
        document: {createElement: () => ({addEventListener(name, callback) { this[name] = callback; }})},
        define(dependencies, factory) {
            initialize = factory(jquery, (value) => value, {render(list, options) {
                renders.push({...options, operations: Array.from(options.operations)});
            }});
        }
    });
    initialize({urls: {history: '/history/key/secret/', operations: '/operations'}}, element);
    return {
        requests, renders, message,
        latest: () => renders.at(-1),
        select(treeId) {
            events.get('ergonode:category-mapping:ready')({}, {getCategoryTreeId: () => treeId});
        },
        saved() { events.get('ergonode:category-mapping:saved')(); },
        removed() { events.get('ergonode:category-mapping:snapshot-removed')(); }
    };
}

function page(ids, total = ids.length, cursor = null) {
    return {
        items: ids.map((operation_id) => ({operation_id})),
        total, page_size: 10, has_more: cursor !== null, next_before_id: cursor
    };
}

test('waits for a selected mapping and requests only its summary endpoint', () => {
    const app = harness();

    assert.equal(app.requests.length, 0);
    app.select(7);
    const request = app.requests[0];
    assert.equal(request.options.url, '/operations');
    assert.equal(request.options.method, 'GET');
    assert.deepEqual({...request.options.data}, {category_tree_id: 7});
    request.resolve(page([50, 49]));
    assert.equal(app.requests.length, 1);
    assert.equal(app.latest().selectedOperationId, null);
    assert.equal(app.latest().loaded, true);
});

test('loads older summaries on demand and keeps mapping and operation in links', () => {
    const app = harness(7);
    app.requests[0].resolve(page([50, 49], 3, 49));
    app.latest().onLoadOlder();
    app.latest().onLoadOlder();
    assert.equal(app.requests.length, 2);
    assert.deepEqual({...app.requests[1].options.data}, {category_tree_id: 7, before_operation_id: 49});
    app.requests[1].resolve(page([48], 3));
    assert.deepEqual(app.latest().operations.map((item) => item.operation_id), [50, 49, 48]);
    const link = new URL(app.latest().operationUrl(48));
    assert.equal(link.pathname, '/history/key/secret/');
    assert.equal(link.searchParams.get('category_tree_id'), '7');
    assert.equal(link.searchParams.get('operation_id'), '48');
    assert.equal(link.searchParams.has('details'), false);
    assert.equal(app.latest().onDetails, undefined);
    assert.equal(app.latest().pagination.has_more, false);
});

test('late responses from the previous mapping cannot replace the selected history', () => {
    const app = harness(7);
    app.select(8);
    assert.equal(app.requests[0].aborted, true);
    assert.deepEqual({...app.requests[1].options.data}, {category_tree_id: 8});
    app.requests[0].resolve(page([70]));
    assert.equal(app.latest().operations.length, 0);
    assert.equal(app.latest().loading, true);
    app.requests[1].resolve(page([80]));
    assert.deepEqual(app.latest().operations.map((item) => item.operation_id), [80]);
    assert.equal(new URL(app.latest().operationUrl(80)).searchParams.get('category_tree_id'), '8');
});

test('failed pagination keeps loaded rows and retries the same page', () => {
    const app = harness(7);
    app.requests[0].resolve(page([50], 2, 50));
    app.latest().onLoadOlder();
    app.requests[1].reject();
    assert.equal(app.message.hidden, false);
    assert.deepEqual(app.latest().operations.map((item) => item.operation_id), [50]);
    app.message.children[0].click();
    assert.deepEqual({...app.requests[2].options.data}, {category_tree_id: 7, before_operation_id: 50});
    app.requests[2].resolve(page([49], 2));
    assert.equal(app.message.hidden, true);
    assert.deepEqual(app.latest().operations.map((item) => item.operation_id), [50, 49]);
});

test('saving the mapping refreshes the first page without a tree-state request', () => {
    const app = harness(7);
    app.requests[0].resolve(page([50]));
    app.saved();
    assert.equal(app.requests.length, 2);
    assert.equal(app.requests[1].options.url, '/operations');
    assert.deepEqual({...app.requests[1].options.data}, {category_tree_id: 7});
    app.requests[1].resolve(page([51, 50]));
    assert.deepEqual(app.latest().operations.map((item) => item.operation_id), [51, 50]);
});

test('removing a snapshot row replaces stale pagination with the new operation', () => {
    const app = harness(7);
    app.requests[0].resolve(page([50], 2, 50));
    app.latest().onLoadOlder();
    app.removed();
    assert.equal(app.requests[1].aborted, true);
    assert.deepEqual({...app.requests[2].options.data}, {category_tree_id: 7});
    app.requests[2].resolve(page([51, 50], 3, 50));
    app.requests[1].resolve(page([49], 2));
    assert.deepEqual(app.latest().operations.map(item => item.operation_id), [51, 50]);
    assert.equal(app.latest().pagination.total, 3);
});

test('mapping operations reuse the history column spacing and card treatment', () => {
    assert.match(styles, /\.vech-operation-list\s*\{[\s\S]*?gap:\s*8px;[\s\S]*?padding:\s*10px 12px;/);
    assert.match(styles, /\.vech-operation\s*\{[\s\S]*?border-radius:\s*8px;[\s\S]*?gap:\s*6px;[\s\S]*?padding:\s*11px 12px;/);
    assert.match(styles, /\.vech-operation\.is-selected,[\s\S]*?border-color:\s*var\(--veui-blue-border\);/);
    assert.match(styles, /\.vech-operation-row\.is-selected,[\s\S]*?box-shadow:\s*inset 3px 0 0 var\(--veui-blue\);/);
    assert.match(
        styles,
        /\.vech-mapping-history\s*\{[\s\S]*?min-width:\s*0;\s*\}/
    );
    assert.match(styles, /\.vech-mapping-history \.vech-operation-list\s*\{[\s\S]*?padding:\s*10px 0;/);
    assert.doesNotMatch(styles, /\.vech-mapping-history\s*\{[\s\S]*?border-top:/);
});
