import { expect, userEvent, within } from 'storybook/test';

import { loadAmdModule, translateIdentity } from '@ergonode-storybook/load-amd-module.js';

import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/attribute-mapping.css';
import '../../view/adminhtml/web/css/template-admin.css';
import buttonsSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/buttons.js?raw';
import entityOptionsSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/entity-options.js?raw';
import searchSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/search.js?raw';
import textSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/text.js?raw';
import visibilityToggleSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/visibility-toggle.js?raw';
import workspaceSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/workspace.js?raw';
import sourceOptionsSource from '../../view/adminhtml/web/js/template-source-options.js?raw';

const dependencies = { 'mage/translate': translateIdentity };
const buttons = loadAmdModule(buttonsSource, dependencies, 'Ergonode_CoreAdminUi/js/buttons');
const entityOptions = loadAmdModule(entityOptionsSource, dependencies, 'Ergonode_CoreAdminUi/js/entity-options');
const text = loadAmdModule(textSource, {}, 'Ergonode_CoreAdminUi/js/text');
const searchBehavior = loadAmdModule(
    searchSource,
    { 'Ergonode_CoreAdminUi/js/text': text },
    'Ergonode_CoreAdminUi/js/search'
);
const visibilityToggle = loadAmdModule(
    visibilityToggleSource,
    { 'Ergonode_CoreAdminUi/js/buttons': buttons },
    'Ergonode_CoreAdminUi/js/visibility-toggle'
);
const workspace = loadAmdModule(workspaceSource, dependencies, 'Ergonode_CoreAdminUi/js/workspace');
const sourceOptions = loadAmdModule(
    sourceOptionsSource,
    {
        'Ergonode_CoreAdminUi/js/entity-options': entityOptions,
        'Ergonode_CoreAdminUi/js/visibility-toggle': visibilityToggle,
        'mage/translate': translateIdentity
    },
    'Ergonode_TemplateAdminUi/js/template-source-options'
);

function sourceCard(code, label, active) {
    const card = document.createElement('article');

    card.className = `vea-attribute-card vet-map-card vet-template-card${active ? '' : ' is-inactive'}`;
    card.dataset.role = 'entity-card';
    card.dataset.code = code;
    card.dataset.label = label;
    card.innerHTML = `
        <span class="vea-drag-handle" aria-hidden="true"></span>
        <span class="vea-card-copy"><strong>${label}</strong><small>${code}</small></span>`;
    card.hidden = !active;

    return card;
}

function sourcePanel({ cards, menuLabel, role, searchRole, title }) {
    const panel = document.createElement('section');
    const head = document.createElement('div');
    const heading = Object.assign(document.createElement('strong'), { textContent: title });
    const tools = document.createElement('div');
    const searchControl = document.createElement('label');
    const list = document.createElement('div');

    panel.className = 'veui-panel vet-panel vet-source-panel vea-panel vea-side-panel';
    panel.dataset.role = role;
    panel.dataset.sourceOptionsLabel = menuLabel;
    panel.dataset.showExcludedHint = 'Show excluded templates and attribute sets';
    panel.dataset.hideExcludedHint = 'Hide excluded templates and attribute sets';
    head.className = 'veui-panel-head veui-panel-head-with-tools vea-panel-head vet-panel-head';
    heading.className = 'veui-panel-title';
    head.append(heading);
    tools.className = 'veui-panel-head-tools vea-side-tools vet-side-tools';
    searchControl.className = 'veui-search veui-search-expandable vet-search';
    searchControl.innerHTML = `
        <span class="veui-search-icon" aria-hidden="true"></span>
        <input type="search" data-role="${searchRole}" aria-label="Search ${title}" placeholder="Szukaj...">`;
    const placeholder = document.createElement('span');

    placeholder.className =
        'veui-entity-options veui-source-options vet-source-options vet-options-placeholder';
    placeholder.dataset.role = 'entity-options-placeholder';
    placeholder.setAttribute('aria-hidden', 'true');
    placeholder.innerHTML = `
        <span class="veui-entity-options-placeholder-trigger vet-options-placeholder-trigger">
            <span class="veui-entity-options-icon"></span>
        </span>`;
    tools.append(searchControl, placeholder);
    list.className = 'vea-attribute-list vet-card-list vet-template-source-list';
    list.dataset.role = role === 'template-drop-source'
        ? 'unmapped-template-list'
        : 'unmapped-set-list';
    list.append(...cards);
    head.append(tools);
    panel.append(head, list);

    return { list, panel };
}

function sortPanel(panel, list) {
    const toggle = panel.querySelector('[data-role="source-sort-toggle"]');
    const direction = panel.querySelector('[data-role="source-sort-direction"]');
    const value = toggle?.dataset.sortValue || 'label';

    searchBehavior.sort(list, '[data-role="entity-card"]', {
        direction: direction?.dataset.direction || 'asc',
        value: (card) => card.dataset[value] || ''
    });
}

function bindSort(scope, source, status, secondary) {
    searchBehavior.bindSort(scope, source.panel, {
        toggleSelector: '[data-role="source-sort-toggle"]',
        directionSelector: '[data-role="source-sort-direction"]',
        directionLabelSelector: '.veui-entity-options-action-label',
        labelSelector: '.veui-entity-options-action-label',
        defaultValue: 'label',
        values: ['label', 'code'],
        options: {
            label: { label: 'Nazwa', ariaLabel: 'Sortuj po nazwie' },
            code: secondary
        },
        ascLabel: 'Sortuj rosnąco',
        ascOptionLabel: 'Góra',
        descLabel: 'Sortuj malejąco',
        descOptionLabel: 'Dół',
        update: () => {
            sortPanel(source.panel, source.list);
            status.textContent = `Posortowano ${source.panel.dataset.role}`;
        }
    });
    sortPanel(source.panel, source.list);
}

function renderPanel(args) {
    const frame = document.createElement('div');
    const root = document.createElement('div');
    const layout = document.createElement('div');
    const status = Object.assign(document.createElement('p'), { textContent: 'Wybierz akcję' });
    const templates = sourcePanel({
        cards: [
            sourceCard('bag', 'Bag', true),
            sourceCard('bottom', 'Bottom', !args.hasExcluded)
        ],
        menuLabel: 'Template actions: Ergonode',
        role: 'template-drop-source',
        searchRole: 'template-search',
        title: 'Templates'
    });
    const attributeSets = sourcePanel({
        cards: [sourceCard('12', 'Default', true), sourceCard('4', 'Fashion', true)],
        menuLabel: 'Attribute set actions: Magento',
        role: 'attribute-set-drop-source',
        searchRole: 'attribute-set-search',
        title: 'Attribute sets'
    });

    frame.style.minHeight = '480px';
    frame.style.padding = '24px';
    root.className = 'veui-workspace vet-admin';
    layout.style.display = 'grid';
    layout.style.gap = '20px';
    layout.style.gridTemplateColumns = 'repeat(2, minmax(300px, 1fr))';
    layout.append(templates.panel, attributeSets.panel);
    status.setAttribute('role', 'status');
    status.style.margin = '12px 14px';
    root.append(layout, status);
    frame.append(root);

    sourceOptions.initialize(root, args.hasExcluded);
    workspace.mount(root, (scope) => {
        entityOptions.bind(scope, root);
        scope.delegate('click', '[data-role="visibility-toggle"]', (event, button) => {
            const visible = visibilityToggle.toggle(button);

            templates.list.querySelectorAll('.is-inactive').forEach((card) => {
                card.hidden = !visible;
            });
            status.textContent = visible ? 'Pokazano wykluczone' : 'Ukryto wykluczone';
        });
        scope.delegate('click', '[data-role="refresh-templates"]', () => {
            status.textContent = 'Uruchomiono Refresh';
        });
        bindSort(scope, templates, status, { label: 'Kod', ariaLabel: 'Sortuj po kodzie' });
        bindSort(scope, attributeSets, status, { label: 'ID', ariaLabel: 'Sortuj po ID' });
    });

    root.querySelectorAll('[data-role="entity-options"]').forEach((menu) => {
        menu.open = args.open;
    });

    return frame;
}

const meta = {
    id: 'ergo-v-058-03',
    title: 'Ergonode UI/Widoki/Szablony/ERGO-V-058 · Mapowanie szablonów/ERGO-V-058.03 · Opcje kolumn bocznych',
    tags: ['autodocs'],
    render: renderPanel,
    args: {
        hasExcluded: true,
        open: false
    },
    argTypes: {
        hasExcluded: { control: 'boolean' },
        open: { control: 'boolean' }
    }
};

export default meta;

export const Playground = {};

export const Stany = {
    render: () => {
        const grid = document.createElement('div');

        grid.className = 'veui-storybook-grid';
        grid.append(
            renderPanel({ hasExcluded: false, open: true }),
            renderPanel({ hasExcluded: true, open: true })
        );

        return grid;
    }
};

export const ObslugaMysza = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const trigger = canvas.getByRole('button', { name: 'Template actions: Ergonode' });

        await userEvent.click(trigger);
        const sort = canvas.getAllByRole('button', { name: 'Sortuj po nazwie' })[0];

        await userEvent.click(sort);
        await expect(sort).toHaveAttribute('aria-label', 'Sortuj po kodzie');
        await expect(canvas.getByRole('status')).toHaveTextContent('Posortowano template-drop-source');
        await expect(canvas.getByRole('button', { name: 'Attribute set actions: Magento' })).toBeVisible();
    }
};

export const ObslugaKlawiatura = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const trigger = canvas.getByRole('button', { name: 'Template actions: Ergonode' });
        const menu = trigger.closest('details');

        trigger.focus();
        await userEvent.keyboard('{Enter}');
        await expect(menu).toHaveAttribute('open');
        const sort = canvas.getAllByRole('button', { name: 'Sortuj po nazwie' })[0];

        sort.focus();
        await userEvent.keyboard('{Enter}');
        await expect(sort).toHaveAttribute('aria-label', 'Sortuj po kodzie');
        await expect(menu).not.toHaveAttribute('open');
        trigger.focus();
        await userEvent.keyboard('{Enter}');
        await userEvent.keyboard('{Escape}');
        await expect(menu).not.toHaveAttribute('open');
        await expect(trigger).toHaveFocus();
    }
};
