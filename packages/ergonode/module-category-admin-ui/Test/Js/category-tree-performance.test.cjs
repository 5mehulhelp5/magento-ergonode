'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const jsRoot = path.resolve(__dirname, '../../view/adminhtml/web/js');

function load(name, dependencies = {}, globals = {}) {
    let result;
    const filename = path.join(jsRoot, name + '.js');
    vm.runInNewContext(fs.readFileSync(filename, 'utf8'), {
        ...globals,
        define(names, factory) {
            result = factory(...names.map((key) => dependencies[key]));
        }
    }, {filename});
    return result;
}

const treeView = load('category-tree-view');
const treeState = load('category-tree-state');
const mappingState = load('category-tree-mapping-state');
const collapseStorage = load('category-tree-collapse-storage');
const compare = (a, b) => a.id - b.id;

function project(items, options = {}) {
    return treeView.project(treeView.index(items, 'id', 'parent', compare), {
        matches: () => true,
        isExcluded: () => false,
        ...options
    });
}

test('search retains ancestors and promotes matches below excluded parents', () => {
    const result = project([
        {id: 1}, {id: 2, parent: 1}, {id: 3, parent: 2}, {id: 4, parent: 1}
    ], {matches: (item) => item.id === 3, isExcluded: (item) => item.id === 2});
    assert.equal(result.count, 1);
    assert.equal(result.nodes.length, 1);
    assert.equal(result.nodes[0].item.id, 1);
    assert.equal(result.nodes[0].children[0].item.id, 3);
    assert.equal(result.nodes[0].children.length, 1);
});

test('connected parents remain context for unmapped children without inflating counts', () => {
    const result = project([
        {id: 1}, {id: 2, parent: 1}, {id: 3, parent: 1}
    ], {isContext: (item) => item.id !== 3});
    assert.equal(result.count, 1);
    assert.equal(result.nodes[0].context, true);
    assert.equal(result.nodes[0].children.length, 1);
    assert.equal(result.nodes[0].children[0].item.id, 3);
    assert.equal(project([{id: 1}], {isContext: () => true}).nodes.length, 0);
});

test('index preserves sibling order, detached roots and category codes matching object properties', () => {
    const items = [
        {id: 'child', parent: 'constructor', order: 2},
        {id: 'constructor', order: 1},
        {id: '__proto__', parent: 'constructor', order: 1},
        {id: 'orphan', parent: 'missing', order: 2}
    ];
    const index = treeView.index(items, 'id', 'parent', (a, b) => a.order - b.order);
    assert.equal(index.byId.__proto__, items[2]);
    assert.deepEqual(Array.from(index.children.constructor, (item) => item.id), ['__proto__', 'child']);
    assert.deepEqual(Array.from(index.roots, (item) => item.id), ['constructor', 'orphan']);
    assert.equal(treeView.project(index, {matches: () => true, isExcluded: () => false}).count, 4);
});

test('projection checks each category once on a 10,000 category tree', () => {
    let visited = 0;
    const items = Array.from({length: 10000}, (_, i) => ({id: i + 1, parent: i ? Math.floor((i - 1) / 20) + 1 : null}));
    const result = project(items, {matches: () => { visited++; return true; }});
    assert.equal(result.count, items.length);
    assert.equal(visited, items.length);
});

// Exercise the production controller's model, rendering traversal and batching.
// Only page bootstrap, card contents and toolbar decoration are replaced: no
// browser or Magento is needed to count materialized branches deterministically.
function controller(config = {}) {
    class Node {
        constructor(attributes = {}) {
            this.attributes = attributes;
            this.nodes = [];
            this.value = '';
            this.content = '';
            this.length = 1;
            this[0] = this;
        }
        is(other) { return this === other; }
        toggleClass() { return this; }
        setAttribute(key, value) { this.attributes[key] = value; }
        getAttribute(key) { return this.attributes[key]; }
        append(...children) { this.nodes.push(...children.flat()); return this; }
        empty() { this.nodes = []; return this; }
        children() { return this.nodes; }
        val(value) { if (value === undefined) { return this.value; } this.value = value; return this; }
        text(value) { if (value === undefined) { return this.content; } this.content = value; return this; }
        prop(key, value) { this.attributes[key] = value; return this; }
        attr(key, value) { if (value === undefined) { return this.attributes[key]; } this.attributes[key] = value; return this; }
    }
    const slots = new Map();
    const root = new Node();
    root.find = (key) => {
        if (!slots.has(key)) { slots.set(key, new Node()); }
        return slots.get(key);
    };
    root.trigger = () => {};
    const element = {};
    const $ = (value, attributes) => value === element ? root : new Node(attributes);
    $.extend = (deep, target, value) => Object.assign(target, structuredClone(value));
    const requests = [];
    // Controlled transport; the controller and save recovery run unchanged.
    $.Deferred = require('./helpers/deferred.cjs');
    $.ajax = (options) => {
        const request = $.Deferred();
        requests.push({options, request});
        return request;
    };
    let renders = 0;
    let timerId = 0;
    const timers = new Map();
    const cleanup = [];
    const scope = {claim: () => true, cleanup: (fn) => cleanup.push(fn)};
    const dependencies = {
        jquery: $,
        'mage/translate': (text) => text,
        'Ergonode_CategoryAdminUi/js/save-recovery': load('save-recovery', {jquery: $}, {Promise}),
        'Ergonode_CategoryAdminUi/js/category-tree-subtree-mapping': load('category-tree-subtree-mapping'),
        'Ergonode_CategoryAdminUi/js/category-tree-state': treeState,
        'Ergonode_CategoryAdminUi/js/category-tree-view': treeView,
        'Ergonode_CategoryAdminUi/js/category-tree-mapping-state': mappingState,
        'Ergonode_CategoryAdminUi/js/category-tree-collapse-storage': collapseStorage,
        'Ergonode_CoreAdminUi/js/workspace': {mount: () => scope},
        'Ergonode_CoreAdminUi/js/workspace-context': {create: () => ({message: {show: () => {}}})}
    };
    const source = fs.readFileSync(path.join(jsRoot, 'category-tree-mapping.js'), 'utf8').replace('        init();', `
        operationState = {start: function () { return true; }, finish: function () {}};
        updateBulkControls = function () {};
        updateCounts = function () { recordRender(); };
        updateButtons = function () {};
        buildErgonodeCard = function (category, context) {
            return $('<div/>', {card: 'source', code: category.code, context: context});
        };
        buildErgonodeRootCard = function () { return $('<div/>', {card: 'source-root'}); };
        buildMagentoCard = function (category) { return $('<div/>', {card: 'magento', id: category.id}); };
        element.test = {
            render: renderAll,
            batch: batchUpdate,
            add: addAndMapCategory,
            remove: removeCategory,
            move: setCategoryParent,
            replace: replaceModels,
            schedule: scheduleSearch,
            findRemoval: findPendingMappingRemovalByMagentoId,
            map: setMapping,
            selectBranch: setBulkBranchSelected,
            disconnect: unmapSelectedCategories,
            dirty: function () { return dirty; },
            unmapSelected: function (source, identifiers) {
                bulkSelection[source] = Object.fromEntries(identifiers.map(function (id) { return [id, true]; }));
                unmapSelectedCategories(source);
            },
            toggleMagento: function (id) { treeState.toggle(collapsedMagentoNodes, id); renderMagentoList(); },
            toggleSource: function (code) { treeState.toggle(collapsedSourceNodes, code); renderErgonodeList(); }
        };
        initializeCollapsedNodes();
        renderAll();
        exposeExtensionApi();
    `);
    vm.runInNewContext(source, {
        window: {
            clearTimeout: (id) => timers.delete(id),
            setTimeout: (fn) => { timers.set(++timerId, fn); return timerId; }
        },
        recordRender: () => renders++,
        define: (names, factory) => factory(...names.map((key) => dependencies[key]))(config, element)
    });
    const cards = (source) => {
        const result = [];
        function walk(node) {
            if (node.attributes.card === source) { result.push(node); }
            node.nodes.forEach(walk);
        }
        walk(root.find(`[data-role="${source === 'magento' ? 'magento' : 'ergo'}-list"]`));
        return result;
    };
    return {element, slots, cards, timers, requests, renders: () => renders};
}

function largeTree() {
    return Array.from({length: 2501}, (_, i) => ({
        id: i + 1,
        parent_id: i === 0 ? 0 : i <= 50 ? 1 : 2 + ((i - 51) % 50),
        label: i === 2500 ? 'needle' : `Category ${i + 1}`,
        level: i === 0 ? 1 : i <= 50 ? 2 : 3
    }));
}

test('collapsed trees render only visible cards; search finds a deeply nested category', () => {
    const harness = controller({magento_categories: largeTree()});
    assert.equal(harness.cards('magento').length, 1);
    assert.equal(harness.slots.get('[data-role="magento-count"]').text(), '2501');
    harness.element.test.toggleMagento(1);
    assert.equal(harness.cards('magento').length, 51);
    harness.slots.get('[data-role="magento-search"]').val('needle');
    harness.element.test.render();
    assert.equal(harness.cards('magento').length, 3);
    assert.equal(harness.slots.get('[data-role="magento-count"]').text(), '1');
    harness.slots.get('[data-role="magento-search"]').val('');
    harness.element.test.render();
    assert.equal(harness.cards('magento').length, 51, 'search must preserve collapsed state');
});

test('source descendants are lazy while mapped parents and excluded ancestors keep their semantics', () => {
    const harness = controller({categories: [
        {code: 'parent', magento_category_id: 10},
        {code: 'excluded', source_parent_code: 'parent', active: false},
        {code: 'leaf', source_parent_code: 'excluded'},
        {code: 'mapped-leaf', source_parent_code: 'parent', magento_category_id: 11}
    ]});
    assert.equal(harness.cards('source').length, 1);
    assert.equal(harness.cards('source')[0].attributes.context, true);
    harness.element.test.toggleSource('parent');
    assert.deepEqual(harness.cards('source').map((card) => card.attributes.code), ['parent', 'leaf']);
});

test('batching 500 mappings renders once, retains all payload rows and refreshes indexes', () => {
    const harness = controller({magento_categories: largeTree()});
    harness.element.test.batch(() => {
        for (let id = 2; id <= 501; id++) {
            assert.equal(harness.element.test.add({code: `code-${id}`}, id), true);
        }
        assert.equal(harness.renders(), 1);
    });
    assert.equal(harness.renders(), 2);
    assert.equal(harness.element.veaCategoryMappingApi.getCategories().length, 500);
    assert.equal(harness.cards('magento').length, 1);
    harness.element.test.remove('code-2');
    assert.equal(harness.element.veaCategoryMappingApi.getCategories().length, 499);
});

test('nested or failed batches release render suspension and keep completed edits visible', () => {
    const harness = controller({magento_categories: largeTree()});
    assert.throws(() => harness.element.test.batch(() => {
        harness.element.test.batch(() => harness.element.test.add({code: 'first'}, 2));
        throw new Error('failed later item');
    }), /failed later item/);
    assert.equal(harness.renders(), 2);
    harness.element.test.add({code: 'next'}, 3);
    assert.equal(harness.renders(), 3);
});

test('model replacement drops old category indexes and pending unlink lookup stays current', () => {
    const harness = controller({categories: [{code: 'old', magento_category_id: 2}], magento_categories: largeTree()});
    harness.element.test.map('old', null);
    assert.equal(harness.element.test.findRemoval(2).code, 'old');
    harness.element.test.replace({categories: [], magento_categories: [{id: 9000, label: 'New root'}]}, true);
    assert.deepEqual(harness.cards('magento').map((card) => card.attributes.id), [9000]);
    assert.equal(harness.element.test.add({code: 'invalid'}, 2), false);
    assert.equal(harness.element.test.add({code: 'new'}, 9000), true);
});

test('rapid search input coalesces into one render per tree', () => {
    const harness = controller();
    let searchRenders = 0;
    for (let i = 0; i < 10; i++) {
        harness.element.test.schedule('magento', () => searchRenders++);
    }
    assert.equal(harness.timers.size, 1);
    Array.from(harness.timers.values()).forEach((fn) => fn());
    assert.equal(searchRenders, 1);
});


test('mapping index preserves reassignment, duplicate cleanup and no-op semantics', () => {
    const categories = [
        {code: 'first', magento_category_id: 12},
        {code: 'duplicate', magento_category_id: 12},
        {code: '__proto__', magento_category_id: 13}
    ];
    const index = mappingState.createIndex(categories);
    assert.equal(index.assign('missing', 12), false);
    assert.equal(index.assign('__proto__', '12'), true);
    assert.deepEqual(categories.map(item => item.magento_category_id), [null, null, 12]);
    assert.equal(index.findByMagentoId(13), null);
    assert.equal(index.findByMagentoId('12'), categories[2]);
    assert.equal(index.assign('__proto__', 12), false);
    assert.equal(index.assign('__proto__', null), true);
    assert.equal(index.findByMagentoId(12), null);
    assert.equal(index.assign('__proto__', null), false);
    const fresh = {code: 'fresh', magento_category_id: null};
    categories.push(fresh);
    index.add(fresh);
    assert.equal(index.assign('fresh', 13), true);
    assert.equal(index.findByMagentoId(13), fresh);
});

test('bulk assignment never rescans unrelated categories', () => {
    let reads = 0;
    const categories = Array.from({length: 10000}, (_, i) => ({
        get code() { reads++; return `code-${i}`; }, magento_category_id: null
    }));
    const index = mappingState.createIndex(categories);
    reads = 0;
    for (let i = 0; i < categories.length; i++) {
        assert.equal(index.assign(`code-${i}`, i + 1), true);
    }
    assert.equal(reads, 0);
    assert.equal(categories[9999].magento_category_id, 10000);
});

test('mixed edits in one batch refresh removed rows and preserve exclusive mappings', () => {
    const harness = controller({magento_categories: largeTree()});
    harness.element.test.batch(() => {
        assert.equal(harness.element.test.add({code: 'first'}, 2), true);
        assert.equal(harness.element.test.add({code: 'first'}, 3), false);
        assert.equal(harness.element.test.add({code: 'second'}, 2), true);
        assert.equal(harness.element.test.map('second', 3), true);
        assert.equal(harness.element.test.remove('second'), true);
        assert.equal(harness.element.test.add({code: 'second'}, 2), true);
        assert.equal(harness.element.test.add({code: 'first'}, 3), true);
    });
    const rows = harness.element.veaCategoryMappingApi.getCategories();
    assert.deepEqual(Array.from(rows, row => [row.code, row.magento_category_id]), [['first', 3], ['second', 2]]);
    assert.equal(harness.renders(), 2);
});


test('bulk disconnect changes only selected mappings and refreshes the assignment index', () => {
    const harness = controller({
        categories: [{code: 'first', magento_category_id: 2}, {code: 'second', magento_category_id: 3}],
        magento_categories: largeTree()
    });
    harness.element.test.unmapSelected('magento', ['2']);
    let rows = harness.element.veaCategoryMappingApi.getCategories();
    assert.deepEqual(Array.from(rows, row => row.magento_category_id), [null, 3]);
    harness.element.test.unmapSelected('ergo', ['second', 'missing']);
    rows = harness.element.veaCategoryMappingApi.getCategories();
    assert.deepEqual(Array.from(rows, row => row.magento_category_id), [null, null]);
    assert.equal(harness.element.test.add({code: 'first'}, 3), true);
});

for (const rootExpanded of [false, true]) {
    test(`select all, Disconnect and Save preserve the full tree (expanded: ${rootExpanded})`, () => {
        const magento = largeTree();
        const categories = magento.slice(1).map((item, index) => ({
            code: `code-${item.id}`,
            parent_code: item.parent_id === 1 ? null : `code-${item.parent_id}`,
            source_parent_code: item.parent_id === 1 ? null : `code-${item.parent_id}`,
            sort_order: index,
            ergonode_category_id: null,
            magento_category_id: item.id
        }));
        const harness = controller({category_tree_id: 3, categories,
            magento_categories: magento, urls: {save: '/save'}, form_key: 'test-form-key'});
        const api = harness.element.veaCategoryMappingApi;
        if (rootExpanded) harness.element.test.toggleMagento(1);
        assert.ok(harness.cards('magento').length < magento.length, 'descendants are not rendered');
        const before = JSON.parse(JSON.stringify(api.getCategories()));
        harness.element.test.selectBranch('magento', '1', true);
        assert.equal(api.getBulkSelection('magento').length, magento.length);
        harness.element.test.disconnect('magento');
        assert.equal(harness.element.test.dirty(), true);
        assert.equal(api.getBulkSelection('magento').length, 0);
        assert.deepEqual(JSON.parse(JSON.stringify(api.getCategories())),
            before.map(item => ({...item, magento_category_id: null})));
        for (let attempt = 0; attempt < 2; attempt++) {
            api.saveLayout();
            const {options, request} = harness.requests[attempt];
            const payload = JSON.parse(options.data.payload);
            assert.equal(options.url, '/save');
            assert.equal(options.data.form_key, 'test-form-key');
            assert.equal(payload.category_tree_id, 3);
            assert.equal(payload.categories.length, categories.length);
            assert.deepEqual(payload.categories, before.map(item => ({
                code: item.code, parent_code: item.parent_code, sort_order: item.sort_order,
                magento_category_id: null, extension_data: item.extension_data
            })));
            assert.equal(payload.visibility.length, categories.length + magento.length);
            request.resolve({success: true});
            assert.equal(harness.element.test.dirty(), false);
        }
        assert.deepEqual(harness.requests[0].options.data, harness.requests[1].options.data);
    });
}
