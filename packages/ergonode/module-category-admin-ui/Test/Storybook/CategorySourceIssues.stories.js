import { expect, userEvent, waitFor, within } from 'storybook/test';
import { loadAmdModule } from '@ergonode-storybook/load-amd-module.js';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-actions.css';
import '../../view/adminhtml/web/css/category-tree-mapping.css';
import source from '../../view/adminhtml/web/js/category-source-issues.js?raw';

const mount = loadAmdModule(source, {'mage/translate': value => value},
    'Ergonode_CategoryAdminUi/js/category-source-issues');

function render(args) {
    const root = document.createElement('div');
    const selected = {category_tree_id: 7, tree_code: args.treeCode, is_active: args.active};
    const state = {
        status: args.status,
        requires_refresh: args.status !== 'available',
        checked_at: '2026-09-09 12:30:00',
        snapshot_at: '2026-09-08 14:20:00',
        missing_categories: args.missing ? [
            {code: 'chairs', label: 'Krzesła', magento_category_id: 42},
            {code: '<img src=x onerror=alert(1)>', label: '<script>sample</script>', magento_category_id: 43}
        ] : []
    };

    root.className = 'veui-workspace vec-admin';
    root.tabIndex = -1;
    root.innerHTML = `
        <div class="veui-layout vec-layout">
            <section class="veui-panel vec-panel vec-source-panel" data-column-role="source">
                <div class="veui-panel-head"><strong>Ergonode</strong></div>
                <div class="vec-side-tree" data-role="ergo-list">Kategorie Ergonode</div>
            </section>
            <section class="veui-panel vec-panel vec-target-panel" data-column-role="target">
                <div class="veui-panel-head"><strong>Magento</strong></div>
                <section class="vec-source-issues" data-role="source-issues" aria-label="Stan źródła"
                         aria-live="polite" hidden></section>
                <div class="vec-side-tree vec-magento-mapping-tree" data-role="magento-list">Kategorie Magento</div>
            </section>
            <aside class="veui-panel vec-panel vec-settings-panel" data-column-role="settings">
                <div class="veui-panel-head"><strong>Konfiguracje drzew</strong></div>
                <div class="vec-settings-body">
                    <div class="vec-configuration-list" role="list">
                        <div class="vec-configuration-card is-selected${args.active ? '' : ' is-disabled'}"
                             data-role="category-tree-configuration" data-category-tree-id="7" role="listitem">
                            <span class="vec-configuration-drag" aria-hidden="true"></span>
                            <a class="vec-configuration-link" href="#source-tree" aria-current="page">
                                <strong>Główne drzewo</strong><code>default</code><span>Magento</span>
                            </a>
                            <span class="vec-configuration-actions">
                                <span class="vec-configuration-disabled-info vec-source-status"
                                      data-role="source-status-badge" role="img" tabindex="0" hidden></span>
                            </span>
                        </div>
                    </div>
                </div>
            </aside>
        </div>`;
    const update = mount(root, {
        retry() {
            root.dataset.action = 'retry';
            if (args.recover) {
                const healthy = {status: 'available'};
                update(healthy, [{...selected, source_state: healthy}], selected, {});
            }
        },
        disable() { root.dataset.action = 'disable'; }
    });
    update(state, [{...selected, source_state: state}], selected, {refresh: args.canEdit, disable: args.canEdit});
    return root;
}

export default {
    id: 'ergo-v-014-01',
    title: 'Ergonode UI/Widoki/Kategorie/ERGO-V-014 · Mapowanie drzewa kategorii/ERGO-V-014.01 · Problemy danych źródłowych',
    render,
    args: {status: 'missing', treeCode: 'default', active: true, canEdit: true, missing: false},
    argTypes: {status: {control: 'select', options: ['available', 'missing', 'unavailable', 'recovered']}},
    parameters: {layout: 'padded'}
};

export const Playground = {};
export const MissingTree = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const retry = canvas.getByRole('button', {name: 'Sprawdź ponownie'});

        await expect(canvasElement.querySelector('[data-column-role="target"] [data-role="source-issues"]')).toBeVisible();
        await expect(canvasElement.querySelector('[data-role="ergo-list"]')).toBeEmptyDOMElement();
        await expect(canvasElement.querySelector('[data-role="magento-list"]')).toBeEmptyDOMElement();
        await expect(canvasElement.querySelector('[data-column-role="source"] .veui-panel-head')).not.toBeVisible();
        await expect(canvasElement.querySelector('.vec-configuration-card')).toHaveClass('has-source-issue');
        retry.focus();
        await userEvent.keyboard('{Enter}');
        await expect(canvasElement.querySelector('[data-action="retry"]')).not.toBeNull();
        await userEvent.tab();
        await expect(canvas.getByRole('button', {name: 'Wyłącz powiązanie'})).toHaveFocus();
        await userEvent.keyboard(' ');
        await expect(canvasElement.querySelector('[data-action="disable"]')).not.toBeNull();
    }
};
export const ConnectionFailure = {args: {status: 'unavailable'}};
export const RecoveredNeedsRefresh = {args: {status: 'recovered'}};
export const MissingCategories = {
    args: {status: 'available', missing: true},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);

        await userEvent.click(canvas.getByText('Zachowane kategorie (2)'));
        await expect(canvas.getByText('Krzesła — chairs → Magento #42')).toBeVisible();
        await expect(canvasElement.querySelector('img, script')).toBeNull();
    }
};
export const DisabledMapping = {args: {active: false}};
export const ReadOnly = {args: {canEdit: false}};
export const Healthy = {args: {status: 'available'}};

export const Tooltip = {
    play: async ({canvasElement}) => {
        const badge = canvasElement.querySelector('[data-role="source-status-badge"]');
        const tooltip = within(badge).getByRole('tooltip', {hidden: true});

        await userEvent.click(badge);
        await waitFor(() => expect(tooltip).toBeVisible());
        await expect(tooltip).toHaveTextContent('Nie znaleziono drzewa w Ergonode');
        await userEvent.tab();
        await waitFor(() => expect(tooltip).not.toBeVisible());
        badge.focus();
        await waitFor(() => expect(tooltip).toBeVisible());
    }
};
export const Recovery = {
    args: {recover: true},
    play: async ({canvasElement}) => {
        await userEvent.click(within(canvasElement).getByRole('button', {name: 'Sprawdź ponownie'}));
        await expect(canvasElement.querySelector('[data-role="source-issues"]')).not.toBeVisible();
        await expect(canvasElement.querySelector('.vec-configuration-card')).not.toHaveClass('has-source-issue');
        await expect(canvasElement.querySelector('[data-role="source-status-badge"]')).not.toBeVisible();
        await expect(canvasElement.querySelector('[data-column-role="source"] .veui-panel-head')).toBeVisible();
        await expect(canvasElement.querySelector('[data-column-role="target"] .veui-panel-head')).toBeVisible();
        await expect(canvasElement.querySelector('[data-role="ergo-list"]')).toBeVisible();
        await expect(canvasElement.querySelector('[data-role="magento-list"]')).toBeVisible();
    }
};
