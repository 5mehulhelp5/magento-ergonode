import { expect, userEvent, within } from 'storybook/test';
import systemXml from '../../etc/adminhtml/system.xml?raw';

const comment = systemXml.match(/<comment\s*>([\s\S]*?)<\/comment>/)[1].trim();

export default {
    id: 'ergo-v-069-01',
    title: 'Ergonode UI/Widoki/Produkty/ERGO-V-069 · Ustawienia SKU/ERGO-V-069.01 · Tożsamość SKU',
    parameters: {
        a11y: { test: 'error' },
        docs: { description: { component: 'Natywne pole konfiguracji Magento. Fixture przedstawia opcje dostarczane przez PHP; walidację zapisu sprawdzają testy modułu. Pełna skórka formularza wymaga Magento Admin.' } },
    },
    argTypes: {
        attributeSupport: { control: 'boolean' },
        mappingReady: { control: 'boolean' },
        mode: { control: 'select', options: ['', 'mapped', 'assigned'] },
        attributeCode: { control: 'select', options: ['', 'navireo_id'] },
    },
};

export const Playground = {
    args: { attributeSupport: false, mappingReady: false, mode: '', attributeCode: '' },
    render: ({ attributeSupport, mappingReady, mode, attributeCode }) => {
        const root = document.createElement('div');
        root.className = 'admin__fieldset';
        root.innerHTML = `
          <div class="admin__field">
            <label class="admin__field-label" for="ergonode_products_identity_sku_mode">SKU</label>
            <div class="admin__field-control">
                <select id="ergonode_products_identity_sku_mode" class="admin__control-select" aria-describedby="sku-mode-note">
                    <option value="">Choose SKU mode</option>
                    <option value="mapped">Use a Magento attribute as Ergonode SKU</option>
                </select>
                <p class="note" id="sku-mode-note"></p>
            </div></div>
            <div class="admin__field" data-role="mapped-attribute">
              <label class="admin__field-label" for="ergonode_products_identity_magento_attribute">Magento attribute for Ergonode SKU</label>
              <div class="admin__field-control"><select id="ergonode_products_identity_magento_attribute" class="admin__control-select">
                <option value="">Choose Magento product attribute</option>
                <option value="navireo_id">Navireo ID (navireo_id)</option>
              </select></div>
            </div>
            <div class="admin__field" data-role="assigned-readiness">
              <span class="admin__field-label">Assigned SKU readiness</span>
              <div class="admin__field-control">
                <span data-role="readiness-message"></span>
                <p class="note">Configure Magento sku to exactly one unique Global Text Ergonode attribute in Ergonode &gt; Products &gt; Attributes.</p>
              </div>
            </div>`;
        const select = root.querySelector('#ergonode_products_identity_sku_mode');
        root.querySelector('#ergonode_products_identity_magento_attribute').value = attributeCode;
        if (attributeSupport || mode === 'assigned') {
            const option = document.createElement('option');
            option.value = 'assigned';
            option.textContent = attributeSupport
                ? 'Let Ergonode assign SKU; publish Magento SKU as a separate attribute'
                : 'Independent SKUs unavailable — restore product attribute mapping support';
            select.append(option);
        }
        select.value = mode;
        const updateVisibility = () => {
            root.querySelector('[data-role="mapped-attribute"]').hidden = select.value !== 'mapped';
            root.querySelector('[data-role="assigned-readiness"]').hidden = select.value !== 'assigned';
        };
        select.addEventListener('change', updateVisibility);
        updateVisibility();
        root.querySelector('.note').textContent = comment;
        root.querySelector('[data-role="readiness-message"]').textContent = mappingReady
            ? 'Local mapping complete. Ergonode settings, template placement and product value are checked during publication.'
            : 'Incomplete: Magento sku needs exactly one complete Global Text mapping.';
        return root;
    },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const select = canvas.getByRole('combobox', { name: 'SKU' });
        await expect(select).toHaveValue('');
        await expect(canvas.queryByRole('combobox', { name: 'Magento attribute for Ergonode SKU' })).not.toBeInTheDocument();
    },
};

export const AttributeSupport = {
    ...Playground,
    args: { attributeSupport: true, mappingReady: true, mode: '' },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const select = canvas.getByRole('combobox', { name: 'SKU' });
        await userEvent.selectOptions(select, 'assigned');
        await expect(select).toHaveValue('assigned');
        await expect(canvas.queryByRole('combobox', { name: 'Magento attribute for Ergonode SKU' })).not.toBeInTheDocument();
        await expect(canvas.getByText(/Local mapping complete/)).toBeVisible();
    },
};

export const MappedAttribute = {
    ...Playground,
    args: { attributeSupport: false, mode: 'mapped', attributeCode: 'navireo_id' },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByRole('combobox', { name: 'SKU' })).toHaveValue('mapped');
        await expect(canvas.getByRole('combobox', { name: 'Magento attribute for Ergonode SKU' })).toHaveValue('navireo_id');
    },
};

export const RemovedSupport = {
    ...Playground,
    args: { attributeSupport: false, mode: 'assigned' },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByRole('combobox', { name: 'SKU' })).toHaveValue('assigned');
        await expect(canvas.getByRole('option', { name: /unavailable/ })).toBeVisible();
    },
};
