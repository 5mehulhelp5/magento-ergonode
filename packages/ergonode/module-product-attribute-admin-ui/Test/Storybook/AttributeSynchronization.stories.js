import {mountMappingInteractions} from '@ergonode-storybook/mapping-interactions.js';
import {expect, userEvent, within} from 'storybook/test';

import {createMappingWorkspace} from '@ergonode-storybook/mapping-workspace.js';

import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-actions.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/attribute-mapping.css';

function addSyncAction(root, disabled = false) {
    const button = Object.assign(document.createElement('button'), {type: 'button'});
    const icon = document.createElement('span');

    button.className = 'veui-button veui-button-toolbar';
    button.dataset.role = 'sync-ergonode';
    button.disabled = disabled;
    button.setAttribute('aria-label', 'Synchronizuj atrybuty z Magento');
    icon.className = 'veui-sync-ergonode-icon';
    icon.setAttribute('aria-hidden', 'true');
    button.append(icon, document.createTextNode('Sync'));
    button.addEventListener('click', () => {
        root.dataset.synchronizationEntry = 'sync';
    });
    root.querySelector('.veui-toolbar')?.append(button);

    return button;
}

function renderSynchronization(args) {
    const root = createMappingWorkspace({view: 'attribute', state: args.state});

    addSyncAction(root, args.disabled);
    mountMappingInteractions(root);

    return root;
}

function renderMatrix() {
    const matrix = document.createElement('div');

    matrix.style.display = 'grid';
    matrix.style.gap = '16px';
    [false, true].forEach((disabled) => {
        const workspace = renderSynchronization({state: 'mapped', disabled});

        workspace.querySelector('nav').setAttribute(
            'aria-label',
            `Sekcje Ergonode: synchronizacja ${disabled ? 'niedostępna' : 'dostępna'}`
        );
        matrix.append(workspace);
    });

    return matrix;
}

export default {
    id: 'ergo-v-070-06',
    title: 'Ergonode UI/Widoki/Atrybuty produktów/ERGO-V-070 · Mapowanie atrybutów produktów/ERGO-V-070.06 · Synchronizacja atrybutów',
    args: {state: 'mapped', disabled: false},
    argTypes: {
        state: {control: 'select', options: ['mapped', 'unmapped', 'empty', 'draft', 'error', 'inactive']},
        disabled: {control: 'boolean'}
    },
    render: renderSynchronization
};

export const Playground = {};

export const WszystkieStany = {render: renderMatrix};

export const UruchomienieMysza = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const workspace = canvasElement.querySelector('.veui-workspace');

        await userEvent.click(canvas.getByRole('button', {name: 'Synchronizuj atrybuty z Magento'}));
        await expect(workspace).toHaveAttribute('data-synchronization-entry', 'sync');
    }
};

export const UruchomienieKlawiatura = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const workspace = canvasElement.querySelector('.veui-workspace');
        const button = canvas.getByRole('button', {name: 'Synchronizuj atrybuty z Magento'});

        button.focus();
        await userEvent.keyboard('{Enter}');
        await expect(workspace).toHaveAttribute('data-synchronization-entry', 'sync');
    }
};
