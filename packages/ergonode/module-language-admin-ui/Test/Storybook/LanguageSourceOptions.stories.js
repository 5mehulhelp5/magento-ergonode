import { expect, userEvent, within } from 'storybook/test';

import { loadAmdModule, translateIdentity } from '@ergonode-storybook/load-amd-module.js';

import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/attribute-mapping.css';
import '../../view/adminhtml/web/css/language-mapping.css';
import buttonsSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/buttons.js?raw';
import entityOptionsSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/entity-options.js?raw';
import snapshotRemovalSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/snapshot-removal.js?raw';
import visibilityToggleSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/visibility-toggle.js?raw';
import workspaceSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/workspace.js?raw';
import sourceOptionsSource from '../../view/adminhtml/web/js/language-source-options.js?raw';

const dependencies = { 'mage/translate': translateIdentity };
const buttons = loadAmdModule(buttonsSource, dependencies, 'Ergonode_CoreAdminUi/js/buttons');
const entityOptions = loadAmdModule(entityOptionsSource, dependencies, 'Ergonode_CoreAdminUi/js/entity-options');
const visibilityToggle = loadAmdModule(
    visibilityToggleSource,
    { 'Ergonode_CoreAdminUi/js/buttons': buttons },
    'Ergonode_CoreAdminUi/js/visibility-toggle'
);
const workspace = loadAmdModule(workspaceSource, dependencies, 'Ergonode_CoreAdminUi/js/workspace');
const snapshotRemoval = loadAmdModule(snapshotRemovalSource, {
    'Ergonode_CoreAdminUi/js/request': {
        post: async () => ({ success: true })
    },
    'Ergonode_CoreAdminUi/js/buttons': buttons,
    'Ergonode_CoreAdminUi/js/entity-options': entityOptions,
    'mage/translate': translateIdentity
}, 'Ergonode_CoreAdminUi/js/snapshot-removal');
const sourceOptions = loadAmdModule(
    sourceOptionsSource,
    {
        'Ergonode_CoreAdminUi/js/entity-options': entityOptions,
        'Ergonode_CoreAdminUi/js/visibility-toggle': visibilityToggle,
        'mage/translate': translateIdentity
    },
    'Ergonode_LanguageAdminUi/js/language-source-options'
);

function languageCard(code, label, active) {
    const card = document.createElement('article');

    card.className = `vea-attribute-card vel-source-card${active ? '' : ' is-inactive'}`;
    card.dataset.role = 'entity-card';
    card.dataset.source = 'ergo';
    card.dataset.code = code;
    card.dataset.label = label;
    card.innerHTML = `
        <span class="vea-drag-handle" aria-hidden="true"></span>
        <div class="vea-card-copy">
            <strong>${label}</strong>
            <span class="vea-card-subline"><code>${code}</code></span>
        </div>
        <button type="button" class="vea-card-toggle" data-role="source-active-toggle"
                aria-label="${active ? 'Exclude' : 'Include'}"
                aria-pressed="${active ? 'true' : 'false'}">
            <span aria-hidden="true"></span>
        </button>
        <span class="veui-entity-options vel-options-placeholder"
              data-role="entity-options-placeholder" aria-hidden="true">
            <span class="veui-entity-options-placeholder-trigger vel-options-placeholder-trigger">
                <span class="veui-entity-options-icon"></span>
            </span>
        </span>`;
    card.hidden = !active;
    card.classList.toggle('is-filter-hidden', !active);

    return card;
}

function storeViewCard(code, label, active) {
    const card = languageCard(code, label, active);

    card.dataset.source = 'magento';
    card.querySelector('[data-role="source-active-toggle"]').setAttribute(
        'aria-label',
        active ? 'Exclude Store View' : 'Include Store View'
    );
    card.querySelector('[data-role="entity-options-placeholder"]')?.remove();

    return card;
}

function createMappingOptions() {
    const menu = entityOptions.create({
        menuLabel: 'Opcje mapowania',
        actions: [{
            role: 'auto-match',
            className: 'vea-auto-match',
            iconClass: 'vea-auto-match-icon',
            label: 'Auto Connect',
            title: 'Automatically connect languages to Store View locales',
            ariaLabel: 'Automatically connect languages to Store View locales',
            disabled: true
        }]
    });

    menu.classList.add('veui-source-options', 'vel-mapping-options');
    menu.dataset.languageMappingOptions = '1';
    menu.querySelector('[data-role="auto-match"] .veui-entity-options-action-label')
        .dataset.role = 'auto-match-label';

    return menu;
}

function renderPanel(args) {
    const frame = document.createElement('div');
    const root = document.createElement('div');
    const layout = document.createElement('div');
    const panel = document.createElement('section');
    const mappingPanel = document.createElement('section');
    const head = document.createElement('div');
    const title = Object.assign(document.createElement('strong'), {
        textContent: 'Languages'
    });
    const count = Object.assign(document.createElement('span'), {
        textContent: '2'
    });
    const tools = document.createElement('div');
    const search = document.createElement('label');
    const list = document.createElement('div');
    const optionsPlaceholder = document.createElement('span');
    const status = Object.assign(document.createElement('p'), {
        textContent: 'Wybierz akcję'
    });
    const mappingOptions = createMappingOptions();

    frame.style.height = '480px';
    frame.style.padding = '24px';
    root.className = 'veui-workspace vea-mapping vel-mapping';
    root.style.height = '100%';
    layout.className = 'veui-layout vea-shell';
    panel.className = 'veui-panel vea-panel vea-side-panel';
    panel.dataset.role = 'source-panel';
    panel.dataset.sourcePanel = 'ergo';
    panel.dataset.canRefresh = args.canRefresh ? '1' : '0';
    panel.dataset.sourceOptionsLabel = 'Language actions: Ergonode';
    panel.dataset.showExcludedHint = 'Show excluded languages';
    panel.dataset.hideExcludedHint = 'Hide excluded languages';
    panel.style.height = '100%';
    panel.style.margin = '0 auto';
    panel.style.maxWidth = '380px';
    head.className = 'veui-panel-head vea-panel-head';
    title.className = 'veui-panel-title';
    count.className = 'veui-count vea-count';
    head.append(title, count);
    tools.className = 'veui-tools veui-tools-inline vea-side-tools vel-side-tools';
    search.className = 'veui-search';
    search.innerHTML = `
        <span class="veui-search-icon" aria-hidden="true"></span>
        <input type="search" data-role="source-search" placeholder="Szukaj języka...">`;
    optionsPlaceholder.className =
        'veui-entity-options veui-source-options vel-source-options vel-options-placeholder';
    optionsPlaceholder.dataset.role = 'entity-options-placeholder';
    optionsPlaceholder.setAttribute('aria-hidden', 'true');
    optionsPlaceholder.innerHTML = `
        <span class="veui-entity-options-placeholder-trigger vel-options-placeholder-trigger">
            <span class="veui-entity-options-icon"></span>
        </span>`;
    tools.append(search, optionsPlaceholder);
    list.className = 'vea-attribute-list vel-source-list';
    list.append(languageCard('pl_PL', 'Polski', true), languageCard('en_GB', 'English (UK)', !args.hasExcluded));
    status.setAttribute('role', 'status');
    status.style.margin = '12px 14px';
    panel.append(head, tools, list, status);
    mappingPanel.className = 'veui-panel vea-panel vea-pairs-panel';
    mappingPanel.dataset.role = 'mapping-panel';
    mappingPanel.innerHTML = [
        '<div class="veui-panel-head vea-panel-head">',
        '<strong class="veui-panel-title">Mapowanie języków</strong>',
        '<span class="veui-count vea-count">1</span>',
        '</div>'
    ].join('');
    const mappingTools = document.createElement('div');

    mappingTools.className = 'veui-tools veui-tools-inline vel-middle-tools';
    mappingTools.append(mappingOptions);
    mappingPanel.append(mappingTools);
    layout.append(panel, mappingPanel);
    root.append(layout);
    frame.append(root);

    if (args.hydrate !== false) {
        sourceOptions.initialize(root);
        sourceOptions.sync(root, args.autoMatchCount);
        list.querySelectorAll('[data-role="entity-card"]').forEach((card) => {
            const toggle = card.querySelector('[data-role="source-active-toggle"]');
            const active = toggle.getAttribute('aria-pressed') === 'true';

            snapshotRemoval.enhance(card, {
                active,
                mappingAction: true,
                menuLabel: `Opcje języka: ${card.dataset.label}`,
                snapshot: {
                    code: card.dataset.code,
                    label: card.dataset.label
                },
                toggle
            });
            card.querySelector('[data-role="entity-options-placeholder"]')?.remove();
        });
    }
    workspace.mount(root, (scope) => {
        entityOptions.bind(scope, root);
        scope.delegate('click', '[data-role="visibility-toggle"]', (event, button) => {
            const visible = visibilityToggle.toggle(button);

            list.querySelectorAll('.is-inactive').forEach((card) => {
                card.hidden = !visible;
                card.classList.toggle('is-filter-hidden', !visible);
            });
            status.textContent = visible ? 'Pokazano wykluczone' : 'Ukryto wykluczone';
        });
        scope.delegate('click', '[data-role="auto-match"]', () => {
            status.textContent = 'Uruchomiono Auto Connect';
        });
        scope.delegate('click', '[data-role="refresh-ergonode"]', () => {
            status.textContent = 'Uruchomiono Refresh';
        });
    });

    const menu = root.querySelector('[data-language-source-options]');

    if (menu) {
        menu.open = args.open;
    }
    mappingOptions.open = args.open;

    return frame;
}

function renderStoreViewPanel({hasExcluded}) {
    const frame = document.createElement('div');
    const root = document.createElement('div');
    const panel = document.createElement('section');

    frame.style.height = '480px';
    frame.style.padding = '24px';
    root.className = 'veui-workspace vea-mapping vel-mapping';
    root.style.height = '100%';
    panel.className = 'veui-panel vea-panel vea-side-panel';
    panel.dataset.role = 'source-panel';
    panel.dataset.sourcePanel = 'magento';
    panel.dataset.sourceOptionsLabel = 'Language actions: Magento';
    panel.dataset.showExcludedHint = 'Show excluded Store Views';
    panel.dataset.hideExcludedHint = 'Hide excluded Store Views';
    panel.style.height = '100%';
    panel.style.margin = '0 auto';
    panel.style.maxWidth = '380px';
    panel.innerHTML = `
        <div class="veui-panel-head vea-panel-head">
            <strong class="veui-panel-title">Languages</strong>
            <span class="veui-count vea-count">2</span>
        </div>
        <div class="veui-tools veui-tools-inline vea-side-tools vel-side-tools">
            <label class="veui-search">
                <span class="veui-search-icon" aria-hidden="true"></span>
                <input type="search" data-role="source-search" placeholder="Szukaj Store View...">
            </label>
            <span class="veui-entity-options veui-source-options vel-source-options vel-options-placeholder"
                  data-role="entity-options-placeholder" aria-hidden="true">
                <span class="veui-entity-options-placeholder-trigger vel-options-placeholder-trigger">
                    <span class="veui-entity-options-icon"></span>
                </span>
            </span>
        </div>`;
    const list = document.createElement('div');

    list.className = 'vea-attribute-list vel-source-list';
    list.append(
        storeViewCard('default', 'Default Store View', true),
        storeViewCard('outlet', 'Outlet Store View', !hasExcluded)
    );
    panel.append(list);
    root.append(panel);
    frame.append(root);

    sourceOptions.initialize(root);
    sourceOptions.sync(root);
    workspace.mount(root, (scope) => {
        entityOptions.bind(scope, root);
        scope.delegate('click', '[data-role="visibility-toggle"]', (event, button) => {
            const visible = visibilityToggle.toggle(button);

            list.querySelectorAll('.is-inactive').forEach((card) => {
                card.hidden = !visible;
                card.classList.toggle('is-filter-hidden', !visible);
            });
        });
    });

    return frame;
}

const meta = {
    id: 'ergo-v-040-02',
    title: 'Ergonode UI/Widoki/Języki/ERGO-V-040 · Edycja i odświeżanie języków/ERGO-V-040.02 · Opcje kolumn mapowania',
    tags: ['autodocs'],
    render: renderPanel,
    args: {
        canRefresh: true,
        hasExcluded: true,
        autoMatchCount: 3,
        hydrate: true,
        open: false
    },
    argTypes: {
        canRefresh: { control: 'boolean' },
        hasExcluded: { control: 'boolean' },
        autoMatchCount: { control: { type: 'number', min: 0, step: 1 } },
        hydrate: { control: 'boolean' },
        open: { control: 'boolean' }
    }
};

export default meta;

export const Playground = {};

export const PierwszyRenderBezPrzeskoku = {
    args: { hydrate: false }
};

export const Stany = {
    render: () => {
        const grid = document.createElement('div');

        grid.className = 'veui-storybook-grid';
        grid.append(
            renderPanel({
                canRefresh: true,
                hasExcluded: false,
                autoMatchCount: 0,
                open: true
            }),
            renderPanel({
                canRefresh: true,
                hasExcluded: true,
                autoMatchCount: 4,
                open: true
            })
        );

        return grid;
    }
};

export const BrakWykluczonych = {
    args: { hasExcluded: false, open: true },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);

        await expect(canvasElement.querySelector('[data-role="visibility-toggle"]')).toBeDisabled();
    }
};

export const BrakAutomatycznychPolaczen = {
    args: { autoMatchCount: 0, open: true },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const autoConnect = canvas.getByRole('button', {
            name: /^Automatically connect languages to Store View locales/
        });

        await expect(autoConnect).toBeDisabled();
        await expect(autoConnect.querySelector('[data-role="auto-match-count"]')).not.toBeVisible();
    }
};

export const DostepneAutomatycznePolaczenia = {
    args: { autoMatchCount: 4, open: true },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const autoConnect = canvas.getByRole('button', {
            name: 'Automatically connect languages to Store View locales: 4'
        });

        await expect(autoConnect).toBeEnabled();
        await expect(autoConnect).toHaveTextContent('Auto Connect');
        await expect(autoConnect.querySelector('[data-role="auto-match-count"]')).toHaveTextContent('4');
    }
};

export const PrawaKolumnaStoreViews = {
    render: () => renderStoreViewPanel({hasExcluded: true}),
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const trigger = canvas.getByRole('button', {
            name: 'Language actions: Magento'
        });

        await userEvent.click(trigger);
        const toggle = canvas.getByRole('button', {
            name: 'Show excluded Store Views'
        });
        const excludedCard = canvas.getByText('Outlet Store View').closest('article');

        await expect(toggle).toBeEnabled();
        await expect(excludedCard).not.toBeVisible();
        await userEvent.click(toggle);
        await expect(toggle).toHaveAttribute('aria-pressed', 'true');
        await expect(excludedCard).toBeVisible();
        await userEvent.click(trigger);
        toggle.focus();
        await userEvent.keyboard('{Enter}');
        await expect(toggle).toHaveAttribute('aria-pressed', 'false');
        await expect(excludedCard).not.toBeVisible();
    }
};

export const PrawaKolumnaBezWykluczonych = {
    render: () => renderStoreViewPanel({hasExcluded: false}),
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const trigger = canvas.getByRole('button', {
            name: 'Language actions: Magento'
        });

        await userEvent.click(trigger);

        await expect(canvas.getByRole('button', {
            name: 'Show excluded Store Views'
        })).toBeDisabled();
    }
};

export const ObslugaMysza = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const sourceTrigger = canvas.getByRole('button', {name: 'Language actions: Ergonode'});
        const trigger = canvas.getByRole('button', {name: 'Opcje mapowania'});

        await expect(canvasElement.querySelector('[data-role="entity-options-placeholder"]'))
            .not.toBeInTheDocument();
        await userEvent.click(sourceTrigger);
        await expect(within(sourceTrigger.closest('details')).queryByText('Auto Connect'))
            .not.toBeInTheDocument();
        await userEvent.click(trigger);
        const autoConnect = canvas.getByRole('button', {
            name: /^Automatically connect languages to Store View locales/
        });

        await expect(autoConnect).toHaveTextContent('Auto Connect');
        await userEvent.click(autoConnect);
        await expect(canvas.getByRole('status')).toHaveTextContent('Uruchomiono Auto Connect');
        await expect(trigger.closest('details')).not.toHaveAttribute('open');
    }
};

export const ObslugaKlawiatura = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const trigger = canvas.getByRole('button', {
            name: 'Language actions: Ergonode'
        });
        const menu = trigger.closest('details');

        trigger.focus();
        await userEvent.keyboard('{Enter}');
        await expect(menu).toHaveAttribute('open');
        await userEvent.keyboard('{Escape}');
        await expect(menu).not.toHaveAttribute('open');
        await expect(trigger).toHaveFocus();
    }
};

export const AutoConnectKlawiaturaWSrodkowejKolumnie = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const trigger = canvas.getByRole('button', {name: 'Opcje mapowania'});

        trigger.focus();
        await userEvent.keyboard('{Enter}');
        await expect(canvas.getByRole('button', {
            name: 'Automatically connect languages to Store View locales: 3'
        })).toBeVisible();
    }
};
