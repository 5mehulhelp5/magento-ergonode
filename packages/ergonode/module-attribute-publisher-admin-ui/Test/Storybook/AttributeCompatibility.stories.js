import { expect, userEvent, within } from 'storybook/test';
import { loadAmdModule, translateIdentity } from '@ergonode-storybook/load-amd-module.js';
import publisherSource from '../../view/adminhtml/web/js/ergonode-attribute-publisher-mapping.js?raw';
import textSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/text.js?raw';
import mappingElementsSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/mapping-elements.js?raw';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/attribute-mapping.css';
import '../../view/adminhtml/web/css/pending-type-picker.css';

const text = loadAmdModule(textSource, {}, 'attribute-compatibility-text');
const mappingElements = loadAmdModule(mappingElementsSource, {
    'Ergonode_CoreAdminUi/js/text': text, 'mage/translate': translateIdentity
}, 'attribute-compatibility-elements');
function storyJquery(element) {
    return {
        element,
        remove() { element.remove(); },
        modal(action, option, value) {
            if (action === 'option') {
                return option === 'isOpen' ? !this.shell.hidden : value;
            }
            if (action === 'openModal') { this.shell.hidden = false; }
            if (action === 'closeModal') { this.shell.hidden = true; }
            return this;
        }
    };
}

function storyModal(options, widget) {
    const shell = document.createElement('section');
    shell.className = `modal-popup ${options.modalClass}`;
    shell.setAttribute('role', 'dialog');
    shell.setAttribute('aria-label', options.title);
    shell.hidden = true;
    shell.append(widget.element);
    document.body.append(shell);
    widget.shell = shell;
}

const publisher = loadAmdModule(publisherSource, {
    jquery: storyJquery, 'Magento_Ui/js/modal/modal': storyModal,
    'mage/translate': translateIdentity,
    'Ergonode_CoreAdminUi/js/buttons': {}, 'Ergonode_CoreAdminUi/js/request': {},
    'Ergonode_CoreAdminUi/js/bulk-publish-progress': () => ({}),
    'Ergonode_CoreAdminUi/js/text': text,
    'Ergonode_CoreAdminUi/js/mapping-elements': mappingElements
}, 'attribute-compatibility-publisher');

export default {
    id: 'ergo-v-070-01',
    title: 'Ergonode UI/Widoki/Atrybuty produktów/ERGO-V-070 · Mapowanie atrybutów produktów/ERGO-V-070.01 · Zgodność typów atrybutów',
    tags: ['attribute-compatibility'],
    args: { allowTextarea: false, keyboard: false },
    render: (args) => {
        const root = document.createElement('section');
        root.innerHTML = '<article class="vea-pair-row" data-role="mapping-row">' +
            '<div class="vea-pair-card is-empty" data-role="pair-slot" data-side="ergo"></div>' +
            '<div class="vea-pair-card" data-role="pair-slot" data-side="magento" data-code="title" ' +
            'data-label="Title" data-type="text" data-scope="local"><strong>Title</strong></div></article>';
        let cleanups = [];
        root.veaWorkspace = { cleanup(callback) { cleanups.push(callback); } };
        root.storyDestroy = () => { cleanups.splice(0).forEach(cleanup => cleanup()); };
        root.storyMount = () => publisher({ mode: 'attribute', attribute_compatibility: args.allowTextarea
            ? {text: ['text'], textarea: ['text']} : {text: ['text']} }, root);
        root.storyMount();
        return root;
    }
};

export const Playground = {
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        const createButton = canvas.getByRole('button', {name: 'Utwórz atrybut w Ergonode z Magento'});
        if (args.keyboard) {
            createButton.focus();
            await expect(createButton).toHaveFocus();
            await userEvent.keyboard('{Enter}');
        } else {
            await userEvent.click(createButton);
        }
        const trigger = canvasElement.querySelector('[data-role="pending-ergonode-type-trigger"]');
        expect(Boolean(trigger)).toBe(args.allowTextarea);
        expect(canvasElement.querySelector('[data-side="ergo"]').getAttribute('data-type')).toBe('text');
        canvasElement.querySelector('section').storyDestroy();
    }
};
export const MultipleCompatibleTypes = { ...Playground, args: {allowTextarea: true} };
export const KeyboardCreation = { ...Playground, args: {keyboard: true} };

export const WorkspaceLifecycle = {
    args: { allowTextarea: true, keyboard: false, selectBeforeDestroy: false },
    play: async ({ canvasElement, args }) => {
        const root = canvasElement.querySelector('section');
        const canvas = within(canvasElement);
        await userEvent.click(canvas.getByRole('button', {name: 'Utwórz atrybut w Ergonode z Magento'}));
        const trigger = root.querySelector('[data-role="pending-ergonode-type-trigger"]');
        for (let cycle = 0; cycle < 5; cycle++) {
            if (cycle > 0) { root.storyMount(); }
            if (args.keyboard) {
                trigger.focus();
                await userEvent.keyboard('{Enter}');
            } else {
                await userEvent.click(trigger);
            }
            const state = root.veaPendingErgonodeTypeModal;
            await expect(state.element.closest('.modal-popup')).toBeVisible();
            await expect(document.querySelectorAll('[data-role="pending-ergonode-type-modal"]')).toHaveLength(1);
            if (args.selectBeforeDestroy) {
                await userEvent.click(state.element.querySelector('[data-type="textarea"]'));
                await expect(root.querySelector('[data-side="ergo"]')).toHaveAttribute('data-type', 'textarea');
            }
            root.storyDestroy();
            await expect(state.element.isConnected).toBe(false);
            await expect(state.trigger).toBeNull();
            await expect(root.veaPendingErgonodeTypeModal).toBeUndefined();
            await userEvent.click(trigger);
            await expect(root.veaPendingErgonodeTypeModal).toBeUndefined();
            await expect(document.querySelectorAll('.vea-pending-type-modal')).toHaveLength(0);
        }
    }
};
export const ClosedModalLifecycle = { ...WorkspaceLifecycle, args: {allowTextarea: true, selectBeforeDestroy: true} };
export const KeyboardModalLifecycle = { ...WorkspaceLifecycle, args: {allowTextarea: true, keyboard: true} };
