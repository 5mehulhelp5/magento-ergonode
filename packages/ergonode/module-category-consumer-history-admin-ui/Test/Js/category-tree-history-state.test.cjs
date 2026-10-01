'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

function loadHistoryState() {
    let exported;
    const fileName = path.resolve(
        __dirname,
        '../../view/adminhtml/web/js/category-tree-history-state.js'
    );
    const sandbox = {
        define(names, factory) {
            assert.deepEqual(Array.from(names), []);
            exported = factory();
        }
    };

    vm.runInNewContext(fs.readFileSync(fileName, 'utf8'), sandbox, {filename: fileName});

    return exported;
}

function findNode(nodes, identifier, ghost = null) {
    for (const node of nodes) {
        if (node.identifier === identifier && node.ghost === ghost) {
            return node;
        }
        const nested = findNode(node.children || [], identifier, ghost);

        if (nested) {
            return nested;
        }
    }

    return null;
}

test('Ergonode wraps current and historical branches in the configured root without inventing a change', () => {
    const source = {identifier: 'women', parent_identifier: null, label: 'Women'};
    const changes = [{entity_type: 'source', entity_identifier: 'deleted', actions: ['deleted'],
        before: {identifier: 'deleted', parent_identifier: null}, after: null}];
    const tree = {tree_code: 'default', root_category_id: 2, root_label: 'Default Category', is_active: true};
    const roots = loadHistoryState().buildErgonodeTree([source], changes, tree);

    assert.equal(roots.length, 1);
    assert.equal(roots[0].key, '__configured_root__:default');
    assert.equal(roots[0].item.label, 'default');
    assert.equal(roots[0].item.magento_category_id, 2);
    assert.equal(roots[0].children.length, 2);
    assert.equal(roots[0].change, null);
    assert.equal(roots[0].changeKeys.length, 0);
    assert.equal(findNode(roots, 'deleted', 'deleted').entityType, 'source');
});

test('unchanged descendants inherit new-branch context without changing their history or their own actions', () => {
    const sources = [
        {identifier: 'women', parent_identifier: null, label: 'Women'},
        {identifier: 'bottoms', parent_identifier: 'women', label: 'Bottoms'},
        {identifier: 'pants', parent_identifier: 'bottoms', label: 'Pants'},
        {identifier: 'jeans', parent_identifier: 'pants', label: 'Jeans'},
        {identifier: 'shorts', parent_identifier: 'bottoms', label: 'Shorts'},
        {identifier: 'tops', parent_identifier: 'women', label: 'Tops'}
    ];
    const changes = [
        {entity_type: 'source', entity_identifier: 'bottoms', actions: ['created'], before: null, after: sources[1]},
        {entity_type: 'source', entity_identifier: 'shorts', actions: ['moved'],
            before: {...sources[4], parent_identifier: 'tops'}, after: sources[4]}
    ];
    const original = JSON.stringify({sources, changes});
    const roots = loadHistoryState().buildErgonodeTree(sources, changes);

    for (const identifier of ['pants', 'jeans']) {
        assert.equal(findNode(roots, identifier).createdAncestor.identifier, 'bottoms');
        assert.equal(findNode(roots, identifier).change, null);
        assert.deepEqual(Array.from(findNode(roots, identifier).changeKeys), ['source:' + identifier]);
    }
    assert.equal(findNode(roots, 'shorts').createdAncestor, null);
    assert.deepEqual(Array.from(findNode(roots, 'shorts').change.actions), ['moved']);
    assert.equal(findNode(roots, 'shorts', 'from').createdAncestor, null);
    assert.equal(findNode(roots, 'tops').createdAncestor, null);
    assert.equal(JSON.stringify({sources, changes}), original);
    const current = loadHistoryState().buildErgonodeTree(sources, []);
    assert.equal(findNode(current, 'pants').createdAncestor, null);
});

test('one operation produces destination, previous-location and deleted nodes together', () => {
    const historyState = loadHistoryState();
    const items = [
        {identifier: 'old-parent', parent_identifier: null, label: 'Old', sort_order: 1},
        {identifier: 'new-parent', parent_identifier: null, label: 'New', sort_order: 2},
        {identifier: 'moved', parent_identifier: 'new-parent', label: 'Moved', sort_order: 1}
    ];
    const changes = [
        {
            entity_identifier: 'moved',
            actions: ['moved'],
            before: {identifier: 'moved', parent_identifier: 'old-parent', label: 'Moved', sort_order: 1},
            after: items[2]
        },
        {
            entity_identifier: 'deleted',
            actions: ['deleted'],
            before: {identifier: 'deleted', parent_identifier: 'old-parent', label: 'Deleted', sort_order: 2},
            after: null
        }
    ];

    const roots = historyState.buildTree(items, changes);
    const oldParent = roots.find((node) => node.identifier === 'old-parent');
    const newParent = roots.find((node) => node.identifier === 'new-parent');

    assert.equal(oldParent.children.find((node) => node.identifier === 'moved').ghost, 'from');
    assert.equal(oldParent.children.find((node) => node.identifier === 'deleted').ghost, 'deleted');
    assert.equal(newParent.children.find((node) => node.identifier === 'moved').ghost, null);
});

test('position-only changes retain the previous position as a historical node', () => {
    const historyState = loadHistoryState();
    const items = [
        {identifier: 'parent', parent_identifier: null, label: 'Parent', sort_order: 1},
        {identifier: 'reordered', parent_identifier: 'parent', label: 'Reordered', sort_order: 3}
    ];
    const change = {
        entity_identifier: 'reordered',
        actions: ['reordered'],
        before: {...items[1], sort_order: 1},
        after: items[1]
    };

    const roots = historyState.buildTree(items, [change]);
    const parent = findNode(roots, 'parent');
    const previous = findNode(roots, 'reordered', 'from');
    const current = findNode(roots, 'reordered');

    assert.equal(previous.parentIdentifier, 'parent');
    assert.equal(previous.item.sort_order, 1);
    assert.equal(current.item.sort_order, 3);
    assert.equal(parent.children.filter((node) => node.identifier === 'reordered').length, 2);
});

test('deleted descendants remain nested below their deleted parent', () => {
    const historyState = loadHistoryState();
    const changes = [
        {
            entity_identifier: 'deleted-parent',
            actions: ['deleted'],
            before: {identifier: 'deleted-parent', parent_identifier: null, label: 'Parent', sort_order: 1},
            after: null
        },
        {
            entity_identifier: 'deleted-child',
            actions: ['deleted'],
            before: {identifier: 'deleted-child', parent_identifier: 'deleted-parent', label: 'Child', sort_order: 1},
            after: null
        }
    ];

    const roots = historyState.buildTree([], changes);

    assert.equal(roots.length, 1);
    assert.equal(roots[0].identifier, 'deleted-parent');
    assert.equal(roots[0].children[0].identifier, 'deleted-child');
});

test('merged tree uses Magento hierarchy and combines target with Ergonode changes', () => {
    const historyState = loadHistoryState();
    const sourceItems = [
        {identifier: 'chairs', label: 'Dining Chairs', magento_category_id: 12, sort_order: 1}
    ];
    const targetItems = [
        {identifier: '2', parent_identifier: null, label: 'Root', sort_order: 0},
        {identifier: '3', parent_identifier: '2', label: 'Furniture', sort_order: 1},
        {identifier: '12', parent_identifier: '3', label: 'Chairs / Magento', sort_order: 1}
    ];
    const changes = [
        {
            entity_type: 'source',
            entity_identifier: 'chairs',
            actions: ['renamed'],
            before: {identifier: 'chairs', label: 'Chairs', magento_category_id: 12},
            after: sourceItems[0]
        },
        {
            entity_type: 'target',
            entity_identifier: '12',
            actions: ['moved'],
            before: {...targetItems[2], parent_identifier: '2'},
            after: targetItems[2]
        }
    ];

    const roots = historyState.buildMagentoTree(sourceItems, targetItems, changes);
    const current = findNode(roots, '12');
    const previous = findNode(roots, '12', 'from');

    assert.equal(current.parentIdentifier, '3');
    assert.equal(previous.parentIdentifier, '2');
    assert.equal(current.sourceItems[0].identifier, 'chairs');
    assert.deepEqual(Array.from(current.change.actions), ['moved', 'renamed']);
    assert.deepEqual(Array.from(current.changeKeys), ['target:12', 'source:chairs']);
});

test('remapping shows previous Magento association and current association in one tree', () => {
    const historyState = loadHistoryState();
    const before = {identifier: 'chairs', label: 'Chairs', magento_category_id: 11, sort_order: 1};
    const after = {identifier: 'chairs', label: 'Chairs', magento_category_id: 12, sort_order: 1};
    const targets = [
        {identifier: '2', parent_identifier: null, label: 'Root', sort_order: 0},
        {identifier: '11', parent_identifier: '2', label: 'Old category', sort_order: 1},
        {identifier: '12', parent_identifier: '2', label: 'New category', sort_order: 2}
    ];
    const change = {
        entity_type: 'source',
        entity_identifier: 'chairs',
        actions: ['reconnected'],
        before,
        after
    };

    const roots = historyState.buildMagentoTree([after], targets, [change]);
    const oldTarget = findNode(roots, '11');
    const newTarget = findNode(roots, '12');

    assert.equal(oldTarget.children[0].identifier, 'chairs');
    assert.equal(oldTarget.children[0].ghost, 'mapping-from');
    assert.equal(newTarget.sourceItems[0].identifier, 'chairs');
});

test('moved remapping shows the complete previous Magento location as a sibling row', () => {
    const historyState = loadHistoryState();
    const before = {
        identifier: 'jackets', label: 'Jackets', parent_identifier: 'tops',
        magento_category_id: 6, sort_order: 3
    };
    const after = {
        identifier: 'jackets', label: 'Sport Jackets', parent_identifier: 'gear',
        magento_category_id: 23, sort_order: 2
    };
    const targets = [
        {identifier: '2', parent_identifier: null, label: 'Root', sort_order: 0, path: '1/2'},
        {identifier: '3', parent_identifier: '2', label: 'Gear', sort_order: 1, path: '1/2/3'},
        {identifier: '6', parent_identifier: '3', label: 'Watches', sort_order: 1, path: '1/2/3/6'},
        {identifier: '20', parent_identifier: '2', label: 'Women', sort_order: 2, path: '1/2/20'},
        {identifier: '21', parent_identifier: '20', label: 'Tops', sort_order: 1, path: '1/2/20/21'},
        {identifier: '23', parent_identifier: '21', label: 'Jackets', sort_order: 1, path: '1/2/20/21/23'}
    ];
    const change = {
        entity_type: 'source',
        entity_identifier: 'jackets',
        actions: ['moved', 'reconnected'],
        before,
        after
    };

    const roots = historyState.buildMagentoTree([after], targets, [change]);
    const previous = findNode(roots, '6', 'from');
    const current = findNode(roots, '23');

    assert.equal(previous.parentIdentifier, '3');
    assert.equal(previous.item.path, '1/2/3/6');
    assert.equal(previous.item.label, 'Watches');
    assert.equal(previous.sourceItems[0].identifier, 'jackets');
    assert.deepEqual(Array.from(previous.changeKeys), ['target:6', 'source:jackets']);
    assert.equal(findNode(roots, 'jackets', 'mapping-from'), null);
    assert.equal(current.sourceItems[0].identifier, 'jackets');
});

test('unmapping marks the previous Magento category without adding a child row', () => {
    const historyState = loadHistoryState();
    const before = {identifier: 'chairs', label: 'Chairs', magento_category_id: 11, sort_order: 1};
    const after = {...before, magento_category_id: null};
    const targets = [
        {identifier: '2', parent_identifier: null, label: 'Root', sort_order: 0},
        {identifier: '11', parent_identifier: '2', label: 'Chairs / Magento', sort_order: 1}
    ];
    const change = {
        entity_type: 'source',
        entity_identifier: 'chairs',
        actions: ['disconnected'],
        before,
        after
    };

    const roots = historyState.buildMagentoTree([after], targets, [change]);
    const previousTarget = findNode(roots, '11');

    assert.equal(previousTarget.children.length, 0);
    assert.equal(previousTarget.sourceItems[0].identifier, 'chairs');
    assert.deepEqual(Array.from(previousTarget.change.actions), ['disconnected']);
    assert.deepEqual(Array.from(previousTarget.changeKeys), ['target:11', 'source:chairs']);
});

test('unmapped categories are absent from Magento and retain their hierarchy in Ergonode', () => {
    const historyState = loadHistoryState();
    const sources = [
        {identifier: 'parent', label: 'Parent', parent_identifier: null, magento_category_id: 2, sort_order: 1},
        {identifier: 'unmapped', label: 'Unmapped', parent_identifier: 'parent', magento_category_id: null, sort_order: 1},
        {identifier: 'nested', label: 'Nested', parent_identifier: 'unmapped', magento_category_id: null, sort_order: 1}
    ];
    const magentoRoots = historyState.buildMagentoTree(
        sources,
        [{identifier: '2', parent_identifier: null, label: 'Root', sort_order: 0}],
        []
    );
    const ergonodeRoots = historyState.buildErgonodeTree(sources, []);

    assert.equal(magentoRoots.length, 1);
    assert.equal(findNode(magentoRoots, 'unmapped'), null);
    assert.equal(ergonodeRoots.length, 1);
    assert.equal(ergonodeRoots[0].identifier, 'parent');
    assert.equal(ergonodeRoots[0].children[0].identifier, 'unmapped');
    assert.equal(ergonodeRoots[0].children[0].children[0].identifier, 'nested');
    assert.deepEqual(Array.from(findNode(ergonodeRoots, 'nested').changeKeys), ['source:nested']);
});

test('Ergonode includes previous locations and deletions without Magento mappings', () => {
    const historyState = loadHistoryState();
    const sources = [
        {identifier: 'old-parent', label: 'Old', parent_identifier: null},
        {identifier: 'new-parent', label: 'New', parent_identifier: null},
        {identifier: 'moved', label: 'Moved', parent_identifier: 'new-parent', magento_category_id: null}
    ];
    const changes = [
        {
            entity_type: 'source', entity_identifier: 'moved', actions: ['moved'],
            before: {...sources[2], parent_identifier: 'old-parent'}, after: sources[2]
        },
        {
            entity_type: 'source', entity_identifier: 'deleted', actions: ['deleted'],
            before: {identifier: 'deleted', label: 'Deleted', parent_identifier: 'old-parent'}, after: null
        },
        {
            entity_type: 'target', entity_identifier: 'moved', actions: ['renamed'],
            before: sources[2], after: {...sources[2], label: 'Magento only'}
        }
    ];
    const roots = historyState.buildErgonodeTree(sources, changes);

    assert.equal(findNode(roots, 'moved').parentIdentifier, 'new-parent');
    assert.equal(findNode(roots, 'moved', 'from').parentIdentifier, 'old-parent');
    assert.equal(findNode(roots, 'deleted', 'deleted').parentIdentifier, 'old-parent');
    assert.equal(findNode(roots, 'moved').change.entity_type, 'source');
    assert.deepEqual(Array.from(findNode(roots, 'deleted', 'deleted').changeKeys), ['source:deleted']);
});

test('Ergonode uses source hierarchy for source-only movement before and after', () => {
    const historyState = loadHistoryState();
    const sources = [
        {identifier: 'manual-parent', label: 'Manual', parent_identifier: null, source_parent_identifier: null},
        {identifier: 'old-source-parent', label: 'Old source', parent_identifier: null, source_parent_identifier: null},
        {identifier: 'new-source-parent', label: 'New source', parent_identifier: null, source_parent_identifier: null},
        {
            identifier: 'moved',
            label: 'Moved',
            parent_identifier: 'manual-parent',
            source_parent_identifier: 'new-source-parent',
            sort_order: 6,
            source_sort_order: 8
        }
    ];
    const change = {
        entity_type: 'source',
        entity_identifier: 'moved',
        actions: ['source_moved', 'source_reordered'],
        before: {...sources[3], source_parent_identifier: 'old-source-parent', source_sort_order: 1},
        after: sources[3]
    };

    const roots = historyState.buildErgonodeTree(sources, [change]);
    const previous = findNode(roots, 'moved', 'from');
    const current = findNode(roots, 'moved');

    assert.equal(previous.parentIdentifier, 'old-source-parent');
    assert.equal(previous.item.sort_order, 1);
    assert.equal(current.parentIdentifier, 'new-source-parent');
    assert.equal(current.item.sort_order, 8);
});

test('Ergonode retains deleted parent-child hierarchy in an otherwise empty tree', () => {
    const historyState = loadHistoryState();
    const changes = ['parent', 'child'].map((identifier) => ({
        entity_type: 'source', entity_identifier: identifier, actions: ['deleted'],
        before: {identifier, label: identifier, parent_identifier: identifier === 'child' ? 'parent' : null},
        after: null
    }));
    const roots = historyState.buildErgonodeTree([], changes);

    assert.equal(roots.length, 1);
    assert.equal(roots[0].children[0].identifier, 'child');
    assert.equal(roots[0].children[0].ghost, 'deleted');
    assert.equal(roots[0].children[0].entityType, 'source');
});

test('historical descendants share the old ancestor chain while current children stay in their new branch', () => {
    const items = [
        {identifier: 'root', parent_identifier: null, label: 'Root'},
        {identifier: 'destination', parent_identifier: 'root', label: 'Destination'},
        {identifier: 'ancestor', parent_identifier: 'destination', label: 'Renamed ancestor', sort_order: 8},
        {identifier: 'middle', parent_identifier: 'ancestor', label: 'Middle'},
        {identifier: 'leaf', parent_identifier: 'destination', label: 'Leaf', sort_order: 4},
        {identifier: 'current-only', parent_identifier: 'middle', label: 'New child'}
    ];
    const changes = [
        {
            entity_identifier: 'leaf', actions: ['moved'],
            before: {...items[4], parent_identifier: 'middle', sort_order: 1}, after: items[4]
        },
        {
            entity_identifier: 'deleted', actions: ['deleted'],
            before: {identifier: 'deleted', parent_identifier: 'middle', label: 'Deleted', sort_order: 2}, after: null
        },
        {
            entity_identifier: 'ancestor', actions: ['moved', 'renamed'],
            before: {...items[2], parent_identifier: 'root', label: 'Old ancestor', sort_order: 2}, after: items[2]
        },
        {entity_identifier: 'current-only', actions: ['created'], before: null, after: items[5]}
    ];
    const original = JSON.stringify({items, changes});
    const roots = loadHistoryState().buildTree(items, changes);
    const previousAncestor = findNode(roots, 'ancestor', 'from');
    const previousMiddle = findNode(roots, 'middle', 'from');

    assert.equal(previousAncestor.item.label, 'Old ancestor');
    assert.equal(previousAncestor.item.sort_order, 2);
    assert.equal(previousAncestor.children[0], previousMiddle);
    assert.deepEqual(Array.from(previousMiddle.children, node => [node.identifier, node.ghost]), [
        ['leaf', 'from'], ['deleted', 'deleted']
    ]);
    assert.equal(findNode(roots, 'middle').children[0].identifier, 'current-only');
    assert.equal(findNode(roots, 'destination').children.includes(findNode(roots, 'leaf')), true);
    assert.equal(JSON.stringify({items, changes}), original);
});

test('moving to a former descendant does not create a cycle between historical and current parents', () => {
    const beforeParent = {identifier: 'parent', parent_identifier: null, label: 'Parent'};
    const beforeChild = {identifier: 'child', parent_identifier: 'parent', label: 'Child'};
    const items = [{...beforeParent, parent_identifier: 'child'}, {...beforeChild, parent_identifier: null}];
    const changes = [beforeParent, beforeChild].map((before, index) => ({
        entity_identifier: before.identifier, actions: ['moved'], before, after: items[index]
    }));
    const roots = loadHistoryState().buildTree(items, changes);

    assert.equal(roots.length, 2);
    assert.equal(findNode(roots, 'parent', 'from').children[0], findNode(roots, 'child', 'from'));
    assert.equal(findNode(roots, 'child').children[0], findNode(roots, 'parent'));
});

test('Ergonode retains a previous root location when the effective manual parent differs', () => {
    const manual = {identifier: 'manual', parent_identifier: null, source_parent_identifier: null};
    const after = {identifier: 'leaf', parent_identifier: 'manual', source_parent_identifier: 'manual'};
    const roots = loadHistoryState().buildErgonodeTree([manual, after], [{
        entity_type: 'source', entity_identifier: 'leaf', actions: ['source_moved'],
        before: {...after, source_parent_identifier: null}, after
    }]);

    assert.equal(roots.includes(findNode(roots, 'leaf', 'from')), true);
    assert.equal(findNode(roots, 'manual').children[0], findNode(roots, 'leaf'));
});

function previousMappingFixture(deletedParents = false) {
    const targets = [
        {identifier: '2', parent_identifier: null, label: 'Root', path: '1/2'},
        {identifier: '3', parent_identifier: '2', label: 'Gear', path: '1/2/3'},
        {identifier: '6', parent_identifier: '3', label: 'Watches', path: '1/2/3/6'},
        {identifier: '20', parent_identifier: '3', label: 'Women renamed', path: '1/2/3/20'},
        {identifier: '21', parent_identifier: '20', label: 'Tops', path: '1/2/3/20/21'},
        {identifier: '23', parent_identifier: '3', label: 'Jackets', path: '1/2/3/23'}
    ];
    const sources = [
        {identifier: 'gear', parent_identifier: null, label: 'Gear', magento_category_id: 3},
        {identifier: 'women', parent_identifier: 'gear', label: 'Women renamed', magento_category_id: 20},
        {identifier: 'tops', parent_identifier: 'women', label: 'Tops', magento_category_id: 21},
        {identifier: 'jackets', parent_identifier: 'gear', label: 'Sport Jackets', magento_category_id: 23}
    ];
    const changes = [
        {
            entity_type: 'source', entity_identifier: 'jackets', actions: ['moved', 'reconnected'],
            before: {...sources[3], parent_identifier: 'tops', label: 'Jackets', magento_category_id: 6, sort_order: 3},
            after: sources[3]
        },
        {
            entity_type: 'source', entity_identifier: 'women', actions: deletedParents ? ['deleted'] : ['moved', 'renamed'],
            before: {...sources[1], parent_identifier: null, label: 'Women'}, after: deletedParents ? null : sources[1]
        },
        {
            entity_type: 'target', entity_identifier: '20', actions: deletedParents ? ['deleted'] : ['moved', 'renamed'],
            before: {...targets[3], parent_identifier: '2', label: 'Women', path: '1/2/20'},
            after: deletedParents ? null : targets[3]
        },
        {
            entity_type: 'target', entity_identifier: '21', actions: deletedParents ? ['deleted'] : ['moved'],
            before: {...targets[4], path: '1/2/20/21'}, after: deletedParents ? null : targets[4]
        }
    ];
    if (deletedParents) {
        changes.push({entity_type: 'source', entity_identifier: 'tops', actions: ['deleted'], before: sources[2], after: null});
    }
    return {
        sources: sources.filter(item => !deletedParents || !['women', 'tops'].includes(item.identifier)),
        targets: targets.filter(item => !deletedParents || !['20', '21'].includes(item.identifier)), changes
    };
}

for (const deletedParents of [false, true]) {
    test(`previous remapping follows the recorded source layout through ${deletedParents ? 'deleted' : 'moved'} parents`, () => {
        const {sources, targets, changes} = previousMappingFixture(deletedParents);
        const roots = loadHistoryState().buildMagentoTree(sources, targets, changes);
        const previous = findNode(roots, '6', 'from');
        const tops = findNode(roots, '21', deletedParents ? 'deleted' : 'from');
        // The mapping was Watches under Gear, but the saved manual layout was Women / Tops / Jackets.
        assert.equal(previous.parentIdentifier, '21');
        assert.equal(tops.children.includes(previous), true);
        assert.equal(previous.item.label, 'Watches');
        assert.equal(previous.item.path, '1/2/3/6');
        assert.equal(previous.item.sort_order, 3);
        assert.equal(previous.sourceItems[0].label, 'Jackets');
        assert.equal(findNode(roots, '3').children.includes(previous), false);
        assert.equal(findNode(roots, '23').sourceItems[0].label, 'Sport Jackets');
        assert.equal(findNode(roots, '2').children.some(node => node.item.label === 'Women' && node.children.includes(tops)), true);
    });
}

test('previous remapping keeps an unmapped historical parent instead of flattening its children', () => {
    const {sources, targets, changes} = previousMappingFixture();
    sources[2] = {...sources[2], magento_category_id: null};
    const roots = loadHistoryState().buildMagentoTree(sources, targets, changes);
    const previous = findNode(roots, '6', 'from');
    const tops = findNode(roots, 'tops', 'from');

    assert.equal(tops.item.is_source_only, true);
    assert.equal(tops.item.label, 'Tops');
    assert.equal(tops.children.includes(previous), true);
    assert.equal(previous.parentIdentifier, 'tops');
});

test('simultaneous remapping and source movement retain Watches and Jackets as separate previous locations', () => {
    const targets = [
        {identifier: '2', parent_identifier: null, label: 'Root'},
        {identifier: '3', parent_identifier: '2', label: 'Gear'},
        {identifier: '6', parent_identifier: '3', label: 'Watches', sort_order: 1},
        {identifier: '20', parent_identifier: '2', label: 'Women'},
        {identifier: '21', parent_identifier: '20', label: 'Tops'},
        {identifier: '23', parent_identifier: '21', label: 'Jackets'}
    ];
    const sources = [
        {identifier: 'gear', parent_identifier: null, source_parent_identifier: null, magento_category_id: 3},
        {identifier: 'women', parent_identifier: null, source_parent_identifier: null, magento_category_id: 20},
        {identifier: 'tops', parent_identifier: 'women', source_parent_identifier: 'women', magento_category_id: 21},
        {identifier: 'watches', label: 'Watches', parent_identifier: 'gear', source_parent_identifier: 'gear',
            magento_category_id: 6, sort_order: 1, source_sort_order: 13},
        {identifier: 'jackets', label: 'Sport Jackets', parent_identifier: 'gear', source_parent_identifier: 'tops',
            magento_category_id: 23, sort_order: 2, source_sort_order: 3}
    ];
    const changes = [
        {entity_type: 'source', entity_identifier: 'watches', actions: ['reordered'],
            before: {...sources[3], sort_order: 13}, after: sources[3]},
        {entity_type: 'source', entity_identifier: 'jackets',
            actions: ['moved', 'source_moved', 'source_reordered', 'renamed', 'reconnected'],
            before: {...sources[4], label: 'Jackets', parent_identifier: 'tops', source_parent_identifier: 'gear',
                magento_category_id: 6, sort_order: 3, source_sort_order: 99}, after: sources[4]}
    ];
    const original = JSON.stringify({sources, targets, changes});
    const roots = loadHistoryState().buildMagentoTree(sources, targets, changes);
    const oldWatches = findNode(roots, '6', 'from');
    const watches = findNode(roots, '6');
    const oldJackets = findNode(roots, '23', 'from');
    const jackets = findNode(roots, '23');

    assert.equal(oldWatches.parentIdentifier, '21');
    assert.equal(oldWatches.item.sort_order, 3);
    assert.equal(watches.parentIdentifier, '3');
    assert.equal(watches.change.actions.includes('moved'), true);
    assert.equal(watches.change.actions.includes('reordered'), true);
    assert.deepEqual(Array.from(watches.sourceItems, item => item.identifier), ['watches']);
    assert.equal(watches.changes.find(change => change.entity_type === 'target').before.parent_identifier, '21');
    assert.equal(oldJackets.parentIdentifier, '3');
    assert.equal(oldJackets.item.label, 'Jackets');
    assert.equal(oldJackets.item.sort_order, 99);
    assert.equal(jackets.parentIdentifier, '21');
    assert.equal(jackets.sourceItems[0].label, 'Sport Jackets');
    assert.equal(JSON.stringify({sources, targets, changes}), original);
});

test('source-only movement with a stable mapping restores the complete previous source ancestor chain', () => {
    const sources = [
        {identifier: 'old', label: 'Old', parent_identifier: null, source_parent_identifier: null, magento_category_id: 10},
        {identifier: 'new', label: 'New', parent_identifier: null, source_parent_identifier: null, magento_category_id: 20},
        {identifier: 'middle', label: 'Middle', parent_identifier: 'new', source_parent_identifier: 'new', magento_category_id: null},
        {identifier: 'leaf', label: 'Leaf', parent_identifier: 'new', source_parent_identifier: 'new', magento_category_id: 30}
    ];
    const targets = [
        {identifier: '2', parent_identifier: null, label: 'Root'},
        {identifier: '10', parent_identifier: '2', label: 'Old'},
        {identifier: '20', parent_identifier: '2', label: 'New'},
        {identifier: '30', parent_identifier: '20', label: 'Leaf'}
    ];
    const changes = [
        {entity_type: 'source', entity_identifier: 'middle', actions: ['source_moved'],
            before: {...sources[2], source_parent_identifier: 'old'}, after: sources[2]},
        {entity_type: 'source', entity_identifier: 'leaf', actions: ['source_moved'],
            before: {...sources[3], source_parent_identifier: 'middle'}, after: sources[3]}
    ];
    const roots = loadHistoryState().buildMagentoTree(sources, targets, changes);
    const middle = findNode(roots, 'middle', 'from');

    assert.equal(findNode(roots, '10').children.includes(middle), true);
    assert.equal(middle.children[0], findNode(roots, '30', 'from'));
    assert.equal(findNode(roots, '30').parentIdentifier, '20');
});

test('wide trees sort siblings once rather than after every insertion', () => {
    let api;
    const sandbox = vm.createContext({define(_names, factory) { api = factory(); }});
    vm.runInContext(`
        var comparisons = 0;
        const originalSort = Array.prototype.sort;
        Array.prototype.sort = function (compare) {
            return originalSort.call(this, (a, b) => { comparisons++; return compare(a, b); });
        };
    `, sandbox);
    const file = path.resolve(__dirname, '../../view/adminhtml/web/js/category-tree-history-state.js');
    vm.runInContext(fs.readFileSync(file, 'utf8'), sandbox, {filename: file});
    const items = Array.from({length: 3000}, (_, index) => ({
        identifier: String(index), parent_identifier: null, sort_order: 3000 - index
    }));
    const nodes = api.buildTree(items, []);
    assert.equal(nodes.length, 3000);
    assert.equal(nodes[0].identifier, '2999');
    assert.equal(nodes[2999].identifier, '0');
    assert.ok(sandbox.comparisons < 100000, `Unexpected sort work: ${sandbox.comparisons}`);
});

test('text null remains a root identifier and can own current and deleted children', () => {
    const api = loadHistoryState();
    const parent = {identifier: 'null', parent_identifier: null, label: 'Null'};
    const child = {identifier: 'child', parent_identifier: 'null', label: 'Child'};
    const sibling = {identifier: 'other', parent_identifier: null, label: 'Other'};
    const current = api.buildErgonodeTree([parent, child, sibling], []);

    assert.equal(current.length, 2);
    assert.equal(findNode(current, 'null').children[0].identifier, 'child');
    const deleted = api.buildErgonodeTree([sibling], [parent, child].map(item => ({
        entity_type: 'source', entity_identifier: item.identifier, actions: ['deleted'],
        before: item, after: null
    })));

    assert.equal(deleted.length, 2);
    assert.equal(findNode(deleted, 'null', 'deleted').children[0].identifier, 'child');
});

test('prototype keys retain source mappings without inventing changes', () => {
    for (const code of ['__proto__', 'constructor', 'toString']) {
        const source = {identifier: code, parent_identifier: null, label: code, magento_category_id: 10};
        const targets = [
            {identifier: '2', parent_identifier: null, label: 'Root'},
            {identifier: '10', parent_identifier: '2', label: 'Target'}
        ];
        const roots = loadHistoryState().buildMagentoTree([source], targets, []);
        const target = findNode(roots, '10');

        assert.equal(target.sourceItems.length, 1, code);
        assert.equal(target.sourceItems[0].identifier, code);
        assert.equal(target.change, null, code);
    }
});

test('prototype keys retain their old and new mappings in historical projections', () => {
    for (const code of ['__proto__', 'constructor', 'toString']) {
        const before = {identifier: code, parent_identifier: null, label: code, magento_category_id: 10};
        const after = {...before, magento_category_id: 11};
        const change = {entity_type: 'source', entity_identifier: code, actions: ['reconnected'], before, after};
        const targets = [
            {identifier: '2', parent_identifier: null, label: 'Root'},
            {identifier: '10', parent_identifier: '2', label: 'Old'},
            {identifier: '11', parent_identifier: '2', label: 'New'}
        ];
        const roots = loadHistoryState().buildMagentoTree([after], targets, [change]);

        assert.equal(findNode(roots, '11').sourceItems[0].identifier, code);
        assert.equal(findNode(roots, code, 'mapping-from').sourceItems[0].magento_category_id, 10);
    }
});

for (const movement of ['moved', 'reordered', 'source_moved', 'source_reordered']) {
    test('text null and absent parents stay distinct during remapping with ' + movement, () => {
        for (const code of ['null', 'other']) {
            const before = {identifier: code, parent_identifier: null, source_parent_identifier: null,
                label: code, sort_order: 0, source_sort_order: 0, magento_category_id: 10};
            const after = {...before, magento_category_id: 11, sort_order: 1, source_sort_order: 1};
            if (movement === 'moved') { after.parent_identifier = 'parent'; }
            if (movement === 'source_moved') { after.source_parent_identifier = 'parent'; }
            const sibling = {identifier: code === 'null' ? 'other' : 'null', parent_identifier: null,
                label: 'Sibling', magento_category_id: 12};
            const parent = {identifier: 'parent', parent_identifier: null, label: 'Parent', magento_category_id: 13};
            const targets = [
                {identifier: '2', parent_identifier: null, label: 'Root', sort_order: 0},
                {identifier: '10', parent_identifier: '2', label: 'Old', sort_order: 1},
                {identifier: '11', parent_identifier: '2', label: 'New', sort_order: 2},
                {identifier: '12', parent_identifier: '2', label: 'Sibling', sort_order: 3},
                {identifier: '13', parent_identifier: '2', label: 'Parent', sort_order: 4}
            ];
            const change = {entity_type: 'source', entity_identifier: code,
                actions: ['reconnected', movement], before, after};
            const roots = loadHistoryState().buildMagentoTree([after, sibling, parent], targets, [change]);
            assert.equal(roots.length, 1);
            assert.equal(findNode(roots, '11').sourceItems[0].identifier, code);
            const previous = findNode(roots, movement.startsWith('source_') ? '11' : '10', 'from');
            assert.equal(previous.parentIdentifier, '2');
            assert.equal(previous.sourceItems[0].identifier, code);
            assert.equal(findNode(roots, '12').sourceItems[0].identifier, sibling.identifier);
        }
    });
}

test('historical projection still attaches children to a real parent with code null', () => {
    const parent = {identifier: 'null', parent_identifier: null, label: 'Parent', magento_category_id: 20};
    const before = {identifier: 'child', parent_identifier: 'null', label: 'Child', sort_order: 0,
        magento_category_id: 10};
    const after = {...before, magento_category_id: 11, sort_order: 1};
    const targets = [
        {identifier: '2', parent_identifier: null, label: 'Root'},
        {identifier: '20', parent_identifier: '2', label: 'Parent'},
        {identifier: '10', parent_identifier: '20', label: 'Old'},
        {identifier: '11', parent_identifier: '20', label: 'New'}
    ];
    const roots = loadHistoryState().buildMagentoTree([parent, after], targets, [{
        entity_type: 'source', entity_identifier: 'child', actions: ['reconnected', 'reordered'], before, after
    }]);
    assert.equal(findNode(roots, '10', 'from').parentIdentifier, '20');
    assert.equal(findNode(roots, '11').sourceItems[0].identifier, 'child');
});
