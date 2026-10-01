import { createCoreModuleLoader } from '@ergonode-storybook/core-modules.js';
import { expect, userEvent, within } from 'storybook/test';
import { loadAmdModule, translateIdentity } from '@ergonode-storybook/load-amd-module.js';

import entityOptionsSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/entity-options.js?raw';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/attribute-mapping.css';

const entityOptions = loadAmdModule(entityOptionsSource, {
    'mage/translate': translateIdentity
}, 'Ergonode_CoreAdminUi/js/entity-options');
const loadCore = createCoreModuleLoader();
const productionMapping = loadCore('Ergonode_CoreAdminUi/js/attribute-mapping');
const workspace = loadCore('Ergonode_CoreAdminUi/js/workspace');

function fixture(args) {
    const root = document.createElement('section');
    const panel = document.createElement('section');
    const list = document.createElement('div');
    const card = document.createElement('article');
    const status = document.createElement('p');
    const code = args.code || 'color';

    root.className = 'veui-workspace vea-mapping';
    root.style.minHeight = '360px';
    root.veaConfig = {
        allow_magento_attribute_creation: true,
        existing_magento_attribute_codes: args.exists ? [code] : []
    };
    panel.className = 'veui-panel vea-panel vea-side-panel';
    panel.style.height = '310px';
    panel.style.margin = '0 auto';
    panel.style.maxWidth = '380px';
    panel.innerHTML = `
        <div class="veui-panel-head vea-panel-head">
            <strong class="veui-panel-title">Atrybuty Ergonode</strong>
        </div>`;
    list.className = 'vea-attribute-list';
    card.className = 'vea-attribute-card';
    card.dataset.role = 'entity-card';
    card.dataset.source = 'ergo';
    card.dataset.code = code;
    card.dataset.type = 'select';
    card.dataset.label = 'Kolor';
    card.innerHTML = `
        <span class="vea-drag-handle" aria-hidden="true"></span>
        <div class="vea-card-copy">
            <strong>Kolor</strong>
            <span class="vea-card-subline"><code>${code}</code><span class="vea-type-badge vea-type-select">select</span></span>
        </div>`;
    card.append(entityOptions.create({
        menuLabel: 'Opcje atrybutu: Kolor',
        actions: [
            {
                active: true,
                iconClass: 'veui-entity-options-add-icon',
                label: 'Dodaj do mapowania',
                requiresActive: true,
                role: 'entity-add-to-mapping'
            },
            productionMapping.createMagentoAttributeAction(root, card, true)
        ]
    }));
    status.setAttribute('role', 'status');
    status.textContent = 'Nie wybrano akcji';
    status.style.margin = '12px 14px';
    card.addEventListener('click', (event) => {
        if (event.target.closest('[data-role="entity-create-magento-attribute"]')) {
            status.textContent = `Atrybut powstanie po zapisaniu: ${code}`;
        }
    });
    list.append(card);
    panel.append(list, status);
    root.append(panel);
    workspace.mount(root, (scope) => entityOptions.bind(scope, root));

    return root;
}

function allStates() {
    const matrix = document.createElement('div');

    matrix.style.display = 'grid';
    matrix.style.gap = '18px';
    matrix.style.gridTemplateColumns = 'repeat(2, minmax(300px, 1fr))';
    matrix.append(fixture({exists: false, code: 'new_color'}), fixture({exists: true, code: 'color'}));

    return matrix;
}

export default {
    id: 'ergo-v-070-03',
    title: 'Ergonode UI/Widoki/Atrybuty produktów/ERGO-V-070 · Mapowanie atrybutów produktów/ERGO-V-070.03 · Tworzenie atrybutu w Magento',
    args: { exists: true, code: 'color' },
    argTypes: {
        exists: { control: 'boolean' },
        code: { control: 'text' }
    },
    render: fixture
};

export const Playground = {};
export const Available = {
    args: { exists: false, code: 'new_color' },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);

        await userEvent.click(canvas.getByRole('button', { name: 'Opcje atrybutu: Kolor' }));
        await userEvent.click(canvas.getByRole('button', { name: 'Utwórz w Magento' }));
        await expect(canvas.getByRole('status')).toHaveTextContent('Atrybut powstanie po zapisaniu: new_color');
    }
};
export const ExistingCode = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);

        await userEvent.click(canvas.getByRole('button', { name: 'Opcje atrybutu: Kolor' }));
        const action = canvas.getByRole('button', {
            name: 'Utwórz w Magento. Atrybut Magento o kodzie „color” już istnieje.'
        });

        await expect(action).toBeDisabled();
        await expect(action).toHaveAttribute('title', 'Atrybut Magento o kodzie „color” już istnieje.');
        await expect(action.querySelector('.vea-existing-magento-attribute-icon')).toBeInTheDocument();
    }
};
export const ExistingCodeKeyboard = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const menuButton = canvas.getByRole('button', { name: 'Opcje atrybutu: Kolor' });
        const menu = canvasElement.querySelector('[data-role="entity-options"]');

        menuButton.focus();
        await userEvent.keyboard('{Enter}');
        await expect(menu).toHaveAttribute('open', '');
        await expect(canvas.getByRole('button', {
            name: 'Utwórz w Magento. Atrybut Magento o kodzie „color” już istnieje.'
        })).toBeDisabled();
    }
};
export const AllStates = {
    render: allStates
};
