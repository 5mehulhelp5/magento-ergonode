import { createCoreModuleLoader } from '@ergonode-storybook/core-modules.js';
import { expect, userEvent, within } from 'storybook/test';

import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/attribute-mapping.css';

const productionMapping = createCoreModuleLoader()('Ergonode_CoreAdminUi/js/attribute-mapping');

productionMapping.configureAttributeTypeCompatibility({select: ['select']});

function fixture() {
    const root = document.createElement('section');

    root.className = 'vea-mapping';
    root.innerHTML = `
        <div class="veui-toolbar">
            <button type="button" data-role="request-auto-match">Auto-dopasuj przez backend</button>
            <button type="button" data-role="save-mapping" disabled>Zapisz</button>
            <span role="status">Brak niezapisanych propozycji</span>
        </div>
        <div data-role="mapping-list">
            <article data-role="mapping-row" class="vea-pair-row vea-status-tone-warning">
                <div data-role="pair-slot" data-side="ergo" data-code="color" data-type="select">
                    <strong>Kolor</strong><code>color</code>
                </div>
                <span class="vea-link-indicator"><span></span></span>
                <div data-role="pair-slot" data-side="magento" data-code="" data-type="" class="is-empty">
                    <strong>Upuść atrybut</strong><span data-role="slot-hint">brakuje targetu</span>
                </div>
            </article>
        </div>`;
    root.veaWorkspace = {claim: () => false, delegate: () => {}};
    root.veaContext = {
        dirty: {
            isDirty: () => Boolean(root.querySelector('[data-side="magento"]').dataset.code)
        }
    };

    root.querySelector('[data-role="request-auto-match"]').addEventListener('click', () => {
        productionMapping.applyAutoMatches(root, [{
            left: {source: 'ergo', label: 'Kolor', code: 'color', type: 'select', scope: 'global'},
            right: {source: 'magento', label: 'Color', code: 'color', type: 'select', scope: 'global'}
        }]);
        root.querySelector('[role="status"]').textContent = 'Propozycja gotowa — wymaga zapisu';
    });
    root.querySelector('[data-role="save-mapping"]').addEventListener('click', () => {
        root.querySelector('[role="status"]').textContent = 'Zapisano świadomie';
    });

    return root;
}

function autoConnectButton(availableCount) {
    const button = document.createElement('button');

    button.type = 'button';
    button.className = 'veui-entity-options-action vea-auto-match';
    button.dataset.role = 'auto-match';
    button.title = 'Automatycznie dopasuj aktywne atrybuty';
    button.setAttribute('aria-label', 'Automatycznie dopasuj aktywne atrybuty');
    button.innerHTML = `
        <span class="vea-auto-match-icon" aria-hidden="true"></span>
        <span class="veui-entity-options-action-label" data-role="auto-match-label">Auto Connect</span>`;
    productionMapping.setAutoMatchAvailability(
        {querySelector: () => button},
        Array.from({length: availableCount}, () => ({})),
        false
    );

    return button;
}

function availabilityStates() {
    const matrix = document.createElement('div');
    const unavailable = document.createElement('section');
    const available = document.createElement('section');

    matrix.style.display = 'grid';
    matrix.style.gap = '16px';
    unavailable.innerHTML = '<strong>Brak możliwych połączeń</strong>';
    unavailable.append(autoConnectButton(0));
    available.innerHTML = '<strong>Dostępne połączenie</strong>';
    available.append(autoConnectButton(1));
    matrix.append(unavailable, available);

    return matrix;
}

export default {
    id: 'ergo-v-070-02',
    title: 'Ergonode UI/Widoki/Atrybuty produktów/ERGO-V-070 · Mapowanie atrybutów produktów/ERGO-V-070.02 · Automatyczne dopasowanie',
    render: fixture
};

export const Playground = {};
export const AllAvailabilityStates = {
    render: availabilityStates,
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const buttons = canvas.getAllByRole('button', {name: 'Automatycznie dopasuj aktywne atrybuty'});

        await expect(buttons[0]).toBeDisabled();
        await expect(buttons[0]).toHaveAttribute('data-available-count', '0');
        await expect(buttons[1]).toBeEnabled();
        await expect(buttons[1]).toHaveAttribute('data-available-count', '1');
    }
};
export const RequiresExplicitSave = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const save = canvas.getByRole('button', {name: 'Zapisz'});

        await expect(save).toBeDisabled();
        await userEvent.click(canvas.getByRole('button', {name: 'Auto-dopasuj przez backend'}));
        await expect(canvas.getByText('Color')).toBeVisible();
        await expect(canvas.getByRole('status')).toHaveTextContent('wymaga zapisu');
        await expect(save).toBeEnabled();
        await userEvent.click(save);
        await expect(canvas.getByRole('status')).toHaveTextContent('Zapisano świadomie');
    }
};
