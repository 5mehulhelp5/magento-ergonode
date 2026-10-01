import { expect, userEvent, waitFor, within } from 'storybook/test';

import { loadAmdModule, translateIdentity } from '@ergonode-storybook/load-amd-module.js';

import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-actions.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/attribute-mapping.css';
import '@ergonode-modules/TemplateAdminUi/view/adminhtml/web/css/template-admin.css';
import templateCodeSource from '../../view/adminhtml/web/js/template-code.js?raw';
import templatePublisherSource from '../../view/adminhtml/web/js/template-publisher.js?raw';

const templateCode = loadAmdModule(
    templateCodeSource,
    {},
    'Ergonode_TemplatePublisherAdminUi/js/template-code'
);
const templatePublisher = loadAmdModule(templatePublisherSource, {
    'mage/translate': translateIdentity,
    'Ergonode_CoreAdminUi/js/buttons': {
        run: (button, action) => {
            button.disabled = true;

            return Promise.resolve().then(action).finally(() => { button.disabled = false; });
        }
    },
    'Ergonode_CoreAdminUi/js/request': {
        post: (_url, _config, payload) => new Promise((resolve) => window.setTimeout(() => resolve({
            success: true,
            message: 'Template został utworzony w Ergonode.',
            template: { code: payload.template_code, name: 'Summer Collection' }
        }), 80))
    },
    'Ergonode_TemplatePublisherAdminUi/js/template-code': templateCode
}, 'Ergonode_TemplatePublisherAdminUi/js/template-publisher');

function fillSide(element, label, identifier) {
    const subline = document.createElement('span');

    subline.className = 'vea-card-subline';
    subline.append(Object.assign(document.createElement('code'), { textContent: identifier }));
    element.replaceChildren(
        Object.assign(document.createElement('strong'), { textContent: label }),
        subline
    );
}

function appendPendingBadge(element, text) {
    const badge = Object.assign(document.createElement('span'), {
        className: 'veui-pending-badge',
        textContent: text
    });
    const target = element.querySelector(':scope > .vea-card-subline') || element;

    target.append(badge);

    return badge;
}

function side(label, value, empty = false, source = '') {
    const element = document.createElement('div');

    element.className = `vea-pair-card vet-pair-side${empty ? ' is-empty' : ''}`;
    if (empty) {
        const hint = Object.assign(document.createElement('span'), { textContent: source });

        hint.dataset.role = 'slot-hint';
        element.append(
            Object.assign(document.createElement('strong'), { textContent: value }),
            hint
        );
    } else {
        fillSide(element, label, value);
    }

    return element;
}

function render(args) {
    const frame = document.createElement('div');
    const card = document.createElement('article');
    const ergo = side(
        args.created ? 'Summer Collection' : 'Ergonode',
        args.created ? 'summer_collection' : 'Upuść template',
        !args.created,
        'z Ergonode'
    );
    const connector = document.createElement('button');
    const magento = side('Summer Collection', '#12');
    const collisionTemplates = args.collision === 'code'
        ? [{ code: 'summer_collection', names: ['Inna nazwa'] }]
        : (args.collision === 'name'
            ? [{ code: 'summer_legacy', names: ['Letnia kolekcja', 'Summer Collection'] }]
            : []);

    frame.style.maxWidth = '760px';
    frame.style.margin = '40px auto';
    frame.className = 'vet-admin';
    card.className = 'vea-pair-row vea-attribute-pair-row vea-status-tone-warning vet-pair-card vet-draft-card';
    connector.type = 'button';
    connector.className = 'vea-link-indicator vet-link-action';
    connector.setAttribute('aria-label', 'Wyczyść szkic');
    connector.append(document.createElement('span'));
    connector.addEventListener('click', () => card.remove());

    if (args.created) {
        appendPendingBadge(ergo, 'TWORZENIE');
        appendPendingBadge(magento, 'ZAPISYWANIE');
    } else {
        appendPendingBadge(magento, 'SZKIC');
    }

    card.append(ergo, connector, magento);
    frame.append(card);
    if (!args.created) {
        frame.veaContext = {
            message: { show: () => {} },
            registerDraftTemplateAction: (action) => {
                action.render(ergo, {
                    attributeSet: { id: 12, name: 'Summer Collection' },
                    draftId: 1,
                    templates: collisionTemplates,
                    create: (options) => {
                        const placeholder = options.template;

                        ergo.classList.remove('is-empty');
                        fillSide(ergo, placeholder.name, placeholder.code);
                        magento.querySelector('.veui-pending-badge')?.remove();
                        appendPendingBadge(ergo, placeholder.creating_label);
                        appendPendingBadge(magento, 'ZAPISYWANIE');

                        return options.request().then((response) => {
                            options.applyResponse(placeholder, response);
                            ergo.querySelector('.veui-pending-badge')?.remove();

                            return response;
                        });
                    }
                });

                return () => {};
            }
        };
        frame.veaWorkspace = { cleanup: () => {} };
        templatePublisher({ urls: { create: '/template/create' } }, frame);
    }

    return frame;
}

export default {
    id: 'ergo-v-058-07',
    title: 'Ergonode UI/Widoki/Szablony/ERGO-V-058 · Mapowanie szablonów/ERGO-V-058.07 · Tworzenie szablonów',
    tags: ['autodocs'],
    render,
    args: { collision: 'none', created: false },
    argTypes: {
        collision: { control: 'select', options: ['none', 'code', 'name'] },
        created: { control: 'boolean' }
    }
};

export const Playground = {};

export const UniwersalnyKomponentPary = {
    play: async ({ canvasElement }) => {
        const row = canvasElement.querySelector('.vet-draft-card');
        const emptySide = canvasElement.querySelector('.vet-pair-side.is-empty');
        const filledSide = canvasElement.querySelector('.vet-pair-side:not(.is-empty)');
        const reference = document.createElement('div');
        const rowStyle = window.getComputedStyle(row);
        const rowAccentStyle = window.getComputedStyle(row, '::before');
        const emptySideStyle = window.getComputedStyle(emptySide);

        reference.className = 'vea-pair-card';
        reference.style.position = 'fixed';
        reference.style.visibility = 'hidden';
        fillSide(reference, 'Summer Collection', '#12');
        appendPendingBadge(reference, 'SZKIC');
        canvasElement.append(reference);

        await expect(rowStyle.borderStyle).toBe('solid');
        await expect(rowAccentStyle.display).toBe('none');
        await expect(emptySideStyle.backgroundColor).not.toBe('rgb(255, 255, 255)');
        await expect(emptySideStyle.borderLeftColor).toBe(emptySideStyle.borderRightColor);
        await expect(emptySideStyle.borderStyle).toBe('dashed');
        await expect(emptySideStyle.cursor).not.toBe('grab');
        await expect(emptySide.querySelector(':scope > strong')).toHaveTextContent('Upuść template');
        await expect(emptySide.querySelector('[data-role="slot-hint"]')).toHaveTextContent('z Ergonode');
        await expect(filledSide.querySelector(':scope > strong')).toHaveTextContent('Summer Collection');
        await expect(filledSide.querySelector('.vea-card-subline > code')).toHaveTextContent('#12');
        await expect(filledSide.querySelector('.vea-card-subline > .veui-pending-badge'))
            .toHaveTextContent('SZKIC');
        await expect(filledSide.querySelector(':scope > .veui-pending-badge')).toBeNull();
        await expect(window.getComputedStyle(filledSide).backgroundColor)
            .toBe(window.getComputedStyle(reference).backgroundColor);
        await expect(window.getComputedStyle(filledSide).borderColor)
            .toBe(window.getComputedStyle(reference).borderColor);
        await expect(window.getComputedStyle(filledSide).padding)
            .toBe(window.getComputedStyle(reference).padding);

        reference.remove();
    }
};

export const UtworzonyWTrakcieZapisu = {
    args: { created: true }
};

export const UtworzenieZMagento = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const button = canvas.getByRole('button', {
            name: 'Utwórz template w Ergonode z zestawu atrybutów Magento'
        });

        button.focus();
        await userEvent.keyboard('{Enter}');
        await expect(canvas.getAllByText('Summer Collection')).toHaveLength(2);
        await expect(canvas.getByText('summer_collection')).toBeVisible();
        await expect(canvas.getByText('TWORZENIE')).toBeVisible();
        await waitFor(async () => {
            await expect(canvas.getByText('ZAPISYWANIE')).toBeVisible();
        });
    }
};

export const ZablokowanePrzezNazweErgonode = {
    args: { collision: 'name' },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const button = canvas.getByRole('button', {
            name: 'Nie można utworzyć template: nazwa Summer Collection w Ergonode odpowiada kodowi summer_collection.'
        });
        const icon = button.querySelector('.veui-create-ergonode-icon');

        await expect(button).toBeDisabled();
        await expect(window.getComputedStyle(icon).backgroundColor)
            .toBe(window.getComputedStyle(icon).color);
    }
};

export const CzyszczeniePrzezLacznik = {
    args: { created: true },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const button = canvas.getByRole('button', { name: 'Wyczyść szkic' });

        await userEvent.click(button);
        await expect(canvas.queryByRole('button', { name: 'Wyczyść szkic' })).not.toBeInTheDocument();
    }
};
