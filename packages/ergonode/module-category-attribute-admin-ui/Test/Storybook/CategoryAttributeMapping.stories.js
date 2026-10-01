import { createNavigation } from '@ergonode-storybook/section-navigation.js';
import { createCoreModuleLoader } from '@ergonode-storybook/core-modules.js';
import {mountMappingInteractions} from '@ergonode-storybook/mapping-interactions.js';
import {checkMappingHeaders} from '@ergonode-storybook/mapping-header-interactions.js';
import {createMappingWorkspace} from '@ergonode-storybook/mapping-workspace.js';
import { expect, userEvent, waitFor, within } from 'storybook/test';
import { loadAmdModule } from '@ergonode-storybook/load-amd-module.js';

import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/attribute-mapping.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/attribute-empty-state.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/option-mapping.css';
import requirementsSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/mapping-requirements.js?raw';

const productionRequirements = loadAmdModule(
    requirementsSource,
    {},
    'Ergonode_CoreAdminUi/js/mapping-requirements'
);
const productionMapping = createCoreModuleLoader()('Ergonode_CoreAdminUi/js/attribute-mapping');
productionMapping.configureAttributeTypeCompatibility({unit: ['unit', 'decimal']});

const sourceAttributes = [
    { label: 'Opis kategorii', code: 'category_description', type: 'textarea', scope: 'local' },
    { label: 'Kolor przewodni', code: 'category_color', type: 'select', scope: 'local' },
    { label: 'Baner', code: 'category_banner', type: 'image', scope: 'global' }
];
const targetAttributes = [
    { label: 'Name', code: 'name', type: 'text', scope: 'global', required: true },
    { label: 'Is Active', code: 'is_active', type: 'select', scope: 'store view', required: true },
    { label: 'Include in Navigation Menu', code: 'include_in_menu', type: 'select', scope: 'store view', required: true },
    { label: 'Description', code: 'description', type: 'textarea', scope: 'store view' },
    { label: 'Image', code: 'image', type: 'image', scope: 'store view' },
    { label: 'Meta Description', code: 'meta_description', type: 'textarea', scope: 'store view' },
    { label: 'URL Key', code: 'url_key', type: 'text', scope: 'store view' }
];

function categoryNavigation() {
    return createNavigation({currentSection: 'category_attributes', categoryOptionsAvailable: true});
}

function card(attribute, source) {
    const node = document.createElement('div');
    const isRequired = source === 'magento' && attribute.required;
    const tooltipId = `category-attribute-requirement-${attribute.code}`;

    node.className = 'vea-attribute-card';
    Object.assign(node.dataset, {
        role: 'entity-card',
        source,
        code: attribute.code,
        type: attribute.type,
        scope: attribute.scope,
        search: `${attribute.label} ${attribute.code}`,
        mappingRequired: isRequired ? 'true' : 'false'
    });
    node.tabIndex = 0;
    node.setAttribute('role', 'group');
    node.innerHTML = [
        '<span class="vea-drag-handle" aria-hidden="true"></span>',
        '<div class="vea-card-copy"><strong>', attribute.label, '</strong>',
        '<span class="vea-card-subline"><code>', attribute.code, '</code>',
        '<span class="vea-type-badge vea-type-', attribute.type, '">', attribute.type, '</span>',
        '<span class="vea-scope">', attribute.scope, '</span>',
        isRequired ? '<span class="veui-required-badge">* Wymagane</span>' : '',
        '</span></div>',
        isRequired
            ? '<button type="button" class="veui-mapping-requirement-control" aria-label="Wymagane mapowanie atrybutu" aria-describedby="' + tooltipId + '"><span class="veui-mapping-requirement-icon" aria-hidden="true"></span><span class="veui-mapping-requirement-tooltip" id="' + tooltipId + '" role="tooltip"><strong>Wymagane mapowanie atrybutu</strong><span>Ten atrybut jest wymagany w Magento. Przypisz mu odpowiadający atrybut Ergonode. Bez tego synchronizacja nie może się rozpocząć.</span></span></button>'
            : '<button class="vea-card-toggle" aria-label="Wyłącz ' + attribute.label + '" aria-pressed="true"><span></span></button>'
    ].join('');

    return node;
}

function pair(left, right, tone = 'ok', options = false) {
    const row = document.createElement('article');

    row.className = `vea-pair-row vea-attribute-pair-row vea-status-tone-${tone}`;
    row.dataset.role = 'mapping-row';
    row.innerHTML = `<div class="vea-pair-card" data-role="pair-slot" data-side="ergo" data-code="${left.code}"><strong>${left.label}</strong><span class="vea-card-subline"><code>${left.code}</code><span class="vea-type-badge">${left.type}</span></span></div><span class="vea-link-indicator"><span></span></span><div class="vea-pair-card" data-role="pair-slot" data-side="magento" data-code="${right.code}"><strong>${right.label}</strong><span class="vea-card-subline"><code>${right.code}</code><span class="vea-type-badge">${right.type}</span></span></div>${options ? '<a class="vea-option-mapping-action" href="#">Opcje 1/3</a>' : ''}`;

    return row;
}

function fixture(args) {
    const root = createMappingWorkspace({view: 'category-attribute', state: 'empty'});
    const toolbar = root.querySelector('.veui-toolbar');
    const middle = root.querySelector('[data-role="mapping-panel"]');
    const list = middle.querySelector('[data-role="mapping-list"]');
    const availableTargetAttributes = args.manualSystemAttributes
        ? targetAttributes.filter((attribute) => !['is_active', 'include_in_menu'].includes(attribute.code))
        : targetAttributes;

    toolbar.replaceChildren(categoryNavigation());
    [['ergo', args.empty ? [] : sourceAttributes], ['magento', availableTargetAttributes]].forEach(([source, attributes]) => {
        const panel = root.querySelector(`[data-source-panel="${source}"]`);
        const search = panel.querySelector('[data-role="source-search"]');
        const sourceList = panel.querySelector('[data-role="source-list"]');

        panel.dataset.role = 'attribute-side';
        search.dataset.role = 'attribute-search';
        sourceList.replaceChildren(...attributes.map((attribute) => card(attribute, source)));
    });
    if (args.requiredMissing) {
        // Required target attributes stay visible and are marked as incomplete.
    } else if (args.draft) {
        list.append(pair(sourceAttributes[1], {label: 'Wybierz atrybut Magento', code: '', type: ''}, 'warning'));
    } else if (args.incompatible) {
        list.append(pair(sourceAttributes[2], targetAttributes[2], 'error'));
    } else if (!args.empty) {
        list.append(
            pair(sourceAttributes[0], targetAttributes[0]),
            pair(sourceAttributes[1], {label: 'Theme', code: 'category_theme', type: 'select'}, 'ok', args.options)
        );
        if (!args.manualSystemAttributes) {
            list.append(pair(sourceAttributes[2], targetAttributes[1]));
        }
    }
    productionRequirements.create(root).refresh();
    mountMappingInteractions(root);

    return root;
}

export default {
    id: 'ergo-v-008',
    title: 'Ergonode UI/Widoki/Atrybuty kategorii/ERGO-V-008 · Mapowanie atrybutów kategorii/Pełny widok',
    args: {
        empty: false,
        draft: false,
        incompatible: false,
        options: false,
        requiredMissing: false,
        manualSystemAttributes: false
    },
    argTypes: {
        empty: { control: 'boolean' },
        draft: { control: 'boolean' },
        incompatible: { control: 'boolean' },
        options: { control: 'boolean' },
        requiredMissing: { control: 'boolean' },
        manualSystemAttributes: { control: 'boolean' }
    },
    render: fixture
};

export const Playground = {};
export const Empty = { args: { empty: true } };
export const Draft = { args: { draft: true } };
export const Incompatible = { args: { incompatible: true } };
export const WithOptionMapping = { args: { options: true } };
export const RequiredMissing = {
    args: { requiredMissing: true },
    play: async ({ canvasElement }) => {
        const nameCard = canvasElement.querySelector('[data-role="entity-card"][data-source="magento"][data-code="name"]');
        const card = within(nameCard);
        const requirement = card.getByRole('button', { name: 'Wymagane mapowanie atrybutu' });
        const tooltip = card.getByRole('tooltip', {hidden: true});

        await expect(nameCard).toHaveAttribute('aria-invalid', 'true');
        await expect(card.queryByRole('button', { name: 'Wyłącz Name' })).not.toBeInTheDocument();
        await expect(tooltip).not.toBeVisible();
        await userEvent.click(requirement);
        await waitFor(() => expect(tooltip).toBeVisible());
    }
};
export const ManualSystemAttributes = {
    args: { manualSystemAttributes: true },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);

        await expect(canvas.queryByText('Is Active')).not.toBeInTheDocument();
        await expect(canvas.queryByText('Include in Navigation Menu')).not.toBeInTheDocument();
        await expect(canvasElement.querySelector('[data-role="entity-card"][data-source="magento"][data-code="name"]')).toBeVisible();
    }
};
export const MagentoManagedName = {
    args: { manualSystemAttributes: true },
    render: (args) => {
        const root = fixture(args);
        root.querySelector('[data-role="entity-card"][data-source="magento"][data-code="name"]')?.remove();

        return root;
    },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);

        await expect(canvasElement.querySelector('[data-role="entity-card"][data-source="magento"][data-code="name"]')).not.toBeInTheDocument();
        await expect(canvas.queryByText('Is Active')).not.toBeInTheDocument();
        await expect(canvas.queryByText('Include in Navigation Menu')).not.toBeInTheDocument();
    }
};
export const Search = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const searches = canvas.getAllByPlaceholderText('Szukaj: nazwa, kod...');

        await userEvent.type(searches[0], 'kolor');
        await expect(canvasElement.querySelector('[data-role="entity-card"][data-source="ergo"][data-code="category_color"]')).toBeVisible();
        await expect(canvasElement.querySelector('[data-role="entity-card"][data-source="ergo"][data-code="category_description"]')).not.toBeVisible();
        await expect(productionMapping.canMapAttributeTypes('unit', 'decimal')).toBe(true);
    }
};
export const AutoConnectWSrodkowejKolumnie = {
    play: async ({ canvasElement }) => {
        const leftPanel = canvasElement.querySelector('[data-role="source-panel"][data-source="ergo"]')
            || canvasElement.querySelectorAll('.vea-side-panel')[0];
        const middlePanel = canvasElement.querySelector('.vea-mapping-panel');
        const middle = within(middlePanel);

        await expect(leftPanel.querySelector('[data-role="auto-match"]')).not.toBeInTheDocument();
        await userEvent.click(middle.getByRole('button', {name: 'Opcje mapowania'}));
        await expect(middle.getByRole('button', {
            name: 'Automatycznie dopasuj aktywne atrybuty'
        })).toBeVisible();
    }
};

export const ScaloneNaglowki = {
    play: checkMappingHeaders
};

export const NaglowkiOpcjiKategorii = {
    render: () => {
        const root = createMappingWorkspace({view: 'option'});

        mountMappingInteractions(root);
        return root;
    },
    play: checkMappingHeaders
};

export const AutomatyczneMapowanieOpcjiKategorii = {
    render: () => {
        const root = createMappingWorkspace({view: 'option', state: 'unmapped'});
        const requests = [];
        root.querySelector('.veui-toolbar').replaceChildren(createNavigation({
            currentSection: 'category_options', categoryOptionsAvailable: true
        }));
        root.querySelectorAll('[data-source-panel]').forEach((panel) => {
            panel.dataset.role = 'attribute-side';
            panel.dataset.source = panel.dataset.sourcePanel;
            panel.querySelector('[data-role="source-search"]').dataset.role = 'attribute-search';
            panel.querySelectorAll('[data-role="source-active-toggle"]').forEach((toggle) => {
                toggle.dataset.role = 'attribute-active-toggle';
            });
        });
        const load = createCoreModuleLoader({
            'Ergonode_CoreAdminUi/js/request': {
                post: async (url, config, data) => {
                    requests.push(url);
                    if (url !== '/ergonode/category_option/autoMatch') {
                        throw new Error('Auto Connect must not save or synchronize options.');
                    }
                    const payload = JSON.parse(data.payload);
                    expect(payload.attribute_mapping_id).toBe(7);
                    return {success: true, matches: [{
                        left: payload.ergonode_options[0], right: payload.magento_options[0]
                    }]};
                }
            }
        });
        load('Ergonode_CoreAdminUi/js/option-mapping')({
            attribute_mapping_id: 7,
            urls: {auto_match: '/ergonode/category_option/autoMatch', save: '/ergonode/category_option/save'}
        }, root);
        root.autoMatchRequests = requests;
        return root;
    },
    play: async ({canvasElement}) => {
        const root = canvasElement.querySelector('[data-mapping-view="option"]');
        const menu = within(root.querySelector('.vea-mapping-panel')).getByRole('button', {name: 'Opcje mapowania'});
        await userEvent.click(menu);
        const button = root.querySelector('[data-role="auto-match"]');
        button.focus();
        await userEvent.keyboard('{Enter}');
        await waitFor(() => expect(root.querySelectorAll('[data-role="mapping-row"]')).toHaveLength(1));
        await expect(root.autoMatchRequests).toEqual(['/ergonode/category_option/autoMatch']);
        await expect(root.querySelector('[data-role="save-mapping"]')).toBeEnabled();
    }
};
