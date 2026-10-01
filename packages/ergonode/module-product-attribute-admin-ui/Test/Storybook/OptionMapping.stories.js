import {mountMappingInteractions} from '@ergonode-storybook/mapping-interactions.js';
import {checkMappingHeaders} from '@ergonode-storybook/mapping-header-interactions.js';
import { expect, userEvent, within } from 'storybook/test';

import { loadAmdModule } from '@ergonode-storybook/load-amd-module.js';
import { createMappingWorkspace } from '@ergonode-storybook/mapping-workspace.js';

import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-actions.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/attribute-mapping.css';
import saveIconUrl from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/images/save.svg';
import entityOptionsSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/entity-options.js?raw';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/option-mapping.css';

const entityOptions = loadAmdModule(entityOptionsSource, {
    'mage/translate': (value) => value
}, 'Ergonode_CoreAdminUi/js/entity-options');

function replaceButtonContent(button, label, icon) {
    const labelNode = document.createElement(icon.labelTag || 'span');

    button.replaceChildren(icon.node);
    labelNode.textContent = label;
    if (icon.labelRole) {
        labelNode.dataset.role = icon.labelRole;
    }
    button.append(labelNode);
    button.title = icon.title || label;
    button.setAttribute('aria-label', icon.title || label);
}

function maskedIcon(className) {
    const icon = document.createElement('span');

    icon.className = className;
    icon.setAttribute('aria-hidden', 'true');

    return icon;
}

function saveIcon() {
    const icon = document.createElement('img');

    icon.className = 'vea-save-icon';
    icon.src = saveIconUrl;
    icon.alt = '';
    icon.setAttribute('aria-hidden', 'true');

    return icon;
}

function createSourceOptions(side, visibilityAvailable) {
    const isErgonode = side === 'ergo';
    const visibilityAction = {
        role: 'visibility-toggle',
        className: 'veui-visibility-control',
        iconClass: 'veui-visibility-icon',
        label: 'Excluded',
        title: 'Pokaż opcje pominięte w mapowaniu',
        ariaLabel: 'Pokaż opcje pominięte w mapowaniu',
        disabled: !visibilityAvailable,
        attributes: {
            'aria-pressed': 'false',
            'data-show-hint': 'Pokaż opcje pominięte w mapowaniu',
            'data-hide-hint': 'Ukryj opcje pominięte w mapowaniu'
        }
    };
    const actions = isErgonode ? [
        {
            role: 'refresh-ergonode',
            className: 'vea-refresh-ergonode',
            iconClass: 'vea-refresh-ergonode-icon',
            label: 'Odśwież',
            ariaLabel: 'Odśwież dane opcji z Ergonode'
        },
        visibilityAction
    ] : [visibilityAction];

    actions.push(
        {
            role: 'attribute-sort-direction',
            iconClass: 'veui-sort-direction-icon',
            label: 'Góra',
            title: 'Sortuj rosnąco'
        },
        {
            role: 'attribute-sort-toggle',
            iconClass: 'veui-sort-field-icon',
            label: 'Nazwa',
            title: 'Sortuj po etykiecie'
        }
    );
    const menu = entityOptions.create({
        menuLabel: `Option actions: ${isErgonode ? 'Ergonode' : 'Magento'}`,
        actions
    });

    menu.dataset.sourceOptions = side;
    menu.classList.add('veui-source-options');

    return menu;
}

function renderOptionMapping(args) {
    const root = createMappingWorkspace({view: 'option', ...args});
    const syncButton = Object.assign(document.createElement('button'), {type: 'button'});
    const syncIcon = maskedIcon('veui-sync-ergonode-icon');
    const actions = [
        ['auto-match', 'Auto Connect', {
            node: maskedIcon('vea-auto-match-icon'),
            title: 'Automatycznie połącz pasujące opcje bez zapisywania'
        }],
        ['complete-missing', 'Uzupełnij', {
            node: maskedIcon('vea-complete-missing-icon'),
            title: 'Uzupełnij brakujące mapowania opcji bez zapisywania'
        }],
        ['save-mapping', 'Zapisz', {
            node: saveIcon(),
            labelTag: 'em',
            title: 'Zapisz mapowanie opcji'
        }]
    ];

    root.querySelectorAll('[data-role="source-panel"]').forEach((panel) => {
        panel.querySelector('[data-role="entity-options"]')?.remove();
        panel.querySelector('.vea-side-tools')?.append(
            createSourceOptions(panel.dataset.sourcePanel, args.state === 'inactive')
        );
    });
    syncButton.className = 'veui-button veui-button-toolbar';
    syncButton.dataset.role = 'sync-ergonode';
    syncButton.setAttribute('aria-label', 'Synchronizuj opcje z Magento');
    syncButton.append(syncIcon, document.createTextNode('Sync'));
    root.querySelector('.veui-toolbar')?.append(syncButton);
    root.querySelectorAll('[data-role="mapping-row"]').forEach((row) => {
        row.classList.remove('vea-attribute-pair-row');
    });
    root.querySelectorAll('[data-role="unlink-mapping"]').forEach((button) => {
        button.title = 'Usuń mapowanie opcji';
        button.setAttribute('aria-label', 'Usuń mapowanie opcji: Granatowy - Navy');
    });

    actions.forEach(([role, label, icon]) => {
        const button = root.querySelector(`[data-role="${role}"]`);

        if (button) {
            replaceButtonContent(button, label, icon);
        }
    });
    const complete = root.querySelector('[data-role="complete-missing"]');
    const status = document.createElement('strong');

    complete.disabled = false;
    status.dataset.role = 'synchronization-result';
    status.textContent = 'Brak zmian w szkicu';
    status.style.display = 'block';
    root.querySelector('[data-role="mapping-panel"]')?.append(status);
    root.dataset.synchronizationCount = '0';
    root.querySelector('[data-role="refresh-ergonode"]')?.addEventListener('click', () => {
        root.dataset.refreshEntry = 'refresh';
        status.textContent = 'Dane opcji zostały odświeżone z Ergonode';
    });
    syncButton.addEventListener('click', () => {
        root.dataset.synchronizationCount = String(Number(root.dataset.synchronizationCount) + 1);
        root.dataset.synchronizationEntry = 'sync';
        status.textContent = 'Opcje zostały zsynchronizowane z Magento';
    });
    root.querySelector('[data-role="auto-match"]')?.addEventListener('click', () => {
        root.dataset.draftAction = 'auto-connect';
        root.dataset.suggestionSource = 'backend';
        status.textContent = 'Sugestie backendu połączono w szkicu — zapisz oddzielnie';
    });
    root.querySelector('[data-role="complete-missing"]')?.addEventListener('click', () => {
        root.dataset.draftAction = 'complete';
        status.textContent = 'Przygotowano brakujące opcje w szkicu — zapisz oddzielnie';
    });

    mountMappingInteractions(root);

    return root;
}

export default {
    id: 'ergo-v-051',
    title: 'Ergonode UI/Widoki/Atrybuty produktów/ERGO-V-051 · Mapowanie opcji/Pełny widok',
    tags: ['autodocs'],
    render: renderOptionMapping,
    args: {state: 'mapped', contextLoading: false},
    argTypes: {
        state: {control: 'select', options: ['mapped', 'empty', 'draft', 'error', 'inactive']},
        contextLoading: {control: 'boolean'}
    }
};

export const Playground = {};
export const Pusty = {args: {state: 'empty'}};
export const Szkic = {args: {state: 'draft'}};
export const UzupełnieniePrzygotowujeSzkicBezZapisu = {
    args: {state: 'draft'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const workspace = canvasElement.querySelector('.veui-workspace');

        await userEvent.click(canvas.getByRole('button', {name: 'Opcje mapowania'}));
        await userEvent.click(canvas.getByRole('button', {
            name: 'Uzupełnij brakujące mapowania opcji bez zapisywania'
        }));
        await expect(canvas.getByText('Przygotowano brakujące opcje w szkicu — zapisz oddzielnie')).toBeVisible();
        await expect(workspace).toHaveAttribute('data-draft-action', 'complete');
        await expect(workspace).toHaveAttribute('data-synchronization-count', '0');
    }
};
export const BladTypu = {args: {state: 'error'}};
export const AutoDopasowanieWOpcjachMapowania = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const workspace = canvasElement.querySelector('.veui-workspace');

        await userEvent.click(canvas.getByRole('button', {name: 'Opcje mapowania'}));
        await userEvent.click(canvas.getByRole('button', {
            name: 'Automatycznie połącz pasujące opcje bez zapisywania'
        }));
        await expect(canvas.getByText('Sugestie backendu połączono w szkicu — zapisz oddzielnie')).toBeVisible();
        await expect(workspace).toHaveAttribute('data-draft-action', 'auto-connect');
        await expect(workspace).toHaveAttribute('data-suggestion-source', 'backend');
        await expect(workspace).toHaveAttribute('data-synchronization-count', '0');
    }
};
export const OdswiezenieNieUruchamiaSynchronizacji = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const workspace = canvasElement.querySelector('.veui-workspace');

        await userEvent.click(canvas.getByRole('button', {name: 'Option actions: Ergonode'}));
        await userEvent.click(canvas.getByRole('button', {name: 'Odśwież dane opcji z Ergonode'}));
        await expect(canvas.getByText('Dane opcji zostały odświeżone z Ergonode')).toBeVisible();
        await expect(workspace).toHaveAttribute('data-refresh-entry', 'refresh');
        await expect(workspace).toHaveAttribute('data-synchronization-count', '0');
    }
};
export const SynchronizacjaJestOsobnaAkcja = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const workspace = canvasElement.querySelector('.veui-workspace');

        await userEvent.click(canvas.getByRole('button', {name: 'Synchronizuj opcje z Magento'}));
        await expect(canvas.getByText('Opcje zostały zsynchronizowane z Magento')).toBeVisible();
        await expect(workspace).toHaveAttribute('data-synchronization-entry', 'sync');
        await expect(workspace).toHaveAttribute('data-synchronization-count', '1');
    }
};
export const WidocznoscWykluczonychOpcji = {
    args: {state: 'inactive'},
    play: async ({canvasElement}) => {
        const panels = canvasElement.querySelectorAll('[data-role="source-panel"]');
        const left = within(panels[0]);
        const right = within(panels[1]);
        const leftCard = panels[0].querySelector('[data-role="entity-card"]');
        const rightCard = panels[1].querySelector('[data-role="entity-card"]');

        await userEvent.click(left.getByRole('button', {name: 'Option actions: Ergonode'}));
        await expect(left.getByRole('button', {name: 'Pokaż opcje pominięte w mapowaniu'}))
            .toHaveTextContent('Excluded');
        await userEvent.click(left.getByRole('button', {name: 'Pokaż opcje pominięte w mapowaniu'}));
        await expect(leftCard).toBeVisible();
        await expect(rightCard).not.toBeVisible();

        await userEvent.click(right.getByRole('button', {name: 'Option actions: Magento'}));
        await expect(right.getByRole('button', {name: 'Pokaż opcje pominięte w mapowaniu'}))
            .toHaveTextContent('Excluded');
        await userEvent.click(right.getByRole('button', {name: 'Pokaż opcje pominięte w mapowaniu'}));
        await expect(rightCard).toBeVisible();
    }
};
export const OpcjeZrodelKlawiatura = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const trigger = canvas.getByRole('button', {name: 'Option actions: Ergonode'});

        trigger.focus();
        await userEvent.keyboard('{Enter}');
        await expect(canvas.getByRole('button', {name: 'Zapisz mapowanie opcji'})).toBeVisible();
        await expect(within(trigger.closest('details')).queryByText('Auto Connect')).not.toBeInTheDocument();
    }
};
export const OpcjeMapowaniaMysza = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const trigger = canvas.getByRole('button', {name: 'Opcje mapowania'});

        await userEvent.click(trigger);
        await expect(canvas.getByRole('button', {
            name: 'Automatycznie połącz pasujące opcje bez zapisywania'
        })).toBeVisible();
        await expect(canvas.getByRole('button', {
            name: 'Uzupełnij brakujące mapowania opcji bez zapisywania'
        })).toBeVisible();
    }
};
export const RozlaczenieMysza = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);

        await userEvent.click(canvas.getByRole('button', {name: 'Usuń mapowanie opcji: Granatowy - Navy'}));
        await expect(canvas.queryByRole('article')).not.toBeInTheDocument();
    }
};
export const RozlaczenieKlawiatura = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const unlink = canvas.getByRole('button', {name: 'Usuń mapowanie opcji: Granatowy - Navy'});

        unlink.focus();
        await userEvent.keyboard('{Enter}');
        await expect(canvas.queryByRole('article')).not.toBeInTheDocument();
    }
};
export const LadowanieKontekstu = {
    args: {contextLoading: true},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);

        await expect(canvas.getByText('Ładowanie opcji')).toBeVisible();
        await expect(canvas.getByLabelText('Wybierz zmapowaną parę atrybutów')).toBeInTheDocument();
    }
};

export const ScaloneNaglowki = {
    play: checkMappingHeaders
};
