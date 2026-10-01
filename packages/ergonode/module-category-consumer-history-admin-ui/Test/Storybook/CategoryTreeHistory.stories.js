import { expect, userEvent, waitFor, within } from 'storybook/test';

import { loadAmdModule } from '@ergonode-storybook/load-amd-module.js';
import { createCoreModuleLoader } from '@ergonode-storybook/core-modules.js';
import { createNavigation } from '@ergonode-storybook/section-navigation.js';

import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CategoryAdminUi/view/adminhtml/web/css/category-tree-mapping.css';
import '../../view/adminhtml/web/css/category-tree-history.css';
import magentoMark from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/images/magento-mark.svg';
import ergonodeMark from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/images/m2_configuration.svg';
import bikePathIconUrl from '../../view/adminhtml/web/images/bike-path.svg';
import bicycleJourneyIconUrl from '../../view/adminhtml/web/images/bicycle-journey.svg';
import connectedIconUrl from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/images/auto-match.svg';
import disconnectedIconUrl from '@ergonode-modules/CategoryAdminUi/view/adminhtml/web/images/category-unmap.svg';
import newProductIconUrl from '../../view/adminhtml/web/images/new-product.svg';
import rankingPodiumIconUrl from '../../view/adminhtml/web/images/ranking-podium.svg';
import operationsSource from '../../view/adminhtml/web/js/category-tree-operations.js?raw';
import historySource from '../../view/adminhtml/web/js/category-tree-history.js?raw';
import historyStateSource from '../../view/adminhtml/web/js/category-tree-history-state.js?raw';

const changes = [
    {
        change_id: 1,
        entity_type: 'source',
        entity_identifier: 'chairs',
        category_code: 'chairs',
        actions: ['moved', 'renamed', 'reconnected'],
        before: source('chairs', 'Chairs', 'living-room', 1, 11, 'Chairs / Magento'),
        after: source('chairs', 'Dining Chairs', 'dining-room', 2, 12, 'Dining / Magento')
    },
    {
        change_id: 2,
        entity_type: 'source',
        entity_identifier: 'lighting',
        category_code: 'lighting',
        actions: ['created', 'connected'],
        before: null,
        after: source('lighting', 'Lighting', 'living-room', 3, 14, 'Lighting')
    },
    {
        change_id: 3,
        entity_type: 'source',
        entity_identifier: 'old-decor',
        category_code: 'old-decor',
        actions: ['deleted'],
        before: source('old-decor', 'Old decor', 'living-room', 4, null, null),
        after: null
    },
    {
        change_id: 4,
        entity_type: 'source',
        entity_identifier: 'tables',
        category_code: 'tables',
        actions: ['reordered', 'excluded'],
        before: source('tables', 'Tables', 'dining-room', 1, 13, 'Tables / Magento'),
        after: {...source('tables', 'Tables', 'dining-room', 4, 13, 'Tables / Magento'), active: false}
    },
    {
        change_id: 5,
        entity_type: 'target',
        entity_identifier: '12',
        category_code: 'chairs',
        actions: ['moved', 'reordered'],
        before: target('12', 'Dining / Magento', '2', 1, 'chairs'),
        after: target('12', 'Dining / Magento', '3', 2, 'chairs')
    },
    {
        change_id: 6,
        entity_type: 'source',
        entity_identifier: 'mapped-only',
        category_code: 'mapped-only',
        actions: ['connected'],
        before: source('mapped-only', 'Mapped only', 'living-room', 4, null, null),
        after: source('mapped-only', 'Mapped only', 'living-room', 4, 15, 'Mapped only')
    }
];

const selectedState = {
    tree: {
        category_tree_id: 7,
        tree_code: 'main-pl',
        root_category_id: 2,
        root_label: 'Default Category',
        is_active: true
    },
    source: [
        source('living-room', 'Living room', null, 1, 20, 'Living room / Magento'),
        source('dining-room', 'Dining room', null, 2, 21, 'Dining room / Magento'),
        source('archive', 'Archive', null, 3, 30, 'Archive / Magento'),
        source('archive-child', 'Archived collection', 'archive', 1, 31, 'Archived collection / Magento'),
        source('archive-leaf', 'Archived product group', 'archive-child', 1, 32, 'Archived product group / Magento'),
        changes[0].after,
        changes[1].after,
        changes[3].after,
        changes[5].after
    ],
    target: [
        target('2', 'Default Category', null, 0, null),
        target('3', 'Furniture / Magento', '2', 1, null),
        target('20', 'Living room / Magento', '2', 2, 'living-room'),
        target('21', 'Dining room / Magento', '2', 3, 'dining-room'),
        target('11', 'Chairs / Magento', '20', 1, null),
        target('13', 'Tables / Magento', '21', 1, 'tables'),
        target('14', 'Lighting', '20', 2, 'lighting'),
        target('15', 'Mapped only', '20', 3, 'mapped-only'),
        target('30', 'Archive / Magento', '2', 4, 'archive'),
        target('31', 'Archived collection / Magento', '30', 1, 'archive-child'),
        target('32', 'Archived product group / Magento', '31', 1, 'archive-leaf'),
        changes[4].after
    ],
    operation: {
        operation_id: 42,
        operation_code: 'synchronize_reset',
        origin: 'cli',
        mode: 'treeStream',
        status: 'success',
        actor_name: null,
        started_at: '2026-09-06 10:00:00',
        finished_at: '2026-09-06 10:00:03'
    },
    changes
};

const currentState = {...selectedState, operation: null, changes: []};
const operations = [
    {
        operation_id: 42,
        change_set_id: 70,
        operation_code: 'synchronize_reset',
        origin: 'cli',
        mode: 'treeStream',
        status: 'success',
        actor_name: null,
        started_at: '2026-09-06 10:00:00',
        finished_at: '2026-09-06 10:00:03',
        summary: {changes: 6, categories: 5, moved: 2},
        operation_summary: {events: 23, trees: 2, conflicts: 0},
        change_count: 6
    },
    {
        operation_id: 41,
        change_set_id: 69,
        operation_code: 'save',
        origin: 'admin',
        mode: 'treeStream',
        status: 'warning',
        actor_name: 'Anna Admin',
        started_at: '2026-09-05 14:32:00',
        finished_at: '2026-09-05 14:32:01',
        summary: {changes: 0, categories: 0},
        operation_summary: {updated: 0, unchanged: 5},
        change_count: 0
    }
];
const olderOperations = Array.from({length: 10}, (_, index) => ({
    operation_id: 40 - index,
    change_set_id: 68 - index,
    operation_code: 'synchronize',
    origin: index % 2 ? 'cron' : 'cli',
    mode: 'treeStream',
    status: 'success',
    actor_name: null,
    started_at: `2026-09-${String(4 - Math.floor(index / 3)).padStart(2, '0')} 02:00:00`,
    finished_at: `2026-09-${String(4 - Math.floor(index / 3)).padStart(2, '0')} 02:00:02`,
    summary: {changes: 1, categories: 1},
    operation_summary: {trees: 1},
    change_count: 1
}));
const matrixChanges = changes.concat([
    {
        change_id: 7,
        entity_type: 'source',
        entity_identifier: 'unmapped',
        category_code: 'unmapped',
        actions: ['disconnected'],
        before: source('unmapped', 'Unmapped category', 'living-room', 5, 18, 'Previous Magento category'),
        after: source('unmapped', 'Unmapped category', 'living-room', 5, null, null)
    },
    {
        change_id: 8,
        entity_type: 'source',
        entity_identifier: 'included',
        category_code: 'included',
        actions: ['included'],
        before: {...source('included', 'Included category', 'dining-room', 5, null, null), active: false},
        after: source('included', 'Included category', 'dining-room', 5, null, null)
    },
    {
        change_id: 9,
        entity_type: 'source',
        entity_identifier: 'source-shift',
        category_code: 'source-shift',
        actions: ['source_moved', 'source_reordered'],
        before: {...source('source-shift', 'Source-only movement', 'dining-room', 6, null, null), source_parent_identifier: 'old-source-parent', source_sort_order: 1},
        after: {...source('source-shift', 'Source-only movement', 'dining-room', 6, null, null), source_parent_identifier: 'new-source-parent', source_sort_order: 8}
    },
    {
        change_id: 10,
        entity_type: 'target',
        entity_identifier: '21',
        category_code: 'dining-room',
        actions: ['reordered'],
        before: target('21', 'Dining room / Magento', '2', 2, 'dining-room'),
        after: target('21', 'Dining room / Magento', '2', 3, 'dining-room')
    }
]);
const matrixState = {
    ...selectedState,
    source: selectedState.source.concat(matrixChanges.slice(changes.length).filter((change) => (
        change.entity_type === 'source' && change.after
    )).map((change) => change.after)),
    target: selectedState.target.concat([
        target('18', 'Previous Magento category', '20', 3, null)
    ]),
    changes: matrixChanges
};
const matrixOperations = [
    {
        ...operations[0],
        summary: {changes: matrixChanges.length, categories: 8},
        change_count: matrixChanges.length
    },
    operations[1],
    {
        operation_id: 40,
        change_set_id: 68,
        operation_code: 'synchronize',
        origin: 'cron',
        mode: 'treeStream',
        status: 'failed',
        actor_name: null,
        started_at: '2026-09-04 02:00:00',
        finished_at: '2026-09-04 02:00:02',
        summary: {changes: 1, categories: 1},
        operation_summary: {failed: 1},
        change_count: 1
    }
];

function source(identifier, label, parent, position, magentoCategoryId, magentoLabel) {
    return {
        identifier,
        label,
        parent_identifier: parent,
        source_parent_identifier: parent,
        sort_order: position,
        source_sort_order: position,
        magento_category_id: magentoCategoryId,
        magento_label: magentoLabel,
        active: true
    };
}

function target(identifier, label, parent, position, categoryCode) {
    return {
        identifier,
        label,
        parent_identifier: parent,
        sort_order: position,
        level: parent ? 2 : 1,
        path: parent ? `1/${parent}/${identifier}` : `1/${identifier}`,
        active: true,
        category_code: categoryCode
    };
}

// The archive branch is unrelated to the selected fixture operation.
function fixtureView(state, changesOnly) {
    const view = structuredClone(state);
    view.changes_only = changesOnly;
    if (changesOnly) {
        view.source = view.source.filter(item => view.changes.length && !item.identifier.startsWith('archive'));
        view.target = view.target.filter(item => view.changes.length && !['30', '31', '32'].includes(item.identifier));
    }
    return view;
}

function storyJquery() {
    return {};
}

storyJquery.ajax = (options) => {
    const isOperationsRequest = options.url === '/history/operations';
    const response = options.data.operation_id === 42 ? selectedState : {
        ...currentState,
        operation: operations.find((operation) => operation.operation_id === options.data.operation_id) || null
    };
    const chain = {
        abort() {},
        done(callback) {
            callback(isOperationsRequest ? {
                success: true,
                page: {
                    items: olderOperations,
                    total: 12,
                    page_size: 10,
                    has_more: false,
                    next_before_id: null
                }
            } : {success: true, state: fixtureView(response, options.data.changes_only === 1)});
            return chain;
        },
        fail() {
            return chain;
        },
        always(callback) {
            callback();
            return chain;
        }
    };

    return chain;
};

const productionHistoryState = loadAmdModule(
    historyStateSource,
    {},
    'Ergonode_CategoryConsumerHistoryAdminUi/js/category-tree-history-state'
);
const productionOperations = loadAmdModule(operationsSource, {'mage/translate': (value) => value});
function loadProductionHistory() {
    const core = createCoreModuleLoader();

    return loadAmdModule(
        historySource,
        {
            jquery: storyJquery,
            'mage/translate': (value) => value,
            'Ergonode_CoreAdminUi/js/workspace': core('Ergonode_CoreAdminUi/js/workspace'),
            'Ergonode_CoreAdminUi/js/entity-options': core('Ergonode_CoreAdminUi/js/entity-options'),
            'Ergonode_CategoryConsumerHistoryAdminUi/js/category-tree-history-state': productionHistoryState,
            'Ergonode_CategoryConsumerHistoryAdminUi/js/category-tree-operations': productionOperations
        },
        'Ergonode_CategoryConsumerHistoryAdminUi/js/category-tree-history'
    );
}


function historicalParentsState(scenario) {
    const state = structuredClone(selectedState);
    const removed = scenario === 'deleted';
    const oldSources = [
        source('women', 'Women', null, 1, 20, 'Women'),
        source('tops', 'Tops', 'women', 1, 21, 'Tops'),
        source('jackets', 'Jackets', 'tops', 3, 6, 'Watches')
    ];
    const oldTargets = [
        {...target('20', 'Women', '2', 1, 'women'), path: '1/2/20'},
        {...target('21', 'Tops', '20', 1, 'tops'), path: '1/2/20/21'}
    ];
    state.source = [
        source('gear', 'Gear', null, 2, 3, 'Gear'),
        {...oldSources[0], parent_identifier: 'gear', source_parent_identifier: 'gear'},
        oldSources[1],
        source('jackets', 'Sport Jackets', 'gear', 2, 23, 'Jackets')
    ];
    state.target = [
        target('2', 'Default Category', null, 0, null),
        {...target('3', 'Gear', '2', 2, 'gear'), path: '1/2/3'},
        {...target('6', 'Watches', '3', 1, null), path: '1/2/3/6'},
        {...oldTargets[0], parent_identifier: '3', path: '1/2/3/20'},
        {...oldTargets[1], path: '1/2/3/20/21'},
        {...target('23', 'Jackets', '3', 2, 'jackets'), path: '1/2/3/23'}
    ];
    state.changes = [
        {entity_type: 'source', entity_identifier: 'jackets', actions: ['moved', 'reconnected'],
            before: oldSources[2], after: state.source[3]},
        {entity_type: 'source', entity_identifier: 'women', actions: removed ? ['deleted'] : ['moved'],
            before: oldSources[0], after: removed ? null : state.source[1]},
        ...oldTargets.map((before, index) => ({entity_type: 'target', entity_identifier: before.identifier,
            actions: removed ? ['deleted'] : ['moved'], before, after: removed ? null : state.target[index + 3]}))
    ];
    if (removed) {
        state.changes.push({entity_type: 'source', entity_identifier: 'tops', actions: ['deleted'],
            before: oldSources[1], after: null});
        state.source = state.source.filter(item => !['women', 'tops'].includes(item.identifier));
        state.target = state.target.filter(item => !['20', '21'].includes(item.identifier));
    }
    return state;
}

function synchronizedMovementState() {
    const state = structuredClone(selectedState);

    state.source = [
        source('gear', 'Gear', null, 2, 3, 'Gear'),
        source('women', 'Women', null, 1, 20, 'Women'),
        source('tops', 'Tops', 'women', 1, 21, 'Tops'),
        {...source('watches', 'Watches', 'gear', 1, 6, 'Watches'), source_sort_order: 13},
        {...source('jackets', 'Sport Jackets', 'gear', 2, 23, 'Jackets'),
            source_parent_identifier: 'tops', source_sort_order: 3}
    ];
    state.target = [
        target('2', 'Default Category', null, 0, null),
        {...target('3', 'Gear', '2', 2, 'gear'), path: '1/2/3'},
        {...target('6', 'Watches', '3', 1, 'watches'), path: '1/2/3/6'},
        {...target('20', 'Women', '2', 1, 'women'), path: '1/2/20'},
        {...target('21', 'Tops', '20', 1, 'tops'), path: '1/2/20/21'},
        {...target('23', 'Jackets', '21', 2, 'jackets'), path: '1/2/20/21/23'}
    ];
    state.changes = [
        {entity_type: 'source', entity_identifier: 'watches', actions: ['reordered'],
            before: {...state.source[3], sort_order: 13}, after: state.source[3]},
        {entity_type: 'source', entity_identifier: 'jackets',
            actions: ['moved', 'source_moved', 'source_reordered', 'renamed', 'reconnected'],
            before: {...source('jackets', 'Jackets', 'tops', 3, 6, 'Watches'),
                source_parent_identifier: 'gear', source_sort_order: 99}, after: state.source[4]}
    ];
    return state;
}

function restoredBranchState() {
    const state = structuredClone(selectedState);

    state.source = [
        source('women', 'Women', null, 1, null, null),
        source('bottoms', 'Bottoms', 'women', 1, null, null),
        source('pants', 'Pants', 'bottoms', 1, null, null),
        source('shorts', 'Shorts', 'bottoms', 2, null, null)
    ];
    state.changes = [{change_id: 1, entity_type: 'source', entity_identifier: 'bottoms',
        category_code: 'bottoms', actions: ['created'], before: null, after: state.source[1]}];
    state.operation.operation_code = 'refresh_snapshot';
    return state;
}

function fixture() {
    const root = document.createElement('div');

    root.className = 'veui-workspace veui-workspace-viewbar vec-admin vech-workspace';
    root.innerHTML = `
        <div class="veui-toolbar veui-viewbar vech-toolbar">
            <label class="vech-tree-picker"><span>Category tree</span><select data-role="tree-picker"><option value="7">main-pl</option></select></label>
        </div>
        <div class="veui-message vech-message" data-role="message" hidden></div>
        <div class="veui-layout vech-layout">
            ${[['source', 'Ergonode', ergonodeMark], ['target', 'Magento', magentoMark]].map(([side, label, icon]) => `
                <section class="veui-panel vech-tree-panel" aria-labelledby="vech-title-${side}">
                    <header class="veui-panel-head veui-panel-head-with-tools vech-panel-head">
                        <div class="vech-panel-heading">
                            <strong id="vech-title-${side}" class="veui-panel-title vech-tree-title"><img src="${icon}" alt=""/><span class="veui-panel-title-text">${label}</span></strong>
                        </div>
                        <div class="veui-panel-head-tools">
                            <label class="veui-search veui-search-expandable"><span class="veui-search-icon" aria-hidden="true"></span><input type="search" data-role="tree-search" data-side="${side}" aria-label="Search ${label} tree" placeholder="Search the tree..."/></label>
                            ${side === 'source' ? '<span data-role="source-options"></span>' : ''}
                        </div>
                    </header>
                    <div id="vech-tree-${side}" class="vec-side-tree vech-tree" data-role="tree" data-side="${side}" role="group" aria-labelledby="vech-title-${side}" tabindex="0"></div>
                </section>`).join('')}
            <aside class="veui-panel vech-operation-panel">
                <header class="veui-panel-head vech-panel-head"><div class="vech-panel-heading"><strong class="veui-panel-title">Operations</strong></div><label class="vech-changes-filter"><input type="checkbox" data-role="changes-only" checked><span>Only changes</span></label></header>
                <div class="vech-operation-list" data-role="operation-list"></div>
            </aside>
        </div>
        <dialog class="vech-details-dialog" data-role="details-dialog" aria-labelledby="vech-details-title">
            <header class="veui-panel-head vech-panel-head"><strong id="vech-details-title" class="veui-panel-title">Operation details</strong><button type="button" class="vech-details-close" data-role="close-details" autofocus>Close</button></header>
            <div class="vech-details" data-role="operation-details"></div>
        </dialog>`;
    root.querySelector('.vech-toolbar').prepend(createNavigation({
        currentSection: 'category_history',
        categoryOptionsAvailable: true
    }));

    return root;
}

function render(args) {
    const root = fixture();
    const state = args.restoredBranch ? restoredBranchState() : args.synchronizedMovement ? synchronizedMovementState() :
        args.historicalParents ? historicalParentsState(args.historicalParents) :
        (args.unchanged ? {...currentState, operation: operations[1]} : args.current ? currentState : (args.matrix ? matrixState : selectedState));
    const config = {
        trees: [{category_tree_id: 7, tree_code: 'main-pl', history_url: '#'}],
        category_tree_id: 7,
        selected_operation_id: state.operation?.operation_id,
        selected_operation: args.operationOutsidePage ? state.operation : null,
        operations: args.unchangedPage ? [operations[1]] : args.operationOutsidePage ? [operations[0]] : args.restoredBranch ? [{...operations[0], operation_code: 'refresh_snapshot',
            summary: {changes: 1, categories: 1}, change_count: 1}] : args.matrix ? matrixOperations : operations,
        operations_pagination: args.restoredBranch ? {
            total: 1, page_size: 10, has_more: false, next_before_id: null
        } : args.matrix ? {
            total: matrixOperations.length,
            page_size: 10,
            has_more: false,
            next_before_id: null
        } : {
            total: 12,
            page_size: 10,
            has_more: true,
            next_before_id: 41
        },
        state: fixtureView(state, Boolean(args.changesOnly)),
        urls: {state: '/history/state', operations: '/history/operations', mapping: '/mapping?category_tree_id=7'}
    };

    if (!window.HTMLElement.prototype.scrollIntoView) {
        window.HTMLElement.prototype.scrollIntoView = function () {};
    }
    root.addEventListener('click', (event) => {
        const link = event.target.closest('a');

        if (link) {
            event.preventDefault();
            root.dataset.navigationUrl = link.getAttribute('href');
        }
    });
    loadProductionHistory()(config, root);
    return root;
}

export default {
    id: 'ergo-v-015',
    title: 'Ergonode UI/Widoki/Kategorie/ERGO-V-015 · Historia drzewa/Pełny widok',
    tags: ['autodocs'],
    argTypes: {
        matrix: {table: {disable: true}},
        current: {table: {disable: true}},
        restoredBranch: {table: {disable: true}},
        historicalParents: {table: {disable: true}}
    },
    render
};

export const Playground = {args: {changesOnly: true}};

export const CategoryNavigation = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const categoryGroup = within(canvas.getByRole('group', {name: 'Categories sections'}));

        await expect(categoryGroup.getByRole('link', {name: 'Categories'}))
            .toHaveAttribute('href', '#category-tree');
        await userEvent.click(canvas.getByRole('button', {name: 'Category options'}));
        await expect(categoryGroup.getByRole('link', {name: 'Tree'})).toBeVisible();
        await expect(categoryGroup.getByRole('link', {name: 'Attributes'})).toBeVisible();
        await expect(categoryGroup.queryByRole('link', {name: 'Options'})).not.toBeInTheDocument();
        await expect(categoryGroup.queryByRole('link', {name: 'History'})).not.toBeInTheDocument();
    }
};

export const RestoredBranchWithExistingChildren = {
    args: {restoredBranch: true},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const tree = canvasElement.querySelector('[data-role="tree"][data-side="source"]');
        const sourceTree = within(tree);
        const rootToggle = sourceTree.getByRole('button', {name: 'Collapse children: main-pl'});
        const pants = tree.querySelector('[data-node-key="pants"]');
        const icon = pants.querySelector('.vech-change-indicator-created');

        await expect(sourceTree.getByText('main-pl', {selector: 'strong'})).toBeVisible();
        await expect(pants).toBeVisible();
        await expect(sourceTree.getByText('Shorts')).toBeVisible();
        await expect(tree.textContent).not.toContain('Not mapped');
        await expect(tree.querySelectorAll('.vech-change-indicator-created')).toHaveLength(3);
        await expect(pants).not.toHaveClass('is-created');
        await expect(icon).toHaveAccessibleName('Child of a created category');
        await expect(getComputedStyle(icon).width).toBe('28px');
        await expect(getComputedStyle(icon.querySelector('.vech-change-icon')).maskSize).toBe('24px 20px');
        icon.focus();
        await expect(within(icon).getByRole('tooltip')).toBeVisible();
        await expect(within(icon).getByRole('tooltip')).toHaveTextContent(
            'This category belongs to the branch created at Bottoms.'
        );
        rootToggle.focus();
        await userEvent.keyboard('{Enter}');
        await expect(pants).not.toBeVisible();
        await userEvent.click(sourceTree.getByRole('button', {name: 'Expand children: main-pl'}));
        await expect(pants).toBeVisible();
        await userEvent.type(canvas.getByRole('searchbox', {name: 'Search Ergonode tree'}), 'pants');
        await expect(pants).toBeVisible();
        await expect(sourceTree.getByText('main-pl', {selector: 'strong'})).toBeVisible();
        await expect(sourceTree.getByText('Shorts')).not.toBeVisible();
        await userEvent.clear(canvas.getByRole('searchbox', {name: 'Search Ergonode tree'}));
        await userEvent.click(canvasElement.querySelector('[data-details-operation-id]'));
        const dialog = canvas.getByRole('dialog', {name: 'Operation details'});

        await expect(within(dialog).getByRole('button', {name: 'Bottoms Created'})).toBeVisible();
        await expect(dialog.querySelectorAll('.vech-change-link')).toHaveLength(1);
        await userEvent.click(within(dialog).getByRole('button', {name: 'Close'}));
    }
};

async function showConnected(canvasElement) {
    const canvas = within(canvasElement);

    await userEvent.click(canvas.getByRole('button', {name: 'Category actions: Ergonode'}));
    await userEvent.click(canvas.getByRole('button', {name: 'Show connected categories'}));
}

export const ConnectedVisibilityInteraction = {
    args: {matrix: true},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const tree = canvasElement.querySelector('[data-role="tree"][data-side="source"]');
        const target = canvasElement.querySelector('[data-role="tree"][data-side="target"]');
        const sourceTree = within(tree);
        const menuButton = canvas.getByRole('button', {name: 'Category actions: Ergonode'});
        const menu = menuButton.closest('details');
        const search = canvas.getByRole('searchbox', {name: 'Search Ergonode tree'});

        await expect(sourceTree.getByText('Mapped only')).not.toBeVisible();
        await expect(sourceTree.getByText('Unmapped category')).toBeVisible();
        await expect(sourceTree.getByText('Living room')).toBeVisible();
        await expect(sourceTree.getByText('Archive')).not.toBeVisible();
        const connectedRow = tree.querySelector('[data-node-key="mapped-only"]');
        const connectedIcon = connectedRow.querySelector('.vech-change-icon-connected');

        await expect(connectedIcon).toBeInTheDocument();
        await expect(connectedIcon).not.toBeVisible();
        await expect(tree.querySelector('.vech-change-icon-disconnected')).not.toBeInTheDocument();
        await expect(target.querySelector('.vech-change-icon-connected')).toBeVisible();

        await showConnected(canvasElement);
        await expect(menu).not.toHaveAttribute('open');
        await expect(menuButton).toHaveFocus();
        await expect(sourceTree.getByText('Mapped only')).toBeVisible();
        await expect(connectedIcon).toBeVisible();
        await expect(getComputedStyle(connectedIcon).maskImage)
            .toContain(new URL(connectedIconUrl, document.baseURI).href);
        const connectedIndicator = within(connectedRow).getByRole('img', {name: 'Connected'});

        connectedIndicator.focus();
        await expect(within(connectedIndicator).getByRole('tooltip')).toBeVisible();
        await expect(within(connectedIndicator).getByRole('tooltip')).toHaveTextContent('Magento: none → Mapped only');
        menuButton.focus();
        for (const row of tree.querySelectorAll('.vech-node-row')) {
            await expect(row).toHaveStyle({backgroundColor: 'rgba(0, 0, 0, 0)'});
            await expect(getComputedStyle(row).backgroundImage).toBe('none');
            await expect(getComputedStyle(row.querySelector('.vech-node-label')).fontSize).toBe('11px');
            await expect(getComputedStyle(row.querySelector('.vech-node-label')).color).toBe('rgb(100, 116, 139)');
            await expect(getComputedStyle(row.querySelector('.vech-ergonode-code')).fontSize).toBe('9.5px');
            await expect(getComputedStyle(row.querySelector('.vech-ergonode-code')).color).toBe('rgb(71, 85, 105)');
        }
        await userEvent.keyboard('{Enter}');
        const hide = canvas.getByRole('button', {name: 'Hide connected categories'});
        await expect(hide).toHaveAttribute('aria-pressed', 'false');
        hide.focus();
        await userEvent.keyboard('{Enter}');
        await expect(sourceTree.getByText('Mapped only')).not.toBeVisible();
        await expect(menuButton).toHaveFocus();
        await userEvent.keyboard(' ');
        await expect(canvas.getByRole('button', {name: 'Show connected categories'}))
            .toHaveAttribute('aria-pressed', 'true');
        await userEvent.keyboard('{Escape}');
        await expect(menu).not.toHaveAttribute('open');
        await expect(menuButton).toHaveFocus();

        await userEvent.type(search, 'Mapped only');
        await expect(sourceTree.getByText('Mapped only')).not.toBeVisible();
        await expect(sourceTree.getByText('No categories match your search.')).toBeVisible();
        await showConnected(canvasElement);
        await expect(sourceTree.getByText('Mapped only')).toBeVisible();
        await expect(sourceTree.getByText('Living room')).toBeVisible();
        await expect(sourceTree.getByText('Unmapped category')).not.toBeVisible();
        await expect(target.querySelector('[data-node-key="18"]')).toBeVisible();
        await userEvent.clear(search);

        await userEvent.click(menuButton);
        await userEvent.click(canvas.getByRole('button', {name: 'Hide connected categories'}));
        await userEvent.click(canvasElement.querySelector('[data-details-operation-id="42"]'));
        const dialog = canvas.getByRole('dialog');
        await userEvent.click(within(dialog).getByRole('button', {name: 'Mapped only Connected'}));
        await userEvent.click(within(dialog).getByRole('button', {name: 'Show on tree'}));
        await waitFor(() => expect(sourceTree.getByText('Mapped only')).toBeVisible());
        await expect(tree.querySelector('[data-node-key="mapped-only"]')).toHaveFocus();
        await userEvent.click(menuButton);
        await expect(canvas.getByRole('button', {name: 'Hide connected categories'}))
            .toHaveAttribute('aria-pressed', 'false');
        await userEvent.click(canvas.getByRole('link', {name: 'Categories'}));
        await expect(menu).not.toHaveAttribute('open');
    }
};

export const AllCategoriesConnected = {
    args: {current: true},
    play: async ({canvasElement}) => {
        const tree = canvasElement.querySelector('[data-role="tree"][data-side="source"]');

        await expect(within(tree).getByText('All categories are connected.')).toBeVisible();
        await expect(within(tree).getByText('Living room')).not.toBeVisible();
        await showConnected(canvasElement);
        await expect(within(tree).queryByText('All categories are connected.')).not.toBeInTheDocument();
        await expect(within(tree).getByText('Living room')).toBeVisible();
    }
};

export const HeaderSearchInteraction = {
    play: async ({canvasElement}) => {
        await expect(canvasElement.querySelectorAll('.veui-panel-subtitle, .vech-context, [data-role="state-context"]'))
            .toHaveLength(0);
        for (const side of ['source', 'target']) {
            const search = canvasElement.querySelector(`[data-role="tree-search"][data-side="${side}"]`);
            const panel = search.closest('.vech-tree-panel');
            const head = panel.querySelector('.veui-panel-head');
            const field = search.closest('.veui-search');
            const tree = panel.querySelector('[data-role="tree"]');
            const width = () => field.getBoundingClientRect().width;

            await expect(head).toContainElement(field);
            await expect(panel.querySelector('.veui-count')).not.toBeInTheDocument();
            await expect(tree.getBoundingClientRect().top).toBeCloseTo(head.getBoundingClientRect().bottom, 0);
            await waitFor(() => expect(width()).toBeCloseTo(34, 0));
            await expect(getComputedStyle(field).borderTopWidth).toBe('1px');
            await expect(getComputedStyle(field).borderTopStyle).toBe('solid');
            await expect(getComputedStyle(field).borderRadius).toBe('8px');

            try {
                for (const panelWidth of ['', '280px']) {
                    panel.style.width = panelWidth;
                    search.focus();
                    await expect(search).toHaveFocus();
                    await waitFor(() => expect(width()).toBeGreaterThan(100));
                    await expect(field.getBoundingClientRect().right).toBeLessThan(head.getBoundingClientRect().right);
                    await userEvent.type(search, 'Chairs');
                    tree.focus();
                    await expect(search).toHaveValue('Chairs');
                    await waitFor(() => expect(width()).toBeGreaterThan(100));
                    await expect(tree.querySelectorAll('.vech-node-row').length).toBeGreaterThan(0);
                    await userEvent.clear(search);
                    tree.focus();
                    await waitFor(() => expect(width()).toBeCloseTo(34, 0));
                }
            } finally {
                panel.style.width = '';
            }
        }
    }
};

export const ChangeMatrix = {
    args: {matrix: true},
    play: async ({canvasElement}) => {
        const tree = canvasElement.querySelector('[data-role="tree"][data-side="target"]');
        const reorderedRow = tree.querySelector('[data-node-key="21"]:not(.is-ghost)');
        const reorderedFromRow = tree.querySelector('[data-node-key="21"].is-ghost-from');
        const movedFromRow = tree.querySelector('[data-node-key="12"].is-ghost-from');
        const reorderedIcon = reorderedRow.querySelector('.vech-change-icon-reordered');
        const unmappedRow = tree.querySelector('[data-node-key="18"]');
        const unmappedIcon = unmappedRow.querySelector('.vech-change-icon-disconnected');
        const unmappedIndicator = unmappedRow.querySelector('.vech-change-indicator');
        const unmappedPath = unmappedRow.querySelector('.vec-card-path');

        await expect(reorderedRow.querySelectorAll(':scope > .vech-node-actions > .vech-change-indicator'))
            .toHaveLength(1);
        await expect(getComputedStyle(reorderedIcon).maskImage)
            .toContain(new URL(rankingPodiumIconUrl, document.baseURI).href);
        await expect(reorderedFromRow).toBeInTheDocument();
        await expect(reorderedFromRow.querySelector('.vech-change-icon-reordered')).toBeInTheDocument();
        await expect(getComputedStyle(reorderedFromRow.querySelector('.vech-node-label')).textDecorationLine)
            .not.toContain('line-through');
        await expect(getComputedStyle(movedFromRow.querySelector('.vech-node-label')).textDecorationLine)
            .not.toContain('line-through');
        await expect(getComputedStyle(movedFromRow.querySelector('.vech-magento-path-value')).textDecorationLine)
            .not.toContain('line-through');
        await expect(getComputedStyle(movedFromRow.querySelector('.vech-ergonode-code')).textDecorationLine)
            .not.toContain('line-through');
        await expect(getComputedStyle(movedFromRow).backgroundImage).toContain('repeating-linear-gradient');
        await expect(getComputedStyle(reorderedFromRow).backgroundImage).toContain('repeating-linear-gradient');
        await expect(movedFromRow.querySelector('.vech-ergonode-code').tagName).toBe('SPAN');
        await expect(movedFromRow.querySelector('.vech-mapped-code')).not.toBeInTheDocument();
        await expect(tree.querySelector('[data-node-key="12"] .vech-change-icon-reordered'))
            .not.toBeInTheDocument();
        await expect(unmappedRow).not.toHaveClass('is-ghost');
        await expect(unmappedRow.querySelector('.vech-node-label'))
            .toHaveTextContent('Previous Magento category');
        await expect(unmappedPath).toHaveTextContent('/1/20/18 (unmapped)');
        await expect(unmappedPath).toHaveAttribute('title', '/1/20/18 (unmapped)');
        await expect(unmappedPath.querySelector('.vech-unmapped-code')).toHaveTextContent('unmapped');
        await expect(unmappedPath.querySelector('.vech-unmapped-code').tagName).toBe('SPAN');
        await expect(getComputedStyle(unmappedPath.querySelector('.vech-unmapped-code')).textDecorationLine)
            .toContain('line-through');
        await expect(getComputedStyle(unmappedPath).textDecorationLine).not.toContain('line-through');
        await expect(unmappedIcon).toHaveClass('vec-unmap-icon');
        await expect(getComputedStyle(unmappedIcon).maskImage)
            .toContain(new URL(disconnectedIconUrl, document.baseURI).href);
        await expect(unmappedIcon).toHaveStyle({width: '17px', height: '17px'});
        await expect(getComputedStyle(unmappedIcon).maskSize).toBe('15px 15px');
        await expect(getComputedStyle(unmappedIcon).color).toBe('rgb(194, 65, 12)');
        await expect(unmappedRow).toHaveStyle({backgroundColor: 'rgb(255, 247, 237)'});
        await expect(getComputedStyle(unmappedRow).backgroundImage).toContain('repeating-linear-gradient');
        await expect(getComputedStyle(unmappedRow).backgroundImage).toContain('rgb(255, 247, 237)');
        await expect(tree.querySelector('.is-ghost-mapping-from[data-node-key="unmapped"]'))
            .not.toBeInTheDocument();
        unmappedIndicator.focus();
        await expect(within(unmappedIndicator).getByRole('tooltip')).not.toHaveTextContent('Ergonode code:');
    }
};

export const ErgonodeChangeMatrix = {
    args: {matrix: true},
    play: async ({canvasElement}) => {
        await showConnected(canvasElement);
        const tree = canvasElement.querySelector('[data-role="tree"][data-side="source"]');
        const movedIndicator = tree.querySelector('[data-node-key="chairs"]:not(.is-ghost) .vech-change-indicator');
        const movedIcon = movedIndicator.querySelector('.vech-change-icon-moved');
        const unmappedRow = tree.querySelector('[data-node-key="unmapped"]');
        const unmappedIcon = unmappedRow.querySelector('.vech-change-icon-disconnected');

        await expect(unmappedIcon).not.toBeInTheDocument();
        await expect(unmappedRow).toHaveStyle({backgroundColor: 'rgba(0, 0, 0, 0)'});
        await expect(tree.querySelector('[data-node-key="tables"] .vech-change-icon-connected'))
            .not.toBeInTheDocument();
        await expect(tree.querySelector('[data-node-key="tables"] .vech-change-icon-excluded')).toBeInTheDocument();
        await expect(getComputedStyle(tree.querySelector('[data-node-key="tables"]:not(.is-ghost) .vech-node-label')).textDecorationLine)
            .toContain('line-through');
        await expect(getComputedStyle(tree.querySelector('[data-node-key="tables"]:not(.is-ghost) .vech-ergonode-code')).textDecorationLine)
            .toContain('line-through');
        await expect(tree.querySelector('[data-node-key="included"] .vech-change-icon-included')).toBeInTheDocument();
        await expect(tree.querySelector('[data-node-key="old-decor"] .vec-delete-snapshot-icon')).toBeInTheDocument();
        await expect(getComputedStyle(movedIcon).maskImage)
            .toContain(new URL(bicycleJourneyIconUrl, document.baseURI).href);
        await expect(getComputedStyle(tree.querySelector(
            '[data-node-key="chairs"].is-ghost-from .vech-change-icon-moved'
        )).maskImage).toContain(new URL(bikePathIconUrl, document.baseURI).href);
        await expect(tree.querySelector('[data-node-key="source-shift"] .vech-change-icon-reordered'))
            .not.toBeInTheDocument();
        await expect(tree.querySelectorAll('[data-node-key="source-shift"]')).toHaveLength(2);
        await expect(tree.querySelector('[data-node-key="source-shift"].is-ghost-from')).toBeInTheDocument();
        await expect(tree.querySelector('.vech-change-info')).not.toBeInTheDocument();
        movedIndicator.focus();
        await expect(within(movedIndicator).getByRole('tooltip')).toBeVisible();
        await expect(tree.querySelector('[data-node-key="living-room"] .vech-node-actions')).not.toBeInTheDocument();
        await userEvent.click(canvasElement.querySelector('[data-details-operation-id]'));
        await userEvent.click(within(canvasElement).getByRole('button', {name: 'Unmapped category Disconnected'}));
        const unmappedBadge = canvasElement.querySelector('.vech-badge-unmapped');

        await expect(getComputedStyle(unmappedBadge).color).toBe('rgb(194, 65, 12)');
        await expect(unmappedBadge).toHaveStyle({backgroundColor: 'rgb(255, 247, 237)'});
    }
};

export const GroupedOperationInteraction = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const dialog = canvas.getByRole('dialog', {hidden: true});
        const magentoSearch = canvas.getByRole('searchbox', {name: 'Search Magento tree'});
        const ergonodeSearch = canvas.getByRole('searchbox', {name: 'Search Ergonode tree'});
        const detailsButton = () => canvasElement.querySelector('[data-details-operation-id="42"]');
        const showChange = async (name) => {
            await userEvent.click(detailsButton());
            await userEvent.click(within(dialog).getByRole('button', {name}));
            await userEvent.click(within(dialog).getByRole('button', {name: 'Show on tree'}));
            await expect(dialog).not.toBeVisible();
        };
        const magentoTree = canvasElement.querySelector('[data-role="tree"][data-side="target"]');
        const ergonodeTree = canvasElement.querySelector('[data-role="tree"][data-side="source"]');
        const magento = within(magentoTree);
        const ergonode = within(ergonodeTree);

        await expect(canvasElement.querySelector('.vech-layout')).toHaveClass('veui-layout');
        await expect(magentoSearch.closest('.veui-panel-head-with-tools')).not.toBeNull();
        await expect(ergonodeSearch.closest('.veui-panel-head-with-tools')).not.toBeNull();
        await expect(canvas.queryAllByRole('tab')).toHaveLength(0);
        await expect(magentoTree).toBeVisible();
        await expect(ergonodeTree).toBeVisible();
        await expect(dialog).not.toBeVisible();
        await expect(Array.from(canvasElement.querySelector('.vech-layout').children).map((panel) =>
            panel.querySelector('[data-role="tree"]')?.dataset.side || 'operations'))
            .toEqual(['source', 'target', 'operations']);
        await expect(canvasElement.querySelectorAll('.vech-layout > .veui-panel')).toHaveLength(3);
        await userEvent.click(detailsButton());
        await expect(dialog).toBeVisible();
        await expect(within(dialog).getByText('Selected change')).toBeVisible();
        await expect(within(dialog).getByRole('button', {name: 'Close'})).toHaveFocus();
        await userEvent.click(within(dialog).getByRole('button', {name: 'Close'}));
        await expect(detailsButton()).toHaveFocus();
        await expect(canvasElement.querySelector('[data-operation-id="42"] .vech-operation-meta'))
            .toHaveTextContent('CLI');
        await expect(canvasElement.querySelector('[data-operation-id="42"] .vech-operation-counts'))
            .toHaveTextContent('6 changes in 5 categories');
        await expect(detailsButton()).toHaveAccessibleName(/^Change list:/);
        await expect(canvas.getByText('2 of 12 operations · 10 remaining')).toBeInTheDocument();
        await userEvent.click(canvas.getByRole('button', {name: 'Load 10 older'}));
        await expect(canvas.getByText('12 of 12 operations · 0 remaining')).toBeInTheDocument();
        await expect(canvasElement.querySelectorAll('[data-operation-id]')).toHaveLength(13);
        await expect(magento.queryByText('Old decor')).not.toBeInTheDocument();
        await expect(canvas.queryByText('Unmapped Ergonode categories')).not.toBeInTheDocument();
        await expect(magentoTree.querySelectorAll('.vech-node-row.has-source').length).toBeGreaterThanOrEqual(4);
        await expect(magentoTree.querySelectorAll('.vech-node-row.has-change').length).toBeGreaterThanOrEqual(4);

        const unchangedRow = magentoTree.querySelector('.vech-node-row[data-node-key="20"]');

        await expect(unchangedRow).toHaveStyle({backgroundColor: 'rgba(0, 0, 0, 0)'});
        await expect(getComputedStyle(unchangedRow.querySelector('.vech-node-label')).color)
            .toBe('rgb(100, 116, 139)');
        await expect(unchangedRow).not.toHaveClass('has-change');
        await expect(magentoTree.querySelector('[data-node-key="14"].is-primary-created'))
            .toHaveStyle({backgroundColor: 'rgb(251, 244, 229)'});
        await expect(magentoTree.querySelector('[data-node-key="12"].is-primary-moved:not(.is-ghost)'))
            .toHaveStyle({backgroundColor: 'rgb(239, 246, 255)'});
        await expect(magentoTree.querySelector('[data-node-key="13"].is-primary-excluded'))
            .toHaveStyle({backgroundColor: 'rgb(255, 247, 237)'});
        const excludedBackground = getComputedStyle(magentoTree.querySelector('[data-node-key="13"].is-primary-excluded'))
            .backgroundImage;
        await expect(excludedBackground).toContain('repeating-linear-gradient');
        await expect(excludedBackground).toContain('rgb(255, 247, 237)');
        await expect(getComputedStyle(
            magentoTree.querySelector('[data-node-key="13"] .vech-node-label')
        ).textDecorationLine).toContain('line-through');
        await expect(getComputedStyle(
            magentoTree.querySelector('[data-node-key="13"] .vech-magento-path-value')
        ).textDecorationLine).toContain('line-through');
        await expect(getComputedStyle(
            magentoTree.querySelector('[data-node-key="13"] .vech-ergonode-code')
        ).textDecorationLine).toContain('line-through');
        await expect(magentoTree.querySelector('.vech-node-row.is-primary-connected'))
            .toHaveStyle({backgroundColor: 'rgb(236, 253, 245)'});
        await expect(ergonodeTree.querySelector('.vech-node-row.is-primary-deleted'))
            .toHaveStyle({backgroundColor: 'rgba(0, 0, 0, 0)'});
        for (const row of magentoTree.querySelectorAll('.vech-node-row:not(.has-change)')) {
            await expect(row.querySelector('.vech-node-actions')).not.toBeInTheDocument();
        }
        for (const row of magentoTree.querySelectorAll('.vech-node-row.has-change')) {
            await expect(row.querySelectorAll(':scope > .vech-node-actions > .vech-change-indicator'))
                .toHaveLength(1);
        }
        await expect(canvasElement.querySelector('.vech-change-info')).not.toBeInTheDocument();
        await expect(canvasElement.querySelector('.vech-change-indicator .vec-mapping-status-icon'))
            .not.toBeInTheDocument();
        const mappedIndicators = magentoTree.querySelectorAll('.vech-change-indicator .vech-change-icon-connected');

        await expect(mappedIndicators).toHaveLength(1);
        await expect(magentoTree.querySelector('[data-node-key="13"] .vech-change-icon-connected'))
            .not.toBeInTheDocument();
        const createdIcon = magentoTree.querySelector('[data-node-key="14"] .vech-change-icon-created');

        await expect(createdIcon).toBeInTheDocument();
        await expect(getComputedStyle(createdIcon).maskImage)
            .toContain(new URL(newProductIconUrl, document.baseURI).href);
        await expect(getComputedStyle(createdIcon).color).toBe('rgb(154, 111, 36)');
        await expect(magentoTree.querySelector('[data-node-key="12"]:not(.is-ghost) .vech-change-icon-moved'))
            .toBeInTheDocument();
        const previousMappedLocation = magentoTree.querySelector('[data-node-key="11"].is-ghost-from');

        await expect(previousMappedLocation)
            .toBeInTheDocument();
        await expect(previousMappedLocation.querySelector('.vech-change-icon-moved')).toBeInTheDocument();
        await expect(previousMappedLocation.querySelector('.vech-node-label'))
            .toHaveTextContent('Chairs / Magento');
        await expect(previousMappedLocation.querySelector('.vec-card-path'))
            .toHaveTextContent('/1/20/11 (chairs)');
        await expect(getComputedStyle(previousMappedLocation.querySelector('.vech-node-label')).textDecorationLine)
            .not.toContain('line-through');
        await expect(getComputedStyle(
            previousMappedLocation.querySelector('.vech-magento-path-value')
        ).textDecorationLine).not.toContain('line-through');
        await expect(getComputedStyle(
            previousMappedLocation.querySelector('.vech-ergonode-code')
        ).textDecorationLine).not.toContain('line-through');
        for (const icon of mappedIndicators) {
            await expect(icon).toHaveAttribute('aria-hidden', 'true');
            await expect(getComputedStyle(icon).maskImage)
                .toContain(new URL(connectedIconUrl, document.baseURI).href);
            await expect(icon).toHaveStyle({width: '17px', height: '17px'});
            await expect(getComputedStyle(icon).maskSize).toBe('15px 15px');
        }
        const movedIcon = magentoTree.querySelector('[data-node-key="12"]:not(.is-ghost) .vech-change-icon-moved');

        await expect(getComputedStyle(movedIcon).maskImage)
            .toContain(new URL(bicycleJourneyIconUrl, document.baseURI).href);
        await expect(getComputedStyle(previousMappedLocation.querySelector('.vech-change-icon-moved')).maskImage)
            .toContain(new URL(bikePathIconUrl, document.baseURI).href);
        await expect(magentoTree.querySelector('[data-node-key="12"]:not(.is-ghost) .vech-node-label'))
            .toHaveTextContent('Dining / Magento');
        await expect(magentoTree.querySelector('[data-node-key="12"]:not(.is-ghost) .vec-card-copy'))
            .not.toHaveTextContent('Dining Chairs');
        await expect(magentoTree.querySelector('.vech-source-label')).not.toBeInTheDocument();
        const diningPath = magentoTree.querySelector('[data-node-key="12"]:not(.is-ghost) .vec-card-path');

        await expect(diningPath).toHaveTextContent('/1/3/12 (chairs)');
        await expect(diningPath.querySelector('.vec-card-path-current')).toHaveTextContent('12');
        await expect(diningPath.querySelector('.vec-card-path-current').tagName).toBe('STRONG');
        await expect(diningPath.querySelector('.vech-mapped-code')).toHaveTextContent('chairs');
        await expect(diningPath.querySelector('.vech-mapped-code').tagName).toBe('STRONG');
        await expect(magentoTree.querySelector('[data-node-key="20"] .vech-ergonode-code').tagName)
            .toBe('SPAN');
        await expect(magentoTree.querySelector('[data-node-key="13"] .vech-node-label'))
            .toHaveTextContent('Tables / Magento');
        await expect(magentoTree.querySelector('[data-node-key="13"] .vec-card-path'))
            .toHaveTextContent('/1/21/13 (tables)');
        await expect(magentoTree.querySelector('[data-node-key="13"] .vech-ergonode-code').tagName)
            .toBe('SPAN');
        await expect(magentoTree.querySelector('[data-node-key="14"] .vech-node-label'))
            .toHaveTextContent('Lighting');
        await expect(magentoTree.querySelector('[data-node-key="14"] .vech-node-label'))
            .not.toHaveTextContent('→');
        await expect(magentoTree.querySelector('[data-node-key="14"] .vech-mapped-code').tagName)
            .toBe('STRONG');
        await expect(magentoTree.querySelector('[data-node-key="15"] .vech-mapped-code').tagName)
            .toBe('STRONG');
        for (const row of canvasElement.querySelectorAll('.vech-node-row')) {
            await expect(row).toHaveStyle({borderWidth: '0px', boxShadow: 'none', outlineStyle: 'none'});
        }

        await expect(magento.getByRole('button', {name: 'Expand children: Archive / Magento'}))
            .toHaveAttribute('aria-expanded', 'false');
        await expect(magento.getByText('Archived product group / Magento'))
            .not.toBeVisible();
        await expect(magento.getByText('Lighting')).toBeVisible();
        await expect(magento.getByRole('button', {name: 'Collapse children: Living room / Magento'}))
            .toHaveAttribute('aria-expanded', 'true');
        await userEvent.type(magentoSearch, 'Archived product group');
        await expect(magento.getByText('Archived product group / Magento')).toBeVisible();
        await userEvent.clear(magentoSearch);
        await expect(magento.getByText('Archived product group / Magento'))
            .not.toBeVisible();

        await userEvent.click(canvas.getByRole('button', {name: 'Collapse children: Default Category'}));
        await expect(magentoTree.querySelector('.vech-node-row[data-node-key="3"]')).not.toBeVisible();
        await showChange('Dining / Magento Moved, Reordered');
        await waitFor(() => {
            expect(magentoTree.querySelector('.vech-node-row.is-focused')).toBeVisible();
            expect(magentoTree.querySelector('.vech-node-row.is-focused')).toHaveAttribute('data-node-key', '12');
            expect(canvasElement.querySelector('.vech-change-link.is-selected')).toBeTruthy();
        });
        await expect(magentoTree.querySelector('.vech-node-row.is-focused')).toHaveFocus();
        await expect(magentoTree.querySelector('.vech-node-row.is-focused .vech-node-label'))
            .toHaveStyle({color: 'rgb(37, 99, 235)'});

        await userEvent.type(magentoSearch, 'tables');
        await expect(magento.getByText('Default Category')).toBeVisible();
        await expect(magento.getByText('Tables / Magento')).toBeVisible();
        await expect(magento.getByText('Lighting')).not.toBeVisible();
        await userEvent.clear(magentoSearch);

        const rootToggle = canvas.getByRole('button', {name: 'Collapse children: Default Category'});

        await userEvent.tab();
        rootToggle.focus();
        await userEvent.keyboard('{Enter}');
        await expect(rootToggle).toHaveAttribute('aria-expanded', 'false');
        await userEvent.keyboard('{Enter}');
        await expect(rootToggle).toHaveAttribute('aria-expanded', 'true');

        await showChange(/Dining Chairs/);
        await waitFor(() => {
            expect(ergonodeTree.querySelector('.vech-node-row.is-focused')).toHaveAttribute('data-node-key', 'chairs');
        });
        await expect(magentoTree).toBeVisible();
        await expect(ergonode.getByRole('button', {name: 'Expand children: Archive'}))
            .toHaveAttribute('aria-expanded', 'false');
        await expect(ergonode.getByText('Archived collection')).not.toBeVisible();
        await expect(ergonode.getByText('Dining Chairs')).toBeVisible();
        await expect(ergonodeTree.querySelector('[data-node-key="chairs"].is-ghost-from'))
            .toBeVisible();
        await expect(getComputedStyle(ergonodeTree.querySelector(
            '[data-node-key="chairs"].is-ghost-from .vech-node-label'
        )).textDecorationLine).not.toContain('line-through');
        await expect(ergonode.getByText('Old decor').closest('.vech-node').parentElement.closest('.vech-node'))
            .toHaveAttribute('data-key', 'source:living-room');

        await userEvent.click(ergonode.getByRole('button', {name: 'Collapse children: Living room'}));
        await expect(ergonode.getByText('Old decor')).not.toBeVisible();
        await showChange('Old decor Deleted');
        await waitFor(() => {
            expect(ergonodeTree.querySelector('.vech-node-row.is-focused')).toHaveAttribute('data-node-key', 'old-decor');
            expect(ergonode.getByText('Old decor')).toBeVisible();
        });

        await userEvent.type(ergonodeSearch, 'tables');
        await expect(ergonode.getByText('Dining room')).toBeVisible();
        for (const tableRow of ergonode.getAllByText('Tables')) {
            await expect(tableRow).toBeVisible();
        }
        await expect(ergonode.getByText('Old decor')).not.toBeVisible();
        await expect(magento.getByText('Lighting')).toBeVisible();
        await showChange('Dining / Magento Moved, Reordered');
        await waitFor(() => {
            expect(magentoTree.querySelector('.vech-node-row.is-focused')).toBeVisible();
            expect(magentoSearch).toHaveValue('');
            expect(ergonodeSearch).toHaveValue('tables');
        });
        await userEvent.clear(ergonodeSearch);

        detailsButton().focus();
        await userEvent.keyboard('{Enter}');
        await expect(dialog).toBeVisible();
        await userEvent.click(within(dialog).getByRole('button', {name: 'Close'}));
        await expect(detailsButton()).toHaveFocus();

        const currentLink = canvas.getByRole('link', {name: /Current state/});

        await expect(currentLink).toHaveAttribute('href', '/mapping?category_tree_id=7');
        currentLink.focus();
        await userEvent.keyboard('{Enter}');
        await expect(canvasElement.querySelector('.vech-workspace').dataset.navigationUrl).toBe('/mapping?category_tree_id=7');

        await userEvent.click(canvasElement.querySelector('[data-operation-id="42"]'));
        await expect(ergonode.getByText('Old decor')).toBeVisible();
        await expect(ergonode.getByText('Archived collection')).not.toBeVisible();
        await expect(canvasElement.querySelectorAll('.vech-change-indicator').length).toBeGreaterThan(0);

        await userEvent.click(canvasElement.querySelector('[data-details-operation-id="41"]'));
        await expect(dialog).toBeVisible();
        await expect(canvas.getByText('The operation did not change this tree.')).toBeVisible();
        await userEvent.click(within(dialog).getByRole('button', {name: 'Close'}));
        await expect(canvasElement.querySelector('[data-details-operation-id="41"]')).toHaveFocus();
        await expect(canvasElement.querySelectorAll('.vech-change-indicator')).toHaveLength(0);
        await expect(canvasElement.querySelectorAll('[data-role="toggle-children"][aria-expanded="true"]'))
            .toHaveLength(1);
        await expect(canvas.getByRole('button', {name: 'Collapse children: main-pl'})).toBeVisible();
    }
};


async function inspectHistoricalParents({canvasElement}) {
    const magento = canvasElement.querySelector('[data-role="tree"][data-side="target"]');
    const previous = magento.querySelector('[data-node-key="6"].is-ghost-from');
    const tops = previous.closest('.vech-node').parentElement.closest('.vech-node');
    const women = tops.parentElement.closest('.vech-node');
    const row = node => node.querySelector(':scope > .vech-node-line > .vech-node-row');

    await expect(row(tops)).toHaveAttribute('data-node-key', '21');
    await expect(row(women)).toHaveAttribute('data-node-key', '20');
    await expect(row(women)).toHaveClass('is-ghost');
    await expect(previous).toBeVisible();
    await expect(previous.querySelector('.vech-node-label')).toHaveTextContent('Watches');
    await expect(previous.querySelector('.vec-card-copy')).not.toHaveTextContent('Ergonode: Jackets');
    await expect(previous.querySelector('.vech-node-label')).not.toHaveTextContent('→');
    await expect(getComputedStyle(previous.querySelector('.vech-node-label')).textDecorationLine)
        .not.toContain('line-through');
    await expect(getComputedStyle(previous).backgroundImage).toContain('repeating-linear-gradient');
    const toggle = women.querySelector(':scope > .vech-node-line > button');
    await userEvent.click(toggle);
    await expect(previous).not.toBeVisible();
    toggle.focus();
    await userEvent.keyboard('{Enter}');
    await expect(previous).toBeVisible();
    const search = within(canvasElement).getByRole('searchbox', {name: 'Search Magento tree'});
    await userEvent.type(search, 'jackets');
    await expect(previous).toBeVisible();
    await expect(row(tops)).toBeVisible();
    await expect(row(women)).toBeVisible();
    await userEvent.clear(search);
    const ergonode = canvasElement.querySelector('[data-role="tree"][data-side="source"]');
    await showConnected(canvasElement);
    const oldJackets = ergonode.querySelector('[data-node-key="jackets"].is-ghost-from');
    const oldTops = oldJackets.closest('.vech-node').parentElement.closest('.vech-node');
    await expect(row(oldTops)).toHaveAttribute('data-node-key', 'tops');
    await expect(oldJackets).toBeVisible();
}

export const MovedHistoricalParents = {
    args: {historicalParents: 'moved'},
    play: inspectHistoricalParents
};

export const DeletedHistoricalParents = {
    args: {historicalParents: 'deleted'},
    play: inspectHistoricalParents
};

export const SynchronizedMovementAndRemapping = {
    args: {synchronizedMovement: true},
    play: async ({canvasElement}) => {
        const tree = canvasElement.querySelector('[data-role="tree"][data-side="target"]');
        const watches = tree.querySelector('[data-node-key="6"]:not(.is-ghost)');
        const oldWatches = tree.querySelector('[data-node-key="6"].is-ghost-from');
        const jackets = tree.querySelector('[data-node-key="23"]:not(.is-ghost)');
        const oldJackets = tree.querySelector('[data-node-key="23"].is-ghost-from');
        const parentRow = row => row.closest('.vech-node').parentElement.closest('.vech-node')
            .querySelector(':scope > .vech-node-line > .vech-node-row');

        await expect(watches.querySelector('.vech-change-icon-moved')).toBeInTheDocument();
        await expect(watches.querySelector('.vech-change-icon-reordered')).not.toBeInTheDocument();
        await expect(parentRow(watches)).toHaveAttribute('data-node-key', '3');
        await expect(parentRow(oldWatches)).toHaveAttribute('data-node-key', '21');
        await expect(parentRow(parentRow(oldWatches))).toHaveAttribute('data-node-key', '20');
        await expect(parentRow(jackets)).toHaveAttribute('data-node-key', '21');
        await expect(parentRow(oldJackets)).toHaveAttribute('data-node-key', '3');
        await expect(jackets.querySelector('.vech-node-label')).toHaveTextContent('Jackets');
        await expect(jackets.querySelector('.vec-card-copy')).not.toHaveTextContent('Sport Jackets');
        await expect(tree.querySelector('.vech-source-label')).not.toBeInTheDocument();
        await expect(oldJackets.querySelector('.vech-node-label')).toHaveTextContent('Jackets');
        await expect(oldWatches.querySelector('.vech-node-label')).toHaveTextContent('Watches');
        for (const row of [oldWatches, oldJackets]) {
            await expect(row).toBeVisible();
            await expect(getComputedStyle(row).backgroundImage).toContain('repeating-linear-gradient');
            for (const value of row.querySelectorAll('.vech-node-label, .vech-magento-path-value, .vech-ergonode-code')) {
                await expect(getComputedStyle(value).textDecorationLine).not.toContain('line-through');
            }
        }
        oldWatches.querySelector('.vech-change-indicator').focus();
        await expect(within(oldWatches).getByRole('tooltip')).toHaveTextContent('Previous parent: 21');
        oldJackets.querySelector('.vech-change-indicator').focus();
        await expect(within(oldJackets).getByRole('tooltip')).toHaveTextContent('Previous parent: 3');
        const search = within(canvasElement).getByRole('searchbox', {name: 'Search Magento tree'});
        await userEvent.type(search, 'jackets');
        await expect(oldJackets).toBeVisible();
        await expect(oldWatches).toBeVisible();
        await userEvent.clear(search);
        const gear = parentRow(oldJackets).closest('.vech-node').querySelector(':scope > .vech-node-line > button');
        await userEvent.click(gear);
        await expect(oldJackets).not.toBeVisible();
        gear.focus();
        await userEvent.keyboard('{Enter}');
        await expect(oldJackets).toBeVisible();
    }
};

export const ChangesOnly = {
    args: {changesOnly: true},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const toggle = canvas.getByRole('checkbox', {name: 'Only changes'});
        const archive = () => canvas.queryAllByText('Archive / Magento', {exact: true});
        const unchanged = () => canvasElement.querySelector('[data-operation-id="41"]');
        await expect(toggle).toBeChecked();
        await expect(archive()).toHaveLength(0);
        await expect(unchanged()).not.toBeInTheDocument();
        await userEvent.click(canvas.getByRole('button', {name: 'Load 10 older'}));
        await expect(canvasElement.querySelectorAll('.vech-operation-row')).toHaveLength(11);
        await expect(canvas.getByText('12 of 12 operations · 0 remaining')).toBeVisible();
        toggle.focus();
        await userEvent.keyboard(' ');
        await waitFor(() => expect(archive().length).toBeGreaterThan(0));
        await expect(toggle).toHaveFocus();
        await expect(toggle).not.toBeChecked();
        await expect(unchanged()).toBeVisible();
        await userEvent.click(unchanged());
        await userEvent.click(toggle);
        await waitFor(() => expect(archive()).toHaveLength(0));
        await expect(unchanged()).not.toBeInTheDocument();
        await userEvent.click(toggle);
        await expect(unchanged()).toHaveAttribute('aria-current', 'true');
    }
};

export const ChangesOnlyUnchangedDeepLink = {
    args: {changesOnly: true, unchanged: true, operationOutsidePage: true},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const unchanged = () => canvasElement.querySelector('[data-operation-id="41"]');
        await expect(unchanged()).not.toBeInTheDocument();
        await expect(canvasElement.querySelectorAll('[data-role="entity-card"]')).toHaveLength(0);
        await userEvent.click(canvas.getByRole('checkbox', {name: 'Only changes'}));
        await expect(unchanged()).toHaveAttribute('aria-current', 'true');
        await expect(canvasElement.querySelectorAll('[data-operation-id="41"]')).toHaveLength(1);
    }
};

export const ChangesOnlyEmptyPage = {
    args: {changesOnly: true, unchanged: true, unchangedPage: true},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        await expect(canvasElement.querySelectorAll('.vech-operation-row')).toHaveLength(0);
        await expect(within(canvasElement.querySelector('[data-role="operation-list"]')).getByText('No changes to show.')).toBeVisible();
        await userEvent.click(canvas.getByRole('button', {name: 'Load 10 older'}));
        await expect(canvasElement.querySelectorAll('.vech-operation-row')).toHaveLength(10);
        await expect(canvasElement.querySelector('[data-operation-id="41"]')).not.toBeInTheDocument();
    }
};
