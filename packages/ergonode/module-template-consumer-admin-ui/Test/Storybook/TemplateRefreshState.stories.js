import { expect, userEvent, within } from 'storybook/test';

import { loadAmdModule, translateIdentity } from '@ergonode-storybook/load-amd-module.js';

import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/attribute-mapping.css';
import '@ergonode-modules/TemplateAdminUi/view/adminhtml/web/css/template-admin.css';
import buttonsSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/buttons.js?raw';
import entityOptionsSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/entity-options.js?raw';
import visibilityToggleSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/visibility-toggle.js?raw';
import refreshStateSource from '../../view/adminhtml/web/js/template-refresh-state.js?raw';
import sourceOptionsSource from '@ergonode-modules/TemplateAdminUi/view/adminhtml/web/js/template-source-options.js?raw';

const dependencies = { 'mage/translate': translateIdentity };
const buttons = loadAmdModule(buttonsSource, dependencies, 'Ergonode_CoreAdminUi/js/buttons');
const entityOptions = loadAmdModule(entityOptionsSource, dependencies, 'Ergonode_CoreAdminUi/js/entity-options');
const visibilityToggle = loadAmdModule(
    visibilityToggleSource,
    { 'Ergonode_CoreAdminUi/js/buttons': buttons },
    'Ergonode_CoreAdminUi/js/visibility-toggle'
);
const sourceOptions = loadAmdModule(
    sourceOptionsSource,
    {
        'Ergonode_CoreAdminUi/js/entity-options': entityOptions,
        'Ergonode_CoreAdminUi/js/visibility-toggle': visibilityToggle,
        'mage/translate': translateIdentity
    },
    'Ergonode_TemplateAdminUi/js/template-source-options'
);

const refreshState = loadAmdModule(
    refreshStateSource,
    {},
    'Ergonode_TemplateAdminUi/js/template-refresh-state'
);

function createPanel(args) {
    const frame = document.createElement('div');
    const panel = document.createElement('section');
    const head = document.createElement('div');
    const title = Object.assign(document.createElement('strong'), { textContent: 'Templates' });
    const tools = document.createElement('div');
    const search = document.createElement('label');
    const searchInput = Object.assign(document.createElement('input'), {
        type: 'search',
        placeholder: 'Szukaj template...'
    });
    const list = document.createElement('div');
    const status = document.createElement('div');
    const statusCopy = document.createElement('div');
    const statusTitle = Object.assign(document.createElement('strong'), {
        textContent: 'Odświeżanie szablonów'
    });
    const statusText = Object.assign(document.createElement('span'), {
        textContent: args.statusText
    });

    frame.style.height = '520px';
    frame.style.padding = '24px';
    frame.style.width = '380px';
    frame.className = 'veui-workspace vet-admin';
    panel.className = 'veui-panel vet-panel vet-source-panel vea-panel vea-side-panel';
    panel.dataset.role = 'template-drop-source';
    panel.dataset.sourceOptionsLabel = 'Template actions: Ergonode';
    panel.dataset.showExcludedHint = 'Show excluded templates and attribute sets';
    panel.dataset.hideExcludedHint = 'Hide excluded templates and attribute sets';
    panel.style.height = '100%';
    head.className = 'veui-panel-head veui-panel-head-with-tools vea-panel-head vet-panel-head';
    title.className = 'veui-panel-title';
    head.append(title);
    tools.className = 'veui-panel-head-tools vea-side-tools vet-side-tools';
    search.className = 'veui-search veui-search-expandable vet-search';
    searchInput.dataset.role = 'template-search';
    searchInput.setAttribute('aria-label', 'Search templates');
    search.append(
        Object.assign(document.createElement('span'), { className: 'veui-search-icon' }),
        searchInput
    );
    tools.append(search);
    list.className = 'vea-attribute-list vet-card-list vet-template-source-list';
    ['Shoes', 'Accessories', 'Home'].forEach((label) => {
        const card = document.createElement('article');

        card.className = 'vea-attribute-card vet-map-card vet-template-card vet-status-ok';
        card.textContent = label;
        list.append(card);
    });
    status.className = 'vet-template-refresh-state';
    status.dataset.role = 'template-refresh-state';
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');
    status.hidden = true;
    statusTitle.dataset.role = 'template-refresh-title';
    statusText.dataset.role = 'template-refresh-text';
    statusCopy.append(statusTitle, statusText);
    const spinner = Object.assign(document.createElement('span'), {
        className: 'vea-context-loading-spinner'
    });

    spinner.setAttribute('aria-hidden', 'true');
    status.append(spinner, statusCopy);
    head.append(tools);
    panel.append(head, status, list);
    frame.append(panel);

    sourceOptions.initialize(frame, true);
    const button = entityOptions.createAction({role: 'refresh-templates', className: 'vea-refresh-ergonode',
        iconClass: 'vea-refresh-ergonode-icon', label: 'Refresh', title: 'Odśwież szablony z Ergonode', ariaLabel: 'Odśwież szablony z Ergonode'});
    frame.querySelector('[data-template-source-options] .veui-entity-options-menu').prepend(button);

    const controller = refreshState.create(frame);

    button.addEventListener('click', () => {
        controller.show('Odświeżanie szablonów', args.statusText);
    });
    if (args.refreshing) {
        controller.show('Odświeżanie szablonów', args.statusText);
    }

    return frame;
}

const meta = {
    id: 'ergo-v-058-06',
    title: 'Ergonode UI/Widoki/Szablony/ERGO-V-058 · Mapowanie szablonów/ERGO-V-058.06 · Odświeżanie szablonów',
    tags: ['autodocs'],
    render: createPanel,
    args: {
        refreshing: false,
        statusText: 'Pobieram szablony i ich strukturę. Zestawy atrybutów Magento pozostają bez zmian.'
    },
    argTypes: {
        refreshing: { control: 'boolean' },
        statusText: { control: 'text' }
    }
};

export default meta;

export const Playground = {};

export const WTrakcieOdświeżania = {
    args: {
        refreshing: true
    }
};

export const LimitZapytań = {
    args: {
        refreshing: true,
        statusText: 'Wznowię odświeżanie za 5 s.'
    }
};

export const UruchomieniePrzyciskiem = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const trigger = canvas.getByRole('button', { name: 'Template actions: Ergonode' });

        await userEvent.click(trigger);
        const button = canvas.getByRole('button', { name: 'Odśwież szablony z Ergonode' });
        const panel = canvasElement.querySelector('[data-role="template-drop-source"]');
        const search = canvas.getByRole('searchbox');

        await userEvent.click(button);
        const status = canvas.getByRole('status');

        await expect(panel).toHaveClass('is-refreshing');
        await expect(panel).toHaveAttribute('aria-busy', 'true');
        await expect(status).toBeVisible();
        await expect(button).toBeDisabled();
        await expect(search).toBeDisabled();
    }
};
