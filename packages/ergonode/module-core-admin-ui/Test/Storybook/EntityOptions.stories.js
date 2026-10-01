import { expect, userEvent, within } from 'storybook/test';
import {
    loadAmdModule,
    translateIdentity
} from '@ergonode-storybook/load-amd-module.js';

import '../../view/adminhtml/web/css/ergonode-workspace.css';
import '../../view/adminhtml/web/css/attribute-mapping.css';
import entityOptionsSource from '../../view/adminhtml/web/js/entity-options.js?raw';
import snapshotRemovalSource from '../../view/adminhtml/web/js/snapshot-removal.js?raw';
import workspaceSource from '../../view/adminhtml/web/js/workspace.js?raw';

const dependencies = {'mage/translate': translateIdentity};
const entityOptions = loadAmdModule(
    entityOptionsSource,
    dependencies,
    'Ergonode_CoreAdminUi/js/entity-options'
);
const workspace = loadAmdModule(
    workspaceSource,
    dependencies,
    'Ergonode_CoreAdminUi/js/workspace'
);
const snapshotRemoval = loadAmdModule(snapshotRemovalSource, {
    'Ergonode_CoreAdminUi/js/request': {
        post: async () => ({ success: true, message: 'Usunięto z tej listy' })
    },
    'Ergonode_CoreAdminUi/js/buttons': {
        run: async (button, task) => task()
    },
    'Ergonode_CoreAdminUi/js/entity-options': entityOptions,
    'mage/translate': translateIdentity
}, 'Ergonode_CoreAdminUi/js/snapshot-removal');

const variants = {
    language: {
        code: 'pl_PL',
        label: 'Polski',
        menuLabel: 'Opcje języka'
    },
    attribute: {
        code: 'color',
        createLabel: 'Utwórz w Magento',
        label: 'Kolor',
        menuLabel: 'Opcje atrybutu'
    },
    option: {
        code: 'red',
        createLabel: 'Utwórz w Magento',
        label: 'Czerwony',
        menuLabel: 'Opcje wartości'
    }
};

function fixture(args) {
    const data = variants[args.variant];
    const root = document.createElement('div');
    const panel = document.createElement('section');
    const list = document.createElement('div');
    const card = document.createElement('div');
    const status = document.createElement('p');

    root.className = 'veui-workspace vea-mapping';
    root.style.minHeight = '420px';
    panel.className = 'veui-panel vea-panel vea-side-panel';
    panel.style.height = '360px';
    panel.style.margin = '0 auto';
    panel.style.maxWidth = '380px';
    panel.innerHTML = `
        <div class="veui-panel-head vea-panel-head">
            <strong class="veui-panel-title">Ergonode ${args.variant}</strong>
        </div>`;
    list.className = 'vea-attribute-list';
    card.className = `vea-attribute-card${args.active ? '' : ' is-inactive'}`;
    card.dataset.role = 'entity-card';
    card.dataset.source = 'ergo';
    card.dataset.code = data.code;
    card.dataset.label = data.label;
    card.setAttribute('role', 'group');
    card.setAttribute('tabindex', '0');
    card.setAttribute('aria-label', `Dodaj do mapowania: ${data.label}`);
    card.innerHTML = `
        <span class="vea-drag-handle" aria-hidden="true"></span>
        <div class="vea-card-copy">
            <strong>${data.label}</strong>
            <span class="vea-card-subline"><code>${data.code}</code></span>
        </div>
        <button type="button" class="vea-card-toggle" data-role="source-active-toggle"
                aria-label="${args.active ? 'Exclude' : 'Include'}"
                aria-pressed="${args.active ? 'true' : 'false'}">
            <span aria-hidden="true"></span>
        </button>`;
    status.setAttribute('role', 'status');
    status.textContent = 'Nie wybrano akcji';
    status.style.margin = '12px 14px';
    list.appendChild(card);
    panel.append(list, status);
    root.appendChild(panel);

    workspace.mount(root, (scope) => {
        const toggle = card.querySelector('[data-role="source-active-toggle"]');
        const actions = [];

        if (data.createLabel) {
            actions.push({
                active: args.active,
                iconClass: 'vea-create-option-icon',
                label: data.createLabel,
                requiresActive: true,
                role: 'entity-create-in-magento'
            });
        }
        snapshotRemoval.enhance(card, {
            active: args.active,
            actions,
            mappingAction: true,
            menuLabel: `${data.menuLabel}: ${data.label}`,
            snapshot: {
                code: data.code,
                label: data.label
            },
            toggle,
        });
        snapshotRemoval.bind(scope, root, {urls: {delete_snapshot: '#delete'}}, {
            confirm: () => true,
            message: {
                show: (tone, message) => {
                    status.textContent = message;
                }
            },
            onSuccess: () => {}
        });
        scope.delegate('click', '[data-role="entity-add-to-mapping"]', () => {
            status.textContent = `Dodano do mapowania: ${data.label}`;
        });
        scope.delegate('click', '[data-role="entity-create-in-magento"]', () => {
            status.textContent = `Utworzono w Magento: ${data.label}`;
        });
        scope.delegate('click', '[data-role="source-active-toggle"]', (event, button) => {
            const active = button.getAttribute('aria-pressed') !== 'true';

            snapshotRemoval.setActiveState(card, active);
            status.textContent = active ? 'Element włączony' : 'Element wyłączony';
        });
    });

    return root;
}

export default {
    id: 'ergo-c-027',
    title: 'Ergonode UI/Komponenty/Wspólne/ERGO-C-027 · Opcje encji',
    args: { active: true, variant: 'attribute' },
    argTypes: {
        active: { control: 'boolean' },
        variant: { control: 'select', options: Object.keys(variants) }
    },
    render: fixture
};

export const Playground = {};
export const Language = { args: { variant: 'language' } };
export const Attribute = { args: { variant: 'attribute' } };
export const Option = { args: { variant: 'option' } };
export const Inactive = { args: { active: false } };
export const ConciseVisibilityAction = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);

        await userEvent.click(canvas.getByRole('button', { name: 'Opcje atrybutu: Kolor' }));
        await expect(canvas.getByRole('button', { name: 'Exclude' })).toBeVisible();
        await userEvent.click(canvas.getByRole('button', { name: 'Exclude' }));
        await userEvent.click(canvas.getByRole('button', { name: 'Opcje atrybutu: Kolor' }));
        await expect(canvas.getByRole('button', { name: 'Include' })).toBeVisible();
    }
};
export const Keyboard = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const menuButton = canvas.getByRole('button', { name: 'Opcje atrybutu: Kolor' });
        const menu = canvasElement.querySelector('[data-role="entity-options"]');

        menuButton.focus();
        await userEvent.keyboard('{Enter}');
        await expect(canvas.getByRole('button', { name: 'Dodaj do mapowania' })).toBeVisible();
        await expect(menu).toHaveAttribute('open', '');
        await userEvent.keyboard('{Escape}');
        await expect(menu).not.toHaveAttribute('open');
        await expect(menuButton).toHaveFocus();
    }
};
export const CreateInMagento = {
    args: { variant: 'option' },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);

        await userEvent.click(canvas.getByRole('button', { name: 'Opcje wartości: Czerwony' }));
        await userEvent.click(canvas.getByRole('button', { name: 'Utwórz w Magento' }));
        await expect(canvas.getByRole('status')).toHaveTextContent('Utworzono w Magento: Czerwony');
    }
};
export const RemoveFromList = {
    args: { variant: 'language' },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);

        await userEvent.click(canvas.getByRole('button', { name: 'Opcje języka: Polski' }));
        await userEvent.click(canvas.getByRole('button', { name: 'Remove from list' }));
        await expect(canvas.getByRole('status')).toHaveTextContent('Usunięto z tej listy');
    }
};
