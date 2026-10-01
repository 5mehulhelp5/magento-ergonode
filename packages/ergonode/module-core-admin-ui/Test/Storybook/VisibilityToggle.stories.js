import { expect, userEvent, within } from 'storybook/test';

import { loadAmdModule } from '@ergonode-storybook/load-amd-module.js';

import '../../view/adminhtml/web/css/ergonode-workspace.css';
import buttonsSource from '../../view/adminhtml/web/js/buttons.js?raw';
import visibilityToggleSource from '../../view/adminhtml/web/js/visibility-toggle.js?raw';

const buttons = loadAmdModule(
    buttonsSource,
    {'mage/translate': (value) => value},
    'Ergonode_CoreAdminUi/js/buttons'
);
const visibilityToggle = loadAmdModule(
    visibilityToggleSource,
    {'Ergonode_CoreAdminUi/js/buttons': buttons},
    'Ergonode_CoreAdminUi/js/visibility-toggle'
);

function createButton(args) {
    const button = document.createElement('button');

    button.type = 'button';
    button.className = 'veui-button veui-visibility-control veui-visibility-toggle';
    button.dataset.role = 'visibility-toggle';
    button.dataset.showHint = args.showHint;
    button.dataset.hideHint = args.hideHint;
    button.setAttribute('aria-pressed', args.visible ? 'true' : 'false');
    button.innerHTML = '<span class="veui-visibility-icon" aria-hidden="true"></span><span>Pominięte</span>';
    visibilityToggle.sync(button);
    button.addEventListener('click', () => visibilityToggle.toggle(button));

    return button;
}

function renderToggle(args) {
    const root = document.createElement('div');
    const toolbar = document.createElement('div');

    root.className = 'veui-workspace veui-workspace-viewbar';
    root.style.minHeight = '120px';
    toolbar.className = 'veui-toolbar veui-viewbar';
    toolbar.append(createButton(args));
    root.append(toolbar);

    return root;
}

const defaultArgs = {
    visible: false,
    showHint: 'Pokaż elementy pominięte w mapowaniu',
    hideHint: 'Ukryj elementy pominięte w mapowaniu'
};

const meta = {
    id: 'ergo-c-038',
    title: 'Ergonode UI/Komponenty/Wspólne/ERGO-C-038 · Widoczność pominiętych',
    tags: ['autodocs'],
    render: renderToggle,
    args: defaultArgs,
    argTypes: {
        visible: {
            control: 'boolean',
            description: 'Określa, czy pominięte elementy są dodatkowo widoczne.'
        },
        showHint: {control: 'text'},
        hideHint: {control: 'text'}
    },
    parameters: {
        docs: {
            description: {
                component: 'Wspólny przełącznik widoczności pominiętych elementów. Label pozostaje stały, a precyzyjny hint opisuje bieżącą akcję.'
            }
        }
    }
};

export default meta;

export const Playground = {};

export const Stany = {
    render: () => {
        const grid = document.createElement('div');

        grid.className = 'veui-storybook-grid';
        grid.append(
            renderToggle({...defaultArgs, visible: false}),
            renderToggle({...defaultArgs, visible: true})
        );

        return grid;
    }
};

export const PrzelaczanieMysza = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const button = canvas.getByRole('button', {name: defaultArgs.showHint});

        await userEvent.click(button);
        await expect(button).toHaveAttribute('aria-pressed', 'true');
        await expect(button).toHaveAttribute('title', defaultArgs.hideHint);
        await expect(button).toHaveAccessibleName(defaultArgs.hideHint);
    }
};

export const PrzelaczanieKlawiatura = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const button = canvas.getByRole('button', {name: defaultArgs.showHint});

        await userEvent.tab();
        await expect(button).toHaveFocus();
        await userEvent.keyboard('{Enter}');
        await expect(button).toHaveAttribute('aria-pressed', 'true');
        await expect(button).toHaveAccessibleName(defaultArgs.hideHint);
    }
};
