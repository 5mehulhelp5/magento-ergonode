import { expect, within } from 'storybook/test';
import { loadAmdModule, translateIdentity } from '@ergonode-storybook/load-amd-module.js';

import messagesSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/messages.js?raw';
import refreshResultSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/attribute-refresh-result.js?raw';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';

const messages = loadAmdModule(messagesSource, {
    'mage/translate': translateIdentity
}, 'Ergonode_CoreAdminUi/js/messages');
const refreshResult = loadAmdModule(refreshResultSource, {
    'mage/translate': translateIdentity
}, 'Ergonode_CoreAdminUi/js/attribute-refresh-result');

function fixture(args) {
    const root = document.createElement('section');
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
    refreshResult.show(message, {
        imported: args.imported,
        changed: args.changed
    });

    return root;
}

function allStates() {
    const matrix = document.createElement('div');

    matrix.style.display = 'grid';
    matrix.style.gap = '16px';
    matrix.append(
        fixture({imported: 10, changed: 3}),
        fixture({imported: 0, changed: 0})
    );

    return matrix;
}

export default {
    id: 'ergo-v-070-05',
    title: 'Ergonode UI/Widoki/Atrybuty produktów/ERGO-V-070 · Mapowanie atrybutów produktów/ERGO-V-070.05 · Wynik odświeżenia atrybutów',
    args: {imported: 10, changed: 3},
    argTypes: {
        imported: {control: {min: 0, type: 'number'}},
        changed: {control: {min: 0, type: 'number'}}
    },
    render: fixture
};

export const Playground = {};
export const AllStates = {
    render: allStates,
    play: async ({canvasElement}) => {
        const statuses = within(canvasElement).getAllByRole('status');

        await expect(statuses[0]).toHaveClass('veui-message-success');
        await expect(statuses[1]).toHaveClass('veui-message-success');
        await expect(statuses[0]).toHaveTextContent('10 pobranych, 3 zmienionych');
    }
};
