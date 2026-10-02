import { expect, userEvent, within } from 'storybook/test';
import { loadAmdModule } from '@ergonode-storybook/load-amd-module.js';
import source from '../../view/adminhtml/web/js/additional-images.js?raw';
import template from '../../view/adminhtml/web/template/additional-images.html?raw';

const defaults = { name: 'groups[media][fields][additional_images][value]', options: { back: 'Back (back)', detail: 'Detail (detail)' }, rows: [] };
function render(args) {
    const form = document.createElement('form');
    const root = document.createElement('div');
    form.append(root);
    loadAmdModule(source, { 'mage/translate': text => text, 'text!Ergonode_MediaAdminUi/template/additional-images.html': template })({ ...defaults, ...args }, root);
    return form;
}
export default {
    id: 'ergo-v-066-01', title: 'Ergonode UI/Widoki/Produkty/ERGO-V-066 · Edycja produktu/ERGO-V-066.01 · Dodatkowe zdjęcia', render, parameters: { a11y: { test: 'error' } } };
export const Playground = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const add = canvas.getByRole('button', { name: 'Add image' });
        add.focus();
        await userEvent.keyboard('{Enter}');
        await userEvent.selectOptions(canvas.getByRole('combobox'), 'back');
        await expect(canvas.getByRole('spinbutton')).toHaveValue(2);
        await expect(new FormData(canvasElement.querySelector('form')).get(defaults.name + '[0][attribute]')).toBe('back');
        await userEvent.click(add);
        await userEvent.selectOptions(canvas.getAllByRole('combobox')[1], 'detail');
        await expect(canvas.getAllByRole('spinbutton')[1].checkValidity()).toBe(false);
        await userEvent.clear(canvas.getAllByRole('spinbutton')[1]);
        await userEvent.type(canvas.getAllByRole('spinbutton')[1], '4');
        await expect(canvas.getAllByRole('spinbutton')[1].checkValidity()).toBe(true);
        await userEvent.click(canvas.getAllByRole('button', { name: 'Remove image' })[0]);
        await expect(canvas.getAllByRole('combobox')).toHaveLength(1);
    }
};
export const Configured = { args: { rows: [{ attribute: 'back', position: 2 }, { attribute: 'detail', position: 4 }] } };
export const RemovedSource = { args: { rows: [{ attribute: 'removed', position: 2 }] } };
export const ConnectionFailure = { args: { options: {}, rows: [{ attribute: 'back', position: 2 }], error: 'Image attributes are unavailable. Check the Ergonode connection.' } };
