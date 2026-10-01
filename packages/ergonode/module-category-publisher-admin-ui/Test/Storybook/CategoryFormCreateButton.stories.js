import { expect, userEvent, within, waitFor } from 'storybook/test';
import { loadAmdModule } from '@ergonode-storybook/load-amd-module.js';

import '../../view/adminhtml/web/css/ergonode-category-publisher.css';
import '../../view/adminhtml/web/css/category-form.css';
import source from '../../view/adminhtml/web/js/form/element/category-create.js?raw';
import readinessSource from '../../view/adminhtml/web/js/publication-readiness.js?raw';

function observable(initialValue, changed = () => {}) {
    let value = initialValue;

    return function (nextValue) {
        if (arguments.length) {
            value = nextValue;
            changed(value);
        }

        return value;
    };
}

const jsonPost = {
    post: () => Promise.resolve({
            success: true,
            code: 'chairs',
            message: 'Kategoria została utworzona w Ergonode i zmapowana z Magento.'
        })
};

function createComponent(transport) {
    return loadAmdModule(source, {
    require: {toUrl: () => '/ergonode-logo.svg'},
    'Magento_Ui/js/form/element/abstract': {extend: (definition) => definition},
    'mage/translate': (value) => value,
    'Ergonode_PublisherAdminUi/js/json-post': transport,
    'Ergonode_PublisherAdminUi/js/manual-auth': () => ({ensure: () => Promise.resolve(true)}),
    'Ergonode_CategoryPublisherAdminUi/js/publication-readiness': loadAmdModule(readinessSource, {
        'mage/translate': (value) => value,
        'Ergonode_PublisherAdminUi/js/json-post': jsonPost
    }, 'Ergonode_CategoryPublisherAdminUi/js/publication-readiness')
}, 'Ergonode_CategoryPublisherAdminUi/js/form/element/category-create');
}

function render(args) {
    const fieldset = document.createElement('section');
    const title = document.createElement('h2');
    const field = document.createElement('div');
    const label = document.createElement('label');
    const input = document.createElement('input');
    const action = document.createElement('div');
    const button = document.createElement('button');
    const status = document.createElement('div');

    fieldset.className = 'admin__fieldset-wrapper';
    title.className = 'admin__fieldset-wrapper-title';
    title.textContent = 'Ergonode';
    field.className = 'admin__field _disabled';
    label.className = 'admin__field-label';
    label.htmlFor = 'publisher-story-category-code';
    label.textContent = 'Kod kategorii';
    input.id = 'publisher-story-category-code';
    input.className = 'admin__control-text';
    input.disabled = true;
    input.value = args.mapped ? 'chairs' : '';
    action.className = 'vec-category-form-create';
    button.type = 'button';
    button.className = 'action-default scalable vec-category-form-create-button';
    button.dataset.role = 'create-ergonode-category-from-form';
    button.textContent = 'Opublikuj';
    status.className = 'vec-category-form-create-status';
    status.role = 'status';
    status.hidden = true;
    action.append(button, status);
    field.append(label, input);
    fieldset.append(title, field, action);

    if (args.mapped) {
        button.hidden = true;
        return fieldset;
    }

    const requests = [];
    const transport = args.pending ? {
        post(_url, _config, payload) {
            return new Promise(resolve => requests.push({payload, resolve}));
        }
    } : jsonPost;
    const instance = Object.create(createComponent(transport));
    let authDestroyed = false;

    fieldset.publisherFixture = {instance, requests, authDestroyed: () => authDestroyed};
    instance._super = () => instance;

    instance.urls = {create: '/category/create'};
    instance.categoryId = observable(12, () => instance.syncContext());
    instance.categoryTreeId = observable(7);
    instance.categoryCode = observable('', (value) => {
        input.value = value;
        button.hidden = value !== '';
    });
    instance.busy = observable(false, (value) => { button.disabled = value; });
    instance.messageType = observable('', (value) => {
        status.classList.toggle('is-success', value === 'success');
        status.classList.toggle('is-error', value === 'error');
    });
    instance.message = observable('', (value) => {
        status.textContent = value;
        status.hidden = value === '';
    });
    instance.visible = observable(true);
    instance.auth = {ensure: () => Promise.resolve(true), destroy: () => { authDestroyed = true; }};
    instance.writeReady = observable(true);
    instance.readiness = {ensure: () => Promise.resolve(true)};
    instance.source = {
        set(_path, value) { instance.categoryCode(value); }
    };
    instance.syncContext();
    button.addEventListener('click', () => instance.create());

    return fieldset;
}

export default {
    id: 'ergo-v-068-02',
    title: 'Ergonode UI/Widoki/Kategorie/ERGO-V-068 · Formularz kategorii/ERGO-V-068.02 · Przycisk publikacji formularza',
    render,
    args: {mapped: false},
    argTypes: {mapped: {control: 'boolean'}},
    parameters: {a11y: {test: 'error'}}
};

export const Playground = {};
export const Unmapped = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);

        await userEvent.click(canvas.getByRole('button', {name: 'Opublikuj'}));
        await expect(canvas.getByRole('status')).toHaveTextContent('zmapowana z Magento');
        await expect(canvas.queryByRole('button', {name: 'Opublikuj'})).not.toBeInTheDocument();
        await expect(canvas.getByLabelText('Kod kategorii')).toHaveValue('chairs');
    }
};
export const Mapped = {
    args: {mapped: true},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);

        await expect(canvas.queryByRole('button', {name: 'Opublikuj'})).not.toBeInTheDocument();
        await expect(canvas.getByLabelText('Kod kategorii')).toHaveValue('chairs');
    }
};

export const ChangedCategoryIgnoresLateResponse = {
    args: {pending: true},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const fixture = canvasElement.querySelector('section').publisherFixture;

        await userEvent.click(canvas.getByRole('button', {name: 'Opublikuj'}));
        await waitFor(() => expect(fixture.requests).toHaveLength(1));
        fixture.instance.categoryId(99);
        fixture.requests[0].resolve({success: true, code: 'old-category'});
        await waitFor(() => expect(fixture.instance.publication).toBeNull());
        await expect(canvas.getByLabelText('Kod kategorii')).toHaveValue('');
        await expect(canvas.getByRole('button', {name: 'Opublikuj'})).toBeEnabled();
    }
};

export const DisposeDuringRetry = {
    args: {pending: true},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const fixture = canvasElement.querySelector('section').publisherFixture;

        await userEvent.click(canvas.getByRole('button', {name: 'Opublikuj'}));
        await waitFor(() => expect(fixture.requests).toHaveLength(1));
        fixture.requests[0].resolve({success: false, failure_type: 'retryable', retry_after_seconds: 1});
        await waitFor(() => expect(fixture.instance.publication.cancelWait).toBeTypeOf('function'));
        fixture.instance.destroy();
        await new Promise(resolve => window.setTimeout(resolve, 1100));
        await expect(fixture.requests).toHaveLength(1);
        await expect(fixture.authDestroyed()).toBe(true);
        await expect(canvas.getByLabelText('Kod kategorii')).toHaveValue('');
    }
};
