import {expect, waitFor, within} from 'storybook/test';
import {createEntityItem} from '@ergonode-storybook/entity-item.js';
import {loadAmdModule} from '@ergonode-storybook/load-amd-module.js';
import {decorateStoreViewCard} from './language-fixture.js';

import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/attribute-mapping.css';
import '../../view/adminhtml/web/css/language-mapping.css';
import requirementsSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/mapping-requirements.js?raw';

const requirements = loadAmdModule(
    requirementsSource,
    {},
    'Ergonode_CoreAdminUi/js/mapping-requirements'
);

function renderStoreView({required = true, mapped = false} = {}) {
    const frame = document.createElement('div');
    const root = document.createElement('div');
    const panel = document.createElement('section');
    const list = document.createElement('div');
    const data = required
        ? {id: '0', code: 'admin', label: 'Default Store View', locale: 'pl_PL'}
        : {id: '1', code: 'polish', label: 'Polski', locale: 'pl_PL'};
    const card = createEntityItem({
        source: 'magento', code: data.id, label: data.label, bulkSelection: true,
        toggleLabel: 'Store View aktywny do mapowania'
    });

    frame.className = 'veui-storybook-frame';
    root.className = 'veui-workspace vea-mapping vel-mapping';
    root.style.minHeight = '380px';
    panel.className = 'veui-panel vea-panel vea-side-panel';
    panel.style.maxWidth = '420px';
    panel.style.margin = '0 auto';
    panel.innerHTML = '<div class="veui-panel-head vea-panel-head"><strong class="veui-panel-title">Magento Store Views</strong></div>';
    list.className = 'vea-attribute-list vel-source-list';
    list.append(decorateStoreViewCard(card, {
        ...data, scope: 'Main Website / Main Store', required, mapped
    }));
    panel.append(list);
    root.append(panel);
    if (mapped) {
        const row = document.createElement('article');
        row.dataset.role = 'mapping-row';
        row.hidden = true;
        row.innerHTML = '<div data-role="pair-slot" data-side="ergo" data-code="pl_PL"></div>'
            + `<div data-role="pair-slot" data-side="magento" data-code="${data.id}"></div>`;
        root.append(row);
    }
    requirements.create(root).refresh();
    frame.append(root);
    return frame;
}

export default {
    id: 'ergo-v-040-03',
    title: 'Ergonode UI/Widoki/Języki/ERGO-V-040 · Edycja i odświeżanie języków/ERGO-V-040.03 · Karta Store View w mapowaniu języków',
    render: renderStoreView,
    args: {required: true, mapped: false},
    argTypes: {
        required: {control: 'boolean'},
        mapped: {control: 'boolean'}
    },
    parameters: {
        docs: {description: {component: 'Wariant domenowy wspólnej karty encji i wymaganego mapowania z CoreAdminUi.'}}
    }
};

export const Playground = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const card = canvas.getByRole('group', {name: 'Wybierz Magento Store View: Default Store View'});
        const control = canvas.getByRole('button', {name: 'Wybierz język domyślny'});
        const tooltip = canvasElement.querySelector('[role="tooltip"]');

        await expect(card).toHaveAttribute('data-store-code', 'admin');
        await expect(card).toHaveAttribute('data-locale', 'pl_PL');
        await expect(card).toHaveAttribute('aria-invalid', 'true');
        await expect(card.querySelector('.vea-scope')).toHaveTextContent('Main Website / Main Store');
        await expect(tooltip).not.toBeVisible();
        control.focus();
        await waitFor(() => expect(tooltip).toBeVisible());
    }
};

export const ZmapowanyWymagany = {
    args: {mapped: true},
    play: async ({canvasElement}) => {
        const card = canvasElement.querySelector('[data-role="entity-card"]');
        await expect(card).toHaveClass('is-mapped');
        await expect(card).toHaveAttribute('aria-invalid', 'false');
    }
};

export const ZwyklyStoreView = {
    args: {required: false},
    play: async ({canvasElement}) => {
        const card = canvasElement.querySelector('[data-role="entity-card"]');
        await expect(card).not.toHaveAttribute('data-mapping-required');
        await expect(card.querySelector('.veui-required-badge')).toBeNull();
        await expect(card.querySelector('[data-role="source-active-toggle"]')).toBeVisible();
    }
};
