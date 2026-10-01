import { expect, within } from 'storybook/test';
import { loadAmdModule, translateIdentity } from '@ergonode-storybook/load-amd-module.js';

import messagesSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/messages.js?raw';
import saveResultSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/mapping-save-result.js?raw';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';

const messages = loadAmdModule(messagesSource, {
    'mage/translate': translateIdentity
}, 'Ergonode_CoreAdminUi/js/messages');
const saveResult = loadAmdModule(
    saveResultSource,
    {},
    'Ergonode_CoreAdminUi/js/mapping-save-result'
);

function fixture(args) {
    const root = document.createElement('section');
    const scope = `storybook-${Math.random().toString(36).slice(2)}`;
    let message;

    root.className = 'veui-workspace';
    root.innerHTML = `
        <div class="veui-message veui-global-message"
             data-role="global-message"
             aria-live="polite"
             hidden>
            <span class="veui-message-status-icon" aria-hidden="true"></span>
            <span data-role="global-message-text"></span>
        </div>`;
    message = messages.create(root, {autoHideDelay: 0});
    saveResult.persist(scope, {tone: 'warning', message: args.message});
    saveResult.show(message, saveResult.consume(scope));

    return root;
}

function allStates() {
    const matrix = document.createElement('div');

    matrix.style.display = 'grid';
    matrix.style.gap = '16px';
    matrix.append(
        fixture({
            message: 'Atrybut „sku” już istnieje w Ergonode i został połączony z atrybutem Magento „sku”.'
        }),
        fixture({
            message: 'Opcja „red” już istnieje w Ergonode i została połączona z opcją Magento „red”.'
        })
    );

    return matrix;
}

export default {
    id: 'ergo-v-070-07',
    title: 'Ergonode UI/Widoki/Atrybuty produktów/ERGO-V-070 · Mapowanie atrybutów produktów/ERGO-V-070.07 · Ostrzeżenie po zapisie mapowania',
    args: {
        message: 'Atrybut „sku” już istnieje w Ergonode i został połączony z atrybutem Magento „sku”.'
    },
    render: fixture
};

export const Playground = {};
export const AllStates = {
    render: allStates,
    play: async ({canvasElement}) => {
        const statuses = within(canvasElement).getAllByRole('status');

        await expect(statuses).toHaveLength(2);
        await expect(statuses[0]).toHaveClass('veui-message-warning');
        await expect(statuses[0]).toHaveTextContent('Atrybut „sku” już istnieje');
        await expect(statuses[1]).toHaveClass('veui-message-warning');
        await expect(statuses[1]).toHaveTextContent('Opcja „red” już istnieje');
    }
};
