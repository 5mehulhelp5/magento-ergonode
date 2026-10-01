export default {
    id: 'ergo-v-066-02',
    title: 'Ergonode UI/Widoki/Produkty/ERGO-V-066 · Edycja produktu/ERGO-V-066.02 · Identyfikator Ergonode',
    parameters: {
        a11y: { test: 'error' },
        docs: {description: {component: 'Fixture danych pola produktu. Natywna skórka formularzy Magento nie jest częścią tego podglądu; wygląd formularza należy sprawdzać w Magento Admin.'}},
    },
    argTypes: {
        ergonodeSku: { control: 'text' },
        plannedSku: { control: 'text' },
    },
};

export const Playground = {
    args: { ergonodeSku: 'ERG-00001234', plannedSku: '' },
    render: ({ ergonodeSku, plannedSku }) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'admin__fieldset';
        wrapper.innerHTML = `
            <div class="admin__field">
                <label class="admin__field-label" for="ergonode-sku"><span>Ergonode SKU</span></label>
                <div class="admin__field-control">
                    <input id="ergonode-sku" class="admin__control-text" value="" disabled>
                    <div class="admin__field-note">
                        <span>Read-only native Ergonode identity. The product mapping table is the source of truth.</span>
                    </div>
                </div>
            </div>
            ${plannedSku ? `<div class="admin__field">
                <label class="admin__field-label" for="planned-ergonode-sku"><span>Ergonode SKU for first publication</span></label>
                <div class="admin__field-control">
                    <input id="planned-ergonode-sku" class="admin__control-text" value="" disabled>
                    <div class="admin__field-note"><span>Current saved value of the configured Magento identity attribute. Publication requires a non-empty, unique value of at most 64 bytes.</span></div>
                </div>
            </div>` : ''}`;

        wrapper.querySelector('input').value = ergonodeSku;
        if (plannedSku) {
            wrapper.querySelector('#planned-ergonode-sku').value = plannedSku;
        }
        return wrapper;
    },
};

export const NotAssigned = {
    ...Playground,
    args: { ergonodeSku: '', plannedSku: '' },
};

export const MappedBeforePublication = {
    ...Playground,
    args: { ergonodeSku: '', plannedSku: '385' },
};
