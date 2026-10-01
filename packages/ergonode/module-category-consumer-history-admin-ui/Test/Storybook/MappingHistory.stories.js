import { expect, userEvent, waitFor, within } from 'storybook/test';
import { loadAmdModule } from '@ergonode-storybook/load-amd-module.js';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CategoryAdminUi/view/adminhtml/web/css/category-tree-mapping.css';
import '../../view/adminhtml/web/css/category-tree-history.css';
import operationsSource from '../../view/adminhtml/web/js/category-tree-operations.js?raw';
import panelSource from '../../view/adminhtml/web/js/category-tree-mapping-history.js?raw';

const operationsView = loadAmdModule(operationsSource, {'mage/translate': (value) => value});
const operations = Array.from({length: 12}, (_, index) => ({
    operation_id: 50 - index,
    operation_code: index % 2 ? 'synchronize_reset' : 'save',
    origin: index % 2 ? 'cron' : 'admin',
    mode: 'treeStream',
    status: ['success', 'warning', 'failed'][index % 3],
    actor_name: index % 2 ? null : 'Anna Admin',
    finished_at: '2026-09-06 10:32:01',
    summary: {categories: 3},
    change_count: 4
}));

function render(args) {
    const root = document.createElement('div');
    const handlers = new Map();
    let treeId = 7;
    let failed = false;
    root.id = 'ergonode-category-tree-mapping';
    root.className = 'veui-workspace vec-admin';
    root.style.cssText = `width: ${args.width}px; max-width: 100%; height: 700px;`;
    root.innerHTML = `
        <aside class="veui-panel vec-panel vec-settings-panel" style="height:100%">
            <header class="veui-panel-head"><strong class="veui-panel-title">Mapping</strong></header>
            <div class="vec-settings-body">
                <div class="vec-configuration-list">
                    <button class="vec-configuration-card is-selected" data-tree="7" aria-pressed="true">Default mapping</button>
                    <button class="vec-configuration-card" data-tree="8" aria-pressed="false">Outlet mapping</button>
                </div>
                <section class="vech-mapping-history" aria-label="Mapping history">
                    <header class="veui-panel-head"><strong class="veui-panel-title">Operations</strong></header>
                    <div class="veui-message" data-role="history-message" role="status" hidden></div>
                    <div class="vech-operation-list" data-role="operation-list"></div>
                </section>
            </div>
        </aside>`;
    const requests = [];
    root.historyRequests = requests;
    root.veaCategoryMappingApi = {getCategoryTreeId: () => treeId};
    const jquery = () => ({on(names, callback) {
        names.split(' ').forEach((name) => handlers.set(name.split('.')[0], callback));
    }});
    jquery.ajax = (options) => {
        requests.push(options);
        const callbacks = {};
        let aborted = false;
        const request = {
            done(callback) { callbacks.done = callback; return request; },
            fail(callback) { callbacks.fail = callback; return request; },
            always(callback) { callbacks.always = callback; return request; },
            abort() { aborted = true; callbacks.fail({}, 'abort'); callbacks.always(); }
        };
        queueMicrotask(() => {
            if (aborted || args.state === 'loading') {
                return;
            }
            if (args.state === 'error' && !failed) {
                failed = true;
                callbacks.fail({}, 'error');
            } else {
                const items = args.state === 'empty' || options.data.category_tree_id === 8 ? [] : operations;
                const offset = options.data.before_operation_id ? 10 : 0;
                callbacks.done({success: true, page: {
                    items: items.slice(offset, offset + 10), total: items.length, page_size: 10,
                    has_more: items.length > offset + 10, next_before_id: items.length > offset + 10 ? 41 : null
                }});
            }
            callbacks.always();
        });
        return request;
    };
    const panel = loadAmdModule(panelSource, {
        jquery,
        'mage/translate': (value) => value,
        'Ergonode_CategoryConsumerHistoryAdminUi/js/category-tree-operations': operationsView
    });
    root.querySelectorAll('[data-tree]').forEach((button) => button.addEventListener('click', () => {
        treeId = Number(button.dataset.tree);
        root.querySelectorAll('[data-tree]').forEach((item) => {
            const selected = Number(item.dataset.tree) === treeId;
            item.classList.toggle('is-selected', selected);
            item.setAttribute('aria-pressed', String(selected));
        });
        handlers.get('ergonode:category-mapping:ready')({}, root.veaCategoryMappingApi);
    }));
    root.addEventListener('click', (event) => {
        const link = event.target.closest('a');
        if (link) {
            event.preventDefault();
            root.dataset.navigationUrl = link.href;
        }
    });
    panel({urls: {operations: '/operations', history: '/history'}}, root.querySelector('.vech-mapping-history'));
    return root;
}

export default {
    id: 'ergo-v-015-02',
    title: 'Ergonode UI/Widoki/Kategorie/ERGO-V-015 · Historia drzewa/ERGO-V-015.02 · Historia mapowania',
    tags: ['autodocs'],
    render,
    args: {state: 'ready', width: 380},
    argTypes: {state: {control: 'select', options: ['ready', 'empty', 'loading', 'error']}},
    parameters: {a11y: {test: 'error'}}
};

export const Playground = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const root = canvasElement.querySelector('#ergonode-category-tree-mapping');
        await waitFor(() => expect(canvas.getByText('10 of 12 operations · 2 remaining')).toBeVisible());
        await expect(canvas.getByRole('button', {name: /Current state/})).toHaveAttribute('aria-current', 'true');
        await expect(root.historyRequests).toHaveLength(1);
        await userEvent.click(canvas.getByRole('button', {name: 'Load 2 older'}));
        await waitFor(() => expect(canvas.getByText('12 of 12 operations · 0 remaining')).toBeVisible());
        const oldest = canvasElement.querySelector('[data-operation-id="39"]');
        oldest.focus();
        await userEvent.keyboard('{Enter}');
        await expect(new URL(root.dataset.navigationUrl).searchParams.get('operation_id')).toBe('39');
        await expect(new URL(root.dataset.navigationUrl).searchParams.get('category_tree_id')).toBe('7');
        await expect(canvasElement.querySelectorAll('[data-details-operation-id]')).toHaveLength(0);
        await expect(new URL(root.dataset.navigationUrl).searchParams.has('details')).toBe(false);
        await userEvent.click(canvas.getByRole('button', {name: 'Outlet mapping'}));
        await waitFor(() => expect(canvas.getByText('No recorded operations')).toBeVisible());
        await expect(canvasElement.querySelectorAll('[data-operation-id]')).toHaveLength(1);
        await expect(root.historyRequests.at(-1).data).toEqual({category_tree_id: 8});
    }
};
export const Appearance = {};
export const Narrow = {args: {width: 280}};
export const Empty = {args: {state: 'empty'}};
export const Loading = {args: {state: 'loading'}};
export const Error = {
    args: {state: 'error'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const root = canvasElement.querySelector('#ergonode-category-tree-mapping');
        await waitFor(() => expect(canvas.getByRole('button', {name: 'Retry'})).toBeVisible());
        await userEvent.click(canvas.getByRole('button', {name: 'Retry'}));
        await waitFor(() => expect(canvas.getByText('10 of 12 operations · 2 remaining')).toBeVisible());
        await expect(root.historyRequests).toHaveLength(2);
    }
};
