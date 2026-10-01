import {expect, userEvent, within, waitFor} from 'storybook/test';
import {loadAmdModule, translateIdentity} from '@ergonode-storybook/load-amd-module.js';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/history-operations.css';
import '../../view/adminhtml/web/css/history.css';
import ergonodeMark from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/images/m2_configuration.svg';
import disconnectedIconUrl from '../../view/adminhtml/web/images/mapping-disconnected.svg';
import magentoMark from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/images/magento-mark.svg';
import stateSource from '../../view/adminhtml/web/js/history-state.js?raw';
import operationsSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/history/operations.js?raw';
import screenSource from '../../view/adminhtml/web/js/history.js?raw';

const attribute = (code, label, mapped_code = null, active = true, type = 'text') => ({code, label, mapped_code, active, type, scope: 'global'});
const source = [
    attribute('name', 'Product name', 'name'), attribute('colour', 'Product colour', null, true, 'select'),
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
const mount = loadAmdModule(screenSource, {'mage/translate': translateIdentity, 'Ergonode_ProductAttributeHistoryAdminUi/js/history-state': stateModule, 'Ergonode_CoreAdminUi/js/history/operations': loadAmdModule(operationsSource, {'mage/translate': translateIdentity})});

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
    id: 'ergo-v-053',
    title: 'Ergonode UI/Widoki/Atrybuty produktów/ERGO-V-053 · Historia mapowania/Pełny widok',
    parameters: {layout: 'fullscreen', a11y: {test: 'error'}},
    args: {variant: 'history', changesOnly: false},
    argTypes: {variant: {control: 'select', options: ['history', 'empty', 'unchanged', 'failed', 'matrix', 'options', 'lazy-options', 'long-history', 'request-error']}},
    beforeEach: () => {
        const originalFetch = window.fetch;
        const screens = [];
        window.__attributeHistoryOptionRequests = [];
        window.__attributeHistoryFailOptions = false;
        window.__attributeHistoryReleaseOptions = null;
        window.__attributeHistoryDelayOptions = false;
        window.fetch = async (url) => {
            const parsed = new URL(url);
            if (parsed.pathname.endsWith('/lazy-state') && parsed.searchParams.has('side')) {
                const full = lazyState(true);
                const side = parsed.searchParams.get('side');
                const query = parsed.searchParams.get('search');
                if (query) {
                    return {ok: true, json: async () => ({parents: Object.keys(full.options[side]).filter(parent =>
                        full.options[side][parent].some(option => option.label.toLowerCase().includes(query)))})};
                }
                window.__attributeHistoryOptionRequests.push(parsed.searchParams.get('attribute_code'));
                if (window.__attributeHistoryFailOptions) {
                    window.__attributeHistoryFailOptions = false;
                    throw new Error('Fixture option request failed');
                }
                if (window.__attributeHistoryDelayOptions) {
                    await new Promise(resolve => { window.__attributeHistoryReleaseOptions = resolve; });
                }
                const view = stateModule.optionView(full, {side, code: parsed.searchParams.get('attribute_code')});
                const options = {source: {}, target: {}};
                ['source', 'target'].forEach(side => {
                    if (view.parents[side]) { options[side][view.parents[side]] = view[side]; }
                });
                return {ok: true, json: async () => ({options})};
            }
            if (parsed.pathname.includes('request-error')) { throw new Error('Fixture request failed'); }
            return {ok: true, json: async () => parsed.pathname.endsWith('/operations') ?
                {page: {items: older, total: 3, has_more: false}} :
                {state: fixtureView(Number(parsed.searchParams.get('operation_id')) === 44 ? selected :
                    {...previous, operation: {...previous.operation, operation_id: Number(parsed.searchParams.get('operation_id'))}},
                parsed.searchParams.get('changes_only') === '1')}};
        };
        window.__attributeHistoryStoryScreens = screens;
        return () => {
            screens.forEach(screen => screen.destroy());
            window.fetch = originalFetch;
            delete window.__attributeHistoryStoryScreens;
            delete window.__attributeHistoryOptionRequests;
            delete window.__attributeHistoryFailOptions;
            delete window.__attributeHistoryReleaseOptions;
            delete window.__attributeHistoryDelayOptions;
        };
    },
    render: ({variant, changesOnly, operationOutsidePage, unchangedPage}) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'veui-workspace veui-workspace-viewbar veah-workspace';
        wrapper.style.height = '850px';
        wrapper.innerHTML = '<div class="veui-toolbar veui-viewbar"><strong>Product · Attribute mapping history</strong></div><div class="veah-screen"></div>';
        let state = structuredClone(variant === 'unchanged' ? previous : selected);
        if (variant === 'options') {
            state = optionState();
        }
        if (variant === 'lazy-options') { state = lazyState(false); }
        if (variant === 'long-history') { state = longState(); }
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
            page: {items: variant === 'empty' ? [] : variant === 'long-history' ? Array.from({length: 30}, (_, index) => operation(44 - index)) : unchangedPage ? [state.operation] : operationOutsidePage ? [selected.operation] : [state.operation, state.operation.operation_id === 44 ? previous.operation : older[0]], total: variant === 'empty' ? 0 : 3, has_more: variant !== 'empty'},
            urls: {state: '/fixture/' + (variant === 'request-error' ? 'request-error' : variant === 'lazy-options' ? 'lazy-state' : 'state'), operations: '/fixture/operations', mapping: '/attribute/mapping'},
            icons: {source: ergonodeMark, target: magentoMark}, updateUrl: false
        }, wrapper.querySelector('.veah-screen'));
        window.__attributeHistoryStoryScreens?.push(screen);
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
        await expect(within(left).queryByText('Product name')).not.toBeInTheDocument();
        await expect(within(left).queryByText('Brand')).not.toBeInTheDocument();
        await expect(left.querySelector('.veah-mapping')).not.toBeInTheDocument();
        await expect(left.querySelector('[data-action="card-details"]')).not.toBeInTheDocument();
        await expect(getComputedStyle(left.querySelector('[data-code="material"]')).backgroundColor).toBe('rgb(248, 250, 252)');
        await expect(getComputedStyle(left.querySelector('[data-code="material"] .veah-label')).textDecorationLine).toBe('none');
        const disconnected = middle.querySelector('[data-role="entity-card"][data-code="color"]');
        await expect(getComputedStyle(disconnected).backgroundImage).toContain('repeating-linear-gradient');
        await expect(getComputedStyle(disconnected).color).toBe('rgb(194, 65, 12)');
        const formerMapping = disconnected.querySelector('.veah-mapping');
        const struck = formerMapping.querySelectorAll('.veah-disconnected-text');
        await expect(Array.from(struck, node => node.textContent)).toEqual(['Product', 'colour', '(colour)']);
        for (const node of struck) {
            await expect(getComputedStyle(node).textDecorationLine).toBe('line-through');
            await expect(node.textContent).not.toMatch(/\s/);
        }
        await expect(formerMapping.textContent).toBe('↳ Product colour (colour)');
        await expect(getComputedStyle(formerMapping).textDecorationLine).toBe('none');
        await expect(getComputedStyle(disconnected.querySelector('.veah-label')).textDecorationLine).toBe('none');
        await expect(getComputedStyle(disconnected.querySelector('.veah-card-body > .veah-code')).textDecorationLine).toBe('none');
        await expect(getComputedStyle(formerMapping.querySelector('.veah-mapping-arrow')).textDecorationLine).toBe('none');
        const disconnectIcon = disconnected.querySelector('.veah-disconnected-icon');
        await expect(getComputedStyle(disconnectIcon).maskImage).toContain(new URL(disconnectedIconUrl, document.baseURI).href);
        await expect(getComputedStyle(disconnectIcon).maskSize).toBe('15px 15px');
        await expect(disconnected.querySelector('.veah-change')).not.toHaveTextContent('⊘');
        await expect(canvasElement.querySelectorAll('.veah-attributes header time')).toHaveLength(0);
        await userEvent.type(canvas.getByRole('searchbox', {name: 'Search Magento attributes and options'}), 'fabric');
        await expect(middle.querySelectorAll('[data-role="entity-card"]')).toHaveLength(1);
        await userEvent.clear(canvas.getByRole('searchbox', {name: 'Search Magento attributes and options'}));
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
        await expect(current).toHaveAttribute('href', '/attribute/mapping');
        current.focus();
        await userEvent.keyboard('{Enter}');
        await expect(canvasElement.querySelector('.veah-workspace').dataset.navigationUrl).toBe('/attribute/mapping');
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

function optionState() {
    const state = structuredClone(selected);
    const option = (code, label, mapped = null) => ({...attribute(code, label, mapped, true, 'option'), mapped_attribute_code: null});
    state.changes = [];
    state.options = {source: {brand: [option('acme', 'Acme brand')]}, target: {manufacturer: [option('option_1', 'Acme')]}};
    ['source', 'target'].forEach(side => {
        const parent = side === 'source' ? 'brand' : 'manufacturer';
        const item = state.options[side][parent][0];
        state.changes.push({entity: 'option', side, attribute_code: parent, code: item.code, actions: ['disconnected'],
            before: {...item, mapped_code: side === 'source' ? 'option_1' : 'acme', mapped_attribute_code: side === 'source' ? 'manufacturer' : 'brand'}, after: item});
    });
    ['created', 'deleted', 'renamed', 'reconnected', 'excluded', 'included'].forEach((action, index) => {
        const item = option('option_' + (index + 2), action);
        item.active = action !== 'excluded';
        if (action !== 'deleted') { state.options.target.manufacturer.push(item); }
        state.changes.push({entity: 'option', side: 'target', attribute_code: 'manufacturer', code: item.code, actions: [action],
            before: action === 'created' ? null : item, after: action === 'deleted' ? null : item});
    });
    state.operation.operation_code = 'save_options';
    state.operation.change_count = state.changes.length;
    return state;
}

export const OptionHistory = {
    tags: ['option-history'],
    args: {variant: 'options'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const target = canvasElement.querySelector('[data-role="attributes"][data-side="target"]');
        const parent = () => target.querySelector('[data-role="attribute-group"][data-attribute-code="manufacturer"]');
        const option = () => parent().querySelector('[data-role="entity-card"][data-code="option_1"]');
        await expect(canvas.queryByRole('button', {name: 'Options of Manufacturer'})).not.toBeInTheDocument();
        await expect(parent().querySelectorAll('[data-entity="option"][data-role="entity-card"]')).toHaveLength(7);
        await expect(parent().querySelector('[data-entity="attribute"]').getBoundingClientRect().bottom)
            .toBeLessThanOrEqual(option().getBoundingClientRect().top);
        await expect(option().getBoundingClientRect().left)
            .toBeGreaterThan(parent().querySelector('[data-entity="attribute"]').getBoundingClientRect().left);
        const source = canvasElement.querySelector('[data-role="attributes"][data-side="source"]');
        await expect(source.querySelector('[data-role="attribute-group"][data-attribute-code="brand"] [data-code="acme"]')).toBeVisible();
        await expect(option()).toHaveClass('is-disconnected');
        await expect(option().querySelector('.veah-mapping')).toHaveTextContent('Acme brand (acme)');
        await expect(option().querySelector('.veah-disconnected-icon')).toBeVisible();
        await userEvent.type(canvas.getByRole('searchbox', {name: 'Search Magento attributes and options'}), 'Acme');
        await expect(target.querySelectorAll('[data-role="attribute-group"]')).toHaveLength(1);
        await expect(parent().querySelectorAll('[data-role="entity-card"]')).toHaveLength(2);
        const details = option().querySelector('[data-action="card-details"]');
        details.focus();
        await userEvent.keyboard('{Enter}');
        const dialog = canvas.getByRole('dialog');
        await expect(dialog).toBeVisible();
        await expect(within(dialog).getByRole('heading', {name: 'Before'})).toBeVisible();
        await expect(within(dialog).getByRole('heading', {name: 'After'})).toBeVisible();
        await userEvent.click(within(dialog).getByRole('button', {name: 'Show option'}));
        await expect(option()).toHaveFocus();
        await expect(parent().querySelectorAll('[data-entity="option"][data-role="entity-card"]')).toHaveLength(7);
        await userEvent.click(canvasElement.querySelector('[data-action="details"][data-id="44"]'));
        await userEvent.click(within(dialog).getByRole('button', {name: 'Acme · manufacturer Magento · Unmapped'}));
        await userEvent.click(within(dialog).getByRole('button', {name: 'Show option'}));
        await expect(option()).toHaveFocus();
        await userEvent.click(canvasElement.querySelector('[data-action="select"][data-id="43"]'));
        await waitFor(() => expect(canvas.getByText('This operation does not contain option history.')).toBeVisible());
        await expect(target.querySelector('[data-entity="attribute"][data-code="manufacturer"]')).toBeVisible();
        await expect(target.querySelectorAll('[data-entity="option"]')).toHaveLength(0);
    }
};

function lazyState(full) {
    const state = optionState();
    const unchanged = attribute('blue', 'Unchanged blue', null, true, 'option');
    state.options.target.color = full ? [unchanged] : [];
    state.options.target.manufacturer = state.options.target.manufacturer.concat(full ? [attribute('stable', 'Stable brand')] : []);
    state.option_counts = {source: {brand: 1}, target: {color: 1, manufacturer: 7}};
    return state;
}

export const LazyOptions = {
    args: {variant: 'lazy-options'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const target = canvasElement.querySelector('[data-role="attributes"][data-side="target"]');
        const toggle = name => canvas.getByRole('button', {name: 'Unchanged options of ' + name});
        await expect(window.__attributeHistoryOptionRequests).toHaveLength(0);
        await expect(canvas.queryByText('Unchanged blue')).not.toBeInTheDocument();
        await expect(toggle('Color')).toHaveAttribute('aria-expanded', 'false');
        await expect(target.querySelector('[data-entity="attribute"][data-code="manufacturer"]')).toHaveClass('is-changed');
        await expect(target.querySelector('[data-entity="attribute"][data-code="color"]')).not.toHaveClass('is-changed');
        const search = canvas.getByRole('searchbox', {name: 'Search Magento attributes and options'});
        await userEvent.type(search, 'unchanged blue');
        await waitFor(() => expect(target.querySelectorAll('[data-role="attribute-group"]')).toHaveLength(1));
        await expect(toggle('Color')).toHaveAttribute('aria-expanded', 'false');
        await expect(window.__attributeHistoryOptionRequests).toHaveLength(0);
        await userEvent.clear(search);
        window.__attributeHistoryFailOptions = true;
        toggle('Color').focus();
        await userEvent.keyboard('{Enter}');
        await userEvent.click(await canvas.findByRole('button', {name: 'Options could not be loaded. Try again.'}));
        await waitFor(() => expect(canvas.getByText('Unchanged blue')).toBeVisible());
        await expect(window.__attributeHistoryOptionRequests).toEqual(['color', 'color']);
        await userEvent.click(toggle('Color'));
        await expect(canvas.queryByText('Unchanged blue')).not.toBeInTheDocument();
        toggle('Color').focus();
        await userEvent.keyboard(' ');
        await expect(canvas.getByText('Unchanged blue')).toBeVisible();
        await expect(toggle('Color')).toHaveFocus();
        await expect(window.__attributeHistoryOptionRequests).toHaveLength(2);
        window.__attributeHistoryDelayOptions = true;
        await userEvent.click(toggle('Manufacturer'));
        await expect(canvas.getByText('Loading options...')).toBeVisible();
        await waitFor(() => expect(window.__attributeHistoryReleaseOptions).toBeTypeOf('function'));
        await userEvent.click(toggle('Manufacturer'));
        await userEvent.click(toggle('Manufacturer'));
        await expect(window.__attributeHistoryOptionRequests).toHaveLength(3);
        await userEvent.click(canvasElement.querySelector('[data-action="select"][data-id="43"]'));
        await waitFor(() => expect(canvas.getByText('This operation does not contain option history.')).toBeVisible());
        window.__attributeHistoryReleaseOptions();
        await waitFor(() => expect(canvas.queryByText('Stable brand')).not.toBeInTheDocument());
        await expect(target.querySelectorAll('[data-entity="option"]')).toHaveLength(0);
    }
};

function longState() {
    const state = structuredClone(selected);
    state.changes = [];
    ['source', 'target'].forEach(side => {
        state[side] = state[side].concat(Array.from({length: 50}, (_, index) =>
            attribute('long_' + index, 'Long attribute ' + String(index).padStart(2, '0'))));
    });
    state.options = {source: {}, target: {color: Array.from({length: 240}, (_, index) =>
        attribute('option_' + index, 'Color ' + String(index).padStart(3, '0'), null, true, 'option'))}};
    return state;
}

export const LongHistoryScrolling = {
    args: {variant: 'long-history'},
    play: async ({canvasElement}) => {
        const workspace = canvasElement.querySelector('.veah-workspace');
        const source = canvasElement.querySelector('[data-role="attributes"][data-side="source"]');
        const target = canvasElement.querySelector('[data-role="attributes"][data-side="target"]');
        const operations = canvasElement.querySelector('[data-role="operations"]');
        const header = target.previousElementSibling;
        const headerTop = header.getBoundingClientRect().top;
        for (const list of [source, target, operations]) {
            await expect(list.clientHeight).toBeGreaterThan(100);
            await expect(list.scrollHeight).toBeGreaterThan(list.clientHeight);
            await expect(list.getBoundingClientRect().bottom).toBeLessThanOrEqual(workspace.getBoundingClientRect().bottom);
        }
        const toggle = () => within(target).getByRole('button', {name: 'Unchanged options of Color'});
        await expect(toggle()).toHaveTextContent('Options240');
        await expect(toggle().querySelector('svg')).toHaveAttribute('viewBox', '0 0 16 16');
        await expect(getComputedStyle(toggle()).borderTopWidth).toBe('0px');
        await userEvent.click(toggle());
        await expect(toggle()).toHaveAttribute('aria-expanded', 'true');
        await expect(target.querySelectorAll('[data-entity="option"][data-role="entity-card"]')).toHaveLength(240);
        await expect(target.getBoundingClientRect().bottom).toBeLessThanOrEqual(workspace.getBoundingClientRect().bottom);
        target.scrollTop = target.scrollHeight;
        await expect(target.scrollTop).toBeGreaterThan(1000);
        await expect(source.scrollTop).toBe(0);
        await expect(operations.scrollTop).toBe(0);
        await expect(header.getBoundingClientRect().top).toBe(headerTop);
        source.scrollTop = source.scrollHeight;
        operations.scrollTop = operations.scrollHeight;
        await expect(source.scrollTop).toBeGreaterThan(100);
        await expect(operations.scrollTop).toBeGreaterThan(100);
        target.scrollTop = 0;
        await userEvent.click(toggle());
        await expect(target.querySelectorAll('[data-entity="option"][data-role="entity-card"]')).toHaveLength(0);
        workspace.style.height = '450px';
        for (const list of [source, target, operations]) {
            await expect(list.clientHeight).toBeGreaterThan(100);
            await expect(list.getBoundingClientRect().bottom).toBeLessThanOrEqual(workspace.getBoundingClientRect().bottom);
        }
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
