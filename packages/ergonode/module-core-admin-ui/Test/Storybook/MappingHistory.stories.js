import {expect, userEvent, within, waitFor} from 'storybook/test';
import {loadAmdModule, translateIdentity} from '@ergonode-storybook/load-amd-module.js';
import {createMappingWorkspace} from '@ergonode-storybook/mapping-workspace.js';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/attribute-mapping.css';
import '../../view/adminhtml/web/css/history-operations.css';
import operationsSource from '../../view/adminhtml/web/js/history/operations.js?raw';
import panelSource from '../../view/adminhtml/web/js/history/panel.js?raw';

const operations = loadAmdModule(operationsSource, {'mage/translate': translateIdentity});
const mount = loadAmdModule(panelSource, {'mage/translate': translateIdentity, 'Ergonode_CoreAdminUi/js/history/operations': operations});
const operation = id => ({operation_id: id, operation_code: 'save', status: 'success', origin: 'admin', actor_name: 'Anna Admin', started_at: '2026-09-07 10:20:00', finished_at: '2026-09-07 10:20:01', change_count: 5});

export default {
    id: 'ergo-c-029',
    title: 'Ergonode UI/Komponenty/Wspólne/ERGO-C-029 · Panel boczny',
    parameters: {layout: 'fullscreen', a11y: {test: 'error'}},
    args: {variant: 'history', view: 'attribute'},
    argTypes: {view: {control: 'select', options: ['attribute', 'category-attribute']}, variant: {control: 'select', options: ['history', 'empty', 'error']}},
    render: ({variant, view}) => {
        const root = createMappingWorkspace({view});
        const shell = root.querySelector('.vea-shell');
        const magento = shell.querySelector('[data-source-panel="magento"]');
        const content = document.createElement('div');
        content.className = 'vea-side-content';
        content.append(magento.querySelector('.vea-attribute-list'));
        magento.append(content);
        const panel = document.createElement('aside');
        panel.className = 'veah-workspace veah-mapping-history';
        panel.setAttribute('aria-label', 'Attribute mapping history');
        panel.innerHTML = '<header class="veah-mapping-head"><strong class="veui-panel-title">Attribute mapping history</strong></header><div class="veah-operation-list" data-role="history-operations"></div><div class="veui-message" data-role="history-message" role="status" hidden></div>';
        content.append(panel);
        const fetchOriginal = window.fetch;
        window.fetch = async url => {
            root.dataset.requestUrl = url;
            if (variant === 'error') { throw new Error('Fixture request failed'); }
            const older = new URL(url).searchParams.has('before_id');
            return {ok: true, json: async () => ({page: {items: variant === 'empty' ? [] : older ? [operation(1)] : [operation(3), operation(2)], total: variant === 'empty' ? 0 : 3, has_more: variant !== 'empty' && !older}})};
        };
        const domain = view === 'category-attribute' ? 'category_attribute_history' : 'product_attribute_history';
        const instance = mount({urls: {operations: '/fixture/' + domain + '/operations', history: '/fixture/' + domain + '/history'}}, panel);
        root.dataset.historyDomain = domain;
        window.__mappingHistoryCleanup = () => {instance.destroy(); window.fetch = fetchOriginal;};
        root.addEventListener('click', event => {
            if (event.target.closest('.veah-operation-select')) {
                event.preventDefault();
                root.dataset.navigationUrl = event.target.closest('a').href;
            }
        });
        return root;
    },
    beforeEach: () => () => {window.__mappingHistoryCleanup?.(); delete window.__mappingHistoryCleanup;}
};
export const Playground = {};
export const Empty = {args: {variant: 'empty'}};
export const RequestFailure = {
    args: {variant: 'error'},
    play: async ({canvasElement}) => {
        await waitFor(() => expect(within(canvasElement).getByText('History could not be loaded. Try again.')).toBeVisible());
        await expect(within(canvasElement).getByRole('button', {name: 'Try again'})).toBeVisible();
    }
};
export const NavigationAndPagination = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        await waitFor(() => expect(canvas.getByText('2 of 3 operations')).toBeVisible());
        const panel = canvasElement.querySelector('.veah-mapping-history');
        const magento = canvasElement.querySelector('[data-source-panel="magento"]');
        await expect(magento.contains(panel)).toBe(true);
        await expect(canvasElement.querySelector('.vea-shell').children).toHaveLength(3);
        await expect(panel.getBoundingClientRect().top).toBeGreaterThanOrEqual(
            magento.querySelector('.vea-attribute-list').getBoundingClientRect().bottom
        );
        const accent = getComputedStyle(panel.querySelector('.veah-current'), '::after');
        await expect(accent.height).toBe('42px');
        await expect(accent.boxShadow).toContain('inset');
        const link = panel.querySelector('a[data-id="3"]');
        link.focus();
        await userEvent.keyboard('{Enter}');
        await expect(canvasElement.querySelector('.vea-mapping').dataset.navigationUrl).toContain('operation_id=3');
        await expect(canvasElement.querySelector('.vea-mapping').dataset.navigationUrl).toContain(canvasElement.querySelector('.vea-mapping').dataset.historyDomain);
        await userEvent.click(canvas.getByRole('button', {name: 'Load 10 older'}));
        await waitFor(() => expect(canvas.getByText('3 of 3 operations')).toBeVisible());
        await expect(canvasElement.querySelector('.vea-mapping').dataset.requestUrl).toContain('before_id=2');
        await expect(panel.querySelectorAll('a.veah-operation-select')).toHaveLength(3);
    }
};

export const CategoryNavigationAndPagination = {args: {view: 'category-attribute'}, play: NavigationAndPagination.play};
