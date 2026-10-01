import { expect, userEvent, within } from 'storybook/test';
import systemXml from '../../etc/adminhtml/system.xml?raw';
import defaultsXml from '../../../module-category-attribute-history/etc/config.xml?raw';

export default {
    id: 'ergo-v-010-01',
    title: 'Ergonode UI/Widoki/Atrybuty kategorii/ERGO-V-010 · Historia mapowania/ERGO-V-010.01 · Konfiguracja historii',
    parameters: {
        a11y: { test: 'error' },
        docs: { description: { component: 'Fields and defaults come from production XML. Native Magento form styling and saving require Magento Admin verification.' } },
    },
};

function render({ recording, cleanup }) {
    const parser = new DOMParser();
    const config = parser.parseFromString(systemXml, 'text/xml');
    const defaults = parser.parseFromString(defaultsXml, 'text/xml');
    const group = config.querySelector('group');
    const root = document.createElement('fieldset');
    root.className = 'admin__fieldset';
    const legend = document.createElement('legend');
    legend.textContent = group.querySelector('label').textContent;
    root.append(legend);
    for (const field of group.querySelectorAll('field[type]')) {
        const id = field.getAttribute('id');
        const row = document.createElement('div');
        row.className = 'admin__field';
        const label = document.createElement('label');
        label.htmlFor = `history-${id}`;
        label.textContent = field.querySelector('label').textContent;
        const input = document.createElement(field.getAttribute('type') === 'select' ? 'select' : 'input');
        input.id = label.htmlFor;
        input.name = id;
        input.className = input.tagName === 'SELECT' ? 'admin__control-select' : 'admin__control-text';
        if (input.tagName === 'SELECT') {
            input.add(new Option('Yes', '1'));
            input.add(new Option('No', '0'));
        } else {
            input.required = true;
        }
        input.value = defaults.querySelector(`history > ${id}`).textContent;
        if (id === 'enabled') input.value = recording ? '1' : '0';
        if (id === 'cleanup_enabled') input.value = cleanup ? '1' : '0';
        const note = document.createElement('p');
        note.id = `${input.id}-note`;
        note.textContent = field.querySelector('comment').textContent;
        input.setAttribute('aria-describedby', note.id);
        row.append(label, input, note);
        if (field.querySelector('depends')) row.dataset.cleanupDependent = 'true';
        root.append(row);
    }
    const refresh = () => {
        const enabled = root.querySelector('[name="cleanup_enabled"]').value === '1';
        root.querySelectorAll('[data-cleanup-dependent]').forEach(row => { row.hidden = !enabled; });
    };
    root.querySelector('[name="cleanup_enabled"]').addEventListener('change', refresh);
    refresh();
    return root;
}

export const Playground = {
    args: { recording: true, cleanup: true },
    render,
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByLabelText('Record changes')).toHaveValue('1');
        await expect(canvas.getByLabelText('Automatic cleanup')).toHaveValue('1');
        await expect(canvas.getByLabelText('Keep for (days)')).toHaveValue('30');
        await expect(canvas.getByLabelText('Schedule')).toHaveValue('15 2 * * *');
        await userEvent.selectOptions(canvas.getByLabelText('Record changes'), '0');
        await expect(canvas.getByLabelText('Schedule')).toBeVisible();
        await userEvent.selectOptions(canvas.getByLabelText('Automatic cleanup'), '0');
        await expect(canvas.getByLabelText('Keep for (days)')).not.toBeVisible();
        await userEvent.click(canvas.getByLabelText('Automatic cleanup'));
        await userEvent.keyboard('{Home}');
        await userEvent.selectOptions(canvas.getByLabelText('Automatic cleanup'), '1');
        await expect(canvas.getByLabelText('Keep for (days)')).toBeVisible();
    },
};
export const RecordingOff = { args: { recording: false, cleanup: true }, render };
export const CleanupOff = { args: { recording: true, cleanup: false }, render };
export const BothOff = { args: { recording: false, cleanup: false }, render };
