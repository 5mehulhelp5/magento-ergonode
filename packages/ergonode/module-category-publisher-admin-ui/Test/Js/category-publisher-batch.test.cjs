'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const jsRoot = path.resolve(__dirname, '../../view/adminhtml/web/js');
const source = fs.readFileSync(path.join(jsRoot, 'ergonode-category-publisher-mapping.js'), 'utf8');
let generator;
vm.runInNewContext(fs.readFileSync(path.join(jsRoot, 'category-code-generator.js'), 'utf8'), {
    define: (names, factory) => { generator = factory(); }
});

function productionFunction(name) {
    const match = source.match(new RegExp('        function ' + name + '\\([^]*?\\n        \\}'));
    assert.ok(match, `Missing production function: ${name}`);
    return match[0];
}

function harness({magento, categories = [], selected = [], roots = [1], failedId} = {}) {
    magento = magento || [
        {id: 1, parent_id: 0, level: 1, label: 'Root'},
        {id: 2, parent_id: 1, level: 2, label: 'Łóżka'},
        {id: 3, parent_id: 2, level: 3, label: 'Duże'},
        {id: 4, parent_id: 3, level: 4, label: 'Białe'}
    ];
    const stats = {sourceReads: 0, magentoReads: 0, batches: 0, normalizedLabels: 0, idReads: 0};
    let inBatch = false;
    let cleared = false;
    const added = [];
    const collisions = [];
    const api = {
        getCategories: () => { stats.sourceReads++; return structuredClone(categories); },
        getMagentoCategories: () => {
            stats.magentoReads++;
            return magento.map(item => ({...item, get id() { stats.idReads++; return item.id; }}));
        },
        getBulkSelection: () => selected,
        batchUpdate: callback => {
            stats.batches++;
            inBatch = true;
            try { callback(); } finally { inBatch = false; }
        },
        addAndMapCategory: (category, id) => {
            if (id === failedId) { return false; }
            added.push({id, ...structuredClone(category), inBatch});
            return true;
        },
        clearBulkSelection: () => { assert.equal(inBatch, false); cleared = true; }
    };
    const sandbox = {
        api, $t: value => value, notify: () => {},
        reportCategoryCollision: (...args) => collisions.push(args),
        $root: {find: () => ({each: fn => roots.forEach(id => fn.call({id}))})},
        $: element => ({data: () => element.id}),
        categoryCodeGenerator: {
            fromPathLabels: labels => { stats.normalizedLabels += labels.length; return generator.fromPathLabels(labels); }
        }
    };
    vm.runInNewContext([
        'createSelectedCategories', 'selectedMagentoIdsForCreation', 'addPendingCategory',
        'createDraftModels', 'categoryPathCode', 'nearestMappedParentCode'
    ].map(productionFunction).join('\n'), sandbox);
    return {sandbox, api, added, collisions, stats, cleared: () => cleared};
}

test('bulk creation reads each model once and links new parents before children', () => {
    const h = harness({selected: ['4', '3', '2']});
    h.sandbox.createSelectedCategories();
    assert.equal(h.stats.batches, 1);
    assert.equal(h.stats.sourceReads, 1);
    assert.equal(h.stats.magentoReads, 1);
    assert.equal(h.cleared(), true);
    assert.equal(h.added.every(item => item.inBatch), true);
    assert.deepEqual(h.added.map(item => [item.id, item.code, item.parent_code]), [
        [2, 'lozka', null], [3, 'lozka__duze', 'lozka'], [4, 'lozka__duze__biale', 'lozka__duze']
    ]);
});

test('rejected items are not exposed as mapped parents to subsequent items', () => {
    const h = harness({selected: ['4', '3', '2'], failedId: 2});
    h.sandbox.createSelectedCategories();
    assert.deepEqual(h.added.map(item => [item.id, item.parent_code]), [
        [3, null], [4, 'lozka__duze']
    ]);
});

test('selection excludes mapped, inactive, unknown and configured root rows', () => {
    const h = harness({
        magento: [{id: 1}, {id: 2, active: false}, {id: 3}, {id: 4}],
        categories: [{code: 'mapped', magento_category_id: 3}],
        selected: ['1', '2', '3', '4', '999', '0']
    });
    assert.deepEqual(Array.from(h.sandbox.selectedMagentoIdsForCreation()), ['4']);
    const empty = harness();
    assert.deepEqual(Array.from(empty.sandbox.selectedMagentoIdsForCreation()), []);
    assert.equal(empty.stats.sourceReads + empty.stats.magentoReads, 0);
});

test('code collisions include earlier drafts and rejected rows do not reserve their code', () => {
    const magento = [
        {id: 1, level: 1},
        {id: 2, parent_id: 1, level: 2, label: 'Łóżka'},
        {id: 3, parent_id: 1, level: 2, label: 'Lozka'}
    ];
    const h = harness({magento, selected: ['3', '2']});
    h.sandbox.createSelectedCategories();
    assert.deepEqual(h.added.map(item => item.id), [2]);
    assert.equal(h.collisions.length, 1);
    assert.equal(h.collisions[0][1].magento_category_id, 2);
    const rejected = harness({magento, selected: ['3', '2'], failedId: 2});
    rejected.sandbox.createSelectedCategories();
    assert.deepEqual(rejected.added.map(item => item.id), [3]);
    assert.equal(rejected.collisions.length, 0);
});

test('single creation uses a fresh snapshot and resolves a mapped grandparent', () => {
    const h = harness({categories: [{code: 'existing-parent', magento_category_id: 2}]});
    assert.equal(h.sandbox.addPendingCategory(4).parent_code, 'existing-parent');
    assert.equal(h.sandbox.addPendingCategory(999), false);
    assert.equal(h.stats.sourceReads, 2);
    assert.equal(h.stats.magentoReads, 2);
});

test('cached path codes preserve full-path normalization, truncation, empty labels and orphan roots', () => {
    const magento = [
        {id: 1, label: 'Root'},
        {id: 2, parent_id: 1, label: 'Łóżka / Æ'},
        {id: 3, parent_id: 2, label: '---'},
        {id: 4, parent_id: 3, label: 'a'.repeat(127)},
        {id: 5, parent_id: 4, label: 'Białe'},
        {id: 6, parent_id: 999, label: 'Orphan'},
        {id: 7, parent_id: 6, label: 'Child'},
        {id: 8, parent_id: 7, label: 'constructor'}
    ];
    const h = harness({magento});
    const models = h.sandbox.createDraftModels();
    for (const item of [...magento].reverse()) {
        const labels = [];
        let current = item;
        while (current) {
            labels.unshift(current.label);
            current = magento.find(candidate => candidate.id === current.parent_id);
        }
        labels.shift();
        assert.equal(h.sandbox.categoryPathCode(item, models), generator.fromPathLabels(labels));
    }
    assert.equal(h.stats.normalizedLabels, 6, 'each non-root segment is normalized once');
});

test('cycle fallback terminates and preserves the previous per-path root omission', () => {
    const magento = [
        {id: 1, parent_id: 2, label: 'One'}, {id: 2, parent_id: 1, label: 'Two'},
        {id: 3, parent_id: 2, label: 'Three'}
    ];
    const h = harness({magento});
    const models = h.sandbox.createDraftModels();
    assert.equal(h.sandbox.categoryPathCode(magento[0], models), 'one');
    assert.equal(h.sandbox.categoryPathCode(magento[1], models), 'two');
    assert.equal(h.sandbox.categoryPathCode(magento[2], models), 'two__three');
    assert.equal(h.sandbox.nearestMappedParentCode(magento[2], models), null);
});

test('10,000-category preparation reads models once and normalizes each non-root label once', () => {
    const magento = Array.from({length: 10000}, (_, i) => ({
        id: i + 1, parent_id: i ? Math.floor((i - 1) / 20) + 1 : 0,
        label: `Category ${i + 1}`, level: i ? Math.ceil(Math.log(i + 1) / Math.log(20)) + 1 : 1
    }));
    const h = harness({magento, selected: magento.map(item => String(item.id)).reverse()});
    h.sandbox.createSelectedCategories();
    assert.equal(h.added.length, 9999);
    assert.equal(h.stats.sourceReads, 1);
    assert.equal(h.stats.magentoReads, 1);
    assert.equal(h.stats.normalizedLabels, 9999);
    assert.ok(h.stats.idReads < magento.length * 20, `Expected linear reads, got ${h.stats.idReads}`);
});
