import { expect, userEvent, within } from 'storybook/test';

import { loadAmdModule } from '@ergonode-storybook/load-amd-module.js';

import '../../view/adminhtml/web/css/ergonode-workspace.css';
import messagesSource from '../../view/adminhtml/web/js/messages.js?raw';

const messages = loadAmdModule(
    messagesSource,
    {
        'mage/translate': (value) => value
    },
    'Ergonode_CoreAdminUi/js/messages'
);

function renderMessage(args) {
    const root = document.createElement('section');
    const message = document.createElement('div');

    root.className = 'veui-workspace veui-workspace-viewbar';
    root.style.minHeight = '180px';
    message.className = 'veui-message veui-global-message';
    message.dataset.role = 'global-message';
    message.hidden = true;
    message.innerHTML = '<span class="veui-message-status-icon" aria-hidden="true"></span>' +
        '<span data-role="global-message-text"></span>';
    root.append(message);
    messages.create(root).show(args.tone, args.text);

    return root;
}

const meta = {
    id: 'ergo-c-032',
    title: 'Ergonode UI/Komponenty/Wspólne/ERGO-C-032 · Komunikat',
    tags: ['autodocs'],
    render: renderMessage,
    args: {
        tone: 'success',
        text: 'Dane kategorii zostały odświeżone.'
    },
    argTypes: {
        tone: {
            control: 'select',
            options: ['info', 'success', 'warning', 'error']
        },
        text: {control: 'text'}
    },
    parameters: {
        docs: {
            description: {
                component: 'Wspólny komunikat Admin UI: zamykany ręcznie i automatycznie po 30 sekundach.'
            }
        }
    }
};

export default meta;

export const Playground = {};

export const BladAkcji = {
    render: () => {
        const root = document.createElement('section');
        const message = document.createElement('div');

        root.className = 'veui-workspace veui-workspace-viewbar';
        root.style.minHeight = '180px';
        message.className = 'veui-message veui-global-message';
        message.dataset.role = 'global-message';
        message.hidden = true;
        message.innerHTML = '<span class="veui-message-status-icon" aria-hidden="true"></span>' +
            '<span data-role="global-message-text"></span>';
        root.append(message);
        messages.create(root, {autoHideDelay: 0}).error(
            'Nie udało się odświeżyć atrybutów',
            new Error('Integracja Ergonode jest wyłączona.'),
            'Nie udało się wczytać atrybutów z Ergonode.',
            {label: 'Przejdź do konfiguracji', url: '/admin/system_config/edit/section/ergonode'}
        );

        return root;
    },
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const message = canvas.getByRole('alert');
        const action = canvas.getByRole('link', {name: 'Przejdź do konfiguracji'});

        await expect(message).toHaveClass('veui-message-error');
        await expect(message).toHaveTextContent(
            'Nie udało się odświeżyć atrybutów: Integracja Ergonode jest wyłączona.'
        );
        await expect(action).toHaveAttribute('href', '/admin/system_config/edit/section/ergonode');
    }
};

export const Stany = {
    render: () => {
        const matrix = document.createElement('div');

        matrix.style.display = 'grid';
        matrix.style.gap = '12px';
        ['info', 'success', 'warning', 'error'].forEach((tone) => {
            const example = renderMessage({tone, text: `Komunikat ${tone}`});

            example.style.minHeight = '110px';
            matrix.append(example);
        });

        return matrix;
    }
};

export const ZamykanieMysza = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const message = canvasElement.querySelector('[data-role="global-message"]');

        await userEvent.click(canvas.getByRole('button', {name: 'Close message'}));
        await expect(message.hidden).toBe(true);
        await expect(message.querySelector('[data-role="global-message-text"]').textContent).toBe('');
    }
};

export const ZamykanieKlawiatura = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const message = canvasElement.querySelector('[data-role="global-message"]');

        await userEvent.tab();
        await expect(canvas.getByRole('button', {name: 'Close message'})).toHaveFocus();
        await userEvent.keyboard('{Enter}');
        await expect(message.hidden).toBe(true);
    }
};
