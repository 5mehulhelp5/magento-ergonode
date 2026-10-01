import { expect, userEvent, within } from 'storybook/test';
import { loadAmdModule } from '@ergonode-storybook/load-amd-module.js';

import '../../view/adminhtml/web/css/category-form.css';
import source from '../../view/adminhtml/web/js/form/element/category-refresh.js?raw';

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

function storyJquery() {
    return {};
}

storyJquery.ajax = () => ({
    done(callback) {
        queueMicrotask(() => callback({
            success: true,
            reload: false,
            message: 'Odświeżono kategorię z Ergonode.'
        }));
        return this;
    },
    fail() { return this; }
});

function createComponent(dirty) {
    return loadAmdModule(source, {
        jquery: storyJquery,
        uiRegistry: {filter: (predicate) => [{
            provider: 'category_form.category_form_data_source', hasChanged: () => dirty
        }].filter(predicate)},
        'Magento_Ui/js/form/element/abstract': {extend: (definition) => definition},
        'mage/translate': (value) => value
    }, 'Ergonode_CategoryAttributeConsumerAdminUi/js/form/element/category-refresh');
}

function render(args) {
    const fieldset = document.createElement('section');
    const title = document.createElement('h2');
    const field = document.createElement('div');
    const label = document.createElement('label');
    const input = document.createElement('input');
    const notice = document.createElement('div');
    const action = document.createElement('div');
    const button = document.createElement('button');
    const status = document.createElement('div');

    fieldset.className = 'admin__fieldset-wrapper';
    title.className = 'admin__fieldset-wrapper-title';
    title.textContent = 'Ergonode';
    field.className = 'admin__field _disabled';
    label.className = 'admin__field-label';
    label.htmlFor = 'ergonode-category-code';
    label.textContent = 'Kod kategorii';
    input.id = 'ergonode-category-code';
    input.className = 'admin__control-text';
    input.type = 'text';
    input.value = args.code;
    input.disabled = true;
    notice.className = 'admin__field-note';
    notice.textContent = 'Kod jest widoczny tylko dla kategorii prawidłowo zmapowanej z Ergonode.';
    action.className = 'vec-category-form-refresh';
    button.type = 'button';
    button.className = 'action-default scalable vec-category-form-refresh-button';
    button.dataset.role = 'refresh-ergonode-category-from-form';
    button.textContent = 'Odśwież';
    button.hidden = args.code === '';
    status.className = 'vec-category-form-refresh-status';
    status.role = 'status';
    status.hidden = true;
    action.append(button, status);
    field.append(label, input, notice);
    fieldset.append(title, field, action);

    if (args.code !== '') {
        const instance = Object.create(createComponent(args.dirty));

        instance.provider = 'category_form.category_form_data_source';

        instance.urls = {refresh: '/category/refresh'};
        instance.categoryId = observable(12);
        instance.categoryCode = observable(args.code);
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
        instance.reloadPage = () => {};
        button.addEventListener('click', () => instance.refresh());
    }

    return fieldset;
}

export default {
    id: 'ergo-v-068-01',
    title: 'Ergonode UI/Widoki/Kategorie/ERGO-V-068 · Formularz kategorii/ERGO-V-068.01 · Kod mapowania w formularzu kategorii',
    render,
    args: {code: 'chairs', dirty: false},
    argTypes: {code: {control: 'text'}, dirty: {control: 'boolean'}},
    parameters: {a11y: {test: 'error'}}
};

export const Playground = {};
export const Mapped = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const input = canvas.getByLabelText('Kod kategorii');

        await expect(input).toBeDisabled();
        await expect(input).toHaveValue('chairs');
        await userEvent.click(canvas.getByRole('button', {name: 'Odśwież'}));
        await expect(canvas.getByRole('status')).toHaveTextContent('Odświeżono kategorię');
    }
};
export const Unmapped = {
    args: {code: ''},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const input = canvas.getByLabelText('Kod kategorii');

        await expect(input).toBeDisabled();
        await expect(input).toHaveValue('');
        await expect(canvas.queryByRole('button', {name: 'Odśwież'})).not.toBeInTheDocument();
    }
};

export const UnsavedChanges = {
    args: {dirty: true},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const button = canvas.getByRole('button', {name: 'Odśwież'});

        button.focus();
        await userEvent.keyboard('{Enter}');
        await expect(canvas.getByRole('status')).toHaveTextContent('Save or discard your changes');
        await expect(button).toBeEnabled();
    }
};
