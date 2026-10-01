'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(path.resolve(__dirname, '../../view/adminhtml/web/js/category-tree-mapping.js'), 'utf8');

function harness() {
    function $(tag, attributes = {}) {
        return {
            tag, attributes, children: [],
            append(...children) { this.children.push(...children); return this; },
            attr(name, value) { this.attributes[name] = value; return this; },
            text(value) { this.value = value; return this; }
        };
    }
    const state = {
        $, $t: value => value, persistedMappings: {}, mappingsByMagentoId: {}, removalsByMagentoId: {},
        mappingHintSequence: 0, pendingSaveHintSequence: 0,
        normalizeCategories: items => items, normalizeMagentoCategories: items => items,
        initializeCollapsedNodes() {}, renderAll() {},
        getConfiguredRootMapping: () => null,
        buildExcludedCategoryInfo: () => ({excluded: true}),
        buildBulkCategorySelection() {}, buildMagentoCategoryPath() {}, buildMagentoCategoryOptions() {}
    };
    const names = ['hasPendingMappingAddition', 'findPendingMappingRemovalByMagentoId',
        'buildMagentoCard', 'buildMappingIndicator', 'buildPendingSaveIndicator',
        'getMagentoMappingPresentation', 'collectPersistedMappings', 'replaceModels'];
    for (const name of names) {
        const start = source.indexOf(`        function ${name}(`);
        assert.ok(start >= 0, name);
        const end = source.indexOf('\n        function ', start + 1);
        vm.runInNewContext(source.slice(start, end), state);
    }
    return state;
}

function card(state, id = 12, active = true) {
    const result = state.buildMagentoCard({id, active, label: 'Chairs', path: '1/2/12'});
    return {attributes: result.attributes, indicator: result.children[2]};
}

const chairs = {code: 'chairs', label: 'Chairs', active: true, magento_category_id: 12};

function assertPending(result) {
    assert.equal(result.indicator.attributes['data-role'], 'pending-mapping-save');
    assert.equal(result.indicator.attributes['aria-label'], 'Mapowanie oczekuje na zapis');
    assert.equal(result.indicator.children[0].attributes.class, 'vec-pending-save-icon');
    assert.equal(result.indicator.attributes['aria-describedby'], result.indicator.children[1].attributes.id);
}

test('Auto Connect preview renders pending until mappings are persisted', () => {
    const state = harness();
    state.replaceModels({categories: [{...chairs, mapping_source: 'auto'}], magento_categories: [{id: 12}]});
    state.mappingsByMagentoId[12] = state.categories[0];
    assertPending(card(state));
    assert.equal(card(state).attributes['data-mapping-state'], 'pending');
    assert.equal(card(state).attributes['data-drop-zone'], undefined);

    state.persistedMappings = state.collectPersistedMappings(state.categories, true);
    const saved = card(state);
    assert.equal(saved.attributes['data-mapping-state'], 'active');
    assert.match(saved.indicator.children[0].attributes.class, /veui-connected-icon/);
});

test('preview preserves the connected indicator for database mappings', () => {
    const state = harness();
    state.replaceModels({categories: [{...chairs, mapping_source: 'database'}], magento_categories: [{id: 12}]});
    state.mappingsByMagentoId[12] = state.categories[0];
    assert.equal(card(state).attributes['data-mapping-state'], 'active');
    assert.match(card(state).indicator.children[0].attributes.class, /veui-connected-icon/);
});

test('moving a persisted mapping shows pending at both the old and new target', () => {
    const state = harness();
    state.persistedMappings = {chairs: 11};
    state.mappingsByMagentoId[12] = chairs;
    state.removalsByMagentoId[11] = chairs;
    assertPending(card(state, 11));
    assertPending(card(state, 12));
});

test('restoring the saved pair removes the pending indicator', () => {
    const state = harness();
    state.persistedMappings = {chairs: 12};
    state.mappingsByMagentoId[12] = chairs;
    assert.equal(card(state).attributes['data-mapping-state'], 'active');
});

test('configured root stays connected without a category draft mapping', () => {
    const state = harness();
    state.getConfiguredRootMapping = () => ({magentoId: 12, code: 'root', label: 'Root', active: true});
    assert.equal(card(state).attributes['data-mapping-state'], 'active');
    assert.match(card(state).indicator.children[0].attributes.class, /veui-connected-icon/);
});

test('unmapped and excluded targets preserve their existing presentation', () => {
    const state = harness();
    assert.equal(card(state).attributes['data-drop-zone'], 'magento-target');
    assert.equal(card(state).indicator.attributes.class, 'vec-magento-mapping is-empty');
    state.mappingsByMagentoId[12] = chairs;
    assert.equal(card(state, 12, false).indicator.excluded, true);
});

for (const status of ['error', 'disabled']) {
    test(`saved ${status} mapping retains its status`, () => {
        const state = harness();
        state.persistedMappings = {chairs: 12};
        state.mappingsByMagentoId[12] = {...chairs, sync_status: status === 'error' ? 'error' : '', active: status !== 'disabled'};
        assert.equal(card(state).attributes['data-mapping-state'], status);
    });
}
