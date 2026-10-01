import { expect, userEvent, waitFor, within } from 'storybook/test';
import { loadAmdModule } from '@ergonode-storybook/load-amd-module.js';
import { markRequiredEntityItem } from '@ergonode-storybook/entity-item.js';

import '../../view/adminhtml/web/css/ergonode-workspace.css';
import '../../view/adminhtml/web/css/attribute-mapping.css';
import requirementsSource from '../../view/adminhtml/web/js/mapping-requirements.js?raw';

const mappingRequirements = loadAmdModule(
    requirementsSource,
    {},
    'Ergonode_CoreAdminUi/js/mapping-requirements'
);

function storyData(args) {
    if (args.variant === 'attribute') {
        return {
            action: 'Mapuj wymagany atrybut',
            cardAriaLabel: 'Dodaj atrybut do mapowania: SKU',
            cardRole: 'entity-card',
            code: 'sku',
            label: 'SKU',
            meta: '<span class="vea-type-badge vea-type-text">text</span><span class="vea-scope">global</span>',
            oppositeCode: 'product_sku',
            requirementLabel: 'Wymagane mapowanie atrybutu',
            tooltip: 'Ten atrybut jest wymagany w Magento. Przypisz mu odpowiadający atrybut Ergonode. Bez tego synchronizacja nie może się rozpocząć.'
        };
    }

    return {
        action: 'Mapuj wymagany język',
        cardAriaLabel: '',
        cardRole: 'entity-card',
        code: '0',
        label: 'Admin / Default Values',
        meta: '<span class="vea-type-badge">pl_PL</span>',
        oppositeCode: 'pl_PL',
        requirementLabel: 'Wybierz język domyślny',
        tooltip: 'Przypisz język Ergonode, który ma być używany jako domyślny. Bez tego synchronizacja nie może się rozpocząć.'
    };
}

function mappingRow(args) {
    const row = document.createElement('article');
    const data = storyData(args);

    row.dataset.role = 'mapping-row';
    row.hidden = true;
    row.innerHTML = [
        '<div data-role="pair-slot" data-side="ergo" data-code="', data.oppositeCode, '"></div>',
        '<div data-role="pair-slot" data-side="magento" data-code="', data.code, '"></div>'
    ].join('');

    return row;
}

function fixture(args) {
    const data = storyData(args);
    const root = document.createElement('div');
    const panel = document.createElement('section');
    const list = document.createElement('div');
    const action = document.createElement('button');

    root.className = 'veui-workspace vea-mapping';
    root.style.minHeight = '460px';
    panel.className = 'veui-panel vea-panel vea-side-panel';
    panel.style.height = '420px';
    panel.style.margin = '0 auto';
    panel.style.maxWidth = '380px';
    panel.innerHTML = [
        '<div class="veui-panel-head vea-panel-head">',
        '<strong class="veui-panel-title">Magento</strong>',
        '</div>'
    ].join('');

    list.className = 'vea-attribute-list';
    list.innerHTML = [
        '<div class="vea-attribute-card" data-role="', data.cardRole, '" data-source="magento" ',
        'data-code="', data.code, '" data-mapping-required="true" role="group" tabindex="0"',
        data.cardAriaLabel ? ' aria-label="' + data.cardAriaLabel + '"' : '', '>',
        '<span class="vea-drag-handle" aria-hidden="true"></span>',
        '<div class="vea-card-copy"><strong>', data.label, '</strong>',
        '<span class="vea-card-subline"><code>', data.code === '0' ? 'admin' : data.code, '</code>',
        data.meta,
        '</span></div>',
        '</div>'
    ].join('');
    markRequiredEntityItem(list.querySelector('[data-role="entity-card"]'), {
        label: data.requirementLabel,
        description: data.tooltip,
        tooltipId: 'storybook-mapping-requirement-tooltip',
        missing: !args.mapped
    });

    action.type = 'button';
    action.className = 'veui-button';
    action.textContent = data.action;
    action.style.margin = '0 12px 12px';
    panel.append(list, action);
    root.append(panel);

    if (args.mapped) {
        root.append(mappingRow(args));
    }

    const state = mappingRequirements.create(root);

    state.refresh();
    action.addEventListener('click', () => {
        root.append(mappingRow(args));
        state.refresh();
    });

    return root;
}

export default {
    id: 'ergo-c-030',
    title: 'Ergonode UI/Komponenty/Wspólne/ERGO-C-030 · Wymagane mapowanie',
    args: { mapped: false, variant: 'language' },
    argTypes: {
        mapped: { control: 'boolean' },
        variant: { control: 'select', options: ['language', 'attribute'] }
    },
    render: fixture
};

export const Playground = {};
export const Missing = {};
export const Satisfied = { args: { mapped: true } };
export const RequiredAttribute = {
    args: { variant: 'attribute' },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const card = canvas.getByRole('group', { name: 'Dodaj atrybut do mapowania: SKU' });
        const requirement = canvas.getByRole('button', { name: 'Wymagane mapowanie atrybutu' });
        const tooltip = canvasElement.querySelector('[role="tooltip"]');
        const action = canvas.getByRole('button', { name: 'Mapuj wymagany atrybut' });

        await expect(tooltip).toBeInTheDocument();
        await expect(card).toHaveAttribute('aria-invalid', 'true');
        await expect(canvas.queryByRole('button', { name: 'Atrybut aktywny do mapowania' })).not.toBeInTheDocument();
        await expect(tooltip).not.toBeVisible();
        await userEvent.click(requirement);
        await waitFor(() => expect(tooltip).toBeVisible());
        await userEvent.click(action);
        await expect(card).toHaveAttribute('aria-invalid', 'false');
    }
};
export const KeyboardCompletion = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const card = canvasElement.querySelector('[data-role="entity-card"]');
        const requirement = canvas.getByRole('button', { name: 'Wybierz język domyślny' });
        const tooltip = canvasElement.querySelector('[role="tooltip"]');
        const action = canvas.getByRole('button', { name: 'Mapuj wymagany język' });

        await expect(tooltip).toBeInTheDocument();
        await expect(card).toHaveAttribute('aria-invalid', 'true');
        await expect(tooltip).not.toBeVisible();
        requirement.focus();
        await waitFor(() => expect(tooltip).toBeVisible());
        action.focus();
        await userEvent.keyboard('{Enter}');
        await expect(card).toHaveAttribute('aria-invalid', 'false');
    }
};
