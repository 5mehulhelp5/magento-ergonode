import systemXml from '../../etc/adminhtml/system.xml?raw';
import { expect, userEvent, within } from 'storybook/test';

export default {
    id: 'ergo-v-067-01',
    title: 'Ergonode UI/Widoki/Media/ERGO-V-067 · Ustawienia mediów/ERGO-V-067.01 · Konfiguracja galerii',
    parameters: {
        a11y: { test: 'error' },
        docs: { description: { component: 'Pola odczytane z produkcyjnego system.xml. Lista galerii jest fixture danych. Zapis i skórka formularza wymagają Magento Admin.' } },
    },
};

const render = ({ enabled, gallery, galleries, role = 'hover_image', rolePosition = 2 }) => {
    const xml = new DOMParser().parseFromString(systemXml, 'text/xml');
    const form = document.createElement('form');
    form.className = 'admin__fieldset';
    ['synchronization_enabled', 'gallery_attribute', 'role_position', 'additional_role'].forEach((name) => {
        const field = xml.querySelector(`field[id="${name}"]`);
        const group = document.createElement('div');
        group.className = 'admin__field';
        const label = document.createElement('label');
        label.className = 'admin__field-label';
        label.htmlFor = `media-${name}`;
        label.textContent = field.querySelector('label').textContent;
        const select = document.createElement(name === 'role_position' ? 'input' : 'select');
        select.className = name === 'role_position' ? 'admin__control-text' : 'admin__control-select';
        select.id = label.htmlFor;
        select.required = field.querySelector('validate')?.textContent === 'required-entry';
        const options = name === 'synchronization_enabled'
            ? [['0', 'No'], ['1', 'Yes']]
            : name === 'additional_role'
                ? [['', 'None'], ['hover_image', 'Hover image (hover_image)']]
                : [['', 'Choose a gallery attribute'], ...galleries];
        if (name === 'role_position') {
            select.type = 'number';
            select.min = '2';
            select.max = '65535';
            select.required = true;
            select.value = String(rolePosition);
        } else {
            options.forEach(([value, text]) => select.add(new Option(text, value)));
            select.value = name === 'synchronization_enabled' ? String(Number(enabled))
                : name === 'additional_role' ? role : gallery;
        }
        const note = document.createElement('p');
        note.className = 'admin__field-note';
        note.textContent = field.querySelector('comment').textContent;
        group.append(label, select, note);
        form.append(group);
    });
    return form;
};

export const Playground = {
    args: { enabled: true, gallery: 'photos', galleries: [['photos', 'Photos (photos)'], ['details', 'Details (details)']] },
    render,
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const gallery = canvas.getByLabelText('Gallery Attribute');
        await userEvent.selectOptions(gallery, 'details');
        await expect(gallery).toHaveValue('details');
        gallery.focus();
        await userEvent.keyboard('{Tab}');
        await expect(gallery).not.toHaveFocus();
        await userEvent.selectOptions(gallery, '');
        await expect(gallery.checkValidity()).toBe(false);
    },
};
export const SynchronizationDisabled = { args: { ...Playground.args, enabled: false }, render };
export const SelectionRequired = { args: { ...Playground.args, gallery: '' }, render };
export const NoAvailableGallery = { args: { enabled: false, gallery: '', galleries: [] }, render };

export const ReadAndWrite = {
    args: { ...Playground.args }, render,
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByLabelText('Synchronize Media')).toBeEnabled();
        await expect(canvas.getByLabelText('Gallery Attribute')).toBeEnabled();
        await expect(canvas.getByLabelText('Additional image role')).toHaveValue('hover_image');
        const position = canvas.getByLabelText('Additional role position');
        await userEvent.clear(position);
        await userEvent.type(position, '1');
        await expect(position.checkValidity()).toBe(false);
    }
};
export const NoAdditionalRole = { args: { ...Playground.args, role: '' }, render };
