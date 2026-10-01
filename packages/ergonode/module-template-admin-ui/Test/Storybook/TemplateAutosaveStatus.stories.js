import { createAutosaveRegion } from '@ergonode-storybook/autosave-region.js';
import {
    expect,
    userEvent,
    waitFor,
    within,
} from 'storybook/test';

import { loadAmdModule } from '@ergonode-storybook/load-amd-module.js';

import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/attribute-mapping.css';
import '../../view/adminhtml/web/css/template-admin.css';
import coreAutosaveSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/autosave.js?raw';
import autosaveSource from '../../view/adminhtml/web/js/template-autosave.js?raw';

function loadAutosave(request) {
    const coreAutosave = loadAmdModule(
        coreAutosaveSource,
        {},
        'Ergonode_CoreAdminUi/js/autosave'
    );

    return loadAmdModule(
        autosaveSource,
        {
            'Ergonode_CoreAdminUi/js/autosave': coreAutosave,
            'Ergonode_CoreAdminUi/js/request': request
        },
        'Ergonode_TemplateAdminUi/js/template-autosave'
    );
}

function renderRegion(args) {
    return createAutosaveRegion(args, {
        loadAutosave,
        view: 'template',
        config: {urls: {save_mapping: '/save'}},
        serialize: () => ({mappings: {bag: 15}, visibility: []})
    });
}

const meta = {
    id: 'ergo-v-058-01',
    title: 'Ergonode UI/Widoki/Szablony/ERGO-V-058 · Mapowanie szablonów/ERGO-V-058.01 · Blokada podczas automatycznego zapisu',
    tags: ['autodocs'],
    render: renderRegion,
    args: {mode: 'saved'},
    argTypes: {
        mode: {
            control: 'select',
            options: ['saved', 'saving', 'error'],
        },
    },
};

export default meta;

export const Playground = {};

export const Stany = {
    render: () => {
        const grid = document.createElement('div');

        grid.className = 'veui-storybook-grid';
        grid.append(
            renderRegion({mode: 'saved'}),
            renderRegion({mode: 'saving'}),
            renderRegion({mode: 'error'})
        );

        return grid;
    },
};

export const PonowienieMysza = {
    render: () => renderRegion({mode: 'recoverable-error'}),
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const region = canvasElement.querySelector('[data-role="autosave-region"]');

        await waitFor(() => expect(region).toHaveAttribute('data-autosave-state', 'error'));
        await expect(region).not.toHaveAttribute('inert');
        await userEvent.click(canvas.getByRole('button', {name: 'Spróbuj ponownie.'}));
        await waitFor(() => expect(region).toHaveAttribute('data-autosave-state', 'saved'));
        await expect(region).toHaveAttribute('aria-busy', 'false');
        await expect(canvas.queryByRole('alert')).not.toBeInTheDocument();
    },
};

export const PonowienieKlawiatura = {
    render: () => renderRegion({mode: 'recoverable-error'}),
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const region = canvasElement.querySelector('[data-role="autosave-region"]');
        const retry = canvas.getByRole('button', {name: 'Spróbuj ponownie.'});

        await waitFor(() => expect(retry).toBeVisible());
        retry.focus();
        await userEvent.keyboard('{Enter}');
        await waitFor(() => expect(region).toHaveAttribute('data-autosave-state', 'saved'));
    },
};
