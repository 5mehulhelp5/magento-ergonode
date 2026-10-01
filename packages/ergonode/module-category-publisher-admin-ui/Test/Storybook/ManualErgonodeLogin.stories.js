import { expect, userEvent, within } from 'storybook/test';

import { loadAmdModule } from '@ergonode-storybook/load-amd-module.js';

import ergonodeLogo from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/images/m2_configuration.svg';
import '../../view/adminhtml/web/css/ergonode-category-publisher.css';
import '@ergonode-modules/PublisherAdminUi/view/adminhtml/web/css/manual-auth.css';
import categoryCodeGeneratorSource from '../../view/adminhtml/web/js/category-code-generator.js?raw';
import jsonPostSource from '@ergonode-modules/PublisherAdminUi/view/adminhtml/web/js/json-post.js?raw';
import manualAuthSource from '@ergonode-modules/PublisherAdminUi/view/adminhtml/web/js/manual-auth.js?raw';
import readinessSource from '../../view/adminhtml/web/js/publication-readiness.js?raw';
import source from '../../view/adminhtml/web/js/ergonode-category-publisher-mapping.js?raw';

let authenticated = false;

function collection(elements) {
    return {
        elements,
        length: elements.length,
        on() { return this; },
        each(callback) { elements.forEach((element, index) => callback.call(element, index, element)); return this; },
        first() { return collection(elements.slice(0, 1)); },
        find(selector) { return collection(elements.flatMap((element) => [...element.querySelectorAll(selector)])); },
        data(name) { return elements[0]?.dataset[name.replace(/-([a-z])/g, (_, letter) => letter.toUpperCase())]; },
        append(...nodes) { elements.forEach((element) => element.append(...nodes.map((node) => node.element || node))); return this; },
        after(node) { elements.forEach((element) => element.after(node.element || node)); return this; },
        text(value) { elements.forEach((element) => { element.textContent = value; }); return this; },
        prop(name, value) { elements.forEach((element) => { element[name] = value; }); return this; },
        toggleClass(name, active) { elements.forEach((element) => element.classList.toggle(name, active)); return this; },
        trigger() { return this; },
        modal(action) {
            const widget = this;
            if (action === 'openModal') {
                widget.shell.hidden = false;
                widget.shell.style.display = 'flex';
            } else if (action === 'closeModal') {
                widget.shell.hidden = true;
                widget.shell.style.display = 'none';
                widget.options.closed?.();
            }
            return this;
        }
    };
}

function storyJquery(value, attributes) {
    if (typeof value === 'string' && value.startsWith('<')) {
        const tag = value.replace(/[<>/]/g, '');
        const element = document.createElement(tag);

        Object.entries(attributes || {}).forEach(([key, attribute]) => {
            if (key === 'class') element.className = attribute;
            else if (key === 'text') element.textContent = attribute;
            else if (key in element) element[key] = attribute;
            else element.setAttribute(key, attribute);
        });
        const result = collection([element]);
        result.element = element;
        return result;
    }

    return collection(value instanceof Element ? [value] : [...(value || [])]);
}

storyJquery.ajax = ({ url }) => {
    let response = { success: true, write_readiness: {ready: true, message: '', configuration_url: ''}, authenticated: true };

    if (url.includes('/status')) {
        response = { success: true, write_readiness: {ready: true, message: '', configuration_url: ''}, authenticated };
    } else if (url.includes('/category/batch/create')) {
        response = {
            success: true,
            items: [{
                code: 'chairs',
                label: 'Chairs',
                status: 'synchronized',
                message: 'Category has been synchronized with Ergonode.',
                remote_id: 'remote-chairs'
            }]
        };
    }

    if (url.includes('/login')) authenticated = true;
    return {
        done(callback) { queueMicrotask(() => callback(response)); return this; },
        fail() { return this; },
        always(callback) { queueMicrotask(callback); return this; }
    };
};

function storyModal(options, widget, host) {
    const shell = document.createElement('div');
    const inner = document.createElement('div');
    const header = document.createElement('div');
    const title = document.createElement('h1');
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
    title.textContent = options.title;
    content.className = 'modal-content';
    widget.elements[0].style.display = 'block';
    content.append(widget.elements[0]);
    header.append(title);
    inner.append(header, content);
    shell.append(inner);
    host.append(shell);
}

function createProgressStub() {
    return {
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

const translate = (value) => ({
    'Log in to Ergonode': 'Logowanie do Ergonode',
    'Ergonode login': 'Login Ergonode',
    'Password': 'Hasło',
    'Cancel': 'Anuluj',
    'Log in': 'Zaloguj',
    'Why is login required?': 'Dlaczego logowanie jest potrzebne?',
    'Magento can create categories automatically, but Ergonode does not allow category trees to be created or rearranged through the integration connection.':
        'Magento może automatycznie tworzyć kategorie, ale połączenie integracyjne Ergonode nie pozwala tworzyć drzew kategorii ani zmieniać ich układu.',
    'Your Ergonode account must be allowed to view categories and category trees, and to create and edit category trees.':
        'Twoje konto Ergonode musi mieć uprawnienia do wyświetlania kategorii i drzew kategorii oraz do tworzenia i edycji drzew kategorii.'
}[value] || value);
const productionCategoryCodeGenerator = loadAmdModule(
    categoryCodeGeneratorSource,
    {},
    'Ergonode_CategoryPublisherAdminUi/js/category-code-generator'
);
const productionJsonPost = loadAmdModule(jsonPostSource, {
    jquery: storyJquery,
    'mage/translate': translate
}, 'Ergonode_PublisherAdminUi/js/json-post');
function render(args) {
    authenticated = args.state === 'authenticated';
    const root = document.createElement('div');
    const save = document.createElement('button');
    const status = document.createElement('output');
    let recoverSave;
    status.setAttribute('role', 'status');
    root.dataset.dirty = 'true';

    root.id = 'ergonode-category-tree-mapping';
    root.className = 'veui-workspace vec-admin';
    save.type = 'button';
    save.dataset.role = 'save-categories';
    save.textContent = 'Zapisz ręczną zmianę drzewa';
    root.append(save, status);
    const productionManualAuth = loadAmdModule(manualAuthSource, {
        jquery: storyJquery,
        'Magento_Ui/js/modal/modal': (options, widget) => storyModal(options, widget, root),
        'mage/translate': translate,
        'Ergonode_PublisherAdminUi/js/json-post': productionJsonPost
    }, 'Ergonode_PublisherAdminUi/js/manual-auth');
    const productionInitializer = loadAmdModule(source, {
        jquery: storyJquery,
        'mage/translate': translate,
        'Ergonode_CoreAdminUi/js/entity-options': {createAction: () => document.createElement('button')},
        'Ergonode_CategoryPublisherAdminUi/js/category-code-generator': productionCategoryCodeGenerator,
        'Ergonode_CategoryPublisherAdminUi/js/category-publish-progress': createProgressStub,
        'Ergonode_PublisherAdminUi/js/json-post': productionJsonPost,
        'Ergonode_PublisherAdminUi/js/manual-auth': productionManualAuth,
        'Ergonode_CategoryPublisherAdminUi/js/publication-readiness': loadAmdModule(readinessSource, {
            'mage/translate': translate,
            'Ergonode_PublisherAdminUi/js/json-post': productionJsonPost
        }, 'Ergonode_CategoryPublisherAdminUi/js/publication-readiness')
    }, 'Ergonode_CategoryPublisherAdminUi/js/ergonode-category-publisher-mapping');


    root.veaCategoryMappingApi = {
        setSaveRecoveryHandler: (handler) => { recoverSave = handler; },
        getCategories: () => [{
            code: 'chairs',
            ergonode_category_id: args.remoteIdentity ? 'remote-chairs' : null,
            parent_code: 'home',
            source_parent_code: null,
            sort_order: 1,
            source_sort_order: 1,
            extension_data: {to_ergonode: {pending_create: args.pendingCreation ?? !args.remoteIdentity}}
        }],
        getMagentoCategories: () => [],
        addAndMapCategory: () => false,
        getCategoryTreeId: () => 7,
        markCategoryRemotePrepared: () => true,
        markCategoryPublished: () => true,
        render() {},
        saveLayout: () => ({
            done(callback) { queueMicrotask(() => callback({ success: true })); return this; },
            fail() { return this; }
        })
    };
    productionInitializer({
        form_key: 'storybook',
        write_readiness: {ready: true, message: '', configuration_url: ''},
        logo_url: ergonodeLogo,
        urls: {
            status: '/status',
            login: '/login',
            create_tree: '/tree/create',
            create_category_batch: '/category/batch/create'
        }
    }, root);

    if (!args.expiredOnSave) {
        save.addEventListener('click', () => {
            root.dataset.dirty = 'false';
            status.textContent = 'Zapisano mapowanie.';
        });
    }

    if (args.expiredOnSave) {
        save.addEventListener('click', () => {
            Promise.resolve(recoverSave({failure_type: 'authentication_required'})).then((retry) => {
                if (retry) {
                    root.dataset.dirty = 'false';
                    status.textContent = 'Zapisano mapowanie.';
                }
            });
        });
    }

    if (args.state !== 'authenticated') {
        save.click();
    }
    if (args.state === 'loading') {
        queueMicrotask(() => {
            const submit = document.querySelector('.vec-manual-auth [type="submit"]');
            if (submit) submit.disabled = true;
        });
    }
    if (args.state === 'error') {
        queueMicrotask(() => {
            const error = document.querySelector('.vec-manual-auth-error');
            if (error) {
                error.hidden = false;
                error.textContent = 'Nieprawidłowy login lub hasło.';
            }
        });
    }

    return root;
}

export default {
    id: 'ergo-v-068-04',
    title: 'Ergonode UI/Widoki/Kategorie/ERGO-V-068 · Formularz kategorii/ERGO-V-068.04 · Logowanie do Ergonode',
    tags: ['manual-session'],
    render,
    args: { state: 'logged-out', remoteIdentity: false },
    argTypes: {
        state: { control: 'select', options: ['logged-out', 'loading', 'error', 'authenticated'] },
        remoteIdentity: { control: 'boolean' }
    },
    parameters: { a11y: { test: 'error' } }
};

export const Playground = {};

export const LocalLayoutSaveSkipsLogin = {
    args: { state: 'logged-out', remoteIdentity: true },
    play: async () => {
        await new Promise((resolve) => window.setTimeout(resolve, 0));
        await expect(
            within(document.body).queryByRole('dialog', { name: 'Logowanie do Ergonode' })
        ).not.toBeInTheDocument();
    }
};

export const StateMatrix = {
    render: () => {
        const matrix = document.createElement('div');
        matrix.style.display = 'grid';
        matrix.style.gap = '16px';
        ['logged-out', 'loading', 'error', 'authenticated'].forEach((state) => {
            const heading = document.createElement('h2');
            heading.textContent = state;
            matrix.append(heading, render({ state, remoteIdentity: false }));
        });
        return matrix;
    }
};

export const KeyboardLogin = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const save = canvas.getByRole('button', { name: 'Zapisz ręczną zmianę drzewa' });

        await userEvent.click(save);
        const dialog = await within(canvasElement).findByRole('dialog', { name: 'Logowanie do Ergonode' });
        const scoped = within(dialog);
        const logo = dialog.querySelector('[data-role="manual-auth-logo"]');

        await expect(logo).toHaveAttribute('src', ergonodeLogo);
        await expect(logo.closest('.modal-title')).not.toBeNull();
        await expect(scoped.getByText('Dlaczego logowanie jest potrzebne?')).toBeVisible();
        await expect(scoped.getByText(/must have permission/)).toBeVisible();
        await expect(scoped.getByRole('checkbox', {name: 'Remember me'})).not.toBeChecked();
        await userEvent.click(scoped.getByRole('checkbox', {name: 'Remember me'}));
        await userEvent.type(scoped.getByRole('textbox', { name: 'Login Ergonode' }), 'admin@example.com');
        await userEvent.type(scoped.getByLabelText('Hasło'), 'secret');
        await userEvent.keyboard('{Enter}');
        await expect(dialog).not.toBeVisible();
    }
};


export const ExpiredSessionOnSave = {
    args: { state: 'logged-out', remoteIdentity: true, expiredOnSave: true },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const dialog = await canvas.findByRole('dialog', { name: 'Logowanie do Ergonode' });
        const scoped = within(dialog);
        const workspace = canvasElement.querySelector('#ergonode-category-tree-mapping');

        await expect(workspace).toHaveAttribute('data-dirty', 'true');
        await userEvent.type(scoped.getByRole('textbox', { name: 'Login Ergonode' }), 'admin@example.com');
        await userEvent.type(scoped.getByLabelText('Hasło'), 'secret');
        await userEvent.keyboard('{Enter}');
        await expect(canvas.getByRole('status')).toHaveTextContent('Zapisano mapowanie.');
        await expect(workspace).toHaveAttribute('data-dirty', 'false');
    }
};

export const ExpiredSessionCancelled = {
    args: { state: 'logged-out', remoteIdentity: true, expiredOnSave: true },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const dialog = await canvas.findByRole('dialog', { name: 'Logowanie do Ergonode' });

        await userEvent.click(within(dialog).getByRole('button', { name: 'Anuluj' }));
        await expect(dialog).not.toBeVisible();
        await expect(canvasElement.querySelector('#ergonode-category-tree-mapping')).toHaveAttribute('data-dirty', 'true');
        await expect(canvas.getByRole('status')).toBeEmptyDOMElement();
    }
};

export const MissingRemoteIdentitySaveSkipsLogin = {
    args: { state: 'logged-out', remoteIdentity: false, pendingCreation: false },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const save = canvas.getByRole('button', { name: 'Zapisz ręczną zmianę drzewa' });

        // The actual publisher capture listener must let the local save run.
        await expect(canvas.getByRole('status')).toHaveTextContent('Zapisano mapowanie.');
        await expect(canvasElement.querySelector('#ergonode-category-tree-mapping'))
            .toHaveAttribute('data-dirty', 'false');
        await userEvent.click(save);
        await userEvent.tab();
        save.focus();
        await userEvent.keyboard('{Enter}');
        await expect(canvas.getByRole('status')).toHaveTextContent('Zapisano mapowanie.');
        await expect(within(document.body).queryByRole('dialog', { name: 'Logowanie do Ergonode' }))
            .not.toBeInTheDocument();
    }
};
