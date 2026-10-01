import { expect, userEvent, within } from 'storybook/test';
import { loadAmdModule, translateIdentity } from '@ergonode-storybook/load-amd-module.js';

import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/attribute-mapping.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-actions.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/attribute-empty-state.css';
import '@ergonode-modules/AttributePublisherAdminUi/view/adminhtml/web/css/pending-type-picker.css';
import textSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/text.js?raw';
import mappingElementsSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/mapping-elements.js?raw';
import publisherSource from '@ergonode-modules/AttributePublisherAdminUi/view/adminhtml/web/js/ergonode-attribute-publisher-mapping.js?raw';

const text = loadAmdModule(textSource, {}, 'Ergonode_CoreAdminUi/js/text');
const mappingElements = loadAmdModule(mappingElementsSource, {
    'Ergonode_CoreAdminUi/js/text': text,
    'mage/translate': translateIdentity
}, 'Ergonode_CoreAdminUi/js/mapping-elements');
const publisher = loadAmdModule(publisherSource, {
    jquery: () => ({}),
    'Magento_Ui/js/modal/modal': () => {},
    'mage/translate': translateIdentity,
    'Ergonode_CoreAdminUi/js/messages': { error: () => {} },
    'Ergonode_CoreAdminUi/js/buttons': { setBusy: () => {} },
    'Ergonode_CoreAdminUi/js/request': { post: () => Promise.resolve({}) },
    'Ergonode_CoreAdminUi/js/bulk-publish-progress': () => ({}),
    'Ergonode_CoreAdminUi/js/text': text,
    'Ergonode_CoreAdminUi/js/mapping-elements': mappingElements,
    'Ergonode_CoreAdminUi/js/attribute-mapping': {
        canMapAttributeTypes: (ergonodeType, magentoType) =>
            (ergonodeType === 'numeric' && magentoType === 'decimal') || ergonodeType === magentoType
    }
}, 'Ergonode_AttributePublisherAdminUi/js/ergonode-attribute-publisher-mapping');

function emptySourcePanelHtml() {
    return [
        '<section class="veui-panel vea-panel vea-side-panel" data-role="source-panel" data-source="ergo">',
        '<div class="veui-panel-head vea-panel-head"><strong class="veui-panel-title">',
        'Atrybuty kategorii</strong><span class="veui-count">0</span></div>',
        '<div class="vea-attribute-list"><div class="vea-attribute-empty-state" ',
        'data-role="attribute-empty-state"><p>Brak pobranych atrybutów Ergonode.</p>',
        '<div class="vea-attribute-empty-actions" data-role="attribute-empty-actions">',
        '<button type="button" class="veui-button vea-attribute-empty-refresh" ',
        'data-role="refresh-ergonode"><span>Odśwież</span></button>',
        '</div></div></div></section>'
    ].join('');
}

function mappingRowHtml(args) {
    return [
        '<article class="vea-pair-row vea-attribute-pair-row" data-role="mapping-row">',
        args.alreadyMapped
            ? '<div class="vea-pair-card" data-role="pair-slot" data-side="ergo" data-code="score" data-label="Score" data-type="numeric" data-scope="local"><strong>Score</strong></div>'
            : '<div class="vea-pair-card is-empty" data-role="pair-slot" data-side="ergo"><strong>Wymagany typ</strong></div>',
        '<span class="vea-link-indicator" aria-hidden="true"><span></span></span>',
        '<div class="vea-pair-card" data-role="pair-slot" data-side="magento" data-code="score" data-label="Score" data-type="decimal" data-scope="store view"><strong>Score</strong><span class="vea-card-subline"><code>score</code><span class="vea-type-badge vea-type-decimal">decimal</span></span></div>',
        '</article>'
    ].join('');
}

function fixture(args) {
    const root = document.createElement('div');

    root.id = 'ergonode-category-attribute-mapping';
    root.className = 'veui-workspace vea-mapping';
    root.innerHTML = [
        '<div class="veui-toolbar vea-viewbar"><strong>Atrybuty kategorii</strong></div>',
        '<div class="veui-layout vea-shell">',
        args.emptySource ? emptySourcePanelHtml() : '',
        '<section class="veui-panel vea-panel vea-mapping-panel">',
        '<div class="veui-panel-head vea-panel-head"><strong class="veui-panel-title">Mapowanie atrybutów kategorii</strong></div>',
        '<div class="vea-pair-list">',
        args.emptySource ? '' : mappingRowHtml(args),
        '</div></section></div>'
    ].join('');
    publisher({ mode: 'attribute', language_mapping: { active: true } }, root);

    return root;
}

export default {
    id: 'ergo-v-008-01',
    title: 'Ergonode UI/Widoki/Atrybuty kategorii/ERGO-V-008 · Mapowanie atrybutów kategorii/ERGO-V-008.01 · Publikacja atrybutów kategorii',
    args: { alreadyMapped: false, emptySource: false },
    argTypes: {
        alreadyMapped: { control: 'boolean' },
        emptySource: { control: 'boolean' }
    },
    render: fixture
};

export const Playground = {};
export const EmptyErgonodeSource = {
    args: { emptySource: true },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);

        await expect(canvas.getByText('Brak pobranych atrybutów Ergonode.')).toBeVisible();
        await expect(canvas.getByRole('button', { name: 'Odśwież' })).toBeVisible();
        await expect(canvas.queryByRole('button', {
            name: 'Utwórz atrybut w Ergonode z Magento'
        })).not.toBeInTheDocument();
        await expect(canvasElement.querySelector('.vea-empty-create-ergonode')).not.toBeInTheDocument();
    }
};
export const AlreadyMapped = {
    args: { alreadyMapped: true },
    play: async ({ canvasElement }) => {
        await expect(within(canvasElement).queryByRole('button', {
            name: 'Utwórz atrybut w Ergonode z Magento'
        })).not.toBeInTheDocument();
    }
};
export const CreateWithMouse = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const button = canvas.getByRole('button', { name: 'Utwórz atrybut w Ergonode z Magento' });

        await userEvent.click(button);
        const slot = canvasElement.querySelector('[data-role="pair-slot"][data-side="ergo"]');
        await expect(slot).toHaveAttribute('data-code', 'score');
        await expect(slot).toHaveAttribute('data-type', 'numeric');
        await expect(slot).toHaveAttribute('data-pending-create', '1');
    }
};
export const CreateWithKeyboard = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);

        await userEvent.tab();
        const button = canvas.getByRole('button', { name: 'Utwórz atrybut w Ergonode z Magento' });
        await expect(button).toHaveFocus();
        await userEvent.keyboard('{Enter}');
        await expect(canvasElement.querySelector('[data-side="ergo"]')).toHaveAttribute('data-type', 'numeric');
    }
};
