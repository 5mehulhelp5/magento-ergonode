import { expect, userEvent, waitFor, within } from 'storybook/test';

import { loadAmdModule } from '@ergonode-storybook/load-amd-module.js';

import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CategoryAdminUi/view/adminhtml/web/css/category-tree-mapping.css';
import '../../view/adminhtml/web/css/ergonode-category-publisher.css';
import '@ergonode-modules/PublisherAdminUi/view/adminhtml/web/css/manual-auth.css';
import entityOptionsSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/entity-options.js?raw';
import subtreeMappingSource from '@ergonode-modules/CategoryAdminUi/view/adminhtml/web/js/category-tree-subtree-mapping.js?raw';
import categoryCodeGeneratorSource from '../../view/adminhtml/web/js/category-code-generator.js?raw';
import jsonPostSource from '@ergonode-modules/PublisherAdminUi/view/adminhtml/web/js/json-post.js?raw';
import manualAuthSource from '@ergonode-modules/PublisherAdminUi/view/adminhtml/web/js/manual-auth.js?raw';
import readinessSource from '../../view/adminhtml/web/js/publication-readiness.js?raw';
import source from '../../view/adminhtml/web/js/ergonode-category-publisher-mapping.js?raw';

const eventBindings = new WeakMap();

function collection(elements) {
    return {
        elements,
        length: elements.length,
        on(eventName, selector, handler) {
            const type = eventName.split('.')[0];
            elements.forEach((element) => {
                const listener = typeof selector === 'function' ? selector : (event) => {
                const target = event.target.closest(selector);

                if (target && element.contains(target)) {
                    handler.call(target, {
                        currentTarget: target,
                        target: event.target,
                        preventDefault: () => event.preventDefault(),
                        stopImmediatePropagation: () => event.stopImmediatePropagation()
                    });
                }
                };
                element.addEventListener(type, listener);
                const bindings = eventBindings.get(element) || [];
                bindings.push({eventName, type, listener});
                eventBindings.set(element, bindings);
            });
            return this;
        },
        off(namespace) {
            elements.forEach(element => {
                const bindings = eventBindings.get(element) || [];
                bindings.filter(binding => binding.eventName.endsWith(namespace))
                    .forEach(binding => element.removeEventListener(binding.type, binding.listener));
                eventBindings.set(element, bindings.filter(binding => !binding.eventName.endsWith(namespace)));
            });
            return this;
        },
        each(callback) { elements.forEach((element, index) => callback.call(element, index, element)); return this; },
        get(index) { return elements[index]; },
        first() { return collection(elements.slice(0, 1)); },
        find(selector) { return collection(elements.flatMap((element) => [...element.querySelectorAll(selector)])); },
        after(node) { elements.forEach((element) => element.after(node.element || node)); return this; },
        before(node) { elements.forEach((element) => element.before(node.element || node)); return this; },
        append(...nodes) { elements.forEach((element) => element.append(...nodes.map((node) => node.element || node))); return this; },
        prepend(...nodes) { elements.forEach((element) => element.prepend(...nodes.map((node) => node.element || node))); return this; },
        addClass(name) { elements.forEach((element) => element.classList.add(name)); return this; },
        removeClass(name) { elements.forEach((element) => element.classList.remove(name)); return this; },
        toggleClass(name, active) { elements.forEach((element) => element.classList.toggle(name, active)); return this; },
        attr(name, value) {
            if (value === undefined) return elements[0] ? elements[0].getAttribute(name) : undefined;
            elements.forEach((element) => element.setAttribute(name, value));
            return this;
        },
        remove() { elements.forEach((element) => element.remove()); return this; },
        hasClass(name) { return !!elements[0] && elements[0].classList.contains(name); },
        data(name) { return elements[0] ? elements[0].getAttribute(`data-${name}`) : undefined; },
        prop(name, value) {
            if (value === undefined) return elements[0] ? elements[0][name] : undefined;
            elements.forEach((element) => { element[name] = value; });
            return this;
        },
        text(value) {
            if (value === undefined) return elements[0] ? elements[0].textContent : '';
            elements.forEach((element) => { element.textContent = value; });
            return this;
        },
        trigger(name) { elements.forEach((element) => element.dispatchEvent(new Event(name, {bubbles: true}))); return this; },
        modal(action, option, value) {
            if (action === 'option') { return value === undefined ? !this.shell.hidden : this; }
            if (action === 'openModal') {
                this.shell.hidden = false;
                this.shell.classList.add('_show');
                elements.forEach((element) => { element.style.display = ''; });
            }
            if (action === 'closeModal') {
                this.shell.hidden = true;
                this.shell.classList.remove('_show');
                if (this.modalOptions?.closed) this.modalOptions.closed();
            }
            return this;
        }
    };
}

function storyJquery(value, attributes) {
    if (typeof value === 'string' && value.startsWith('<')) {
        const element = document.createElement(value.replace(/[<>/]/g, ''));

        Object.entries(attributes || {}).forEach(([key, attribute]) => {
            if (key === 'class') element.className = attribute;
            else if (key in element) element[key] = attribute;
            else element.setAttribute(key, attribute);
        });
        const result = collection([element]);

        result.element = element;
        return result;
    }
    return collection(value instanceof Element ? [value] : [...(value || [])]);
}

storyJquery.ajax = (options) => ({
    done(callback) {
        const url = String(options.url || '');
        let response = {success: true, write_readiness: {ready: true, message: '', configuration_url: ''}, authenticated: url.includes('/authenticated/')};

        if (url.includes('/tree/create')) {
            response = {success: true, message: 'Drzewo kategorii Ergonode zostało utworzone.'};
        }
        if (url.includes('/category/batch/create')) {
            const item = JSON.parse(options.data.items)[0];

            response = {
                success: true,
                items: [{
                    ...item,
                    status: item.code === 'men' ? 'existing' : 'synchronized',
                    remote_id: `remote-${item.code}`
                }]
            };
        }
        queueMicrotask(() => callback(response));
        return this;
    },
    fail() { return this; },
    always(callback) { queueMicrotask(() => callback()); return this; }
});

function storyModal(options, widget) {
    const shell = document.createElement('div');
    const inner = document.createElement('div');
    const header = document.createElement('header');
    const title = document.createElement('h1');
    const close = document.createElement('button');
    const content = document.createElement('div');

    shell.hidden = true;
    shell.className = `modal-popup ${options.modalClass || ''}`;
    shell.setAttribute('role', 'dialog');
    shell.setAttribute('aria-label', options.title);
    inner.className = 'modal-inner-wrap';
    header.className = 'modal-header';
    title.className = 'modal-title';
    title.textContent = options.title;
    close.type = 'button';
    close.className = 'action-close';
    close.setAttribute('aria-label', 'Zamknij');
    content.className = 'modal-content';
    widget.elements[0].style.display = '';
    content.append(widget.elements[0]);
    header.append(title, close);
    inner.append(header, content);
    shell.append(inner);
    document.body.append(shell);
    widget.shell = shell;
    widget.modalOptions = options;
    close.addEventListener('click', () => widget.modal('closeModal'));
}

function createProgressStub() {
    return {
        destroy() {},
        open() {},
        showBatch() {},
        applyBatch() {},
        addBlocked() {},
        wait() { return Promise.resolve(); },
        finalizing() {},
        complete() {},
        fail() {}
    };
}

const productionEntityOptions = loadAmdModule(entityOptionsSource, {
    'mage/translate': (value) => value
}, 'Ergonode_CoreAdminUi/js/entity-options');
const productionSubtreeMapping = loadAmdModule(
    subtreeMappingSource,
    {},
    'Ergonode_CategoryAdminUi/js/category-tree-subtree-mapping'
);
const productionCategoryCodeGenerator = loadAmdModule(
    categoryCodeGeneratorSource,
    {},
    'Ergonode_CategoryPublisherAdminUi/js/category-code-generator'
);
const translate = (value) => ({'Log in to Ergonode': 'Logowanie do Ergonode'}[value] || value);
const productionJsonPost = loadAmdModule(jsonPostSource, {
    jquery: storyJquery,
    'mage/translate': translate
}, 'Ergonode_PublisherAdminUi/js/json-post');
const productionManualAuth = loadAmdModule(manualAuthSource, {
    jquery: storyJquery,
    'Magento_Ui/js/modal/modal': storyModal,
    'mage/translate': translate,
    'Ergonode_PublisherAdminUi/js/json-post': productionJsonPost
}, 'Ergonode_PublisherAdminUi/js/manual-auth');
const productionInitializer = loadAmdModule(source, {
    jquery: storyJquery,
    'mage/translate': translate,
    'Ergonode_CoreAdminUi/js/entity-options': productionEntityOptions,
    'Ergonode_CategoryAdminUi/js/category-tree-subtree-mapping': productionSubtreeMapping,
    'Ergonode_CategoryPublisherAdminUi/js/category-code-generator': productionCategoryCodeGenerator,
    'Ergonode_CategoryPublisherAdminUi/js/category-publish-progress': createProgressStub,
    'Ergonode_PublisherAdminUi/js/json-post': productionJsonPost,
    'Ergonode_PublisherAdminUi/js/manual-auth': productionManualAuth,
        'Ergonode_CategoryPublisherAdminUi/js/publication-readiness': loadAmdModule(readinessSource, {
            'mage/translate': translate,
            'Ergonode_PublisherAdminUi/js/json-post': productionJsonPost
        }, 'Ergonode_CategoryPublisherAdminUi/js/publication-readiness')
}, 'Ergonode_CategoryPublisherAdminUi/js/ergonode-category-publisher-mapping');

function createUnmapAction(mappedCode) {
    return productionEntityOptions.createAction({
        role: 'unmap-category',
        className: 'vec-unmap',
        iconClass: 'vec-unmap-icon',
        label: 'Disconnect',
        attributes: {'data-code': mappedCode}
    });
}

let mappingHintSequence = 0;

function createMappingIndicator(label, code) {
    const indicator = document.createElement('button');
    const hint = document.createElement('span');
    const hintId = `vec-story-mapping-hint-${++mappingHintSequence}`;

    indicator.type = 'button';
    indicator.className = 'vec-magento-mapping is-mapped vec-mapping-indicator';
    indicator.dataset.mappingLabel = label;
    indicator.dataset.mappingCode = code;
    indicator.setAttribute('aria-label', `${label} · ${code}`);
    indicator.setAttribute('aria-describedby', hintId);
    indicator.setAttribute('draggable', 'false');
    hint.className = 'vec-mapping-hint';
    hint.id = hintId;
    hint.setAttribute('role', 'tooltip');
    hint.append(
        Object.assign(document.createElement('strong'), {textContent: label}),
        Object.assign(document.createElement('span'), {textContent: code})
    );
    indicator.append(
        Object.assign(document.createElement('span'), {
            className: 'vec-mapping-status-icon',
            'aria-hidden': 'true'
        }),
        hint
    );

    return indicator;
}

function createMagentoCategoryOptions(category, mappedCode, configuredRoot) {
    const visibility = document.createElement('button');
    const actions = [];

    if (mappedCode && !configuredRoot) {
        actions.push(createUnmapAction(mappedCode));
    }
    visibility.type = 'button';
    visibility.className = 'veui-visibility-control';
    visibility.dataset.role = 'category-active-toggle';
    visibility.setAttribute('aria-label', 'Exclude');
    visibility.setAttribute('aria-pressed', 'true');
    visibility.append(
        Object.assign(document.createElement('span'), {className: 'veui-visibility-icon', 'aria-hidden': 'true'})
    );

    return productionEntityOptions.create({
        actions,
        menuLabel: `Opcje kategorii: ${category.label}`,
        toggle: visibility
    });
}

function createBulkCategorySelection(category) {
    const control = document.createElement('label');
    const input = document.createElement('input');
    const marker = document.createElement('span');

    control.className = 'vec-bulk-category-select';
    input.type = 'checkbox';
    input.dataset.role = 'bulk-category-select';
    input.dataset.bulkSource = 'magento';
    input.dataset.identifier = String(category.id);
    input.setAttribute('aria-label', `Zaznacz kategorię ${category.label}`);
    marker.setAttribute('aria-hidden', 'true');
    control.append(input, marker);

    return control;
}

function appendMagentoCategory(root, category, mappedCode = '', configuredRoot = false) {
    const card = document.createElement('div');
    const copy = document.createElement('span');
    const mapping = mappedCode
        ? createMappingIndicator(category.label, mappedCode)
        : document.createElement('span');

    card.className = `vec-node-card vec-magento-card has-bulk-selection${configuredRoot ? ' is-configured-root' : ''}`;
    card.dataset.magentoId = String(category.id);
    copy.className = 'vec-card-copy';
    copy.append(
        Object.assign(document.createElement('strong'), {textContent: category.label}),
        Object.assign(document.createElement('span'), {textContent: `#${category.id}`})
    );
    if (!mappedCode) {
        mapping.className = 'vec-magento-mapping is-empty';
        mapping.title = 'Upuść kategorię Ergonode, aby ją zmapować';
        mapping.append(Object.assign(document.createElement('span'), {
            className: 'vec-visually-hidden', textContent: mapping.title
        }));
    }
    card.append(
        createBulkCategorySelection(category),
        copy,
        mapping,
        createMagentoCategoryOptions(category, mappedCode, configuredRoot)
    );
    root.append(card);
}

function appendCategoryActionsFixture(root, variant) {
    const layout = document.createElement('div');
    const panel = document.createElement('section');
    const tools = document.createElement('div');
    const search = document.createElement('label');
    const searchInput = document.createElement('input');
    const bulkOptions = productionEntityOptions.create({
        actions: [],
        menuLabel: 'Akcje kategorii: Magento'
    });
    const list = document.createElement('div');
    const magentoCategories = variant === 'path-code'
        ? [
            {id: 11, parent_id: 0, level: 1, position: 1, label: 'Default Category', url_key: '', active: true},
            {id: 12, parent_id: 11, level: 2, position: 1, label: 'Collections', url_key: 'collections', active: true},
            {
                id: 13,
                parent_id: 12,
                level: 3,
                position: 1,
                label: 'New Luma Yoga Collection',
                url_key: 'yoga-new',
                active: true
            }
        ]
        : [
            {id: 11, parent_id: 0, level: 1, position: 1, label: 'Meble', url_key: 'meble', active: true},
            variant === 'existing'
                ? {id: 12, parent_id: 11, level: 2, position: 1, label: 'Men', url_key: 'men', active: true}
                : {id: 12, parent_id: 11, level: 2, position: 1, label: 'Krzesła', url_key: 'krzesla', active: true},
            ...(variant === 'branch'
                ? [{id: 13, parent_id: 12, level: 3, position: 1, label: 'Biurowe', url_key: 'biurowe', active: true}]
                : [])
        ];
    const categories = [];
    const selectedMagentoIds = new Set();

    layout.className = 'veui-layout';
    layout.style.gridTemplateColumns = 'minmax(620px, 800px)';
    panel.className = 'veui-panel vec-panel vec-target-panel';
    tools.className = 'veui-tools';
    search.className = 'veui-search';
    searchInput.type = 'search';
    searchInput.setAttribute('aria-label', 'Szukaj w kategoriach Magento');
    search.append(searchInput);
    bulkOptions.classList.add('vec-bulk-options');
    bulkOptions.dataset.bulkOptionsSource = 'magento';
    tools.append(search, bulkOptions);
    list.className = 'vec-side-tree vec-magento-mapping-tree';
    if (variant === 'pending') {
        categories.push(
            {
                code: 'meble',
                magento_category_id: 11,
                extension_data: {to_ergonode: {pending_create: true}}
            },
            {
                code: 'krzesla',
                magento_category_id: 12,
                extension_data: {to_ergonode: {pending_create: true}}
            }
        );
        appendMagentoCategory(list, magentoCategories[0], 'meble');
        appendMagentoCategory(list, magentoCategories[1], 'krzesla');
    } else {
        appendMagentoCategory(list, magentoCategories[0], 'default', true);
        appendMagentoCategory(list, magentoCategories[1]);
        if (variant === 'branch' || variant === 'path-code') {
            appendMagentoCategory(list, magentoCategories[2]);
        }
    }
    panel.append(tools, list);
    layout.append(panel);
    root.append(layout);
    list.addEventListener('change', (event) => {
        const checkbox = event.target.closest('[data-role="bulk-category-select"]');

        if (!checkbox) return;
        productionSubtreeMapping.branchIdentifiers(
            magentoCategories,
            Number(checkbox.dataset.identifier),
            'id',
            'parent_id'
        ).forEach((id) => {
            const identifier = String(Number(id));
            const branchCheckbox = list.querySelector(
                `[data-role="bulk-category-select"][data-identifier="${identifier}"]`
            );

            if (checkbox.checked) selectedMagentoIds.add(identifier);
            else selectedMagentoIds.delete(identifier);
            if (branchCheckbox) branchCheckbox.checked = checkbox.checked;
        });
        storyJquery(root).trigger('ergonode:category-mapping:selection-changed');
    });
    root.veaCategoryMappingApi = {
        validateLayout: () => Promise.resolve({success: true}),
        getCategories: () => categories.map((category) => structuredClone(category)),
        getMagentoCategories: () => magentoCategories.map((category) => structuredClone(category)),
        getBulkSelection: () => [...selectedMagentoIds],
        batchUpdate(callback) {
            return callback();
        },
        clearBulkSelection() {
            selectedMagentoIds.clear();
            list.querySelectorAll('[data-role="bulk-category-select"]').forEach((checkbox) => {
                checkbox.checked = false;
            });
            storyJquery(root).trigger('ergonode:category-mapping:selection-changed');
        },
        addAndMapCategory(category, magentoId) {
            const magento = magentoCategories.find((item) => item.id === magentoId);
            const card = root.querySelector(`[data-magento-id="${magentoId}"]`);
            const mapping = card && card.querySelector('.vec-magento-mapping');
            const optionsMenu = card && card.querySelector('.veui-entity-options-menu');

            categories.push({...category, magento_category_id: magentoId});
            if (mapping && magento) {
                mapping.replaceWith(createMappingIndicator(category.label, category.code));
                if (optionsMenu) {
                    optionsMenu.querySelector('[data-role="create-ergonode-category"]')?.remove();
                    optionsMenu.prepend(createUnmapAction(category.code));
                }
            }
            root.dataset.createdIds = [...(root.dataset.createdIds || '').split(',').filter(Boolean), String(magentoId)].join(',');
            return true;
        },
        removeCategory(code) {
            const index = categories.findIndex((category) => category.code === code);

            if (index < 0) return false;
            categories.splice(index, 1);
            root.dataset.removedCode = code;
            return true;
        },
        getCategoryTreeId() {
            return 7;
        },
        markCategoryRemotePrepared(code, remoteId) {
            const category = categories.find((item) => item.code === code);

            if (!category || !remoteId) return false;
            category.ergonode_category_id = remoteId;
            category.extension_data.to_ergonode.remote_prepared = true;
            return true;
        },
        markCategoryPublished(code) {
            const category = categories.find((item) => item.code === code);

            if (!category) return false;
            delete category.extension_data.to_ergonode.pending_create;
            delete category.extension_data.to_ergonode.remote_prepared;
            root.dataset.publishedCode = code;
            return true;
        },
        render() {
            root.querySelectorAll('.is-pending-create').forEach((element) => {
                element.classList.remove('is-pending-create');
            });
            root.querySelectorAll('.vec-pending-ergonode-category').forEach((element) => element.remove());
            root.dataset.rendered = 'true';
        },
        saveLayout(excludedCodes, beforeRender) {
            const api = this;
            const request = {
                done(callback) {
                    queueMicrotask(() => {
                        root.dataset.layoutSaved = 'true';
                        if (beforeRender) beforeRender({success: true});
                        api.render();
                        callback({success: true});
                    });
                    return request;
                },
                fail() { return request; }
            };

            return request;
        },
        notify(type, message) {
            root.dataset.notificationType = type;
            root.dataset.notification = message;
        }
    };
}

function render(args = {}) {
    const root = document.createElement('div');
    const mappingModal = document.createElement('div');
    const refresh = document.createElement('button');
    const heading = document.createElement('div');
    const label = document.createElement('strong');
    const status = document.createElement('span');

    root.className = 'veui-workspace';
    if (args.variant === 'bulk' || args.variant === 'branch'
        || args.variant === 'pending' || args.variant === 'existing'
        || args.variant === 'path-code') {
        appendCategoryActionsFixture(root, args.variant);
        productionInitializer({
            form_key: 'storybook',
        write_readiness: {ready: args.writeReady !== false, message: args.writeReady === false
                ? 'Tworzenie kategorii jest niedostępne. Brakuje klucza API do zapisu.' : '',
                configuration_url: '/configuration'},
            urls: {
                status: '/authenticated/status',
                login: '/login',
                create_tree: '/tree/create',
                create_category_batch: '/category/batch/create'
            }
        }, root);
        return root;
    }
    root.style.margin = '80px auto';
    root.style.width = 'min(920px, calc(100vw - 80px))';
    mappingModal.dataset.role = 'new-mapping-modal';
    heading.className = 'vec-new-mapping-field-heading';
    label.textContent = 'Drzewo Ergonode';
    refresh.type = 'button';
    refresh.className = 'veui-button veui-button-toolbar vec-tree-options-refresh';
    refresh.dataset.role = 'refresh-tree-options';
    refresh.textContent = 'Refresh';
    refresh.addEventListener('click', () => { root.dataset.refreshed = 'true'; });
    status.dataset.role = 'tree-options-status';
    status.hidden = true;
    const treeActions = document.createElement('span');
    treeActions.className = 'vec-new-mapping-tree-actions';
    treeActions.dataset.role = 'mapping-tree-actions';
    treeActions.append(refresh);
    heading.append(label, treeActions);
    mappingModal.append(heading, status);
    root.append(mappingModal);
    root.publisher = productionInitializer({
        form_key: 'storybook',
        write_readiness: {ready: true, message: '', configuration_url: ''},
        urls: {
            status: args.authenticated ? '/authenticated/status' : '/status',
            login: '/login',
            create_tree: '/tree/create'
        }
    }, root);
    return root;
}

export default {
    id: 'ergo-v-014-03',
    title: 'Ergonode UI/Widoki/Kategorie/ERGO-V-014 · Mapowanie drzewa kategorii/ERGO-V-014.03 · Przycisk tworzenia drzewa',
    render,
    parameters: {a11y: {test: 'error'}}
};

export const Playground = {};

export const DisposeOpenTreeDialog = {
    args: {authenticated: true},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const button = canvas.getByRole('button', {name: 'Utwórz drzewo w Ergonode'});
        const root = canvasElement.querySelector('.veui-workspace');
        const dialogId = button.getAttribute('aria-controls');
        const mappingModal = button.closest('[data-role="new-mapping-modal"]');
        document.body.append(mappingModal);
        await userEvent.click(button);
        await waitFor(() => expect(document.getElementById(dialogId)).toBeVisible());
        root.publisher.destroy();
        root.publisher.destroy();
        await expect(document.getElementById(dialogId)).toBeNull();
        await expect(root.veaErgonodeCategoryPublisherObserver).toBeUndefined();
        await expect(button).not.toBeInTheDocument();
        mappingModal.remove();
    }
};

export const ClickOpensLogin = {
    tags: ['mapping-popup'],
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const button = canvas.getByRole('button', {name: 'Utwórz drzewo w Ergonode'});
        const mappingModal = button.closest('[data-role="new-mapping-modal"]');

        await expect(button).toHaveTextContent('Create');
        await expect(button.closest('[data-role="mapping-tree-actions"]')).toContainElement(
            canvas.getByRole('button', { name: 'Refresh' })
        );
        await expect(button.querySelector('.veui-create-ergonode-icon')).toBeInTheDocument();
        document.body.append(mappingModal);
        mappingModal.style.marginLeft = '520px';
        await expect(canvas.queryByRole('button', {name: 'Utwórz drzewo w Ergonode'})).not.toBeInTheDocument();
        await userEvent.click(button);
        await expect(within(document.body).getByRole('dialog', {name: 'Logowanie do Ergonode'})).toBeVisible();
    }
};

export const AuthenticatedClickOpensTreeFormOnTheLeft = {
    tags: ['mapping-popup'],
    args: {authenticated: true},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const button = canvas.getByRole('button', {name: 'Utwórz drzewo w Ergonode'});
        const mappingModal = button.closest('[data-role="new-mapping-modal"]');

        document.body.append(mappingModal);
        mappingModal.style.marginLeft = '520px';
        await expect(canvas.queryByRole('button', {name: 'Utwórz drzewo w Ergonode'})).not.toBeInTheDocument();
        await userEvent.click(button);
        const dialog = within(document.body).getByRole('dialog', {name: 'Utwórz drzewo w Ergonode'});
        const form = within(dialog);

        await waitFor(() => expect(dialog).toBeVisible());
        await expect(button).toHaveAttribute('aria-expanded', 'true');
        await expect(dialog).toHaveAttribute('data-placement', 'left');
        await userEvent.type(form.getByLabelText(/Kod nowego drzewa/), 'summer_2026');
        await userEvent.type(form.getByLabelText(/Nazwa nowego drzewa/), 'Summer 2026');
        await userEvent.click(form.getByRole('button', {name: 'Utwórz'}));
        await waitFor(() => expect(canvasElement.querySelector('[data-refreshed="true"]')).toBeInTheDocument());
        await expect(dialog).not.toBeVisible();
        await expect(button).toHaveAttribute('aria-expanded', 'false');
    }
};

export const BulkCategoryCreation = {
    args: {variant: 'bulk'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);

        await expect(canvas.queryByRole('button', {name: /Utwórz w Ergonode/})).not.toBeVisible();
        await userEvent.click(canvas.getByRole('checkbox', {name: 'Zaznacz kategorię Meble'}));
        await expect(canvas.getByRole('checkbox', {name: 'Zaznacz kategorię Meble'})).toBeChecked();
        await expect(canvas.getByRole('checkbox', {name: 'Zaznacz kategorię Krzesła'})).toBeChecked();
        await userEvent.click(canvas.getByRole('button', {name: 'Akcje kategorii: Magento'}));
        const createButton = canvas.getByRole('button', {name: /Utwórz w Ergonode/});

        await expect(createButton).toHaveTextContent('1');
        await userEvent.click(createButton);
        await waitFor(() => expect(canvasElement.querySelector('[data-created-ids]')).toHaveAttribute('data-created-ids', '12'));
    }
};

export const BranchSelectionCascades = {
    args: {variant: 'branch'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const parent = canvas.getByRole('checkbox', {name: 'Zaznacz kategorię Krzesła'});
        const child = canvas.getByRole('checkbox', {name: 'Zaznacz kategorię Biurowe'});

        await userEvent.click(parent);
        await expect(child).toBeChecked();
        await userEvent.click(parent);
        await expect(child).not.toBeChecked();
    }
};

export const IndividualCategoryAction = {
    args: {variant: 'bulk'}
};

export const CategoryCreationIsImmediate = {
    args: {variant: 'bulk'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const options = canvas.getByRole('button', {name: 'Opcje kategorii: Krzesła'});

        await userEvent.click(options);
        const button = within(options.closest('details')).getByRole('button', {
            name: /Utwórz kategorię w Ergonode i zapisz mapowanie/
        });
        await expect(button.querySelector('.vec-create-ergonode-category-icon')).toBeInTheDocument();
        await expect(button).toHaveAttribute(
            'title',
            'Utwórz kategorię w Ergonode i zapisz mapowanie z kategorią Magento.'
        );
        await userEvent.click(button);
        await waitFor(() => expect(canvasElement.querySelector('[data-layout-saved="true"]')).toBeInTheDocument());
        const root = canvasElement.querySelector('[data-layout-saved="true"]');

        await expect(root).toHaveAttribute('data-notification-type', 'success');
        await expect(root).toHaveAttribute(
            'data-notification',
            'Kategoria została utworzona w Ergonode i zmapowana z Magento.'
        );
        await expect(root).toHaveAttribute('data-published-code', 'krzesla');
        await expect(root).toHaveAttribute('data-rendered', 'true');
        await expect(root.querySelector('.vec-pending-ergonode-category')).not.toBeInTheDocument();
    }
};

export const CategoryCodeUsesFullMagentoPath = {
    args: {variant: 'path-code'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const options = canvas.getByRole('button', {name: 'Opcje kategorii: New Luma Yoga Collection'});

        await userEvent.click(options);
        await userEvent.click(within(options.closest('details')).getByRole('button', {
            name: /Utwórz kategorię w Ergonode i zapisz mapowanie/
        }));
        await waitFor(() => expect(canvasElement.querySelector('[data-layout-saved="true"]')).toBeInTheDocument());
        await expect(canvasElement.querySelector('[data-published-code]')).toHaveAttribute(
            'data-published-code',
            'collections__new_luma_yoga_collection'
        );
    }
};

export const ExistingCategoryIsAttachedToTree = {
    args: {variant: 'existing'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const options = canvas.getByRole('button', {name: 'Opcje kategorii: Men'});

        await userEvent.click(options);
        const button = within(options.closest('details')).getByRole('button', {
            name: /Utwórz kategorię w Ergonode i zapisz mapowanie/
        });
        await userEvent.click(button);
        await waitFor(() => expect(canvasElement.querySelector('[data-layout-saved="true"]')).toBeInTheDocument());
        const root = canvasElement.querySelector('[data-layout-saved="true"]');

        await expect(root).toHaveAttribute('data-notification-type', 'success');
        await expect(root).toHaveAttribute(
            'data-notification',
            'Istniejąca kategoria Ergonode została przypięta do drzewa i zmapowana z Magento.'
        );
        await expect(root).toHaveAttribute('data-published-code', 'men');
        await expect(root).toHaveAttribute('data-rendered', 'true');
    }
};

export const PendingCreationCanBeUnmapped = {
    args: {variant: 'pending'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);

        const pendingIndicator = canvas.getByLabelText(
            'Kategoria „Meble” nie istnieje jeszcze w Ergonode. Kliknij „Zapisz”, aby ją utworzyć i zapisać mapowanie.'
        );
        const tooltip = pendingIndicator.querySelector('.vec-pending-ergonode-category-tooltip');

        await expect(pendingIndicator).not.toHaveAttribute('title');
        await expect(tooltip).not.toBeVisible();
        await userEvent.click(pendingIndicator);
        await waitFor(() => expect(tooltip).toBeVisible());
        const options = canvas.getByRole('button', {name: 'Opcje kategorii: Meble'});

        await userEvent.click(options);
        await userEvent.click(within(options.closest('details')).getByRole('button', {name: 'Disconnect'}));
        await expect(canvasElement.querySelector('[data-removed-code]').dataset.removedCode).toBe('meble');
    }
};

export const PendingCategoriesUseOneIndicatorEach = {
    args: {variant: 'pending'},
    play: async ({canvasElement}) => {
        const cards = [...canvasElement.querySelectorAll('.vec-magento-card.is-pending-create')];

        await expect(cards).toHaveLength(2);
        cards.forEach((card) => {
            expect(card.querySelectorAll(':scope > .vec-pending-ergonode-category')).toHaveLength(1);
            expect(card.querySelector(':scope > .vec-magento-mapping')).not.toBeVisible();
        });
    }
};

export const WriteConfigurationMissing = {
    args: {variant: 'bulk', writeReady: false},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        await expect(canvasElement.querySelector('[data-role="write-readiness"]')).not.toBeInTheDocument();
        await userEvent.click(canvas.getByRole('checkbox', {name: 'Zaznacz kategorię Meble'}));
        await userEvent.click(canvas.getByRole('button', {name: 'Akcje kategorii: Magento'}));
        const create = canvas.getByRole('button', {name: /Utwórz w Ergonode/});
        await expect(create).toBeDisabled();
        await expect(canvas.getByRole('checkbox', {name: 'Zaznacz kategorię Meble'})).toBeChecked();
        await expect(canvasElement.querySelector('[data-created-ids]')).not.toBeInTheDocument();
    }
};
