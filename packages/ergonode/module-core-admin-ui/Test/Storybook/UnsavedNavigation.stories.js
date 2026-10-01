import { expect, userEvent, within } from 'storybook/test';

import { loadAmdModule } from '@ergonode-storybook/load-amd-module.js';

import '../../view/adminhtml/web/css/ergonode-workspace.css';
import unsavedNavigationSource from '../../view/adminhtml/web/js/unsaved-navigation.js?raw';

let storyRoot = null;

function confirm(config) {
    const dialog = document.createElement('section');
    const inner = document.createElement('div');
    const header = document.createElement('header');
    const title = document.createElement('h2');
    const content = document.createElement('div');
    const footer = document.createElement('footer');

    dialog.className = `modal-popup ${config.modalClass}`;
    dialog.setAttribute('role', 'dialog');
    dialog.setAttribute('aria-label', config.title);
    Object.assign(dialog.style, {
        alignItems: 'center',
        display: 'flex',
        justifyContent: 'center',
        marginTop: '18px',
        padding: '20px'
    });
    inner.className = 'modal-inner-wrap';
    header.className = 'modal-header';
    title.className = 'modal-title';
    title.textContent = config.title;
    content.className = 'modal-content';
    content.textContent = config.content;
    footer.className = 'modal-footer';
    config.buttons.forEach((definition) => {
        const button = document.createElement('button');

        button.type = 'button';
        button.className = definition.class;
        button.textContent = definition.text;
        button.addEventListener('click', (event) => {
            definition.click.call({
                closeModal(currentEvent, accepted) {
                    dialog.remove();
                    if (accepted) {
                        config.actions.confirm(currentEvent);
                    } else {
                        config.actions.cancel(currentEvent);
                    }
                    config.actions.always(currentEvent);
                }
            }, event);
        });
        footer.append(button);
    });
    header.append(title);
    inner.append(header, content, footer);
    dialog.append(inner);
    storyRoot.append(dialog);
}

const unsavedNavigation = loadAmdModule(unsavedNavigationSource, {
    'Magento_Ui/js/modal/confirm': confirm,
    'mage/translate': (value) => value
}, 'Ergonode_CoreAdminUi/js/unsaved-navigation');

function renderUnsavedNavigation() {
    const root = document.createElement('section');
    let dirty = true;

    root.className = 'veui-workspace';
    root.style.minHeight = '280px';
    root.innerHTML = `
        <div class="veui-panel" style="padding: 18px">
            <h2>Mapowanie opcji</h2>
            <p>Przeniesiono opcję „Granatowy” do pary „Navy”.</p>
            <a class="veui-button" href="#products">Przejdź do produktów</a>
            <strong data-role="result" style="display: block; margin-top: 14px">Zmiany niezapisane</strong>
        </div>
    `;
    storyRoot = root;

    const result = root.querySelector('[data-role="result"]');
    const controller = unsavedNavigation.create({
        isDirty: () => dirty,
        save: () => {
            dirty = false;
            result.textContent = 'Zapisano mapowanie';

            return Promise.resolve();
        }
    });

    root.querySelector('a').addEventListener('click', (event) => {
        event.preventDefault();
        controller.request({
            navigate: () => {
                result.textContent = dirty
                    ? 'Przejście bez zapisu'
                    : 'Zapisano i rozpoczęto przejście';
            }
        });
    });

    return root;
}

export default {
    id: 'ergo-c-037',
    title: 'Ergonode UI/Komponenty/Wspólne/ERGO-C-037 · Ochrona niezapisanych zmian',
    tags: ['autodocs'],
    render: renderUnsavedNavigation
};

export const Playground = {};

export const ZapiszIPrzejdzMysza = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);

        await userEvent.click(canvas.getByRole('link', {name: 'Przejdź do produktów'}));
        await expect(canvas.getByRole('dialog', {name: 'Warning, data is unsaved'})).toBeVisible();
        await expect(canvas.getByRole('button', {name: 'Discard'})).toBeVisible();
        await userEvent.click(canvas.getByRole('button', {name: 'Save'}));
        await expect(canvas.getByText('Zapisano i rozpoczęto przejście')).toBeVisible();
    }
};

export const PrzejdzBezZapisuKlawiatura = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const link = canvas.getByRole('link', {name: 'Przejdź do produktów'});

        link.focus();
        await userEvent.keyboard('{Enter}');
        await expect(canvas.getByRole('dialog', {name: 'Warning, data is unsaved'})).toBeVisible();
        canvas.getByRole('button', {name: 'Discard'}).focus();
        await userEvent.keyboard('{Enter}');
        await expect(canvas.getByText('Przejście bez zapisu')).toBeVisible();
    }
};

export const ZostanNaStronie = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);

        await userEvent.click(canvas.getByRole('link', {name: 'Przejdź do produktów'}));
        const dialog = canvas.getByRole('dialog', {name: 'Warning, data is unsaved'});

        await expect(dialog).toHaveClass('veui-unsaved-navigation-modal');
        await userEvent.click(canvas.getByRole('button', {name: 'Cancel'}));
        await expect(dialog).not.toBeInTheDocument();
        await expect(canvas.getByText('Zmiany niezapisane')).toBeVisible();
    }
};
