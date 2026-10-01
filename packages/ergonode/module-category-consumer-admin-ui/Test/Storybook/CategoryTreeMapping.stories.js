import '../../view/adminhtml/web/css/category-synchronization.css';
import { createNavigation } from '@ergonode-storybook/section-navigation.js';
import { expect, userEvent, waitFor, within } from 'storybook/test';

import { loadAmdModule } from '@ergonode-storybook/load-amd-module.js';

import autoMatchIconUrl from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/images/auto-match.svg';
import refreshIconUrl from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/images/refresh-ergonode.svg';
import saveIconUrl from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/images/save.svg';
import ergonodeIconUrl from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/images/m2_configuration.svg';
import magentoIconUrl from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/images/magento-mark.svg';
import connectedIconUrl from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/images/auto-match.svg';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-actions.css';
import '@ergonode-modules/CategoryAdminUi/view/adminhtml/web/css/category-tree-mapping.css';
import bulkActionsSource from '@ergonode-modules/CategoryAdminUi/view/adminhtml/web/js/category-tree-bulk-actions.js?raw';
import configurationOrderSource from '@ergonode-modules/CategoryAdminUi/view/adminhtml/web/js/category-tree-configuration-order.js?raw';
import collapseStorageSource from '@ergonode-modules/CategoryAdminUi/view/adminhtml/web/js/category-tree-collapse-storage.js?raw';
import mappingStateSource from '@ergonode-modules/CategoryAdminUi/view/adminhtml/web/js/category-tree-mapping-state.js?raw';
import buttonsSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/buttons.js?raw';
import entityOptionsSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/entity-options.js?raw';
import synchronizationActionsSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/synchronization-actions.js?raw';
import synchronizationAvailabilitySource from '../../view/adminhtml/web/js/category-synchronization-availability.js?raw';
import messagesSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/messages.js?raw';
import visibilityToggleSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/visibility-toggle.js?raw';
import workspaceSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/workspace.js?raw';
import newMappingSource from '@ergonode-modules/CategoryAdminUi/view/adminhtml/web/js/category-tree-new-mapping.js?raw';
import subtreeMappingSource from '@ergonode-modules/CategoryAdminUi/view/adminhtml/web/js/category-tree-subtree-mapping.js?raw';
import treeStateSource from '@ergonode-modules/CategoryAdminUi/view/adminhtml/web/js/category-tree-state.js?raw';
import operationStateSource from '@ergonode-modules/CategoryAdminUi/view/adminhtml/web/js/category-tree-operation-state.js?raw';

const productionOperationState = loadAmdModule(
    operationStateSource,
    {'mage/translate': (value) => ({
        'Connecting categories…': 'Łączenie kategorii…',
        'Saving category mappings…': 'Zapisywanie mapowań kategorii…'
    })[value] || value},
    'Ergonode_CategoryAdminUi/js/category-tree-operation-state'
);

const treeOptions = [
    { id: 1, value: 'main-pl', label: 'Main PL', root: 'PLN Root (#41)', active: true },
    { id: 2, value: 'main-en', label: 'Main EN', root: 'English Root (#52)', active: false }
];
const rootOptions = [
    { value: '', label: 'Wybierz kategorię główną' },
    { value: '41', label: 'PLN Root (#41)', mapped: true },
    { value: '52', label: 'English Root (#52)', mapped: true },
    { value: '63', label: 'Outlet Root (#63)', mapped: false }
];
function storyJquery(element) {
    return {
        element,
        modal(action, value) {
            if (action === 'setTitle') {
                this.shell.querySelector('.modal-title').textContent = value;
                this.shell.setAttribute('aria-label', value);
            } else if (action === 'openModal') {
                this.shell.hidden = false;
                this.shell.style.display = 'flex';
                this.shell.classList.add('_show');
            } else if (action === 'closeModal') {
                this.shell.hidden = true;
                this.shell.style.display = 'none';
                this.shell.classList.remove('_show');
                if (this.options && this.options.closed) {
                    this.options.closed();
                }
            }
        }
    };
}
storyJquery.ajax = () => {
    throw new Error('AJAX is not available in the Storybook fixture.');
};
function storyModal(options, widget) {
    const shell = document.createElement(options.popupTpl.match(/<(div|aside)\b[^>]*role="dialog"/)[1]);
    const inner = document.createElement('div');
    const header = document.createElement('header');
    const title = Object.assign(document.createElement('h1'), { textContent: options.title });
    const content = document.createElement('div');

    widget.options = options;
    widget.shell = shell;
    shell.className = `modal-popup ${options.modalClass}`;
    shell.hidden = true;
    Object.assign(shell.style, {
        alignItems: 'center',
        background: 'rgba(15, 23, 42, .32)',
        display: 'none',
        inset: '0',
        justifyContent: 'center',
        padding: '24px',
        position: 'fixed',
        zIndex: '900'
    });
    shell.setAttribute('role', 'dialog');
    shell.setAttribute('aria-label', options.title);
    inner.className = 'modal-inner-wrap';
    header.className = 'modal-header';
    title.className = 'modal-title';
    content.className = 'modal-content';
    widget.element.style.display = 'block';
    widget.element.parentNode.insertBefore(shell, widget.element);
    header.append(title);
    content.append(widget.element);
    inner.append(header, content);
    shell.append(inner);
}
const productionNewMapping = loadAmdModule(
    newMappingSource,
    {
        jquery: storyJquery,
        'Magento_Ui/js/modal/modal': storyModal,
        'mage/translate': (value) => value,
        'text!ui/template/modal/modal-popup.html': '<aside role="dialog"></aside>'
    },
    'Ergonode_CategoryAdminUi/js/category-tree-new-mapping'
);
const configurationOrder = loadAmdModule(
    configurationOrderSource,
    {},
    'Ergonode_CategoryAdminUi/js/category-tree-configuration-order'
);
const productionTreeState = loadAmdModule(
    treeStateSource,
    {},
    'Ergonode_CategoryAdminUi/js/category-tree-state'
);
const productionCollapseStorage = loadAmdModule(
    collapseStorageSource,
    {},
    'Ergonode_CategoryAdminUi/js/category-tree-collapse-storage'
);
const productionSubtreeMapping = loadAmdModule(
    subtreeMappingSource,
    {},
    'Ergonode_CategoryAdminUi/js/category-tree-subtree-mapping'
);
const productionMappingState = loadAmdModule(
    mappingStateSource,
    {},
    'Ergonode_CategoryAdminUi/js/category-tree-mapping-state'
);
const productionBulkActions = loadAmdModule(
    bulkActionsSource,
    {'Ergonode_CategoryAdminUi/js/category-tree-mapping-state': productionMappingState},
    'Ergonode_CategoryAdminUi/js/category-tree-bulk-actions'
);
const productionMessages = loadAmdModule(
    messagesSource,
    {
        'mage/translate': (value) => value
    },
    'Ergonode_CoreAdminUi/js/messages'
);
const productionButtons = loadAmdModule(
    buttonsSource,
    {'mage/translate': (value) => value},
    'Ergonode_CoreAdminUi/js/buttons'
);
const productionVisibilityToggle = loadAmdModule(
    visibilityToggleSource,
    {'Ergonode_CoreAdminUi/js/buttons': productionButtons},
    'Ergonode_CoreAdminUi/js/visibility-toggle'
);
const productionWorkspace = loadAmdModule(
    workspaceSource,
    {'mage/translate': (value) => value},
    'Ergonode_CoreAdminUi/js/workspace'
);
const productionEntityOptions = loadAmdModule(
    entityOptionsSource,
    {'mage/translate': (value) => value},
    'Ergonode_CoreAdminUi/js/entity-options'
);
const productionSynchronizationActions = loadAmdModule(
    synchronizationActionsSource,
    {},
    'Ergonode_CoreAdminUi/js/synchronization-actions'
);
const productionSynchronizationAvailability = loadAmdModule(
    synchronizationAvailabilitySource,
    {},
    'Ergonode_CategoryConsumerAdminUi/js/category-synchronization-availability'
);
function createCategoryOptions(label, actions) {
    return productionEntityOptions.create({
        actions,
        menuLabel: `Opcje kategorii: ${label}`
    });
}

function syncVisibilityAction(visibilityAction, active) {
    const label = active ? 'Exclude' : 'Include';

    visibilityAction.title = label;
    visibilityAction.setAttribute('aria-label', label);
    visibilityAction.setAttribute('aria-pressed', active ? 'true' : 'false');
    visibilityAction.lastElementChild.textContent = label;
}

function createVisibilityAction(active = true) {
    const visibilityAction = productionEntityOptions.createAction({
        role: 'category-active-toggle',
        className: 'veui-visibility-control',
        iconClass: 'veui-visibility-icon',
        label: active ? 'Exclude' : 'Include'
    });

    syncVisibilityAction(visibilityAction, active);

    return visibilityAction;
}

function createBulkOptions(role, autoConnectWorking = false) {
    const source = role === 'source' ? 'ergo' : 'magento';
    const sourceLabel = role === 'source' ? 'Ergonode' : 'Magento';
    const menuLabel = `Akcje kategorii: ${sourceLabel}`;
    const refreshAction = productionEntityOptions.createAction({
        role: 'refresh-categories',
        className: 'vec-refresh-categories',
        iconClass: 'vec-icon vec-icon-refresh',
        label: 'Refresh'
    });
    const refreshIcon = Object.assign(document.createElement('img'), {
        alt: '',
        className: 'vec-icon vec-icon-refresh',
        src: refreshIconUrl
    });

    refreshIcon.setAttribute('aria-hidden', 'true');
    refreshAction.querySelector('.vec-icon-refresh').replaceWith(refreshIcon);
    const autoConnectAction = productionEntityOptions.createAction({
        role: 'auto-map-categories',
        className: 'vec-auto-map-categories',
        iconClass: 'vec-auto-map-icon',
        label: 'Auto Connect',
        disabled: autoConnectWorking
    });
    const autoConnectIcon = Object.assign(document.createElement('img'), {
        alt: '',
        className: 'vec-auto-map-icon',
        src: autoMatchIconUrl
    });

    autoConnectIcon.setAttribute('aria-hidden', 'true');
    autoConnectAction.querySelector('.vec-auto-map-icon').replaceWith(autoConnectIcon);
    autoConnectAction.classList.toggle('is-working', autoConnectWorking);
    const actions = [
        role === 'source' ? refreshAction : null,
        role === 'target' ? autoConnectAction : null,
        {
            role: 'visibility-toggle',
            className: 'veui-visibility-control',
            iconClass: 'veui-visibility-icon',
            label: 'Excluded',
            disabled: true,
            attributes: {
                'data-bulk-source': source,
                'data-show-hint': 'Pokaż kategorie pominięte w mapowaniu',
                'data-hide-hint': 'Ukryj kategorie pominięte w mapowaniu',
                'aria-pressed': 'false'
            }
        },
        role === 'source' ? {
            role: 'mapped-visibility-toggle',
            className: 'veui-visibility-control',
            iconClass: 'vec-connected-icon veui-connected-icon',
            label: 'Połączone',
            attributes: {
                'data-show-hint': 'Pokaż połączone kategorie',
                'data-hide-hint': 'Ukryj połączone kategorie',
                'aria-pressed': 'true'
            }
        } : null
    ].filter(Boolean);

    actions.push({
        role: 'unmap-selected-categories',
        className: 'vec-unmap-selected-categories',
        iconClass: 'vec-unmap-icon',
        label: 'Disconnect',
        disabled: true
    });
    const menu = productionEntityOptions.create({actions, menuLabel});
    const count = Object.assign(document.createElement('span'), {
        className: 'vec-bulk-selected-count',
        hidden: true,
        textContent: '0'
    });

    menu.classList.add(role === 'target' ? 'veui-split-button-options' : 'vec-bulk-options');
    if (role === 'target') {
        menu.querySelector('.veui-entity-options-menu').classList.add('veui-split-button-menu');
    }
    menu.dataset.bulkOptionsSource = source;
    count.dataset.role = 'bulk-selected-count';
    menu.querySelector('summary').append(count);

    return menu;
}

function createBulkSelection(role, identifier, label) {
    const control = document.createElement('label');
    const input = document.createElement('input');
    const marker = document.createElement('span');

    control.className = 'vec-bulk-category-select';
    input.type = 'checkbox';
    input.dataset.role = 'bulk-category-select';
    input.dataset.bulkSource = role === 'source' ? 'ergo' : 'magento';
    input.dataset.identifier = identifier;
    input.setAttribute('aria-label', `Zaznacz kategorię ${label}`);
    marker.setAttribute('aria-hidden', 'true');
    control.append(input, marker);

    return control;
}

let mappingHintSequence = 0;
let pendingSaveHintSequence = 0;
let excludedCategoryHintSequence = 0;

function createMappingIndicator(className, label, code, statusLabel = '', statusMessage = '') {
    const indicator = document.createElement('button');
    const hint = [label, code, statusLabel, statusMessage].filter(Boolean).join(' · ');
    const hintId = `storybook-mapping-hint-${++mappingHintSequence}`;
    const tooltip = document.createElement('span');

    indicator.type = 'button';
    indicator.className = `${className} vec-mapping-indicator`;
    indicator.setAttribute('aria-label', hint);
    indicator.setAttribute('aria-describedby', hintId);
    indicator.draggable = false;
    tooltip.className = 'vec-mapping-hint';
    tooltip.id = hintId;
    tooltip.setAttribute('role', 'tooltip');
    tooltip.append(
        Object.assign(document.createElement('strong'), { textContent: label }),
        Object.assign(document.createElement('span'), { textContent: code })
    );
    if (statusLabel) {
        tooltip.append(Object.assign(document.createElement('span'), {
            className: 'vec-mapping-state-copy',
            textContent: statusLabel
        }));
    }
    if (statusMessage) {
        tooltip.append(Object.assign(document.createElement('span'), {
            className: 'vec-mapping-error-copy',
            textContent: statusMessage
        }));
    }
    indicator.append(
        Object.assign(document.createElement('span'), {
            className: 'vec-mapping-status-icon veui-connected-icon',
            'aria-hidden': 'true'
        }),
        tooltip
    );

    return indicator;
}

function createPendingSaveIndicator() {
    const indicator = document.createElement('button');
    const tooltip = document.createElement('span');
    const message = 'Mapowanie oczekuje na zapis';
    const tooltipId = `storybook-pending-save-tooltip-${++pendingSaveHintSequence}`;

    indicator.type = 'button';
    indicator.className = 'vec-pending-save';
    indicator.dataset.role = 'pending-mapping-save';
    indicator.setAttribute('aria-label', message);
    indicator.setAttribute('aria-describedby', tooltipId);
    indicator.draggable = false;
    tooltip.className = 'vec-pending-save-tooltip';
    tooltip.id = tooltipId;
    tooltip.setAttribute('role', 'tooltip');
    tooltip.textContent = message;
    indicator.append(
        Object.assign(document.createElement('span'), {
            className: 'vec-pending-save-icon'
        }),
        tooltip
    );

    return indicator;
}

function createExcludedCategoryInfo() {
    const info = document.createElement('span');
    const icon = Object.assign(document.createElement('span'), { textContent: 'i' });
    const tooltip = document.createElement('span');
    const title = 'Kategoria jest wykluczona z mapowania';
    const description = 'Ta kategoria nie będzie używana w mapowaniu. ' +
        'Uwzględnij ją ponownie w jej opcjach, aby znów była dostępna.';
    const tooltipId = `storybook-excluded-category-hint-${++excludedCategoryHintSequence}`;

    info.className = 'vec-configuration-disabled-info';
    info.tabIndex = 0;
    info.setAttribute('role', 'img');
    info.setAttribute('aria-label', title);
    info.setAttribute('aria-describedby', tooltipId);
    icon.className = 'vec-information-icon';
    icon.setAttribute('aria-hidden', 'true');
    tooltip.className = 'vec-mapping-hint vec-configuration-disabled-tooltip';
    tooltip.id = tooltipId;
    tooltip.setAttribute('role', 'tooltip');
    tooltip.append(
        Object.assign(document.createElement('strong'), { textContent: title }),
        Object.assign(document.createElement('span'), { textContent: description })
    );
    info.append(icon, tooltip);

    return info;
}

function syncMagentoExcludedPresentation(card, excluded) {
    const mapping = card.querySelector('.vec-magento-mapping, [data-role="pending-mapping-save"]');
    const options = card.querySelector('[data-role="entity-options"]');
    const currentInfo = card.querySelector('.vec-configuration-disabled-info');

    card.classList.toggle('is-disabled', excluded);
    if (mapping) {
        mapping.hidden = excluded;
        mapping.style.display = excluded ? 'none' : '';
    }
    if (excluded && !currentInfo) {
        const info = createExcludedCategoryInfo();

        if (options) {
            options.before(info);
        } else {
            card.append(info);
        }
    } else if (!excluded && currentInfo) {
        currentInfo.remove();
    }
}

function appendPendingSaveIndicator(card) {
    const options = card.querySelector('[data-role="entity-options"]');
    const indicator = createPendingSaveIndicator();

    if (card.querySelector('[data-role="pending-mapping-save"]')) {
        return;
    }
    if (options) {
        options.before(indicator);
    } else {
        card.append(indicator);
    }
}

function createErgonodeCategoryOptions({ label, active = true }) {
    const actions = [productionEntityOptions.createAction({
        className: 'vec-delete-snapshot-category',
        iconClass: 'vec-delete-snapshot-icon',
        label: 'Usuń z tej listy',
        ariaLabel: 'Usuń z tej listy'
    })];
    actions.push(createVisibilityAction(active));

    return createCategoryOptions(label, actions);
}

function createMagentoCategoryOptions({ label, mappingCode, active = true }) {
    const actions = [];

    if (mappingCode) {
        actions.push(productionEntityOptions.createAction({
            role: 'unmap-category',
            className: 'vec-unmap',
            iconClass: 'vec-unmap-icon',
            label: 'Disconnect'
        }));
    }
    actions.push(createVisibilityAction(active));

    return createCategoryOptions(label, actions);
}

function createPanel(
    title,
    role,
    items = [],
    rootItem = null,
    hasSelection = true,
    autoConnectWorking = false,
    hideMappedByDefault = false
) {
    const panel = document.createElement('section');
    const head = document.createElement('div');
    const heading = document.createElement('div');
    const tree = document.createElement('div');
    const emptyState = Object.assign(document.createElement('div'), {
        className: 'vec-empty-row',
        hidden: true
    });
    const emptyMessage = Object.assign(document.createElement('span'), {
        textContent: 'Brak kategorii. Wszystkie kategorie są już zmapowane.'
    });
    const emptyMappedToggle = Object.assign(document.createElement('button'), {
        className: 'veui-button veui-visibility-control vec-empty-mapped-toggle',
        type: 'button'
    });

    emptyMappedToggle.dataset.role = 'mapped-visibility-toggle';
    emptyMappedToggle.setAttribute('aria-pressed', 'true');
    const emptyMappedIcon = Object.assign(document.createElement('span'), {
        className: 'veui-visibility-icon'
    });

    emptyMappedIcon.setAttribute('aria-hidden', 'true');
    emptyMappedToggle.append(
        emptyMappedIcon,
        Object.assign(document.createElement('span'), { textContent: 'Połączone' })
    );
    emptyState.append(emptyMessage, emptyMappedToggle);
    const visibilityCategories = items.map((item) => ({
        code: item.identifier || item.code,
        source_parent_code: item.source_parent_code || null,
        active: item.active !== false
    }));
    const selectionParents = Object.fromEntries(items.map((item) => [
        String(item.identifier || item.code),
        String(item.source_parent_code || item.parentIdentifier || '')
    ]));
    const selected = new Set();
    let showExcluded = false;
    let hideMapped = role === 'source' && hideMappedByDefault;
    let configuredRootNode = null;
    let configuredRootExpanded = true;

    panel.className = 'veui-panel vec-panel';
    panel.classList.add(role === 'source' ? 'vec-source-panel' : 'vec-target-panel');
    panel.dataset.columnRole = role;
    if (!hasSelection) {
        return panel;
    }
    head.className = 'veui-panel-head vec-panel-head';
    const titleNode = Object.assign(document.createElement('strong'), { textContent: title });
    const tools = document.createElement('div');
    const search = document.createElement('label');

    titleNode.className = 'veui-panel-title';
    tools.className = `veui-panel-head-tools vec-${role}-tools`;
    search.className = 'veui-search veui-search-expandable';
    const searchInput = Object.assign(document.createElement('input'), {
        type: 'search',
        placeholder: `Szukaj w ${role === 'source' ? 'Ergonode' : 'Magento'}...`
    });

    searchInput.dataset.role = role === 'source' ? 'ergo-search' : 'magento-search';
    searchInput.setAttribute('aria-label', searchInput.placeholder);
    search.append(
        Object.assign(document.createElement('span'), { className: 'veui-search-icon' }),
        searchInput
    );
    heading.append(titleNode);
    head.append(heading);
    tools.append(search);
    const brand = document.createElement('span');
    const titleText = Object.assign(document.createElement('span'), {
        className: 'veui-panel-title-text',
        textContent: title
    });

    brand.className = `veui-brand-mark veui-brand-${role === 'source' ? 'ergonode' : 'magento'}`;
    brand.setAttribute('aria-hidden', 'true');
    brand.append(Object.assign(document.createElement('img'), {
        alt: '',
        src: role === 'source' ? ergonodeIconUrl : magentoIconUrl
    }));
    titleNode.replaceChildren(brand, titleText);
    head.classList.add('veui-panel-head-with-tools');
    head.append(tools);
    if (role === 'target') {
        const save = Object.assign(document.createElement('button'), {
            className: 'veui-button veui-button-primary veui-button-toolbar veui-split-button-main vec-button',
            disabled: true,
            title: 'Zapisz mapowanie kategorii',
            type: 'button'
        });
        const saveIcon = Object.assign(document.createElement('img'), {
            alt: '',
            className: 'vec-icon vec-icon-save',
            height: 14,
            src: saveIconUrl,
            width: 14
        });

        save.dataset.role = 'save-categories';
        save.setAttribute('aria-label', 'Zapisz mapowanie kategorii');
        saveIcon.setAttribute('aria-hidden', 'true');
        save.append(saveIcon, Object.assign(document.createElement('span'), { textContent: 'Zapisz' }));
        const actions = document.createElement('div');

        actions.className = 'veui-split-button veui-split-button-align-end veui-split-button-primary';
        actions.dataset.role = 'category-mapping-actions';
        actions.setAttribute('role', 'group');
        actions.setAttribute('aria-label', 'Zapisz mapowanie kategorii');
        actions.append(save);
        tools.append(actions);
    }
    const bulkOptions = createBulkOptions(role, autoConnectWorking);

    (tools.querySelector('[data-role="category-mapping-actions"]') || tools).append(bulkOptions);
    tree.className = 'vec-side-tree';
    const children = document.createElement('div');

    children.className = 'vec-node-children';
    items.forEach(({
        label,
        code,
        identifier,
        mapped,
        mappingLabel,
        mappingCode,
        active = true,
        mappingState = 'active',
        syncMessage = ''
    }) => {
        const card = document.createElement('div');
        const copy = document.createElement('span');
        const categoryCode = identifier || code;

        card.className = `vec-node-card${role === 'source' ? ' vec-ergo-card' : ''}` +
            `${mapped ? ' is-mapped' : ''} has-bulk-selection` +
            `${active ? '' : ' is-blocked'}` +
            `${role === 'target' && !active ? ' is-disabled' : ''}`;
        card.dataset.categoryCode = categoryCode;
        card.dataset.categoryActive = active ? 'true' : 'false';
        card.dataset.bulkIdentifier = categoryCode;
        card.draggable = role === 'source';
        copy.className = 'vec-card-copy';
        copy.append(Object.assign(document.createElement(role === 'target' ? 'span' : 'strong'), {
            className: role === 'target' ? 'vec-card-label' : '',
            textContent: label
        }));
        copy.append(role === 'target'
            ? createMagentoPath(code, categoryCode)
            : Object.assign(document.createElement('span'), { textContent: code }));
        card.append(createBulkSelection(role, categoryCode, label));
        card.append(copy);
        if (role === 'source' && mappingCode) {
            card.append(createMappingIndicator('vec-source-mapping', mappingLabel, mappingCode));
        }
        if (role === 'target') {
            let mapping;
            const stateLabel = {
                active: 'This element is already mapped.',
                disabled: 'Mapping disabled',
                error: 'Mapping error'
            }[mappingState] || '';

            if (mappingCode) {
                mapping = mappingState === 'pending' ? createPendingSaveIndicator() : createMappingIndicator(
                    'vec-magento-mapping is-mapped',
                    mappingLabel,
                    mappingCode,
                    stateLabel,
                    syncMessage
                );
                card.classList.add('is-mapped', `is-mapping-${mappingState}`);
                card.dataset.mappingState = mappingState;
                if (mappingState === 'disabled') {
                    card.classList.add('is-blocked');
                } else if (mappingState === 'error') {
                    card.classList.add('has-mapping-error');
                }
            } else {
                mapping = document.createElement('span');
                mapping.className = 'vec-magento-mapping is-empty';
                mapping.title = 'Upuść kategorię Ergonode, aby ją zmapować';
                mapping.append(Object.assign(document.createElement('span'), {
                    className: 'vec-visually-hidden', textContent: mapping.title
                }));
            }
            card.classList.add('vec-magento-card');
            if (active && !mappingCode) {
                card.dataset.dropZone = 'magento-target';
            }
            card.append(mapping, createMagentoCategoryOptions({ label, mappingCode, mappingLabel, active }));
            syncMagentoExcludedPresentation(card, !active);
        }
        if (role === 'source') {
            card.append(createErgonodeCategoryOptions({ label, mapped, active }));
        }
        children.append(card);
    });
    function syncFilteredCards() {
        const cards = [...children.querySelectorAll('[data-category-active]')];
        const hasVisibleUnmappedDescendant = (categoryCode) => cards.some((candidate) => {
            let parentCode = selectionParents[candidate.dataset.categoryCode];

            if (candidate.classList.contains('is-mapped')
                || (candidate.dataset.categoryActive === 'false' && !showExcluded)
            ) {
                return false;
            }
            while (parentCode) {
                if (parentCode === categoryCode) {
                    return true;
                }
                parentCode = selectionParents[parentCode];
            }

            return false;
        });

        cards.forEach((card) => {
            const isMappedParent = role === 'source'
                && hideMapped
                && card.classList.contains('is-mapped')
                && hasVisibleUnmappedDescendant(card.dataset.categoryCode);

            card.hidden = (card.dataset.categoryActive === 'false' && !showExcluded)
                || (role === 'source'
                    && hideMapped
                    && card.classList.contains('is-mapped')
                    && !isMappedParent);
            card.classList.toggle('is-mapped-parent', isMappedParent);
            card.style.display = card.hidden ? 'none' : '';
        });
        emptyState.hidden = !(role === 'source'
            && hideMapped
            && (Boolean(rootItem) || cards.length > 0)
            && cards.every((card) => card.classList.contains('is-mapped')));
    }
    function syncPanelVisibility() {
        const toggle = bulkOptions.querySelector('[data-role="visibility-toggle"]');
        const available = [...children.querySelectorAll('[data-category-active]')]
            .some((card) => card.dataset.categoryActive === 'false');

        if (!available) {
            showExcluded = false;
        }
        productionVisibilityToggle.setVisible(toggle, available && showExcluded);
        toggle.disabled = !available;
        syncFilteredCards();
    }
    function syncMappedVisibility() {
        const toggles = [
            bulkOptions.querySelector('[data-role="mapped-visibility-toggle"]'),
            role === 'source' ? emptyMappedToggle : null
        ].filter(Boolean);
        const hint = hideMapped ? 'Pokaż połączone kategorie' : 'Ukryj połączone kategorie';

        if (!toggles.length) {
            return;
        }
        toggles.forEach((toggle) => {
            toggle.setAttribute('aria-pressed', hideMapped ? 'true' : 'false');
            toggle.setAttribute('aria-label', hint);
            toggle.title = hint;
        });
        syncFilteredCards();
        if (configuredRootNode) {
            if (hideMapped) {
                const hasVisibleChildren = [...children.querySelectorAll('[data-category-active]')]
                    .some((card) => !card.hidden);

                configuredRootNode.append(children);
                configuredRootNode.hidden = !hasVisibleChildren;
                configuredRootNode.querySelector('.vec-configured-root-card')
                    .classList.toggle('is-mapped-parent', hasVisibleChildren);
                children.hidden = !hasVisibleChildren || !configuredRootExpanded;
            } else {
                configuredRootNode.append(children);
                configuredRootNode.hidden = false;
                configuredRootNode.querySelector('.vec-configured-root-card')
                    .classList.remove('is-mapped-parent');
                children.hidden = !configuredRootExpanded;
            }
        }
    }
    function updateBulkControls() {
        const count = selected.size;
        const summary = bulkOptions.querySelector('summary');
        const countBadge = bulkOptions.querySelector('[data-role="bulk-selected-count"]');
        const cards = [...children.querySelectorAll('[data-bulk-identifier]')];
        const categoryModels = role === 'source'
            ? cards.map((card) => ({
                active: !card.classList.contains('is-blocked'),
                code: card.dataset.bulkIdentifier,
                magento_category_id: card.querySelector('.vec-source-mapping') ? 1 : null
            }))
            : cards.filter((card) => card.querySelector('.vec-magento-mapping.is-mapped'))
                .map((card) => ({
                    code: `mapped-${card.dataset.bulkIdentifier}`,
                    magento_category_id: Number(card.dataset.bulkIdentifier)
                }));
        const magentoModels = role === 'target'
            ? cards.map((card) => ({
                active: !card.classList.contains('is-blocked'),
                id: Number(card.dataset.bulkIdentifier)
            }))
            : [];

        if (role === 'target' && rootItem) {
            magentoModels.push({active: true, id: Number(rootItem.identifier)});
        }
        const availability = productionBulkActions.resolve(
            role === 'source' ? 'ergo' : 'magento',
            [...selected],
            categoryModels,
            magentoModels
        );

        bulkOptions.querySelector('[data-role="unmap-selected-categories"]').disabled = !availability.disconnect;
        countBadge.textContent = String(count);
        countBadge.hidden = count === 0;
        summary.setAttribute('aria-label', `Akcje kategorii: ${role === 'source' ? 'Ergonode' : 'Magento'}` +
            (count ? `. Wybrano: ${count}` : ''));
        syncPanelVisibility();
        syncMappedVisibility();
    }
    function clearSelection() {
        selected.clear();
        tree.querySelectorAll('[data-role="bulk-category-select"]').forEach((checkbox) => {
            checkbox.checked = false;
        });
        updateBulkControls();
    }
    function selectionBranch(identifier) {
        const result = [];
        const queue = [String(identifier)];

        if (role === 'source' && rootItem && identifier === rootItem.identifier) {
            return [String(rootItem.identifier), ...items.map((item) => String(item.identifier || item.code))];
        }

        while (queue.length) {
            const current = queue.shift();

            if (result.includes(current)) {
                continue;
            }
            result.push(current);
            Object.entries(selectionParents).forEach(([child, parent]) => {
                if (parent === current) {
                    queue.push(child);
                }
            });
        }

        return result;
    }
    tree.addEventListener('change', (event) => {
        const checkbox = event.target.closest('[data-role="bulk-category-select"]');

        if (!checkbox) {
            return;
        }
        selectionBranch(checkbox.dataset.identifier).forEach((identifier) => {
                const branchCheckbox = [...tree.querySelectorAll('[data-role="bulk-category-select"]')]
                .find((candidate) => candidate.dataset.identifier === identifier);

            if (!branchCheckbox) {
                return;
            }
            branchCheckbox.checked = checkbox.checked;
            if (checkbox.checked) {
                selected.add(identifier);
            } else {
                selected.delete(identifier);
            }
        });
        updateBulkControls();
    });
    bulkOptions.addEventListener('click', (event) => {
        const action = event.target.closest('.veui-entity-options-action');

        if (!action || action.disabled) {
            return;
        }
        if (action.dataset.role === 'refresh-categories') {
            panel.dataset.refreshed = 'true';
        } else if (action.dataset.role === 'auto-map-categories') {
            panel.dataset.autoConnected = 'true';
        } else if (action.dataset.role === 'visibility-toggle') {
            showExcluded = productionVisibilityToggle.toggle(action);
            syncFilteredCards();
        } else if (action.dataset.role === 'mapped-visibility-toggle') {
            hideMapped = !hideMapped;
            syncMappedVisibility();
        } else if (action.dataset.role === 'unmap-selected-categories') {
            selected.forEach((identifier) => {
                const itemCard = children.querySelector(`[data-bulk-identifier="${identifier}"]`);
                const mapping = itemCard && itemCard.querySelector(
                    role === 'source' ? '.vec-source-mapping' : '.vec-magento-mapping, .vec-configuration-disabled-info'
                );

                if (mapping) {
                    if (role === 'source') {
                        mapping.remove();
                        appendPendingSaveIndicator(itemCard);
                    } else {
                        mapping.replaceWith(createPendingSaveIndicator());
                    }
                    itemCard.classList.remove('is-mapped', 'is-mapping-active', 'is-mapping-disabled', 'is-mapping-error');
                }
            });
            clearSelection();
        }
    });
    updateBulkControls();
    children.addEventListener('click', (event) => {
        const disconnect = event.target.closest('[data-role="unmap-category"]');
        const disconnectCard = disconnect && disconnect.closest('.vec-node-card');
        const disconnectMapping = disconnectCard && disconnectCard.querySelector('.vec-magento-mapping.is-mapped');
        const action = event.target.closest('[data-role="category-active-toggle"]');
        const card = action && action.closest('[data-category-code]');
        const category = card && visibilityCategories.find(({code}) => code === card.dataset.categoryCode);

        if (disconnectMapping) {
            const save = panel.querySelector('[data-role="save-categories"]');

            disconnectMapping.replaceWith(createPendingSaveIndicator());
            disconnectCard.classList.remove(
                'is-mapped',
                'is-mapping-active',
                'is-mapping-disabled',
                'is-mapping-error'
            );
            disconnect.remove();
            if (save) {
                save.disabled = false;
            }
            return;
        }

        if (!card || !category) {
            return;
        }
        if (role === 'source') {
            productionSubtreeMapping.setBranchActive(visibilityCategories, category.code, !category.active);
            visibilityCategories.forEach((item) => {
                const itemCard = children.querySelector(`[data-category-code="${item.code}"]`);
                const itemAction = itemCard.querySelector('[data-role="category-active-toggle"]');

                itemCard.classList.toggle('is-blocked', !item.active);
                itemCard.dataset.categoryActive = item.active ? 'true' : 'false';
                syncVisibilityAction(itemAction, item.active);
            });
        } else {
            category.active = !category.active;
            card.classList.toggle('is-blocked', !category.active);
            card.dataset.categoryActive = category.active ? 'true' : 'false';
            syncVisibilityAction(action, category.active);
            syncMagentoExcludedPresentation(card, !category.active);
        }
        updateBulkControls();
    });
    if (rootItem) {
        const state = {};
        const root = document.createElement('div');
        const row = document.createElement('div');
        const toggle = document.createElement('button');
        const card = document.createElement('div');
        const copy = document.createElement('span');
        const mapping = createMappingIndicator(
            role === 'source' ? 'vec-source-mapping' : 'vec-magento-mapping is-mapped',
            rootItem.mappingLabel,
            rootItem.mappingCode,
            role === 'target' ? 'This element is already mapped.' : ''
        );

        root.className = 'vec-tree-node vec-configured-root-node';
        row.className = 'vec-node-row';
        toggle.type = 'button';
        toggle.className = 'vec-tree-toggle';
        toggle.dataset.role = `${role}-root-toggle`;
        toggle.setAttribute('aria-label', `Zwiń ${rootItem.label}`);
        toggle.setAttribute('aria-expanded', 'true');
        toggle.append(Object.assign(document.createElement('span'), { className: 'vec-tree-toggle-icon' }));
        card.className = `vec-node-card has-bulk-selection ${role === 'source'
            ? 'vec-ergo-card vec-configured-root-card is-mapped'
            : 'vec-magento-card is-configured-root is-mapped is-mapping-active'}`;
        card.dataset.bulkIdentifier = rootItem.identifier;
        card.dataset.categoryCode = rootItem.identifier;
        if (role === 'target') {
            card.dataset.mappingState = 'active';
        }
        copy.className = 'vec-card-copy';
        copy.append(Object.assign(document.createElement(role === 'target' ? 'span' : 'strong'), {
            className: role === 'target' ? 'vec-card-label' : '',
            textContent: rootItem.label
        }));
        copy.append(role === 'target'
            ? createMagentoPath(rootItem.code, rootItem.identifier)
            : Object.assign(document.createElement('span'), { textContent: rootItem.code }));
        card.append(createBulkSelection(role, rootItem.identifier, rootItem.label), copy, mapping);
        if (role === 'target') {
            card.append(createMagentoCategoryOptions({ label: rootItem.label }));
        }
        toggle.addEventListener('click', () => {
            const expanded = productionTreeState.toggle(state, rootItem.code);

            configuredRootExpanded = expanded;
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            toggle.setAttribute('aria-label', `${expanded ? 'Zwiń' : 'Rozwiń'} ${rootItem.label}`);
            children.hidden = !expanded;
        });
        row.append(toggle, card);
        root.append(row, children);
        tree.append(root);
        configuredRootNode = root;
        syncMappedVisibility();
    } else {
        tree.append(children);
    }
    if (role === 'source') {
        tree.append(emptyState);
        emptyMappedToggle.addEventListener('click', () => {
            hideMapped = !hideMapped;
            syncMappedVisibility();
        });
        syncMappedVisibility();
    }
    panel.append(head, tree);

    return panel;
}

function createMagentoPath(path, fallbackIdentifier) {
    const segments = String(path || '').split('/').map((segment) => segment.trim()).filter(Boolean);
    const metadata = document.createElement('span');

    if (!segments.length) {
        segments.push(String(fallbackIdentifier || ''));
    }
    const current = segments.pop();
    const prefix = `/${segments.length ? `${segments.join('/')}/` : ''}`;

    metadata.className = 'vec-card-path';
    metadata.title = prefix + current;
    metadata.append(
        document.createTextNode(prefix),
        Object.assign(document.createElement('strong'), {
            className: 'vec-card-path-current',
            textContent: current
        })
    );

    return metadata;
}

function createToggle(name, label, hint, checked, disabled) {
    const field = document.createElement('label');
    const copy = document.createElement('span');
    const input = document.createElement('input');

    field.className = 'vec-new-mapping-toggle';
    copy.append(
        Object.assign(document.createElement('strong'), { textContent: label }),
        Object.assign(document.createElement('small'), { textContent: hint })
    );
    input.type = 'checkbox';
    input.name = name;
    input.value = '1';
    input.checked = checked;
    input.disabled = disabled;
    field.append(copy, input);

    return field;
}

function createSettings(args, scope) {
    const panel = document.createElement('aside');
    const head = document.createElement('div');
    const heading = document.createElement('div');
    const body = document.createElement('div');

    panel.className = 'veui-panel vec-panel vec-settings-panel';
    panel.dataset.columnRole = 'settings';
    head.className = 'veui-panel-head vec-panel-head vec-settings-head';
    const settingsTitle = Object.assign(document.createElement('strong'), { textContent: 'Konfiguracje drzew' });

    settingsTitle.className = 'veui-panel-title';
    heading.append(settingsTitle);
    head.append(heading);
    body.className = 'vec-settings-body';
    if (!args.hasConfiguration) {
        const empty = document.createElement('div');

        empty.className = 'vec-settings-empty';
        empty.textContent = 'Brak skonfigurowanych drzew kategorii.';
        body.append(empty);
        panel.append(head, body);

        return panel;
    }

    const list = document.createElement('div');
    list.className = 'vec-configuration-list';
    list.dataset.role = 'category-tree-configuration-list';
    list.setAttribute('role', 'list');
    list.setAttribute('aria-label', 'Konfiguracje drzew');
    treeOptions.forEach(({ id, value, label, root, active }) => {
        active = active && !args.allMappingsDisabled;
        const card = document.createElement('div');
        const handle = document.createElement('span');
        const link = document.createElement('a');
        const cardActions = document.createElement('span');
        const selected = args.selectedTree === value;

        card.className = `vec-configuration-card${selected ? ' is-selected' : ''}${active ? '' : ' is-disabled'}`;
        card.dataset.role = 'category-tree-configuration';
        card.dataset.categoryTreeId = String(id);
        card.dataset.treeCode = value;
        card.dataset.rootCategoryId = value === 'main-pl' ? '41' : '52';
        card.dataset.sortOrder = String(id - 1);
        card.dataset.isActive = active ? '1' : '0';
        card.dataset.removeMissing = value === 'main-pl' && args.removeMissing ? '1' : '0';
        card.draggable = !args.readonly;
        card.setAttribute('role', 'listitem');
        handle.className = 'vec-configuration-drag';
        link.className = 'vec-configuration-link';
        link.dataset.role = 'category-tree-configuration-link';
        link.href = `#category-tree-${id}`;
        link.setAttribute('aria-current', selected ? 'page' : 'false');
        const labelNode = Object.assign(document.createElement('strong'), { textContent: label });

        labelNode.dataset.role = 'configuration-option-label';
        link.append(
            labelNode,
            Object.assign(document.createElement('code'), { textContent: value }),
            Object.assign(document.createElement('span'), { textContent: root })
        );
        cardActions.className = 'vec-configuration-actions';
        if (!active) {
            const disabledInfo = document.createElement('span');
            const informationIcon = Object.assign(document.createElement('span'), { textContent: 'i' });
            const tooltip = document.createElement('span');
            const tooltipId = `storybook-configuration-disabled-hint-${id}`;

            disabledInfo.className = 'vec-configuration-disabled-info';
            disabledInfo.tabIndex = 0;
            disabledInfo.setAttribute('role', 'img');
            disabledInfo.setAttribute('aria-label', 'Synchronizacja jest wyłączona');
            disabledInfo.setAttribute('aria-describedby', tooltipId);
            informationIcon.className = 'vec-information-icon';
            informationIcon.setAttribute('aria-hidden', 'true');
            tooltip.className = 'vec-mapping-hint vec-configuration-disabled-tooltip';
            tooltip.id = tooltipId;
            tooltip.setAttribute('role', 'tooltip');
            tooltip.append(
                Object.assign(document.createElement('strong'), { textContent: 'Synchronizacja jest wyłączona' }),
                Object.assign(document.createElement('span'), {
                    textContent: 'Kategorie z tego drzewa nie będą synchronizowane z Magento. Włącz to połączenie w jego opcjach, aby rozpocząć synchronizację.'
                })
            );
            disabledInfo.append(informationIcon, tooltip);
            cardActions.append(disabledInfo);
        }
        if (!args.readonly) {
            const edit = productionEntityOptions.createAction({
                role: 'open-edit-mapping',
                className: 'vec-edit-configuration',
                iconClass: 'veui-section-navigation-icon veui-section-navigation-icon-attribution-pen',
                label: 'Edytuj mapowanie',
                ariaLabel: `Edytuj mapowanie ${label}`,
                attributes: {'aria-haspopup': 'dialog'}
            });
            const options = productionEntityOptions.create({
                actions: [edit],
                menuLabel: `Opcje drzewa: ${label}`
            });

            options.classList.add('vec-configuration-options');
            options.addEventListener('click', (event) => {
                const action = event.target.closest('.veui-entity-options-action');

                if (!action || action.disabled) {
                    return;
                }
            });
            cardActions.append(options);
        }
        card.append(handle, link);
        if (cardActions.childElementCount > 0) {
            card.append(cardActions);
        }
        list.append(card);
    });
    configurationOrder.bind(list, {
        save: (ids) => {
            list.dataset.savedOrder = ids.join(',');
            return Promise.resolve({ success: true });
        },
        successMessage: 'Zapisano kolejność.',
        failureMessage: 'Nie udało się zapisać kolejności.'
    }, scope);
    body.append(list);
    if (!args.readonly) {
        const create = Object.assign(document.createElement('button'), {
            type: 'button',
            textContent: 'Nowe mapowanie'
        });

        create.className = 'vec-new-mapping-secondary';
        create.dataset.role = 'open-new-mapping';
        create.setAttribute('aria-haspopup', 'dialog');
        body.append(create);
    }
    panel.append(head, body);

    return panel;
}

function createSynchronizationActions(root) {
    const actions = document.createElement('div');

    actions.className = 'veui-split-button veui-split-button-align-end';
    actions.dataset.role = 'synchronization-actions';
    actions.setAttribute('role', 'group');
    actions.setAttribute('aria-label', 'Updates');
    actions.innerHTML = `
        <button type="button" class="veui-button veui-button-toolbar veui-split-button-main"
                data-role="sync-category-trees" data-synchronization-action="sync" data-synchronization-scope="all">
            <span class="veui-sync-ergonode-icon" aria-hidden="true"></span><span>Sync</span>
        </button>
        <details class="veui-split-button-options" data-role="synchronization-action-options">
            <summary class="veui-split-button-toggle" role="button" aria-label="Refresh options">
                <span class="veui-split-button-toggle-icon" aria-hidden="true"></span>
            </summary>
            <div class="veui-split-button-menu" role="menu" aria-label="Refresh options">
                ${[['tree', 'Tree'], ['data', root.dataset.dataLabel || 'Names']].map(([scope, label]) => `
                    <div class="vec-sync-group" role="group" aria-label="${label}">
                        <strong class="vec-sync-group-label" aria-hidden="true">${label}</strong>
                        ${[
                            ['sync', 'sync-category-trees', 'Sync'],
                            ['reset-cursor-and-sync', 'reset-sync-cursor-and-sync', 'Sync (force)'],
                            ['reset-cursor', 'reset-sync-cursor', 'Cursor reset']
                        ].map(([action, role, text]) => `
                            <button type="button" class="veui-split-button-option" role="menuitem"
                                    data-role="${role}" data-synchronization-action="${action}"
                                    data-synchronization-scope="${scope}">
                                <span class="${action === 'reset-cursor' ? 'veui-reset-cursor-icon' : 'veui-sync-ergonode-icon'}"
                                      aria-hidden="true"></span><span>${text}</span>
                            </button>`).join('')}
                    </div>`).join('')}
            </div>
        </details>`;
    actions.querySelectorAll('[data-synchronization-action]').forEach((button) => {
        const label = button.querySelector('span:not([aria-hidden])');
        const hint = document.createElement('span');

        button.classList.add('vec-sync-action');
        button.setAttribute('aria-label', label.textContent);
        label.classList.add('vec-sync-action-label');
        hint.className = 'vec-mapping-hint vec-sync-hint';
        hint.setAttribute('role', 'tooltip');
        hint.dataset.role = 'sync-blocking-hint';
        hint.id = `category-sync-${button.dataset.synchronizationScope}-${button.dataset.synchronizationAction}-hint`;
        button.append(hint);
    });
    actions.addEventListener('click', (event) => {
        const action = event.target.closest('[data-synchronization-action]')
            ?.dataset.synchronizationAction;
        const settings = root.querySelector('.vec-settings-panel');
        const resetCursor = root.querySelector('[data-synchronization-action="reset-cursor"]');

        if (action === 'sync' || action === 'reset-cursor-and-sync') {
            settings.dataset.synchronized = 'true';
        }
        if (action === 'reset-cursor' || action === 'reset-cursor-and-sync') {
            resetCursor.disabled = true;
        }
    });

    return actions;
}

function createRootSelect(rootLabel) {
    const picker = document.createElement('div');
    const select = document.createElement('select');
    const trigger = document.createElement('button');
    const value = Object.assign(document.createElement('span'), {
        id: 'storybook-root-picker-value',
        textContent: rootOptions[0].label
    });
    const chevron = document.createElement('span');
    const panel = document.createElement('div');
    const error = Object.assign(document.createElement('span'), {
        textContent: 'Wybierz kategorię główną Magento.',
        hidden: true
    });

    picker.className = 'vec-root-picker';
    picker.dataset.role = 'mapping-root-picker';
    select.name = 'root_category_id';
    select.id = 'storybook-root-category-id';
    select.dataset.role = 'new-mapping-root-category-id';
    select.required = true;
    rootLabel.id = 'storybook-root-category-id-label';
    rootLabel.htmlFor = select.id;
    value.dataset.role = 'mapping-root-picker-value';
    chevron.className = 'vec-root-picker-chevron';
    chevron.setAttribute('aria-hidden', 'true');
    trigger.type = 'button';
    trigger.className = 'vec-root-picker-trigger';
    trigger.dataset.role = 'mapping-root-picker-trigger';
    trigger.setAttribute('role', 'combobox');
    trigger.setAttribute('aria-labelledby', rootLabel.id);
    trigger.setAttribute('aria-controls', 'storybook-root-picker-options');
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.hidden = true;
    trigger.append(value, chevron);
    panel.id = 'storybook-root-picker-options';
    panel.className = 'vec-root-picker-options';
    panel.dataset.role = 'mapping-root-picker-options';
    panel.setAttribute('role', 'listbox');
    panel.setAttribute('aria-labelledby', rootLabel.id);
    panel.hidden = true;
    rootOptions.forEach(({ value: optionValue, label, mapped = false }) => {
        const nativeOption = Object.assign(document.createElement('option'), {
            value: optionValue,
            textContent: label,
            disabled: mapped
        });
        const option = Object.assign(document.createElement('button'), { type: 'button' });

        option.className = `vec-root-picker-option${mapped ? ' is-mapped' : ''}`;
        option.dataset.role = 'mapping-root-picker-option';
        option.dataset.value = optionValue;
        option.setAttribute('role', 'option');
        option.setAttribute('aria-selected', optionValue === '' ? 'true' : 'false');
        option.setAttribute('aria-disabled', mapped ? 'true' : 'false');
        option.append(Object.assign(document.createElement('span'), { textContent: label }));
        if (mapped) {
            const hintId = `storybook-root-mapping-hint-${optionValue}`;
            const status = document.createElement('span');
            const hint = document.createElement('span');

            status.className = 'vec-root-picker-option-status';
            status.setAttribute('aria-hidden', 'true');
            status.append(Object.assign(document.createElement('span'), {
                className: 'vec-mapping-status-icon veui-connected-icon'
            }));
            hint.className = 'vec-mapping-hint';
            hint.id = hintId;
            hint.setAttribute('role', 'tooltip');
            hint.append(Object.assign(document.createElement('strong'), {
                textContent: 'This Store Group already has a mapping.'
            }));
            option.setAttribute('aria-describedby', hintId);
            option.append(status, hint);
        }
        select.append(nativeOption);
        panel.append(option);
    });
    error.className = 'vec-root-picker-error';
    error.dataset.role = 'mapping-root-picker-error';
    error.setAttribute('role', 'alert');
    picker.append(select, trigger, panel, error);

    return { element: picker, select };
}

function createNewMappingDialog() {
    const dialog = document.createElement('div');
    const form = document.createElement('form');
    const intro = Object.assign(document.createElement('p'), {
        textContent: 'Połącz drzewo Ergonode z katalogiem Magento.'
    });
    const treeField = document.createElement('div');
    const treeLabel = Object.assign(document.createElement('label'), { textContent: 'Drzewo Ergonode' });
    const treeHeading = document.createElement('span');
    const refresh = Object.assign(document.createElement('button'), { type: 'button', textContent: 'Refresh' });
    const treeSelect = document.createElement('select');
    const rootField = document.createElement('div');
    const rootLabel = Object.assign(document.createElement('label'), { textContent: 'Kategoria główna Magento' });
    const rootPicker = createRootSelect(rootLabel);
    const rootSelect = rootPicker.select;
    const actions = document.createElement('div');
    const remove = Object.assign(document.createElement('button'), { type: 'button', textContent: 'Usuń mapowanie' });
    const cancel = Object.assign(document.createElement('button'), { type: 'button', textContent: 'Anuluj' });
    const save = Object.assign(document.createElement('button'), { type: 'submit', textContent: 'Zapisz' });
    const deleteForm = document.createElement('form');
    const confirmDelete = Object.assign(document.createElement('button'), { type: 'submit' });

    dialog.className = 'vec-new-mapping-modal';
    dialog.dataset.role = 'new-mapping-modal';
    dialog.style.display = 'none';
    form.className = 'vec-new-mapping-form';
    form.dataset.role = 'new-mapping-form';
    form.append(
        Object.assign(document.createElement('input'), { type: 'hidden', name: 'category_tree_id', value: '0' }),
        Object.assign(document.createElement('input'), { type: 'hidden', name: 'sort_order', disabled: true }),
        Object.assign(document.createElement('input'), { type: 'hidden', name: 'tree_code', disabled: true }),
        Object.assign(document.createElement('input'), { type: 'hidden', name: 'root_category_id', disabled: true })
    );
    form.elements.category_tree_id.dataset.role = 'mapping-category-tree-id';
    form.elements.sort_order.dataset.role = 'mapping-sort-order';
    form.querySelector('input[name="tree_code"]').dataset.role = 'locked-tree-code';
    form.querySelector('input[name="root_category_id"]').dataset.role = 'locked-root-category-id';
    intro.className = 'vec-new-mapping-intro';
    intro.dataset.role = 'mapping-intro';
    treeField.className = 'vec-new-mapping-field';
    treeHeading.className = 'vec-new-mapping-field-heading';
    refresh.className = 'veui-button veui-button-toolbar vec-tree-options-refresh';
    refresh.dataset.role = 'refresh-tree-options';
    const treeActions = document.createElement('span');
    treeActions.className = 'vec-new-mapping-tree-actions';
    treeActions.dataset.role = 'mapping-tree-actions';
    treeActions.append(refresh);
    treeHeading.append(treeLabel, treeActions);
    treeSelect.name = 'tree_code';
    treeSelect.id = 'storybook-category-tree-code';
    treeLabel.htmlFor = treeSelect.id;
    treeSelect.required = true;
    treeSelect.dataset.role = 'new-mapping-tree-code';
    treeSelect.append(
        Object.assign(document.createElement('option'), { value: '', textContent: 'Wybierz drzewo' }),
        ...treeOptions.map(({ value, label }) => Object.assign(document.createElement('option'), {
            value,
            textContent: `${label} · ${value}`
        }))
    );
    treeField.append(
        treeHeading,
        treeSelect,
        Object.assign(document.createElement('small'), { textContent: 'Nazwa i kod pobrane z Ergonode.' }),
        Object.assign(document.createElement('span'), { hidden: true })
    );
    treeField.lastElementChild.dataset.role = 'tree-options-status';
    rootField.className = 'vec-new-mapping-field';
    rootField.append(
        rootLabel,
        rootPicker.element,
        Object.assign(document.createElement('small'), { textContent: 'Kategorie powstaną poniżej tego korzenia.' })
    );
    const notice = document.createElement('p');
    notice.className = 'vec-new-mapping-notice';
    notice.dataset.role = 'mapping-reactivation-notice';
    notice.setAttribute('role', 'status');
    notice.hidden = true;
    notice.textContent = 'Changes may have been skipped while this mapping was inactive. After saving, use '
        + '"Sync (force)" in the Sync menu to include earlier changes. '
        + 'This reprocesses all active mappings in the selected synchronization scope.';
    [['mapping-tree-summary', treeSelect], ['mapping-root-summary', rootPicker.element]].forEach(([role, field]) => {
        const summary = document.createElement('p');
        summary.className = 'vec-new-mapping-summary';
        summary.dataset.role = role;
        summary.hidden = true;
        field.after(summary);
    });
    actions.className = 'vec-new-mapping-actions';
    remove.className = 'veui-button veui-button-toolbar vec-new-mapping-delete';
    remove.dataset.role = 'delete-mapping';
    remove.hidden = true;
    cancel.className = 'veui-button veui-button-toolbar';
    cancel.dataset.role = 'cancel-new-mapping';
    save.className = 'veui-button veui-button-toolbar veui-button-primary';
    save.dataset.role = 'save-new-mapping';
    actions.append(remove, cancel, save);
    form.append(
        intro,
        createToggle('is_active', 'Aktywne', 'Uwzględniaj mapowanie podczas importu.', true, false),
        treeField,
        rootField,
        notice,
        actions
    );
    deleteForm.dataset.role = 'delete-category-tree-form';
    deleteForm.append(
        Object.assign(document.createElement('input'), { type: 'hidden', name: 'category_tree_id', value: '0' }),
        confirmDelete
    );
    deleteForm.elements.category_tree_id.dataset.role = 'delete-category-tree-id';
    confirmDelete.dataset.role = 'confirm-delete-mapping';
    confirmDelete.hidden = true;
    // Native submissions are the transport boundary of this modal fixture.
    [form, deleteForm].forEach((fixtureForm) => fixtureForm.addEventListener('submit', (event) => {
        event.preventDefault();
        fixtureForm.dataset.submitted = 'true';
    }));
    dialog.append(form, deleteForm);

    return dialog;
}

function renderMapping(args) {
    const root = document.createElement('div');
    root.dataset.dataLabel = args.dataLabel || 'Names';
    const scope = productionWorkspace.mount(root);
    const toolbar = document.createElement('div');
    const layout = document.createElement('div');
    const hasSelection = args.hasConfiguration && args.selectedTree !== 'none';
    const selectedOption = treeOptions.find(({ value }) => value === args.selectedTree) || treeOptions[0];
    const sourceItems = hasSelection
        ? [
            {
                label: 'Krzesła',
                identifier: 'chairs',
                code: ['success', 'subtree'].includes(args.state) ? 'chairs → #12' : 'chairs',
                mapped: true,
                mappingLabel: 'Krzesła',
                mappingCode: '#12'
            },
            {
                label: 'Krzesła biurowe',
                identifier: 'office-chairs',
                code: args.allMapped ? 'office-chairs → #15' : 'office-chairs',
                source_parent_code: 'chairs',
                mapped: args.allMapped,
                mappingLabel: args.allMapped ? 'Krzesła biurowe' : undefined,
                mappingCode: args.allMapped ? '#15' : undefined
            },
            {
                label: 'Stoły',
                identifier: 'tables',
                code: args.allMapped ? 'tables → #16' : 'tables · unmatched',
                mapped: args.allMapped,
                mappingLabel: args.allMapped ? 'Stoły' : undefined,
                mappingCode: args.allMapped ? '#16' : undefined
            }
        ]
        : [];
    const targetItems = hasSelection
        ? (args.mappingStates
            ? [
                {
                    label: 'Krzesła',
                    identifier: '12',
                    parentIdentifier: '41',
                    code: '1/41/12',
                    mappingLabel: 'Krzesła',
                    mappingCode: 'chairs',
                    mappingState: 'active'
                },
                {
                    label: 'Outlet',
                    identifier: '13',
                    parentIdentifier: '12',
                    code: '1/41/12/13',
                    mappingLabel: 'Outlet',
                    mappingCode: 'outlet',
                    mappingState: 'disabled'
                },
                {
                    label: 'Lampy',
                    identifier: '14',
                    parentIdentifier: '12',
                    code: '1/41/12/14',
                    mappingLabel: 'Lampy',
                    mappingCode: 'lamps',
                    mappingState: 'error',
                    syncMessage: 'Nie udało się zaktualizować kategorii Magento.'
                },
                {
                    label: 'Stoły',
                    identifier: '15',
                    parentIdentifier: '12',
                    code: '1/41/12/15',
                    mappingLabel: 'Stoły',
                    mappingCode: 'tables',
                    mappingState: 'pending'
                }
            ]
            : [
                {
                    label: 'Krzesła',
                    identifier: '12',
                    parentIdentifier: '41',
                    code: '1/41/12',
                    mappingLabel: 'Krzesła',
                    mappingCode: 'chairs'
                },
                { label: 'Outlet', identifier: '13', parentIdentifier: '12', code: '1/41/12/13' }
            ])
        : [];

    root.className = 'veui-workspace veui-workspace-viewbar vec-admin';
    toolbar.className = 'veui-toolbar veui-viewbar vec-toolbar';
    const categoryNavigation = createNavigation({
        currentSection: 'categories',
        allowedSections: ['attributes', 'products', 'categories', 'templates', 'languages']
    });
    toolbar.append(categoryNavigation);
    if (!args.readonly) {
        toolbar.append(createSynchronizationActions(root));
    }
    if (!['default', 'loading', 'saving'].includes(args.state)) {
        const message = document.createElement('div');
        const copy = {
            success: 'Auto-mapowanie zakończone. Database: 1 Name: 1 Unmatched: 1',
            subtree: 'Zakończono automatyczne mapowanie podkategorii. Połączone podkategorie: 1 Niedopasowane podkategorie: 1',
            conflict: 'Konflikt: niejednoznaczna nazwa „Krzesła” pod kategorią #2.',
            rateLimit: 'Ergonode osiągnęło limit zapytań. Spróbuj ponownie za 20 sekund.',
            transport: 'Ergonode API jest chwilowo niedostępne. Widoczne dane pozostają bez zmian.'
        };

        message.dataset.role = 'message';
        message.className = `veui-message vec-message ${['conflict', 'rateLimit', 'transport'].includes(args.state)
            ? 'vec-message-error'
            : 'vec-message-success'}`;
        message.innerHTML = '<span class="vec-message-dot" aria-hidden="true"></span>' +
            '<span data-role="message-text"></span>';
        root.append(message);
        productionMessages.create(root, {
            containerSelector: '[data-role="message"]',
            textSelector: '[data-role="message-text"]'
        }).show(['conflict', 'rateLimit', 'transport'].includes(args.state) ? 'error' : 'success', copy[args.state]);
    }
    layout.className = 'veui-layout vec-layout';
    layout.append(
        createPanel(
            'Category Tree',
            'source',
            sourceItems,
            hasSelection ? {
                label: selectedOption.label,
                identifier: `__configured_root__:${selectedOption.value}`,
                code: selectedOption.value,
                mappingLabel: selectedOption.root,
                mappingCode: '#41'
            } : null,
            hasSelection,
            args.state === 'loading',
            args.hideMapped
        ),
        createPanel(
            'Category Tree',
            'target',
            targetItems,
            hasSelection ? {
                identifier: '41',
                label: selectedOption.root,
                code: '1/41',
                mappingLabel: selectedOption.label,
                mappingCode: selectedOption.value
            } : null,
            hasSelection,
            false,
            false
        ),
        createSettings(args, scope)
    );
    root.append(toolbar, layout);
    const operationState = productionOperationState.create(root);

    scope.cleanup(operationState.destroy);
    if (args.state === 'loading' || args.state === 'saving') {
        operationState.start(args.state === 'saving' ? 'save' : 'preview');
    }
    productionEntityOptions.bind(scope, root);
    productionSynchronizationAvailability.bind(scope, root);
    const treeBlocker = !args.hasConfiguration || args.allMappingsDisabled
        ? 'Włącz co najmniej jedno mapowanie drzewa kategorii przed synchronizacją.'
        : '';
    productionSynchronizationAvailability.update(root, {
        tree: treeBlocker,
        data: args.dataSyncDisabled ? 'Synchronizacja danych kategorii jest wyłączona.' : treeBlocker
    });
    productionSynchronizationActions.bind(scope, root);
    if (!args.readonly) {
        root.append(createNewMappingDialog());
        productionNewMapping.init({ urls: {}, form_key: 'storybook' }, root, scope);
    }

    return root;
}

const meta = {
    id: 'ergo-v-014',
    title: 'Ergonode UI/Widoki/Kategorie/ERGO-V-014 · Mapowanie drzewa kategorii/Pełny widok',
    tags: ['autodocs'],
    render: renderMapping,
    args: {
        hasConfiguration: true,
        allMappingsDisabled: false,
        dataSyncDisabled: false,
        selectedTree: 'main-pl',
        removeMissing: false,
        readonly: false,
        mappingStates: false,
        allMapped: false,
        hideMapped: true,
        state: 'default'
    },
    argTypes: {
        selectedTree: { control: 'select', options: ['none', ...treeOptions.map(({ value }) => value)] },
        hasConfiguration: { control: 'boolean' },
        allMappingsDisabled: { control: 'boolean' },
        dataSyncDisabled: { control: 'boolean' },
        removeMissing: { control: 'boolean' },
        readonly: { control: 'boolean' },
        mappingStates: { control: 'boolean' },
        allMapped: { control: 'boolean' },
        hideMapped: { control: 'boolean' },
        state: { control: 'select', options: ['default', 'loading', 'saving', 'success', 'subtree', 'conflict', 'rateLimit', 'transport'] }
    }
};

export default meta;

export const Playground = {};

export const ZapisZMenuOpcji = {
    play: async ({ canvasElement }) => {
        const actions = canvasElement.querySelector('[data-role="category-mapping-actions"]');
        const save = within(actions).getByRole('button', { name: 'Zapisz mapowanie kategorii' });
        const options = within(actions).getByRole('button', { name: /Akcje kategorii: Magento/ });
        const saveBounds = save.getBoundingClientRect();
        const optionsBounds = options.getBoundingClientRect();

        await expect(save).toBeDisabled();
        await expect(getComputedStyle(save).opacity).toBe('1');
        await expect(getComputedStyle(save.querySelector('.vec-icon-save')).opacity).toBe('0.56');
        await expect(saveBounds.right).toBeCloseTo(optionsBounds.left, 0);
        await expect(saveBounds.top).toBeCloseTo(optionsBounds.top, 0);
        await expect(saveBounds.height).toBeCloseTo(optionsBounds.height, 0);
        await expect(getComputedStyle(save).backgroundColor).toBe('rgb(194, 65, 0)');
        await expect(getComputedStyle(options).backgroundColor).toBe(getComputedStyle(save).backgroundColor);
        await expect(getComputedStyle(options).color).toBe('rgb(255, 255, 255)');
        await userEvent.click(options);
        const autoConnect = within(actions).getByRole('button', { name: 'Auto Connect' });

        await expect(autoConnect).toBeVisible();
        await expect(actions.querySelector('.veui-split-button-menu').getBoundingClientRect().right)
            .toBeCloseTo(options.getBoundingClientRect().right, 0);
        options.focus();
        await userEvent.keyboard('{Escape}');
        await expect(autoConnect).not.toBeVisible();
        await expect(options).toHaveFocus();
        await userEvent.keyboard('{Enter}');
        await expect(autoConnect).toBeVisible();
        await userEvent.click(autoConnect);
        await expect(autoConnect).not.toBeVisible();
    }
};

export const OdswiezaniePrzyWyszukiwarce = {
    play: async ({ canvasElement }) => {
        const sourcePanel = canvasElement.querySelector('[data-column-role="source"]');
        const head = sourcePanel.querySelector('.veui-panel-head');
        const sourceTools = sourcePanel.querySelector('.vec-source-tools');
        const search = within(sourcePanel).getByRole('searchbox');
        const options = within(sourcePanel).getByRole('button', { name: /Akcje kategorii: Ergonode/ });

        await expect(search.closest('.veui-search')).toBeVisible();
        await expect(head).toContainElement(sourceTools);
        await expect(sourcePanel.querySelector(':scope > .veui-tools')).not.toBeInTheDocument();
        const searchBounds = search.closest('.veui-search').getBoundingClientRect();
        const optionsBounds = options.getBoundingClientRect();

        await expect(Math.abs(searchBounds.y - optionsBounds.y)).toBeLessThan(2);
        await expect(sourcePanel.querySelector('.vec-side-tree').getBoundingClientRect().top)
            .toBeCloseTo(head.getBoundingClientRect().bottom, 0);
        await userEvent.click(options);
        const refresh = within(sourceTools).getByRole('button', { name: 'Refresh', exact: true });

        await expect(refresh).toBeVisible();
        await expect(
            within(canvasElement.querySelector('.vec-toolbar')).queryByRole('button', { name: 'Refresh', exact: true })
        ).not.toBeInTheDocument();
        options.focus();
        await userEvent.keyboard('{Escape}');
        await expect(refresh).not.toBeVisible();
        await expect(options).toHaveFocus();
        await userEvent.keyboard('{Enter}');
        await expect(refresh).toBeVisible();
        await userEvent.keyboard('{Escape}');
        sourcePanel.style.width = '280px';
        try {
            search.focus();
            await waitFor(() => expect(search.closest('.veui-search').getBoundingClientRect().width)
                .toBeGreaterThan(100));
            await expect(options.getBoundingClientRect().right).toBeLessThan(head.getBoundingClientRect().right);
            await expect(sourcePanel.querySelector('.veui-panel-title-text').getBoundingClientRect().width).toBe(1);
        } finally {
            options.focus();
            sourcePanel.style.width = '';
        }
    }
};

export const RozwijaneWyszukiwanie = {
    play: async ({ canvasElement }) => {
        for (const role of ['source', 'target']) {
            const panel = canvasElement.querySelector(`[data-column-role="${role}"]`);
            const head = panel.querySelector('.veui-panel-head');
            const search = within(panel).getByRole('searchbox');
            const field = search.closest('.veui-search');
            const options = within(panel).getByRole('button', { name: /Akcje kategorii:/ });
            const width = () => field.getBoundingClientRect().width;

            await expect(panel.querySelector('.veui-count')).not.toBeInTheDocument();
            await expect(head).toContainElement(field);
            await expect(head).toContainElement(options);
            await expect(panel.querySelector('.vec-side-tree').getBoundingClientRect().top)
                .toBeCloseTo(head.getBoundingClientRect().bottom, 0);
            await waitFor(() => expect(width()).toBeCloseTo(34, 0));
            const fieldStyle = getComputedStyle(field);
            const optionsStyle = getComputedStyle(options);

            await expect(fieldStyle.borderTopWidth).toBe('1px');
            // Magento uses a primary split button; only the neutral source
            // options button shares the search field's visual treatment.
            if (role === 'source') {
                await expect(fieldStyle.borderTopColor).toBe(optionsStyle.borderTopColor);
                await expect(fieldStyle.borderRadius).toBe(optionsStyle.borderRadius);
                await expect(fieldStyle.backgroundColor).toBe(optionsStyle.backgroundColor);
            }
            await expect(fieldStyle.borderTopStyle).toBe('solid');
            if (role === 'target') {
                const save = within(panel).getByRole('button', { name: 'Zapisz mapowanie kategorii' });

                await expect(head).toContainElement(save);
                await expect(save).toBeDisabled();
                await expect(field.getBoundingClientRect().right).toBeLessThan(save.getBoundingClientRect().left);
                await expect(save.getBoundingClientRect().right).toBeLessThanOrEqual(options.getBoundingClientRect().left);
            }
            search.focus();
            await expect(search).toHaveFocus();
            await waitFor(() => expect(width()).toBeGreaterThan(100));
            await userEvent.type(search, 'Women');
            options.focus();
            await expect(options).toHaveFocus();
            await expect(search).toHaveValue('Women');
            await waitFor(() => expect(width()).toBeGreaterThan(100));
            await userEvent.clear(search);
            options.focus();
            await waitFor(() => expect(width()).toBeCloseTo(34, 0));
        }
    }
};

export const BrakKonfiguracji = {
    args: { hasConfiguration: false },
    play: async ({ canvasElement }) => {
        const update = within(canvasElement).getByRole('button', {name: 'Sync'});

        await expect(update).toHaveAttribute('aria-disabled', 'true');
        await expect(update).toHaveAccessibleDescription(
            'Włącz co najmniej jedno mapowanie drzewa kategorii przed synchronizacją.'
        );
        await userEvent.click(update);
        await expect(canvasElement.querySelector('.vec-settings-panel')).not.toHaveAttribute('data-synchronized');
    }
};

export const WszystkieMapowaniaWylaczone = {
    args: {allMappingsDisabled: true},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const update = canvas.getByRole('button', {name: 'Sync'});
        const hint = update.querySelector('[role="tooltip"]');
        const settings = canvasElement.querySelector('.vec-settings-panel');

        await expect(update).toHaveAttribute('aria-disabled', 'true');
        await expect(getComputedStyle(update.querySelector('.vec-sync-action-label')).opacity).toBe('0.56');
        await expect(getComputedStyle(hint).whiteSpace).toBe('normal');
        await expect(hint).not.toBeVisible();
        update.focus();
        await expect(update).toHaveFocus();
        await waitFor(() => expect(hint).toBeVisible());
        await expect(update).toHaveAccessibleDescription(
            'Włącz co najmniej jedno mapowanie drzewa kategorii przed synchronizacją.'
        );
        await userEvent.keyboard('{Enter} ');
        await userEvent.click(update);
        await expect(settings).not.toHaveAttribute('data-synchronized');
        await userEvent.keyboard('{Escape}');
        await waitFor(() => expect(hint).not.toBeVisible());
        await userEvent.click(canvas.getByRole('button', {name: 'Refresh options'}));
        for (const group of canvasElement.querySelectorAll('.vec-sync-group')) {
            for (const label of ['Sync', 'Sync (force)']) {
                const action = within(group).getByRole('menuitem', {name: label});

                await expect(action).toHaveAttribute('aria-disabled', 'true');
                await userEvent.click(action);
                await expect(settings).not.toHaveAttribute('data-synchronized');
            }
            await expect(within(group).getByRole('menuitem', {name: 'Cursor reset'}))
                .toHaveAttribute('aria-disabled', 'false');
        }
    }
};

export const PonowneWlaczenieSynchronizacji = {
    args: {allMappingsDisabled: true},
    play: async ({canvasElement}) => {
        const update = within(canvasElement).getByRole('button', {name: 'Sync'});

        await expect(update).toHaveAttribute('aria-disabled', 'true');
        productionSynchronizationAvailability.update(canvasElement, {tree: '', data: ''});
        await expect(update).toHaveAttribute('aria-disabled', 'false');
        await expect(update).not.toHaveAttribute('aria-describedby');
        await userEvent.click(update);
        await expect(canvasElement.querySelector('.vec-settings-panel')).toHaveAttribute('data-synchronized', 'true');
    }
};

export const WylaczoneDaneKategorii = {
    args: {dataSyncDisabled: true},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);

        await expect(canvas.getByRole('button', {name: 'Sync'})).toHaveAttribute('aria-disabled', 'false');
        await userEvent.click(canvas.getByRole('button', {name: 'Refresh options'}));
        const data = canvasElement.querySelector('[data-synchronization-scope="data"]');
        const tree = canvasElement.querySelector('[data-synchronization-scope="tree"]');

        await expect(data).toHaveAttribute('aria-disabled', 'true');
        await expect(data).toHaveAccessibleDescription('Synchronizacja danych kategorii jest wyłączona.');
        await expect(tree).toHaveAttribute('aria-disabled', 'false');
    }
};

export const BezWybranejKonfiguracji = {
    args: { selectedTree: 'none' }
};

export const TylkoDoOdczytu = {
    args: { readonly: true },
    play: async ({ canvasElement }) => {
        const targetPanel = within(canvasElement.querySelector('.vec-target-panel'));
        const settingsPanel = within(canvasElement.querySelector('.vec-settings-panel'));

        await userEvent.click(targetPanel.getByRole('button', { name: /Akcje kategorii: Magento/ }));
        await expect(targetPanel.queryByRole('button', { name: 'Sync' })).not.toBeInTheDocument();
        await expect(settingsPanel.queryByRole('button', { name: /Opcje drzewa:/ })).not.toBeInTheDocument();
    }
};

export const WylaczonePolaczenie = {
    args: { selectedTree: 'main-en' },
    play: async ({ canvasElement }) => {
        const card = canvasElement.querySelector('[data-tree-code="main-en"]');
        const info = within(card).getByRole('img', { name: 'Synchronizacja jest wyłączona' });
        const tooltip = within(card).getByRole('tooltip', {hidden: true});

        await expect(within(canvasElement).getByRole('button', {name: 'Sync'}))
            .toHaveAttribute('aria-disabled', 'false');
        await expect(card).toHaveClass('is-selected', 'is-disabled');
        await expect(getComputedStyle(card).backgroundColor).toBe('rgb(238, 242, 247)');
        await expect(canvasElement.querySelector('.vec-settings-head .veui-panel-actions')).not.toBeInTheDocument();
        await expect(within(card).queryByRole('button', { name: /Przenieś/ })).not.toBeInTheDocument();
        await expect(tooltip).not.toBeVisible();
        info.focus();
        await expect(info).toHaveFocus();
        await waitFor(() => expect(tooltip).toBeVisible());
        await expect(tooltip).toHaveTextContent(
            'Kategorie z tego drzewa nie będą synchronizowane z Magento. Włącz to połączenie w jego opcjach, aby rozpocząć synchronizację.'
        );
    }
};

export const NoweMapowanieWPopupie = {
    tags: ['mapping-popup'],
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);

        await userEvent.click(canvas.getAllByRole('button', { name: 'Nowe mapowanie' })[0]);
        const dialog = canvas.getByRole('dialog', { name: 'New Mapping' });
        const dialogCanvas = within(dialog);
        const rootSelect = dialogCanvas.getByRole('combobox', { name: 'Kategoria główna Magento' });

        await expect(dialog).toBeVisible();
        await expect(dialog.tagName).toBe('DIV');
        await expect(dialogCanvas.getByRole('option', { name: 'Main PL · main-pl' })).toBeVisible();
        await expect(dialogCanvas.getByRole('button', { name: 'Refresh' })).toBeVisible();
        await expect(dialogCanvas.getByRole('button', { name: 'Zapisz' })).toBeVisible();
        await userEvent.click(rootSelect);
        const occupiedRoot = dialogCanvas.getByRole('option', { name: 'PLN Root (#41)' });
        const availableRoot = dialogCanvas.getByRole('option', { name: 'Outlet Root (#63)' });

        await expect(occupiedRoot).toHaveAttribute('aria-disabled', 'true');
        await expect(occupiedRoot.querySelector('.vec-mapping-status-icon')).toBeInTheDocument();
        await userEvent.click(occupiedRoot);
        await expect(occupiedRoot).toHaveFocus();
        await waitFor(() => expect(within(occupiedRoot).getByRole('tooltip')).toBeVisible());
        await userEvent.keyboard('{ArrowDown}{ArrowDown}');
        await expect(availableRoot).toHaveFocus();
        await userEvent.keyboard('{Enter}');
        await expect(rootSelect).toHaveTextContent('Outlet Root (#63)');
        await userEvent.click(dialogCanvas.getByRole('button', { name: 'Anuluj' }));
        await expect(dialog).not.toBeVisible();
    }
};

export const EdycjaMapowaniaWPopupie = {
    tags: ['mapping-popup'],
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);

        await userEvent.click(canvas.getByRole('button', { name: 'Opcje drzewa: Main PL' }));
        await userEvent.click(canvas.getByRole('button', { name: 'Edytuj mapowanie Main PL' }));
        const dialog = canvas.getByRole('dialog', { name: 'Edit Mapping' });
        const dialogCanvas = within(dialog);

        await expect(dialog).toBeVisible();
        await expect(dialogCanvas.queryByRole('combobox')).not.toBeInTheDocument();
        await expect(dialog.querySelector('[data-role="mapping-tree-summary"]')).toHaveTextContent('Main PL · main-pl');
        await expect(dialog.querySelector('[data-role="mapping-root-summary"]')).toHaveTextContent('PLN Root (#41)');
        const values = new FormData(dialog.querySelector('form'));
        await expect(values.get('tree_code')).toBe('main-pl');
        await expect(values.get('root_category_id')).toBe('41');
        await expect(dialogCanvas.queryByRole('button', { name: 'Refresh' })).not.toBeInTheDocument();
        await expect(dialogCanvas.queryByText('Kursor zmian')).not.toBeInTheDocument();
        await expect(dialogCanvas.queryByText('Ostatnia pobrana zmiana')).not.toBeInTheDocument();
        await expect(dialogCanvas.queryByRole('button', { name: 'Resetuj kursor' })).not.toBeInTheDocument();
        await expect(dialogCanvas.getByRole('button', { name: 'Usuń mapowanie' })).toBeVisible();
        await expect(dialogCanvas.getByRole('checkbox', { name: /Aktywne/ })).toBeChecked();
    }
};

export const AutoMapowanieWToku = {
    tags: ['category-auto-connect'],
    args: { state: 'loading' },
    play: async ({canvasElement}) => {
        const status = within(canvasElement).getByRole('status');

        await expect(status).toBeVisible();
        await expect(status).toHaveTextContent('Łączenie kategorii…');
        await expect(status.querySelector('.vec-operation-spinner')).toBeVisible();
        await expect(canvasElement.querySelector('.vec-layout')).toHaveAttribute('inert');
        await expect(canvasElement.querySelector('.vec-layout')).toHaveAttribute('aria-busy', 'true');
        await expect(canvasElement.querySelector('.vec-toolbar')).toHaveAttribute('inert');
    }
};

export const AutoMapowanieLoaderKlawiatura = {
    tags: ['category-auto-connect'],
    play: async ({canvasElement}) => {
        const root = canvasElement.querySelector('.vec-admin');
        const operationState = productionOperationState.create(root);
        const trigger = root.querySelector('[data-bulk-options-source="magento"] > summary');

        trigger.focus();
        await userEvent.keyboard('{Enter}');
        const autoConnect = within(root).getByRole('button', {name: 'Auto Connect'});

        autoConnect.addEventListener('click', () => operationState.start(), {once: true});
        autoConnect.focus();
        await userEvent.keyboard('{Enter}');
        const status = within(root).getByRole('status');

        await expect(status).toBeVisible();
        await expect(status).toHaveFocus();
        await expect(operationState.start()).toBe(false);
        operationState.finish();
        await expect(status).not.toBeVisible();
        await expect(root.querySelector('.vec-layout')).not.toHaveAttribute('inert');
        await expect(trigger).toHaveFocus();
        operationState.destroy();
    }
};

export const ZapisywanieMapowan = {
    tags: ['category-save'],
    args: { state: 'saving' },
    play: async ({canvasElement}) => {
        const status = within(canvasElement).getByRole('status');

        await expect(status).toHaveTextContent('Zapisywanie mapowań kategorii…');
        await expect(status).toHaveClass('is-saving');
        await expect(status.querySelector('.vec-operation-spinner')).toBeVisible();
        await expect(canvasElement.querySelector('.vec-layout')).toHaveAttribute('inert');
        await expect(canvasElement.querySelector('.vec-toolbar')).toHaveAttribute('aria-busy', 'true');
    }
};

export const ZapisywanieBlokadaIKlawiatura = {
    tags: ['category-save'],
    play: async ({canvasElement}) => {
        const root = canvasElement.querySelector('.vec-admin');
        const state = productionOperationState.create(root);
        const save = root.querySelector('[data-role="save-categories"]');

        save.disabled = false;
        save.addEventListener('click', () => state.start('save'));
        await userEvent.click(save);
        const status = within(root).getByRole('status');

        await expect(status).toHaveFocus();
        await expect(state.start()).toBe(false);
        // Failed save: the draft can be edited or retried, and focus returns to Save.
        state.finish();
        await expect(save).toHaveFocus();
        await expect(root.querySelector('.vec-layout')).not.toHaveAttribute('inert');
        await userEvent.keyboard('{Enter}');
        await expect(status).toHaveFocus();
        // Successful save: Save is disabled, so focus returns to the workspace.
        save.disabled = true;
        state.finish();
        await expect(root).toHaveFocus();
        await expect(status).not.toBeVisible();
        await expect(root.querySelector('.vec-toolbar')).not.toHaveAttribute('aria-busy');
        state.destroy();
    }
};

export const SukcesZElementemNiedopasowanym = { args: { state: 'success' } };

export const MapowanieKorzeni = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const sourceToggle = canvas.getByRole('button', { name: 'Zwiń Main PL' });

        await expect(canvas.getAllByText('main-pl').length).toBeGreaterThanOrEqual(2);
        await expect(canvas.getAllByText('PLN Root (#41)').length).toBeGreaterThanOrEqual(2);
        await userEvent.click(sourceToggle);
        await expect(sourceToggle).toHaveAttribute('aria-expanded', 'false');
        await userEvent.click(sourceToggle);
        await expect(sourceToggle).toHaveAttribute('aria-expanded', 'true');
    }
};

export const WskaznikiZmapowanychKategorii = {
    play: async ({ canvasElement }) => {
        const targetPanel = canvasElement.querySelector('.vec-target-panel');
        const sourceIndicator = canvasElement.querySelector(
            '.vec-source-panel .vec-mapping-indicator'
        );
        const targetIndicator = canvasElement.querySelector(
            '.vec-target-panel .vec-magento-mapping.is-mapped'
        );
        const availableTarget = targetPanel.querySelector('[data-category-code="13"]');
        const sourceHint = sourceIndicator.querySelector('[role="tooltip"]');
        const targetHint = targetIndicator.querySelector('[role="tooltip"]');

        await expect(sourceIndicator).toBeVisible();
        await expect(sourceIndicator).toHaveAttribute('aria-label', 'PLN Root (#41) · #41');
        await expect(sourceIndicator).not.toHaveAttribute('title');
        await expect(sourceIndicator.querySelector('.vec-mapping-status-icon')).toBeInTheDocument();
        await expect(sourceHint).not.toBeVisible();
        sourceIndicator.focus();
        await expect(sourceIndicator).toHaveFocus();
        await waitFor(() => expect(sourceHint).toBeVisible());
        await expect(sourceHint).toHaveTextContent('PLN Root (#41)');
        await expect(sourceHint).toHaveTextContent('#41');
        await expect(targetIndicator).toBeVisible();
        await expect(targetIndicator).toHaveAttribute(
            'aria-label',
            'Main PL · main-pl · This element is already mapped.'
        );
        await expect(targetIndicator).not.toHaveAttribute('title');
        await expect(targetIndicator.querySelector('.vec-mapping-status-icon')).toBeInTheDocument();
        targetIndicator.focus();
        await waitFor(() => expect(sourceHint).not.toBeVisible());
        await expect(targetIndicator).toHaveFocus();
        await waitFor(() => expect(targetHint).toBeVisible());
        await expect(targetHint).toHaveTextContent('Main PL');
        await expect(targetHint).toHaveTextContent('main-pl');
        await expect(availableTarget).toHaveAttribute('data-drop-zone', 'magento-target');
        await expect(availableTarget).not.toHaveClass('is-mapped');
        await expect(getComputedStyle(availableTarget).backgroundColor).toBe('rgba(0, 0, 0, 0)');
        await expect(getComputedStyle(availableTarget.querySelector('.vec-card-copy strong')).color)
            .toBe('rgb(100, 116, 139)');
    }
};

export const StanyMapowanychKategoriiMagento = {
    args: { mappingStates: true },
    play: async ({ canvasElement }) => {
        const targetPanel = canvasElement.querySelector('.vec-target-panel');
        const active = targetPanel.querySelector('[data-mapping-state="active"]:not(.is-configured-root)');
        const disabled = targetPanel.querySelector('[data-mapping-state="disabled"]');
        const error = targetPanel.querySelector('[data-mapping-state="error"]');
        const pending = targetPanel.querySelector('[data-mapping-state="pending"]');
        const errorIndicator = error.querySelector('.vec-mapping-indicator');
        const errorHint = errorIndicator.querySelector('[role="tooltip"]');
        const availableTarget = targetPanel.querySelector('[data-drop-zone="magento-target"]');

        await expect(active).toHaveClass('is-mapped', 'is-mapping-active');
        await expect(pending).toHaveClass('is-mapped', 'is-mapping-pending');
        await expect(pending.querySelector('.veui-connected-icon')).toBeNull();
        const pendingIndicator = within(pending).getByRole('button', { name: 'Mapowanie oczekuje na zapis' });
        await userEvent.click(pendingIndicator);
        await waitFor(() => expect(pending.querySelector('[role="tooltip"]')).toBeVisible());
        pendingIndicator.blur();
        pendingIndicator.focus();
        await waitFor(() => expect(pending.querySelector('[role="tooltip"]')).toBeVisible());
        await expect(active).not.toHaveAttribute('data-drop-zone');
        await expect(active.querySelector('.vec-mapping-status-icon')).toBeInTheDocument();
        await expect(active.querySelector('.vec-card-path')).toHaveTextContent('/1/41/12');
        await expect(active.querySelector('.vec-card-path-current')).toHaveTextContent('12');
        await expect(active.querySelector('.vec-card-path-current').tagName).toBe('STRONG');
        await expect(active.querySelector('.vec-card-path')).not.toHaveTextContent('#12');
        await expect(getComputedStyle(active).backgroundColor).toBe('rgba(0, 0, 0, 0)');
        await expect(disabled).toHaveClass('is-mapped', 'is-mapping-disabled', 'is-blocked');
        await expect(error).toHaveClass('is-mapped', 'is-mapping-error', 'has-mapping-error');
        await expect(availableTarget).toBeNull();
        await expect(errorIndicator).toHaveAttribute(
            'aria-label',
            'Lampy · lamps · Mapping error · Nie udało się zaktualizować kategorii Magento.'
        );
        errorIndicator.focus();
        await waitFor(() => expect(errorHint).toBeVisible());
        await expect(errorHint).toHaveTextContent(
            'Nie udało się zaktualizować kategorii Magento.'
        );
    }
};

export const AutoMapowaniePoddrzewaPoDnD = {
    args: { state: 'subtree', hideMapped: false },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);

        await expect(canvas.getByText(/Zakończono automatyczne mapowanie podkategorii/)).toBeVisible();
        await expect(canvas.getByText('chairs → #12')).toBeVisible();
    }
};

export const BrakTworzeniaKategoriiMagentoWLewymDrzewie = {
    play: async ({ canvasElement }) => {
        const sourcePanel = within(canvasElement.querySelector('.vec-source-panel'));
        const options = sourcePanel.getByRole('button', { name: 'Opcje kategorii: Stoły' });
        const categoryOptions = within(options.closest('details'));

        await userEvent.click(options);
        await expect(categoryOptions.queryByRole('button', { name: /Utwórz.*Magento/ })).not.toBeInTheDocument();
        await expect(categoryOptions.getByRole('button', { name: 'Usuń z tej listy' })).toBeVisible();
        await expect(categoryOptions.getByRole('button', { name: 'Exclude' })).toBeVisible();
    }
};

export const DomyslnieUkryteZmapowaneKategorieErgonode = {
    play: async ({ canvasElement }) => {
        const sourcePanelElement = canvasElement.querySelector('.vec-source-panel');
        const sourcePanel = within(sourcePanelElement);
        const mappedCard = sourcePanelElement.querySelector('[data-category-code="chairs"]');
        const mappedRootCard = sourcePanelElement.querySelector('.vec-configured-root-card');
        const unmappedChild = sourcePanel.getByText('Krzesła biurowe').closest('.vec-node-card');
        const toggle = sourcePanelElement.querySelector('[data-role="mapped-visibility-toggle"]');

        await expect(toggle).toHaveAttribute('aria-pressed', 'true');
        await expect(toggle).toHaveAccessibleName('Pokaż połączone kategorie');
        await expect(mappedCard).toBeVisible();
        await expect(mappedCard).toHaveClass('is-mapped-parent');
        await expect(mappedRootCard).toBeVisible();
        await expect(mappedRootCard).toHaveClass('is-mapped-parent');
        await expect(unmappedChild).toBeVisible();

        await userEvent.click(sourcePanel.getByRole('button', { name: /Akcje kategorii: Ergonode/ }));
        const mappedLabel = toggle.querySelector('.veui-entity-options-action-label');
        const connectedIcon = toggle.querySelector('.vec-connected-icon');
        const connectedStyle = getComputedStyle(connectedIcon);

        await expect(toggle.querySelector('.veui-visibility-icon')).not.toBeInTheDocument();
        await expect(toggle.querySelectorAll('.vec-connected-icon')).toHaveLength(1);
        await expect(connectedIcon).toBeVisible();
        await expect(connectedStyle.maskImage).toContain(new URL(connectedIconUrl, document.baseURI).href);
        await expect(connectedStyle.width).toBe('18px');
        await expect(getComputedStyle(mappedLabel, '::before').content).toBe('none');
        await userEvent.click(toggle);

        await expect(toggle).toHaveAttribute('aria-pressed', 'false');
        await expect(toggle).toHaveAccessibleName('Ukryj połączone kategorie');
        await expect(mappedCard).toBeVisible();
        await expect(mappedCard).not.toHaveClass('is-mapped-parent');
        await expect(mappedRootCard).toBeVisible();
        await expect(mappedRootCard).not.toHaveClass('is-mapped-parent');

        await userEvent.click(sourcePanel.getByRole('button', { name: /Akcje kategorii: Ergonode/ }));
        toggle.focus();
        await userEvent.keyboard('{Enter}');
        await expect(toggle).toHaveAttribute('aria-pressed', 'true');
        await expect(toggle).toHaveAccessibleName('Pokaż połączone kategorie');
        await expect(mappedCard).toHaveClass('is-mapped-parent');
        await expect(getComputedStyle(connectedIcon).maskImage).toBe(connectedStyle.maskImage);
        await expect(toggle.querySelector('.veui-visibility-icon')).not.toBeInTheDocument();
    }
};

export const WszystkieKategorieErgonodeSaZmapowane = {
    args: { allMapped: true },
    play: async ({ canvasElement }) => {
        const sourcePanelElement = canvasElement.querySelector('.vec-source-panel');
        const sourcePanel = within(sourcePanelElement);
        const emptyToggle = sourcePanelElement.querySelector('.vec-empty-mapped-toggle');

        await expect(sourcePanel.getByText('Brak kategorii. Wszystkie kategorie są już zmapowane.')).toBeVisible();
        await expect(emptyToggle).toHaveAccessibleName('Pokaż połączone kategorie');
        await expect(emptyToggle).toHaveTextContent('Połączone');
        await expect(sourcePanelElement.querySelector('[data-category-code="chairs"]')).not.toBeVisible();

        await userEvent.click(emptyToggle);

        await expect(sourcePanel.queryByText('Brak kategorii. Wszystkie kategorie są już zmapowane.'))
            .not.toBeVisible();
        await expect(sourcePanelElement.querySelector('[data-category-code="chairs"]')).toBeVisible();
    }
};

export const UsuwanieKategoriiZListyZMenu = {
    args: { hideMapped: false },
    play: async ({ canvasElement }) => {
        const sourcePanel = within(canvasElement.querySelector('.vec-source-panel'));
        const options = sourcePanel.getByRole('button', { name: 'Opcje kategorii: Krzesła' });
        const categoryOptions = within(options.closest('details'));

        await userEvent.click(options);
        await expect(categoryOptions.getByRole('button', { name: 'Usuń z tej listy' })).toBeVisible();
        await expect(categoryOptions.getByRole('button', { name: 'Exclude' })).toBeVisible();
    }
};

export const KaskadoweWykluczanieGaleziErgonode = {
    args: { hideMapped: false },
    play: async ({ canvasElement }) => {
        const sourcePanel = within(canvasElement.querySelector('.vec-source-panel'));
        const parentOptions = sourcePanel.getByRole('button', { name: 'Opcje kategorii: Krzesła' });
        const childOptions = sourcePanel.getByRole('button', { name: 'Opcje kategorii: Krzesła biurowe' });
        const siblingOptions = sourcePanel.getByRole('button', { name: 'Opcje kategorii: Stoły' });

        await userEvent.click(parentOptions);
        await userEvent.click(within(parentOptions.closest('details')).getByRole('button', {
            name: 'Exclude'
        }));
        await expect(parentOptions.closest('.vec-node-card')).toHaveClass('is-blocked');
        await expect(childOptions.closest('.vec-node-card')).toHaveClass('is-blocked');
        await expect(siblingOptions.closest('.vec-node-card')).not.toHaveClass('is-blocked');

        const sourceActions = canvasElement.querySelector('[data-bulk-options-source="ergo"]');
        await userEvent.click(sourceActions.querySelector('summary'));
        await userEvent.click(sourceActions.querySelector('[data-role="visibility-toggle"]'));
        const currentChildOptions = sourcePanel.getByRole('button', {name: 'Opcje kategorii: Krzesła biurowe'});
        await userEvent.click(currentChildOptions);
        await expect(within(currentChildOptions.closest('details')).getByRole('button', {
            name: 'Include'
        })).toBeVisible();
    }
};

export const MenuMapowaniaMagentoBezUsuwaniaZListy = {
    play: async ({ canvasElement }) => {
        const targetPanel = within(canvasElement.querySelector('.vec-target-panel'));
        const options = targetPanel.getByRole('button', { name: 'Opcje kategorii: Krzesła' });
        const categoryOptions = within(options.closest('details'));

        await userEvent.click(options);
        const unlink = categoryOptions.getByRole('button', { name: 'Disconnect' });
        await expect(unlink).toBeVisible();
        await expect(unlink.querySelector('.vec-unmap-icon')).toBeInTheDocument();
        await expect(categoryOptions.queryByRole('button', {
            name: 'Usuń z tej listy'
        })).not.toBeInTheDocument();
        await expect(categoryOptions.getByRole('button', { name: 'Exclude' })).toBeVisible();
    }
};

export const OdlaczenieMapowaniaOczekujeNaZapis = {
    play: async ({ canvasElement }) => {
        const targetPanelElement = canvasElement.querySelector('.vec-target-panel');
        const targetPanel = within(targetPanelElement);
        const options = targetPanel.getByRole('button', { name: 'Opcje kategorii: Krzesła' });

        await userEvent.click(options);
        await userEvent.click(within(options.closest('details')).getByRole('button', { name: 'Disconnect' }));
        const pendingSave = targetPanel.getByRole('button', { name: 'Mapowanie oczekuje na zapis' });
        const tooltip = pendingSave.querySelector('.vec-pending-save-tooltip');

        await expect(pendingSave).not.toHaveAttribute('title');
        await expect(tooltip).not.toBeVisible();
        await userEvent.click(pendingSave);
        await waitFor(() => expect(tooltip).toBeVisible());
        await expect(tooltip).toHaveTextContent('Mapowanie oczekuje na zapis');
        await expect(targetPanel.getByRole('button', { name: 'Zapisz mapowanie kategorii' })).toBeEnabled();
    }
};

export const ZaznaczanieCalegoDrzewaErgonode = {
    args: { hideMapped: false },
    play: async ({ canvasElement }) => {
        const sourcePanelElement = canvasElement.querySelector('.vec-source-panel');
        const sourcePanel = within(sourcePanelElement);
        const root = sourcePanel.getByRole('checkbox', { name: 'Zaznacz kategorię Main PL' });
        const parent = sourcePanel.getByRole('checkbox', { name: 'Zaznacz kategorię Krzesła' });
        const child = sourcePanel.getByRole('checkbox', { name: 'Zaznacz kategorię Krzesła biurowe' });
        const sibling = sourcePanel.getByRole('checkbox', { name: 'Zaznacz kategorię Stoły' });

        await userEvent.click(root);
        await expect(root).toBeChecked();
        await expect(parent).toBeChecked();
        await expect(child).toBeChecked();
        await expect(sibling).toBeChecked();

        const actionsTrigger = sourcePanel.getByRole('button', { name: /Akcje kategorii: Ergonode/ });

        await userEvent.click(actionsTrigger);
        await expect(actionsTrigger).toHaveAccessibleName(/Akcje kategorii: Ergonode\. Wybrano: 4/);
        await expect(sourcePanelElement.querySelector('[data-role="visibility-toggle"]')).toBeDisabled();
        await expect(sourcePanel.getByRole('button', { name: 'Disconnect' })).toBeEnabled();

        await userEvent.click(root);
        await expect(root).not.toBeChecked();
        await expect(parent).not.toBeChecked();
        await expect(child).not.toBeChecked();
        await expect(sibling).not.toBeChecked();
    }
};

export const ZaznaczanieCalegoDrzewaMagento = {
    play: async ({ canvasElement }) => {
        const targetPanel = within(canvasElement.querySelector('.vec-target-panel'));
        const root = targetPanel.getByRole('checkbox', {
            name: 'Zaznacz kategorię PLN Root (#41)'
        });
        const mapped = targetPanel.getByRole('checkbox', { name: 'Zaznacz kategorię Krzesła' });
        const unmapped = targetPanel.getByRole('checkbox', { name: 'Zaznacz kategorię Outlet' });

        await expect(mapped.closest('.vec-node-card')).toHaveClass('is-mapped');
        await expect(unmapped.closest('.vec-node-card')).not.toHaveClass('is-mapped');
        await userEvent.click(root);
        await expect(root).toBeChecked();
        await expect(mapped).toBeChecked();
        await expect(unmapped).toBeChecked();

        const actionsTrigger = targetPanel.getByRole('button', { name: /Akcje kategorii: Magento/ });
        const actions = within(actionsTrigger.closest('details'));

        await userEvent.click(actionsTrigger);
        await expect(actions.getByRole('button', {
            name: /Akcje kategorii: Magento\. Wybrano: 3/
        })).toBeVisible();
        await expect(canvasElement.querySelector('.vec-target-panel [data-role="visibility-toggle"]')).toBeDisabled();
        await expect(actions.getByRole('button', { name: 'Disconnect' })).toBeEnabled();
    }
};

export const KaskadoweZaznaczaniePoObuStronach = {
    args: { mappingStates: true, hideMapped: false },
    play: async ({ canvasElement }) => {
        const sourcePanel = within(canvasElement.querySelector('.vec-source-panel'));
        const targetPanel = within(canvasElement.querySelector('.vec-target-panel'));
        const sourceParent = sourcePanel.getByRole('checkbox', { name: 'Zaznacz kategorię Krzesła' });
        const sourceChild = sourcePanel.getByRole('checkbox', { name: 'Zaznacz kategorię Krzesła biurowe' });
        const targetParent = targetPanel.getByRole('checkbox', { name: 'Zaznacz kategorię Krzesła' });
        const targetChild = targetPanel.getByRole('checkbox', { name: 'Zaznacz kategorię Outlet' });

        await userEvent.click(sourceParent);
        await expect(sourceChild).toBeChecked();
        await userEvent.click(sourceParent);
        await expect(sourceChild).not.toBeChecked();

        await userEvent.click(targetParent);
        await expect(targetChild).toBeChecked();
        await userEvent.click(targetParent);
        await expect(targetChild).not.toBeChecked();
    }
};

export const OdświeżanieZMenuAkcjiErgonode = {
    play: async ({ canvasElement }) => {
        const panel = canvasElement.querySelector('.vec-source-panel');
        const sourcePanel = within(panel);

        await userEvent.click(sourcePanel.getByRole('button', { name: /Akcje kategorii: Ergonode/ }));
        await userEvent.click(sourcePanel.getByRole('button', { name: 'Refresh' }));
        await expect(panel).toHaveAttribute('data-refreshed', 'true');
    }
};

export const SynchronizacjaWToolbarze = {
    tags: ['category-updates'],
    play: async ({ canvasElement }) => {
        const panel = canvasElement.querySelector('.vec-settings-panel');
        const settingsPanel = within(panel);
        const targetPanel = within(canvasElement.querySelector('.vec-target-panel'));
        const toolbar = within(canvasElement.querySelector('.vec-toolbar'));

        await userEvent.click(targetPanel.getByRole('button', { name: /Akcje kategorii: Magento/ }));
        await expect(targetPanel.queryByRole('button', { name: 'Synchronizuj' })).not.toBeInTheDocument();
        await userEvent.click(settingsPanel.getByRole('button', { name: 'Opcje drzewa: Main PL' }));
        await expect(
            panel.querySelector('[data-role="category-tree-configuration"] [data-role="sync-category-trees"]')
        ).not.toBeInTheDocument();
        const sync = toolbar.getByRole('button', { name: 'Sync' });

        await expect(sync.querySelector('.veui-sync-ergonode-icon')).toBeInTheDocument();
        await userEvent.click(sync);
        await expect(panel).toHaveAttribute('data-synchronized', 'true');
        await userEvent.click(toolbar.getByRole('button', { name: 'Refresh options' }));
        const reset = within(toolbar.getByRole('group', { name: 'Tree' })).getByRole('menuitem', { name: 'Cursor reset' });

        await expect(reset.querySelector('.veui-reset-cursor-icon')).toBeInTheDocument();
    }
};

export const ResetKursoraWMenuSynchronizacji = {
    tags: ['category-updates'],
    play: async ({ canvasElement }) => {
        const toolbar = within(canvasElement.querySelector('.vec-toolbar'));

        await userEvent.click(toolbar.getByRole('button', { name: 'Refresh options' }));
        const reset = within(toolbar.getByRole('group', { name: 'Tree' })).getByRole('menuitem', { name: 'Cursor reset' });

        await expect(reset.querySelector('.veui-reset-cursor-icon')).toBeInTheDocument();
        await userEvent.click(reset);
        await expect(reset).toBeDisabled();
    }
};

export const AutoConnectWMenuSrodkowejKolumny = {
    play: async ({ canvasElement }) => {
        const sourcePanel = within(canvasElement.querySelector('.vec-source-panel'));
        const panel = canvasElement.querySelector('.vec-target-panel');
        const targetPanel = within(panel);
        const toolbar = within(canvasElement.querySelector('.vec-toolbar'));

        await expect(toolbar.queryByRole('button', { name: 'Auto Connect' })).not.toBeInTheDocument();
        await userEvent.click(sourcePanel.getByRole('button', { name: /Akcje kategorii: Ergonode/ }));
        await expect(sourcePanel.queryByRole('button', { name: 'Auto Connect' })).not.toBeInTheDocument();
        await userEvent.click(targetPanel.getByRole('button', { name: /Akcje kategorii: Magento/ }));
        const autoConnect = targetPanel.getByRole('button', { name: 'Auto Connect' });

        await expect(autoConnect).toBeVisible();
        await expect(autoConnect.querySelector('.vec-auto-map-icon')).toBeInTheDocument();
        await userEvent.click(autoConnect);
        await expect(panel).toHaveAttribute('data-auto-connected', 'true');
    }
};

export const DostepnoscAkcjiDlaZaznaczenia = {
    args: { hideMapped: false },
    play: async ({ canvasElement }) => {
        const sourcePanel = within(canvasElement.querySelector('.vec-source-panel'));
        const menu = sourcePanel.getByRole('button', { name: /Akcje kategorii: Ergonode/ });

        await userEvent.click(menu);
        const excluded = canvasElement.querySelector(
            '[data-bulk-options-source="ergo"] [data-role="visibility-toggle"]'
        );
        const disconnect = sourcePanel.getByRole('button', { name: 'Disconnect' });

        await expect(excluded).toBeDisabled();
        await expect(excluded).toHaveAttribute('aria-pressed', 'false');
        await expect(disconnect).toBeDisabled();

        const tables = sourcePanel.getByRole('checkbox', { name: 'Zaznacz kategorię Stoły' });

        await userEvent.click(tables);
        await expect(excluded).toBeDisabled();
        await expect(disconnect).toBeDisabled();

        await userEvent.click(tables);
        await userEvent.click(sourcePanel.getByRole('checkbox', { name: 'Zaznacz kategorię Krzesła' }));
        await expect(excluded).toBeDisabled();
        await expect(disconnect).toBeEnabled();
    }
};

export const NiezaleznePrzelacznikiWykluczonychKategorii = {
    play: async ({ canvasElement }) => {
        const sourcePanelElement = canvasElement.querySelector('.vec-source-panel');
        const targetPanelElement = canvasElement.querySelector('.vec-target-panel');
        const sourcePanel = within(sourcePanelElement);
        const targetPanel = within(targetPanelElement);
        const sourceToggle = sourcePanelElement.querySelector('[data-role="visibility-toggle"]');
        const targetToggle = targetPanelElement.querySelector('[data-role="visibility-toggle"]');
        const tablesCard = sourcePanel.getByText('Stoły').closest('.vec-node-card');
        const outletCard = targetPanel.getByText('Outlet').closest('.vec-node-card');

        await expect(sourceToggle).toBeDisabled();
        await expect(targetToggle).toBeDisabled();

        await userEvent.click(within(tablesCard).getByRole('button', { name: 'Opcje kategorii: Stoły' }));
        await userEvent.click(within(tablesCard).getByRole('button', { name: 'Exclude' }));

        await expect(tablesCard).toHaveClass('is-blocked');
        await expect(tablesCard).not.toBeVisible();
        await expect(sourceToggle).toBeEnabled();
        await expect(targetToggle).toBeDisabled();

        await userEvent.click(sourcePanel.getByRole('button', { name: /Akcje kategorii: Ergonode/ }));
        await userEvent.click(sourceToggle);
        await expect(sourceToggle).toHaveAttribute('aria-pressed', 'true');
        await expect(tablesCard).toBeVisible();

        await userEvent.click(sourceToggle);
        await expect(sourceToggle).toHaveAttribute('aria-pressed', 'false');
        await expect(tablesCard).not.toBeVisible();

        await userEvent.click(within(outletCard).getByRole('button', { name: 'Opcje kategorii: Outlet' }));
        await userEvent.click(within(outletCard).getByRole('button', { name: 'Exclude' }));

        await expect(outletCard).toHaveClass('is-blocked');
        await expect(outletCard).not.toBeVisible();
        await expect(sourceToggle).toBeEnabled();
        await expect(targetToggle).toBeEnabled();

        await userEvent.click(targetPanel.getByRole('button', { name: /Akcje kategorii: Magento/ }));
        await userEvent.click(targetToggle);
        await expect(targetToggle).toHaveAttribute('aria-pressed', 'true');
        await expect(outletCard).toBeVisible();
        await expect(outletCard).toHaveClass('is-blocked', 'is-disabled');

        const excludedInfo = within(outletCard).getByRole('img', {
            name: 'Kategoria jest wykluczona z mapowania'
        });
        const excludedTooltip = excludedInfo.querySelector('.vec-configuration-disabled-tooltip');

        await expect(getComputedStyle(outletCard).backgroundColor).toBe('rgba(0, 0, 0, 0)');
        await expect(getComputedStyle(outletCard).borderTopWidth).toBe('0px');
        await expect(excludedTooltip).not.toBeVisible();
        await userEvent.click(excludedInfo);
        await expect(excludedInfo).toHaveFocus();
        await waitFor(() => expect(excludedTooltip).toBeVisible());
        await expect(excludedTooltip).toHaveTextContent(
            'Ta kategoria nie będzie używana w mapowaniu. ' +
            'Uwzględnij ją ponownie w jej opcjach, aby znów była dostępna.'
        );

        await userEvent.click(targetToggle);
        await expect(targetToggle).toHaveAttribute('aria-pressed', 'false');
        await expect(outletCard).not.toBeVisible();
    }
};

export const MasoweOdlaczanieMapowanErgonode = {
    args: { hideMapped: false },
    play: async ({ canvasElement }) => {
        const sourcePanel = within(canvasElement.querySelector('.vec-source-panel'));
        const chairs = sourcePanel.getByRole('checkbox', { name: 'Zaznacz kategorię Krzesła' });
        const chairsCard = chairs.closest('.vec-node-card');

        await userEvent.click(chairs);
        await userEvent.click(sourcePanel.getByRole('button', { name: /Akcje kategorii: Ergonode/ }));
        await userEvent.click(sourcePanel.getByRole('button', { name: 'Disconnect' }));

        await expect(chairsCard.querySelector('.vec-source-mapping')).not.toBeInTheDocument();
    }
};

export const MasoweOdlaczanieMapowanMagento = {
    args: { mappingStates: true },
    play: async ({ canvasElement }) => {
        const targetPanel = within(canvasElement.querySelector('.vec-target-panel'));
        const chairs = targetPanel.getByRole('checkbox', { name: 'Zaznacz kategorię Krzesła' });
        const outlet = targetPanel.getByRole('checkbox', { name: 'Zaznacz kategorię Outlet' });
        const chairsCard = chairs.closest('.vec-node-card');
        const outletCard = outlet.closest('.vec-node-card');

        await userEvent.click(chairs);
        await expect(outlet).toBeChecked();
        await userEvent.click(targetPanel.getByRole('button', { name: /Akcje kategorii: Magento/ }));
        await userEvent.click(canvasElement.querySelector('[data-bulk-options-source="magento"] [data-role="unmap-selected-categories"]'));

        await expect(chairsCard).not.toHaveClass('is-mapped');
        await expect(outletCard).not.toHaveClass('is-mapped');
        await expect(chairsCard.querySelector('[data-role="pending-mapping-save"]')).toBeInTheDocument();
        await expect(outletCard.querySelector('[data-role="pending-mapping-save"]')).toBeInTheDocument();
        await expect(chairsCard.children).toHaveLength(4);
        await expect(outletCard.children).toHaveLength(4);
    }
};

export const KonfliktNiejednoznacznejNazwy = { args: { state: 'conflict' } };

export const Blad429ZRetryAfter = { args: { state: 'rateLimit' } };

export const BladTransportowyBezUtratyDanych = { args: { state: 'transport' } };

export const ObslugaKlawiatury = {
    tags: ['mapping-popup'],
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const menu = canvas.getByRole('button', {name: 'Opcje drzewa: Main PL'});
        menu.focus();
        await userEvent.keyboard('{Enter}');
        const edit = canvas.getByRole('button', {name: 'Edytuj mapowanie Main PL'});
        edit.focus();
        await userEvent.keyboard('{Enter}');
        const dialog = canvas.getByRole('dialog', {name: 'Edit Mapping'});
        const active = within(dialog).getByRole('checkbox', {name: /Aktywne/});
        active.focus();
        await userEvent.keyboard(' ');
        await expect(active).not.toBeChecked();
        await userEvent.tab();
        await expect(within(dialog).getByRole('button', {name: 'Usuń mapowanie'})).toHaveFocus();
        await expect(within(dialog).queryByRole('checkbox', {name: /Usuń brakujące kategorie/})).toBeNull();
    }
};

export const KolejnoscKonfiguracji = {
    play: async ({canvasElement}) => {
        const list = canvasElement.querySelector('[data-role="category-tree-configuration-list"]');
        const first = list.firstElementChild;
        const second = list.lastElementChild;
        const dataTransfer = new DataTransfer();
        first.dispatchEvent(new DragEvent('dragstart', {bubbles: true, dataTransfer}));
        second.dispatchEvent(new DragEvent('drop', {bubbles: true, cancelable: true, dataTransfer}));
        await waitFor(() => expect(list).toHaveAttribute('data-saved-order', '2,1'));
        await expect(list.firstElementChild).toHaveTextContent('Main EN');
    }
};

export const NazwyWMenuAktualizacji = {
    tags: ['category-updates'],
    play: async ({ canvasElement }) => {
        const toolbar = within(canvasElement.querySelector('.vec-toolbar'));
        await userEvent.click(toolbar.getByRole('button', { name: 'Refresh options' }));
        const names = within(toolbar.getByRole('group', { name: 'Names' }));
        await expect(names.getByRole('menuitem', { name: 'Sync (force)' }))
            .toHaveAttribute('data-synchronization-scope', 'data');
        await userEvent.click(names.getByRole('menuitem', { name: 'Sync (force)' }));
        await expect(canvasElement.querySelector('.vec-settings-panel'))
            .toHaveAttribute('data-synchronized', 'true');
    }
};

export const AtrybutyWMenuAktualizacji = {
    tags: ['category-updates'],
    args: { dataLabel: 'Attributes' },
    play: async ({ canvasElement }) => {
        const toolbar = within(canvasElement.querySelector('.vec-toolbar'));
        await userEvent.click(toolbar.getByRole('button', { name: 'Refresh options' }));
        await expect(toolbar.queryByRole('group', { name: 'Names' })).not.toBeInTheDocument();
        const attributes = within(toolbar.getByRole('group', { name: 'Attributes' }));
        const action = attributes.getByRole('menuitem', { name: 'Sync (force)' });
        action.focus();
        await userEvent.keyboard('{Enter}');
        await expect(canvasElement.querySelector('.vec-settings-panel'))
            .toHaveAttribute('data-synchronized', 'true');
    }
};


export const PonowneWlaczenieMapowania = {
    tags: ['mapping-popup'],
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await userEvent.click(canvas.getByRole('button', { name: 'Opcje drzewa: Main EN' }));
        await userEvent.click(canvas.getByRole('button', { name: 'Edytuj mapowanie Main EN' }));
        const dialog = canvas.getByRole('dialog', { name: 'Edit Mapping' });
        const active = within(dialog).getByRole('checkbox', { name: /Aktywne/ });
        const notice = dialog.querySelector('[data-role="mapping-reactivation-notice"]');
        await expect(active).not.toBeChecked();
        await expect(notice).not.toBeVisible();
        active.focus();
        await userEvent.keyboard(' ');
        await expect(notice).toBeVisible();
        await expect(notice).toHaveTextContent('"Sync (force)"');
        await expect(notice).toHaveTextContent('all active mappings');
        await userEvent.click(active);
        await expect(notice).not.toBeVisible();
        await userEvent.click(active);
        const values = new FormData(dialog.querySelector('form'));
        await expect(values.get('category_tree_id')).toBe('2');
        await expect(values.get('is_active')).toBe('1');
        await expect(values.get('tree_code')).toBe('main-en');
        await expect(values.get('root_category_id')).toBe('52');
    }
};

export const ResetStanuPopupu = {
    tags: ['mapping-popup'],
    play: async ({ canvasElement }) => {
        await PonowneWlaczenieMapowania.play({ canvasElement });
        const canvas = within(canvasElement);
        let dialog = canvas.getByRole('dialog', { name: 'Edit Mapping' });
        await userEvent.click(within(dialog).getByRole('button', { name: 'Anuluj' }));
        await userEvent.click(canvas.getAllByRole('button', { name: 'Nowe mapowanie' })[0]);
        dialog = canvas.getByRole('dialog', { name: 'New Mapping' });
        await expect(dialog.querySelector('[data-role="mapping-reactivation-notice"]')).not.toBeVisible();
        await expect(within(dialog).getByRole('button', { name: 'Refresh' })).toBeVisible();
        await expect(within(dialog).getByRole('combobox', { name: 'Drzewo Ergonode' })).toBeEnabled();
        await expect(within(dialog).getByRole('combobox', { name: 'Kategoria główna Magento' })).toBeEnabled();
        await expect(new FormData(dialog.querySelector('form')).get('category_tree_id')).toBe('0');
    }
};


export const BrakWymaganychMapowanSynchronizacji = {
    tags: ['category-sync-progress'],
    play: async ({canvasElement}) => {
        const reason = 'Required category creation attributes are not completely mapped: include_in_menu, is_active.';
        const canvas = within(canvasElement);
        productionSynchronizationAvailability.update(canvasElement, {tree: reason, data: ''});
        const main = canvas.getByRole('button', {name: 'Sync', exact: true});
        await expect(main).toHaveAttribute('aria-disabled', 'true');
        main.focus();
        await expect(main).toHaveAccessibleDescription(reason);
        await waitFor(() => expect(main.querySelector('[role="tooltip"]')).toBeVisible());
        await userEvent.keyboard('{Enter} ');
        await expect(canvasElement.querySelector('.vec-settings-panel')).not.toHaveAttribute('data-synchronized');
        await userEvent.click(canvas.getByRole('button', {name: 'Refresh options'}));
        const tree = canvasElement.querySelector('[data-synchronization-scope="tree"][data-synchronization-action="reset-cursor-and-sync"]');
        await expect(tree).toHaveAttribute('aria-disabled', 'true');
        tree.focus();
        await expect(tree).toHaveAccessibleDescription(reason);
        await waitFor(() => expect(tree.querySelector('[role="tooltip"]')).toBeVisible());
        await userEvent.keyboard('{Enter} ');
        await expect(canvasElement.querySelector('.vec-settings-panel')).not.toHaveAttribute('data-synchronized');
        await expect(canvasElement.querySelector('[data-synchronization-scope="data"][data-synchronization-action="sync"]'))
            .toHaveAttribute('aria-disabled', 'false');
    }
};
