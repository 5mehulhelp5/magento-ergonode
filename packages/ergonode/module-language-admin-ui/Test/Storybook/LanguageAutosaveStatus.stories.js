import { createAutosaveRegion } from '@ergonode-storybook/autosave-region.js';
import {
    expect,
    userEvent,
    waitFor,
    within,
} from 'storybook/test';

import { loadAmdModule, translateIdentity } from '@ergonode-storybook/load-amd-module.js';
import {createLanguageLoader} from './language-fixture.js';

import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/attribute-mapping.css';
import '../../view/adminhtml/web/css/language-mapping.css';
import coreAutosaveSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/autosave.js?raw';
import autosaveSource from '../../view/adminhtml/web/js/language-autosave.js?raw';

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
            'Ergonode_CoreAdminUi/js/request': {
                post: (...parameters) => request.post(...parameters).then((response) => ({
                    ...response, revision: 'b'.repeat(64)
                }))
            },
            'mage/translate': translateIdentity,
        },
        'Ergonode_LanguageAdminUi/js/language-autosave'
    );
}

function renderRegion(args) {
    return createAutosaveRegion(args, {
        loadAutosave,
        view: 'language',
        config: {urls: {save: '/save'}, revision: 'a'.repeat(64)},
        serialize: () => ({mappings: []}),
        decorateRoot: (root) => root.prepend(createLanguageLoader())
    });
}

const meta = {
    id: 'ergo-v-040-01',
    title: 'Ergonode UI/Widoki/Języki/ERGO-V-040 · Edycja i odświeżanie języków/ERGO-V-040.01 · Adapter autozapisu języków',
    tags: ['autodocs'],
    render: renderRegion,
    args: {
        mode: 'saved',
    },
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
            renderRegion({ mode: 'saved' }),
            renderRegion({ mode: 'saving' }),
            renderRegion({ mode: 'error' })
        );

        return grid;
    },
    play: async ({canvasElement}) => {
        const roots = [...canvasElement.querySelectorAll('.vel-mapping')];
        const [saved, saving, error] = roots;
        await expect(saved.querySelector('[data-role="language-loader"]')).not.toBeVisible();
        await expect(saving.querySelector('[data-role="language-loader"]')).toBeVisible();
        await expect(saving).toHaveAttribute('data-language-busy', 'true');
        await waitFor(() => expect(error.querySelector('[data-role="autosave-region"]'))
            .toHaveAttribute('data-autosave-state', 'error'));
        await expect(error.querySelector('[data-role="language-loader"]')).not.toBeVisible();
    },
};

export const PonowienieMysza = {
    render: () => renderRegion({ mode: 'recoverable-error' }),
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const region = canvasElement.querySelector('[data-role="autosave-region"]');

        await waitFor(() => expect(region).toHaveAttribute('data-autosave-state', 'error'));
        await expect(region).not.toHaveAttribute('inert');
        await userEvent.click(canvas.getByRole('button', { name: 'Spróbuj ponownie.' }));
        await waitFor(() => expect(region).toHaveAttribute('data-autosave-state', 'saved'));
        await expect(region).toHaveAttribute('aria-busy', 'false');
        await expect(canvas.queryByRole('alert')).not.toBeInTheDocument();
    },
};

export const PonowienieKlawiatura = {
    render: () => renderRegion({ mode: 'recoverable-error' }),
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const region = canvasElement.querySelector('[data-role="autosave-region"]');
        await waitFor(() => expect(region).toHaveAttribute('data-autosave-state', 'error'));
        const retry = canvas.getByRole('button', { name: 'Spróbuj ponownie.' });

        await waitFor(() => expect(retry).toBeVisible());
        retry.focus();
        await userEvent.keyboard('{Enter}');
        await waitFor(() => expect(region).toHaveAttribute('data-autosave-state', 'saved'));
    },
};
