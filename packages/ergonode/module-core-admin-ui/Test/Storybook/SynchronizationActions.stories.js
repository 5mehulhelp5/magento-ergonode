import { expect, userEvent, within } from 'storybook/test';
import {
    loadAmdModule,
    translateIdentity
} from '@ergonode-storybook/load-amd-module.js';

import '../../view/adminhtml/web/css/ergonode-workspace.css';
import '../../view/adminhtml/web/css/ergonode-actions.css';
import synchronizationActionsSource from '../../view/adminhtml/web/js/synchronization-actions.js?raw';
import workspaceSource from '../../view/adminhtml/web/js/workspace.js?raw';

const workspace = loadAmdModule(
    workspaceSource,
    { 'mage/translate': translateIdentity },
    'Ergonode_CoreAdminUi/js/workspace'
);
const synchronizationActions = loadAmdModule(
    synchronizationActionsSource,
    {},
    'Ergonode_CoreAdminUi/js/synchronization-actions'
);

function createActions(args) {
    const root = document.createElement('section');
    const toolbar = document.createElement('div');
    const actions = document.createElement('div');
    const sync = document.createElement('button');
    const status = document.createElement('p');

    root.className = 'veui-workspace';
    toolbar.className = 'veui-toolbar veui-viewbar';
    actions.className = args.hasCursorActions
        ? 'veui-split-button veui-split-button-align-end'
        : '';
    if (args.primary && args.hasCursorActions) {
        actions.classList.add('veui-split-button-primary');
    }
    actions.dataset.role = 'synchronization-actions';
    actions.setAttribute('role', 'group');
    actions.setAttribute('aria-label', 'Synchronization actions');
    sync.type = 'button';
    sync.className = 'veui-button veui-button-toolbar';
    if (args.hasCursorActions) {
        sync.classList.add('veui-split-button-main');
    }
    sync.dataset.role = 'sync-ergonode';
    sync.dataset.synchronizationAction = 'sync';
    sync.disabled = args.disabled;
    sync.innerHTML = '<span class="veui-sync-ergonode-icon" aria-hidden="true"></span><span>Sync</span>';
    if (args.busy) {
        sync.classList.add('is-working');
        sync.setAttribute('aria-busy', 'true');
    }
    actions.append(sync);

    if (args.hasCursorActions) {
        const options = document.createElement('details');

        options.className = 'veui-split-button-options';
        options.dataset.role = 'synchronization-action-options';
        options.innerHTML = `
            <summary class="veui-split-button-toggle"
                     role="button" aria-label="Synchronization options">
                <span class="veui-split-button-toggle-icon" aria-hidden="true"></span>
            </summary>
            <div class="veui-split-button-menu" role="menu" aria-label="Synchronization options">
                <button type="button" class="veui-split-button-option"
                        data-synchronization-action="reset-cursor" role="menuitem">
                    <span class="veui-reset-cursor-icon" aria-hidden="true"></span>
                    <span>Reset cursor</span>
                </button>
                <button type="button" class="veui-split-button-option"
                        data-synchronization-action="reset-cursor-and-sync" role="menuitem">
                    <span class="veui-sync-ergonode-icon" aria-hidden="true"></span>
                    <span>Reset cursor &amp; Sync</span>
                </button>
            </div>`;
        actions.append(options);
    }

    status.setAttribute('role', 'status');
    status.textContent = 'No action selected';
    toolbar.append(actions);
    root.append(toolbar, status);
    workspace.mount(root, (scope) => {
        synchronizationActions.bind(scope, root);
        scope.delegate('click', '[data-synchronization-action]', (event, action) => {
            status.textContent = action.dataset.synchronizationAction;
        });
    });

    return root;
}

function stateExample(label, args) {
    const example = document.createElement('section');

    example.className = 'veui-storybook-example';
    example.append(Object.assign(document.createElement('h3'), { textContent: label }));
    example.append(createActions(args));

    return example;
}

export default {
    id: 'ergo-c-035',
    title: 'Ergonode UI/Komponenty/Wspólne/ERGO-C-035 · Akcje synchronizacji',
    render: createActions,
    args: {
        hasCursorActions: true,
        disabled: false,
        busy: false,
        primary: false
    },
    argTypes: {
        hasCursorActions: { control: 'boolean' },
        disabled: { control: 'boolean' },
        busy: { control: 'boolean' },
        primary: { control: 'boolean' }
    }
};

export const Playground = {};

export const Stany = {
    render: () => {
        const grid = document.createElement('div');

        grid.className = 'veui-storybook-grid';
        grid.append(
            stateExample('Proces z kursorem', { hasCursorActions: true, disabled: false, busy: false }),
            stateExample('Proces bez kursora', { hasCursorActions: false, disabled: false, busy: false }),
            stateExample('Brak kontekstu', { hasCursorActions: false, disabled: true, busy: false }),
            stateExample('Synchronizacja trwa', { hasCursorActions: true, disabled: false, busy: true }),
            stateExample('Primary', { hasCursorActions: true, primary: true, disabled: false, busy: false }),
            stateExample('Primary disabled', { hasCursorActions: true, primary: true, disabled: true, busy: false }),
            stateExample('Primary busy', { hasCursorActions: true, primary: true, disabled: false, busy: true })
        );

        return grid;
    }
};

export const Menu = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);

        await userEvent.click(canvas.getByRole('button', { name: 'Synchronization options' }));
        await expect(canvas.getByRole('menuitem', { name: 'Reset cursor' })).toBeVisible();
        await expect(canvas.getByRole('menuitem', { name: 'Reset cursor & Sync' })).toBeVisible();
        await userEvent.click(canvas.getByRole('menuitem', { name: 'Reset cursor & Sync' }));
        await expect(canvas.getByRole('status')).toHaveTextContent('reset-cursor-and-sync');
        await expect(canvasElement.querySelector('[data-role="synchronization-action-options"]'))
            .not.toHaveAttribute('open');
    }
};

export const Keyboard = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const trigger = canvas.getByRole('button', { name: 'Synchronization options' });
        const menu = canvasElement.querySelector('[data-role="synchronization-action-options"]');

        trigger.focus();
        await userEvent.keyboard('{Enter}');
        await expect(menu).toHaveAttribute('open', '');
        await userEvent.keyboard('{Escape}');
        await expect(menu).not.toHaveAttribute('open');
        await expect(trigger).toHaveFocus();
    }
};
