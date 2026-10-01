import {expect, userEvent, within, waitFor} from 'storybook/test';
import {loadAmdModule, translateIdentity} from '@ergonode-storybook/load-amd-module.js';
import {createNavigation} from '@ergonode-storybook/section-navigation.js';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/history-operations.css';
import '../../view/adminhtml/web/css/history.css';
import ergonodeMark from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/images/m2_configuration.svg';
import disconnectedIconUrl from '../../view/adminhtml/web/images/mapping-disconnected.svg';
import magentoMark from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/images/magento-mark.svg';
import stateSource from '../../view/adminhtml/web/js/history-state.js?raw';
import operationsSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/history/operations.js?raw';
import screenSource from '../../view/adminhtml/web/js/history.js?raw';

const attribute = (code, label, mapped_code = null, active = true, type = 'text') => ({code, label, mapped_code, active, type, is_draft: false, scope: 'global'});
const source = [
    attribute('name', 'Category name', 'name'), attribute('colour', 'Category colour', null, true, 'select'),
    attribute('material', 'Material', 'fabric', false, 'multiselect'), attribute('season', 'Season'),
    attribute('brand', 'Brand', 'manufacturer', true, 'select')
];
const target = [
    attribute('name', 'Name', 'name'), attribute('color', 'Color', null, true, 'select'),
    attribute('fabric', 'Fabric', 'material', true, 'multiselect'), attribute('manufacturer', 'Manufacturer', 'brand', true, 'select'),
    attribute('description', 'Description', null, true, 'textarea'), attribute('weight', 'Weight', null, true, 'weight')
];
const changes = [
    {side: 'source', code: 'colour', actions: ['disconnected'], before: {...source[1], mapped_code: 'color'}, after: source[1]},
    {side: 'target', code: 'color', actions: ['disconnected'], before: {...target[1], mapped_code: 'colour'}, after: target[1]},
    {side: 'source', code: 'material', actions: ['excluded'], before: {...source[2], active: true}, after: source[2]},
    {side: 'source', code: 'brand', actions: ['connected'], before: {...source[4], mapped_code: null}, after: source[4]},
    {side: 'target', code: 'manufacturer', actions: ['connected'], before: {...target[3], mapped_code: null}, after: target[3]}
];
const operation = (id, extra = {}) => ({operation_id: id, operation_code: 'save', status: 'success', origin: 'admin', actor_name: 'Anna Admin', started_at: '2026-09-07 10:20:00', finished_at: '2026-09-07 10:20:01', change_count: changes.length, ...extra});
const selected = {operation: operation(44), source, target, changes};
const previous = {operation: operation(43, {change_count: 0, operation_code: 'synchronize', origin: 'cli', actor_name: null}), source, target, changes: []};
const older = [operation(42, {operation_code: 'auto_map'})];
const stateModule = loadAmdModule(stateSource);
const mount = loadAmdModule(screenSource, {'mage/translate': translateIdentity, 'Ergonode_CategoryAttributeHistoryAdminUi/js/history-state': stateModule, 'Ergonode_CoreAdminUi/js/history/operations': loadAmdModule(operationsSource, {'mage/translate': translateIdentity})});

// These fixture rows model the reduced response; PHP tests verify server selection.
function fixtureView(state, changesOnly) {
    const view = structuredClone(state);
    view.changes_only = changesOnly;
    if (changesOnly) {
        view.source = view.source.filter(item => view.changes.length && ['colour', 'material', 'brand'].includes(item.code));
        view.target = view.target.filter(item => view.changes.length && ['color', 'fabric', 'manufacturer'].includes(item.code));
    }
    return view;
}

export default {
    id: 'ergo-v-010',
    title: 'Ergonode UI/Widoki/Atrybuty kategorii/ERGO-V-010 · Historia mapowania/Pełny widok',
    parameters: {layout: 'fullscreen', a11y: {test: 'error'}},
    args: {variant: 'history', changesOnly: false},
    argTypes: {variant: {control: 'select', options: ['history', 'empty', 'unchanged', 'failed', 'matrix', 'drafts', 'drafts-unchanged', 'downloaded', 'legacy', 'request-error']}},
    beforeEach: () => {
        const originalFetch = window.fetch;
        const screens = [];
        window.fetch = async (url) => {
            const parsed = new URL(url);
            if (parsed.pathname.includes('request-error')) { throw new Error('Fixture request failed'); }
            return {ok: true, json: async () => parsed.pathname.endsWith('/operations') ?
                {page: {items: older, total: 3, has_more: false}} :
                {state: fixtureView(Number(parsed.searchParams.get('operation_id')) === 44 ? selected :
                    {...previous, operation: {...previous.operation, operation_id: Number(parsed.searchParams.get('operation_id'))}},
                parsed.searchParams.get('changes_only') === '1')}};
        };
        window.__categoryAttributeHistoryStoryScreens = screens;
        return () => {
            screens.forEach(screen => screen.destroy());
            window.fetch = originalFetch;
            delete window.__categoryAttributeHistoryStoryScreens;
        };
    },
    render: ({variant, changesOnly, operationOutsidePage, unchangedPage}) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'veui-workspace veui-workspace-viewbar veah-workspace';
        wrapper.style.height = '850px';
        wrapper.innerHTML = '<div class="veui-toolbar veui-viewbar"><strong>Category · Attribute mapping history</strong></div><div class="veah-screen"></div>';
        wrapper.querySelector('.veui-toolbar').prepend(createNavigation({
            currentSection: 'category_attribute_history', categoryOptionsAvailable: true
        }));
        let state = structuredClone(variant === 'unchanged' ? previous : selected);
        if (variant === 'drafts' || variant === 'drafts-unchanged') {
            state.source[3].is_draft = true;
            state.target[4].is_draft = true;
            state.changes = [
                {side: 'source', code: 'season', actions: ['draft_added'], before: {...state.source[3], is_draft: false}, after: state.source[3]},
                {side: 'target', code: 'description', actions: ['draft_added'], before: {...state.target[4], is_draft: false}, after: state.target[4]}
            ];
            state.operation.change_count = state.changes.length;
        }
        if (variant === 'drafts-unchanged') {
            state.changes = [];
            state.operation.change_count = 0;
        }
        if (variant === 'downloaded') {
            const downloaded = [attribute('activity', 'Activity', null, true, 'multiselect'),
                attribute('meta_keyword', 'Meta Keywords', null, true, 'textarea'), attribute('product_name', 'Product Name')];
            state.source.push(...downloaded);
            state.changes = downloaded.map(item => ({side: 'source', code: item.code, actions: ['created'], before: null, after: item}));
            state.operation.operation_code = 'refresh_snapshot';
            state.operation.change_count = downloaded.length;
        }
        if (variant === 'legacy') {
            state.source.concat(state.target).forEach(item => { delete item.is_draft; });
            state.changes = [];
            state.operation.change_count = 0;
        }
        if (variant === 'failed') { state.operation.status = 'failed'; }
        if (variant === 'matrix') {
            ['created', 'deleted', 'renamed', 'reconnected', 'included', 'type_changed', 'scope_changed'].forEach((action, index) => {
                const item = attribute('matrix_' + index, action.replace('_', ' '));
                if (action !== 'deleted') { state.target.push(item); }
                state.changes.push({side: 'target', code: item.code, actions: [action], before: action === 'created' ? null : item, after: action === 'deleted' ? null : item});
            });
            state.operation.change_count = state.changes.length;
        }
        const screen = mount({
            state: variant === 'empty' ? null : fixtureView(state, changesOnly),
            page: {items: variant === 'empty' ? [] : unchangedPage ? [state.operation] : operationOutsidePage ? [selected.operation] : [state.operation, state.operation.operation_id === 44 ? previous.operation : older[0]], total: variant === 'empty' ? 0 : 3, has_more: variant !== 'empty'},
            urls: {state: '/fixture/' + (variant === 'request-error' ? 'request-error' : 'state'), operations: '/fixture/operations', mapping: '/category_attribute/mapping'},
            icons: {source: ergonodeMark, target: magentoMark}, updateUrl: false
        }, wrapper.querySelector('.veah-screen'));
        window.__categoryAttributeHistoryStoryScreens?.push(screen);
        wrapper.addEventListener('click', event => {
            const link = event.target.closest('.veah-current');
            if (link) { event.preventDefault(); wrapper.dataset.navigationUrl = link.getAttribute('href'); }
        });
        return wrapper;
    }
};

export const Playground = {args: {changesOnly: true}};
export const Empty = {args: {variant: 'empty'}};
export const NoChanges = {args: {variant: 'unchanged'}};
export const FailedOperation = {args: {variant: 'failed'}};
export const StateMatrix = {args: {variant: 'matrix'}};

export const HistoryInteractions = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const left = canvasElement.querySelector('[data-role="attributes"][data-side="source"]');
        const middle = canvasElement.querySelector('[data-role="attributes"][data-side="target"]');
        await expect(left.querySelectorAll('[data-role="entity-card"]')).toHaveLength(3);
        await expect(within(left).queryByText('Category name')).not.toBeInTheDocument();
        await expect(within(left).queryByText('Brand')).not.toBeInTheDocument();
        await expect(left.querySelector('.veah-mapping')).not.toBeInTheDocument();
        await expect(left.querySelector('button')).not.toBeInTheDocument();
        await expect(getComputedStyle(left.querySelector('[data-code="material"]')).backgroundColor).toBe('rgb(248, 250, 252)');
        await expect(getComputedStyle(left.querySelector('[data-code="material"] .veah-label')).textDecorationLine).toBe('none');
        const disconnected = middle.querySelector('[data-role="entity-card"][data-code="color"]');
        await expect(getComputedStyle(disconnected).backgroundImage).toContain('repeating-linear-gradient');
        await expect(getComputedStyle(disconnected).color).toBe('rgb(194, 65, 12)');
        const formerMapping = disconnected.querySelector('.veah-mapping');
        const struck = formerMapping.querySelectorAll('.veah-disconnected-text');
        await expect(Array.from(struck, node => node.textContent)).toEqual(['Category', 'colour', '(colour)']);
        for (const node of struck) {
            await expect(getComputedStyle(node).textDecorationLine).toBe('line-through');
            await expect(node.textContent).not.toMatch(/\s/);
        }
        await expect(formerMapping.textContent).toBe('↳ Category colour (colour)');
        await expect(getComputedStyle(formerMapping).textDecorationLine).toBe('none');
        await expect(getComputedStyle(disconnected.querySelector('.veah-label')).textDecorationLine).toBe('none');
        await expect(getComputedStyle(disconnected.querySelector('.veah-card-body > .veah-code')).textDecorationLine).toBe('none');
        await expect(getComputedStyle(formerMapping.querySelector('.veah-mapping-arrow')).textDecorationLine).toBe('none');
        const disconnectIcon = disconnected.querySelector('.veah-disconnected-icon');
        await expect(getComputedStyle(disconnectIcon).maskImage).toContain(new URL(disconnectedIconUrl, document.baseURI).href);
        await expect(getComputedStyle(disconnectIcon).maskSize).toBe('15px 15px');
        await expect(disconnected.querySelector('.veah-change')).not.toHaveTextContent('⊘');
        await expect(canvasElement.querySelectorAll('.veah-attributes header time')).toHaveLength(0);
        await userEvent.type(canvas.getByRole('searchbox', {name: 'Search Magento attributes'}), 'fabric');
        await expect(middle.querySelectorAll('[data-role="entity-card"]')).toHaveLength(1);
        await userEvent.clear(canvas.getByRole('searchbox', {name: 'Search Magento attributes'}));
        const details = () => canvasElement.querySelector('[data-action="details"][data-id="44"]');
        details().focus();
        await userEvent.keyboard('{Enter}');
        const dialog = canvas.getByRole('dialog');
        await expect(dialog).toBeVisible();
        await expect(within(dialog).getByRole('button', {name: 'Close'})).toHaveFocus();
        await expect(within(dialog).getByRole('heading', {name: 'Before'})).toBeVisible();
        await expect(within(dialog).getByRole('heading', {name: 'After'})).toBeVisible();
        await userEvent.keyboard('{Escape}');
        await expect(dialog).not.toBeVisible();
        await expect(details()).toHaveFocus();
        await userEvent.click(details());
        await userEvent.click(within(dialog).getByRole('button', {name: 'Brand Ergonode · Connected'}));
        await userEvent.click(within(dialog).getByRole('button', {name: 'Show attribute'}));
        await expect(middle.querySelector('[data-code="manufacturer"].is-focused')).toHaveFocus();
        await userEvent.click(canvas.getByRole('button', {name: 'Load 10 older'}));
        await waitFor(() => expect(canvas.getByText('3 of 3 operations')).toBeVisible());
        await userEvent.click(canvasElement.querySelector('[data-action="select"][data-id="43"]'));
        await waitFor(() => expect(canvasElement.querySelectorAll('.veah-change')).toHaveLength(0));
        const current = canvas.getByRole('link', {name: 'Current state'});
        await expect(current).toHaveAttribute('href', '/category_attribute/mapping');
        current.focus();
        await userEvent.keyboard('{Enter}');
        await expect(canvasElement.querySelector('.veah-workspace').dataset.navigationUrl).toBe('/category_attribute/mapping');
    }
};

export const RequestFailure = {
    args: {variant: 'request-error'},
    play: async ({canvasElement}) => {
        await userEvent.click(canvasElement.querySelector('[data-action="select"][data-id="43"]'));
        await waitFor(() => expect(within(canvasElement).getByRole('status')).toHaveTextContent('History could not be loaded. Try again.'));
        await expect(canvasElement.querySelector('[data-action="select"][data-id="44"]')).toHaveAttribute('aria-pressed', 'true');
    }
};

export const DraftMappings = {
    args: {variant: 'drafts'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        await expect(canvas.getAllByText('Draft mapping — awaiting connection')).toHaveLength(2);
        await expect(canvas.getByText('2 changes')).toBeVisible();
        for (const card of canvasElement.querySelectorAll('.veah-card.is-draft')) {
            await expect(getComputedStyle(card).backgroundColor).toBe('rgb(245, 243, 255)');
            await expect(getComputedStyle(card).borderTopColor).toBe('rgb(196, 181, 253)');
        }
        await expect(canvas.getByText('Attribute state after the selected operation, including unmapped attributes.')).toBeVisible();
        const description = canvasElement.querySelector('[data-side="target"][data-code="description"]');
        await userEvent.click(within(description).getByRole('button', {name: 'Description: Added to draft mapping'}));
        const dialog = canvas.getByRole('dialog');
        await expect(within(dialog).getByText('Draft mapping — awaiting connection')).toBeVisible();
        await expect(within(dialog).getByText('Unmapped')).toBeVisible();
        await userEvent.keyboard('{Escape}');
    }
};

export const OlderEntryWithoutDraftStatus = {
    args: {variant: 'legacy'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText(/This older entry does not include draft mapping status/)).toBeVisible();
        await expect(canvas.queryByText('Draft mapping — awaiting connection')).not.toBeInTheDocument();
        await userEvent.click(canvasElement.querySelector('[data-action="details"][data-id="44"]'));
        await expect(within(canvas.getByRole('dialog')).getByText(/No changes were recorded for this operation/)).toBeVisible();
        await userEvent.keyboard('{Escape}');
    }
};

export const UnchangedDraftMappings = {
    args: {variant: 'drafts-unchanged'},
    play: async ({canvasElement}) => {
        const cards = canvasElement.querySelectorAll('.veah-card.is-draft');
        await expect(cards).toHaveLength(2);
        for (const card of cards) {
            await expect(getComputedStyle(card).backgroundColor).toBe('rgb(245, 243, 255)');
            await expect(card.querySelector('.veah-change')).not.toBeInTheDocument();
        }
    }
};

export const DownloadedAttributes = {
    args: {variant: 'downloaded'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const sourcePanel = canvasElement.querySelector('[data-role="attributes"][data-side="source"]');
        await expect(within(sourcePanel).getAllByText('New', {exact: true})).toHaveLength(3);
        const activity = sourcePanel.querySelector('[data-code="activity"]');
        await expect(getComputedStyle(activity).backgroundColor).toBe('rgb(255, 251, 235)');
        const details = within(activity).getByRole('button', {name: 'Activity: Created'});
        details.focus();
        await userEvent.keyboard('{Enter}');
        const dialog = canvas.getByRole('dialog');
        await expect(within(dialog).getByText('Not present')).toBeVisible();
        await userEvent.click(within(dialog).getByRole('button', {name: 'Show attribute'}));
        await expect(sourcePanel.querySelector('[data-code="activity"]')).toHaveFocus();
        await userEvent.click(canvasElement.querySelector('[data-action="select"][data-id="43"]'));
        await waitFor(() => expect(canvasElement.querySelectorAll('[data-role="new-attribute"]')).toHaveLength(0));
    }
};

export const ChangesOnly = {
    args: {changesOnly: true},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const toggle = canvas.getByRole('checkbox', {name: 'Only changes'});
        const rows = () => canvasElement.querySelectorAll('[data-role="entity-card"]');
        const unchanged = () => canvasElement.querySelector('[data-action="select"][data-id="43"]');
        await expect(toggle).toBeChecked();
        await expect(rows()).toHaveLength(5);
        await expect(unchanged()).not.toBeInTheDocument();
        await expect(canvas.queryByText('Weight', {exact: true})).not.toBeInTheDocument();
        await userEvent.click(canvas.getByRole('button', {name: 'Load 10 older'}));
        await waitFor(() => expect(canvas.getByText('3 of 3 operations')).toBeVisible());
        await expect(canvasElement.querySelectorAll('[data-action="select"]')).toHaveLength(2);
        toggle.focus();
        await userEvent.keyboard(' ');
        await waitFor(() => expect(rows()).toHaveLength(9));
        await expect(toggle).not.toBeChecked();
        await expect(toggle).toHaveFocus();
        await expect(canvas.getByText('Weight', {exact: true})).toBeVisible();
        await expect(unchanged()).toBeVisible();
        await userEvent.click(unchanged());
        await waitFor(() => expect(unchanged()).toHaveAttribute('aria-pressed', 'true'));
        await userEvent.click(toggle);
        await waitFor(() => expect(rows()).toHaveLength(0));
        await expect(unchanged()).not.toBeInTheDocument();
        await expect(canvas.getAllByText('No changes to show.')).toHaveLength(2);
        await userEvent.click(toggle);
        await waitFor(() => expect(rows()).toHaveLength(9));
        await expect(unchanged()).toHaveAttribute('aria-pressed', 'true');
    }
};

export const ChangesOnlyUnchangedDeepLink = {
    args: {changesOnly: true, variant: 'unchanged', operationOutsidePage: true},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const unchanged = () => canvasElement.querySelector('[data-action="select"][data-id="43"]');
        await expect(unchanged()).not.toBeInTheDocument();
        await expect(canvasElement.querySelectorAll('[data-role="entity-card"]')).toHaveLength(0);
        await userEvent.click(canvas.getByRole('checkbox', {name: 'Only changes'}));
        await waitFor(() => expect(unchanged()).toHaveAttribute('aria-pressed', 'true'));
        await expect(canvasElement.querySelectorAll('[data-action="select"][data-id="43"]')).toHaveLength(1);
    }
};

export const ChangesOnlyEmptyPage = {
    args: {changesOnly: true, variant: 'unchanged', unchangedPage: true},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        await expect(canvasElement.querySelectorAll('[data-action="select"]')).toHaveLength(0);
        await expect(within(canvasElement.querySelector('[data-role="operations"]')).getByText('No changes to show.')).toBeVisible();
        await userEvent.click(canvas.getByRole('button', {name: 'Load 10 older'}));
        await waitFor(() => expect(canvasElement.querySelector('[data-action="select"][data-id="42"]')).toBeVisible());
        await expect(canvasElement.querySelector('[data-action="select"][data-id="43"]')).not.toBeInTheDocument();
    }
};

export const FilterRequestFailure = {
    args: {changesOnly: true, variant: 'request-error'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const toggle = canvas.getByRole('checkbox', {name: 'Only changes'});
        await userEvent.click(toggle);
        await waitFor(() => expect(toggle).toBeChecked());
        await expect(canvas.getByRole('status')).toHaveTextContent('History could not be loaded. Try again.');
        await expect(canvas.queryByText('Weight', {exact: true})).not.toBeInTheDocument();
    }
};

export const LateFilterResponse = {
    args: {changesOnly: true},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const toggle = canvas.getByRole('checkbox', {name: 'Only changes'});
        const fetchFixture = window.fetch;
        let release;
        window.fetch = async url => {
            if (new URL(url).searchParams.get('changes_only') === '0') {
                await new Promise(resolve => { release = resolve; });
            }
            return fetchFixture(url);
        };
        await userEvent.click(toggle);
        await waitFor(() => expect(release).toBeTypeOf('function'));
        await userEvent.click(toggle);
        await waitFor(() => expect(canvasElement.querySelector('[aria-busy="true"]')).not.toBeInTheDocument());
        release();
        await waitFor(() => expect(toggle).toBeChecked());
        await expect(canvas.queryByText('Weight', {exact: true})).not.toBeInTheDocument();
    }
};
