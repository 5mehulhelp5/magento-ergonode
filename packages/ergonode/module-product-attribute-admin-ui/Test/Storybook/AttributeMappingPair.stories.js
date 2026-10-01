import { createCoreModuleLoader } from '@ergonode-storybook/core-modules.js';
import { expect, userEvent, within } from 'storybook/test';

import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/attribute-mapping.css';

const loadCore = createCoreModuleLoader();
const productionMapping = loadCore('Ergonode_CoreAdminUi/js/attribute-mapping');
const mappingElements = loadCore('Ergonode_CoreAdminUi/js/mapping-elements');

productionMapping.configureAttributeTypeCompatibility({
    file: ['file', 'text', 'textarea'],
    image: ['image', 'file', 'text', 'textarea'],
    multiselect: ['multiselect', 'text', 'textarea'],
    price: ['price', 'decimal', 'text', 'textarea'],
    select: ['select', 'text', 'textarea', 'multiselect', 'boolean'],
    text: ['text', 'textarea', 'select', 'multiselect'],
    textarea: ['textarea', 'text', 'select', 'multiselect'],
    unit: ['unit', 'decimal', 'text', 'textarea']
});
productionMapping.configureMagentoAttributeTypeConstraints({
    name: ['text'],
    url_key: ['text'],
    price: ['price']
});

function pairHtml({validationMessage = '', validationTone = 'error', mapped = 3, rightFilled = true, tone = 'ok', total = 5, withOptions = true} = {}) {
    const hasOptionMapping = rightFilled && withOptions;

    return `
        <article class="vea-pair-row vea-attribute-pair-row vea-status-tone-${tone}${hasOptionMapping ? ' has-option-mapping' : ''}"
                 data-role="mapping-row"
                 data-validation-message="${validationMessage}" data-validation-code="color"
                 data-validation-tone="${validationTone}">
            <div class="vea-pair-card"
                 data-role="pair-slot"
                 data-side="ergo"
                 data-code="color"
                 data-label="Kolor">
                <strong>Kolor</strong>
                <span class="vea-card-subline"><code>color</code><span class="vea-type-badge vea-type-select">select</span></span>
            </div>
            <button type="button"
                    class="vea-link-indicator vea-unlink-mapping"
                    data-role="unlink-mapping"
                    title="Usuń mapowanie atrybutów"
                    aria-label="Usuń mapowanie atrybutów">
                <span aria-hidden="true"></span>
            </button>
            <div class="vea-pair-card${rightFilled ? '' : ' is-empty'}${hasOptionMapping ? ' has-option-mapping-action' : ''}"
                 data-role="pair-slot"
                 data-side="magento"
                 data-code="${rightFilled ? 'color' : ''}"
                 data-label="${rightFilled ? 'Color' : ''}">
                ${rightFilled ? `
                    <strong>Color</strong>
                    <span class="vea-card-subline"><code>color</code><span class="vea-type-badge vea-type-select">select</span></span>` : `
                    <strong>Wymagany typ</strong>
                    <span class="vea-type-badge vea-type-select">select</span>`}
                ${hasOptionMapping ? `
                    <div class="vea-option-map-entry" data-role="option-mapping-entry">
                        <span class="vea-option-map-progress" title="Zmapowano ${mapped} z ${total} opcji">${mapped}/${total}</span>
                        <button type="button" class="vea-option-map-action" aria-label="Przejdź do mapowania opcji">
                            <span aria-hidden="true"></span>
                        </button>
                    </div>` : ''}
            </div>
            <div class="vea-pair-message" data-role="pair-message" ${validationMessage ? '' : 'hidden'}>${validationMessage}</div>
        </article>`;
}

function fixture(args) {
    const root = document.createElement('section');

    root.className = 'veui-panel vea-panel vea-mapping-panel vea-mapping';
    root.style.margin = '0 auto';
    root.style.maxWidth = '720px';
    root.innerHTML = `<div class="vea-pair-list" data-role="mapping-list">${pairHtml(args)}</div>`;
    root.querySelector('[data-role="unlink-mapping"]').addEventListener('click', (event) => {
        const row = event.currentTarget.closest('[data-role="mapping-row"]');

        productionMapping.unlinkMappingRow(root, row);
    });

    return root;
}

function allStates() {
    const matrix = document.createElement('div');

    matrix.style.display = 'grid';
    matrix.style.gap = '18px';
    matrix.append(
        fixture({mapped: 3, rightFilled: true, tone: 'ok', total: 5, withOptions: true}),
        fixture({rightFilled: false, tone: 'warning', withOptions: false}),
        fixture({rightFilled: true, tone: 'error', withOptions: false}),
        fixture({tone: 'error', withOptions: false, validationMessage: 'Adapter: category_reference. Import: niedostępny — atrybut pomijany. Publikacja: dostępny.'}),
        fixture({tone: 'error', withOptions: false, validationMessage: 'Adapter: category_reference. Import: dostępny. Publikacja: niedostępny — atrybut pomijany.'})
    );

    return matrix;
}

function constrainedPairHtml(code, label, type) {
    const compatibleTypes = productionMapping.compatibleAttributeTypes('ergo', type, code);

    return `
        <article class="vea-pair-row vea-attribute-pair-row vea-status-tone-warning"
                 data-role="mapping-row">
            <div class="vea-pair-card is-empty" data-role="pair-slot" data-side="ergo">
                <strong>Zgodne typy</strong>
                <span class="vea-compatible-types" data-role="slot-hint">
                    ${compatibleTypes.map(mappingElements.typeBadgeHtml).join('')}
                </span>
            </div>
            <button type="button" class="vea-link-indicator vea-unlink-mapping"
                    title="Usuń mapowanie atrybutów" aria-label="Usuń mapowanie atrybutów">
                <span aria-hidden="true"></span>
            </button>
            <div class="vea-pair-card" data-role="pair-slot" data-side="magento"
                 data-code="${code}" data-type="${type}">
                <strong>${label}</strong>
                <span class="vea-card-subline">
                    <code>${code}</code>
                    ${mappingElements.typeBadgeHtml(type)}
                </span>
            </div>
        </article>`;
}

function systemAttributeConstraints() {
    const root = document.createElement('section');

    root.className = 'veui-panel vea-panel vea-mapping-panel vea-mapping';
    root.style.margin = '0 auto';
    root.style.maxWidth = '720px';
    root.innerHTML = `
        <div class="vea-pair-list" data-role="mapping-list">
            ${constrainedPairHtml('name', 'Name', 'text')}
            ${constrainedPairHtml('url_key', 'URL Key', 'text')}
            ${constrainedPairHtml('price', 'Price', 'price')}
        </div>`;

    return root;
}

export default {
    id: 'ergo-v-070-04',
    title: 'Ergonode UI/Widoki/Atrybuty produktów/ERGO-V-070 · Mapowanie atrybutów produktów/ERGO-V-070.04 · Para mapowania atrybutów',
    args: {mapped: 3, rightFilled: true, tone: 'ok', total: 5, withOptions: true},
    argTypes: {
        mapped: {control: {min: 0, type: 'number'}},
        rightFilled: {control: 'boolean'},
        tone: {control: 'select', options: ['ok', 'warning', 'error']},
        total: {control: {min: 0, type: 'number'}},
        withOptions: {control: 'boolean'}
    },
    render: fixture
};

export const Playground = {};
export const AllStates = {render: allStates};
export const OptionActionFocus = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const action = canvas.getByRole('button', {name: 'Przejdź do mapowania opcji'});

        canvas.getByRole('button', {name: 'Usuń mapowanie atrybutów'}).focus();
        await userEvent.tab();
        await expect(action).toHaveFocus();
    }
};
export const SystemAttributeConstraints = {render: systemAttributeConstraints};
export const KeyboardUnlink = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const unlink = canvas.getByRole('button', {name: 'Usuń mapowanie atrybutów'});

        unlink.focus();
        await userEvent.keyboard('{Enter}');
        await expect(canvas.queryByRole('article')).not.toBeInTheDocument();
    }
};

export const MissingAdapter = {
    args: {
        tone: 'error',
        withOptions: false,
        validationMessage: 'Adapter: category_reference. Import: niedostępny — atrybut pomijany. Publikacja: niedostępny — atrybut pomijany.'
    },
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText(/Adapter: category_reference/)).toBeVisible();
        await userEvent.click(canvas.getByRole('button', {name: 'Usuń mapowanie atrybutów'}));
        await expect(canvas.queryByRole('article')).not.toBeInTheDocument();
    }
};
