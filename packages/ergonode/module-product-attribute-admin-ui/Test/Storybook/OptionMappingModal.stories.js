import { expect, userEvent, within } from 'storybook/test';

import { createMappingWorkspace } from '@ergonode-storybook/mapping-workspace.js';

import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/attribute-mapping.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/option-mapping.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/option-mapping-modal.css';

function renderModal() {
    const dialog = document.createElement('div');
    const inner = document.createElement('div');
    const header = document.createElement('header');
    const title = document.createElement('h1');
    const close = document.createElement('button');
    const content = document.createElement('div');
    const workspace = createMappingWorkspace({view: 'option', state: 'mapped', embedded: true});

    dialog.className = 'modal-popup _show vea-option-mapping-modal';
    dialog.setAttribute('role', 'dialog');
    dialog.setAttribute('aria-modal', 'true');
    dialog.setAttribute('aria-labelledby', 'storybook-option-mapping-modal-title');
    inner.className = 'modal-inner-wrap';
    header.className = 'modal-header';
    title.id = 'storybook-option-mapping-modal-title';
    title.className = 'modal-title';
    title.textContent = 'Mapowanie opcji';
    close.className = 'action-close';
    close.type = 'button';
    close.dataset.role = 'closeBtn';
    close.setAttribute('aria-label', 'Zamknij mapowanie opcji');
    close.textContent = 'Zamknij';
    content.className = 'modal-content';
    workspace.classList.add('vea-option-mapping-embedded');
    workspace.querySelector('.vea-viewbar')?.remove();
    content.append(workspace);
    header.append(title, close);
    inner.append(header, content);
    dialog.append(inner);
    close.addEventListener('click', () => {
        dialog.hidden = true;
        dialog.setAttribute('aria-hidden', 'true');
    });

    return dialog;
}

export default {
    id: 'ergo-v-051-01',
    title: 'Ergonode UI/Widoki/Atrybuty produktów/ERGO-V-051 · Mapowanie opcji/ERGO-V-051.01 · Modal mapowania opcji',
    tags: ['autodocs'],
    render: renderModal
};

export const Playground = {};

export const BezNagłówka = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);

        await expect(canvasElement.querySelector('.vea-viewbar')).not.toBeInTheDocument();
        await userEvent.click(canvas.getByRole('button', {name: 'Zamknij mapowanie opcji'}));
        await expect(canvasElement.querySelector('[role="dialog"]')).toHaveAttribute('aria-hidden', 'true');
    }
};
