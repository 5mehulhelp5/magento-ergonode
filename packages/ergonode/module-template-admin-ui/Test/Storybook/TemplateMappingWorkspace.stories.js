import {mountMappingInteractions} from '@ergonode-storybook/mapping-interactions.js';
import {checkMappingHeaders} from '@ergonode-storybook/mapping-header-interactions.js';
import { expect, userEvent } from 'storybook/test';

import { createMappingWorkspace } from '@ergonode-storybook/mapping-workspace.js';

import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-actions.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/attribute-mapping.css';
import '../../view/adminhtml/web/css/template-admin.css';


function renderTemplateMapping(args) {
    const root = createMappingWorkspace({...args, view: 'template'});
    mountMappingInteractions(root);

    return root;
}

export default {
    id: 'ergo-v-058',
    title: 'Ergonode UI/Widoki/Szablony/ERGO-V-058 · Mapowanie szablonów/Pełny widok',
    render: renderTemplateMapping,
    args: {state: 'mapped'},
    argTypes: {
        state: {
            control: 'select',
            options: ['mapped', 'unmapped', 'empty', 'draft', 'error', 'inactive', 'saving', 'autosave-error']
        }
    }
};

export const Playground = {};

export const ParowanieKlawiatura = {
    args: {state: 'unmapped'},
    play: async ({canvasElement}) => {
        const left = canvasElement.querySelector('[data-source="ergo"][data-role="entity-card"]');
        const right = canvasElement.querySelector('[data-source="magento"][data-role="entity-card"]');

        left.focus();
        await userEvent.keyboard('{Enter}');
        right.focus();
        await userEvent.keyboard('{Enter}');
        await expect(canvasElement.querySelectorAll('[data-role="mapping-row"]')).toHaveLength(1);
        await expect(
            canvasElement.querySelector('[data-role="pair-slot"][data-side="ergo"]')
        ).toHaveAttribute('data-code', 'shoes');
        await expect(
            canvasElement.querySelector('[data-role="pair-slot"][data-side="magento"]')
        ).toHaveAttribute('data-code', '4');
    }
};

export const ScaloneNaglowki = {
    play: checkMappingHeaders
};
