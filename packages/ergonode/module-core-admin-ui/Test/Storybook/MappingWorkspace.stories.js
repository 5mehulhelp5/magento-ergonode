import {mountMappingInteractions} from '@ergonode-storybook/mapping-interactions.js';
import {checkMappingHeaders} from '@ergonode-storybook/mapping-header-interactions.js';
import { expect, userEvent, waitFor, within } from 'storybook/test';

import { createMappingWorkspace, mappingViews } from '@ergonode-storybook/mapping-workspace.js';

import '../../view/adminhtml/web/css/ergonode-workspace.css';
import '../../view/adminhtml/web/css/attribute-mapping.css';

const sharedMappingViews = mappingViews.filter((view) => view !== 'template');

const labels = {
    language: 'Języki',
    attribute: 'Atrybuty produktów',
    option: 'Opcje produktów',
    'category-attribute': 'Atrybuty kategorii'
};

function render(args) {
    const root = createMappingWorkspace(args);

    if (args.multipleSources) {
        const list = root.querySelector('[data-source-panel="ergo"] [data-role="source-list"]');
        const first = list?.querySelector('[data-role="entity-card"]');

        if (first) {
            const second = first.cloneNode(true);

            second.dataset.code = `${first.dataset.code}-second`;
            second.dataset.label = `${first.dataset.label} 2`;
            second.dataset.search = `${second.dataset.label} ${second.dataset.code}`;
            second.querySelector('strong').textContent = second.dataset.label;
            second.querySelector('code').textContent = second.dataset.code;
            list.append(second);
        }
    }

    mountMappingInteractions(root);

    return root;
}

export default {
    id: 'ergo-c-031',
    title: 'Ergonode UI/Komponenty/Wspólne/ERGO-C-031 · Wspólny workspace mapowania',
    tags: ['autodocs'],
    render,
    args: {view: 'language', state: 'mapped', contextLoading: false},
    argTypes: {
        view: {control: 'select', options: sharedMappingViews},
        state: {control: 'select', options: ['mapped', 'unmapped', 'empty', 'draft', 'error', 'inactive', 'saving', 'autosave-error']},
        contextLoading: {control: 'boolean'}
    },
    parameters: {
        docs: {
            description: {
                component: 'Wspólna kompozycja HTML i produkcyjnych CSS. Workspace, menu i transfer zbiorczy używają produkcyjnych modułów AMD. Parowanie i filtrowanie to demonstracja stanów fixture; ta story nie weryfikuje kontrolerów domenowych ani zapisu Magento.'
            }
        }
    }
};

export const Playground = {};

export const WspolneWidoki = {
    render: () => {
        const grid = document.createElement('div');

        grid.className = 'veui-storybook-stack';
        sharedMappingViews.forEach((view) => {
            const example = document.createElement('section');
            const heading = document.createElement('h2');

            heading.textContent = labels[view];
            example.append(heading, render({view, embedded: true}));
            grid.append(example);
        });

        return grid;
    }
};

export const Stany = {
    render: () => {
        const grid = document.createElement('div');

        grid.className = 'veui-storybook-stack';
        ['mapped', 'empty', 'draft', 'error', 'inactive'].forEach((state) => {
            grid.append(render({view: 'attribute', state, embedded: true}));
        });

        return grid;
    }
};

export const Klawiatura = {
    args: {view: 'attribute'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const toggle = canvas.getAllByRole('button', {name: 'Włącz / wyłącz element w mapowaniu'})[0];

        toggle.focus();
        await userEvent.keyboard('{Enter}');
        await expect(toggle).toHaveAttribute('aria-pressed', 'false');
    }
};

export const OpcjeMapowaniaAtrybutowMysz = {
    args: {view: 'attribute'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);

        await userEvent.click(canvas.getByRole('button', {name: 'Opcje mapowania'}));
        await expect(canvas.getByRole('button', {
            name: 'Automatycznie dopasuj aktywne atrybuty'
        })).toBeVisible();
        await expect(canvas.getByRole('button', {
            name: 'Uzupełnij brakujące atrybuty w Ergonode i Magento'
        })).toBeVisible();
    }
};

export const OpcjeMapowaniaAtrybutowKlawiatura = {
    args: {view: 'attribute'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const trigger = canvas.getByRole('button', {name: 'Opcje mapowania'});

        trigger.focus();
        await userEvent.keyboard('{Enter}');
        await expect(canvas.getByRole('button', {
            name: 'Automatycznie dopasuj aktywne atrybuty'
        })).toBeVisible();
        await expect(canvas.getByRole('button', {
            name: 'Uzupełnij brakujące atrybuty w Ergonode i Magento'
        })).toBeVisible();
    }
};

export const AutoConnectWSrodkowejKolumnie = {
    render: () => {
        const grid = document.createElement('div');

        grid.className = 'veui-storybook-stack';
        ['language', 'attribute', 'option', 'category-attribute'].forEach((view) => {
            grid.append(render({view, embedded: true}));
        });

        return grid;
    },
    play: async ({canvasElement}) => {
        canvasElement.querySelectorAll('[data-mapping-view]').forEach((root) => {
            const leftPanel = root.querySelector('[data-role="source-panel"][data-source-panel="ergo"]');
            const middlePanel = root.querySelector('[data-role="mapping-panel"]');

            expect(leftPanel.querySelector('[data-role="auto-match"]')).toBeNull();
            expect(middlePanel.querySelector('[data-role="auto-match"]')).not.toBeNull();
        });
    }
};

export const ParowanieDwuklikiem = {
    args: {view: 'attribute', state: 'unmapped'},
    play: async ({canvasElement}) => {
        const left = canvasElement.querySelector('[data-source="ergo"][data-role="entity-card"]');
        const right = canvasElement.querySelector('[data-source="magento"][data-role="entity-card"]');

        await userEvent.dblClick(left);
        await expect(canvasElement.querySelectorAll('[data-role="mapping-row"]')).toHaveLength(1);
        await userEvent.dblClick(right);
        await expect(
            canvasElement.querySelector('[data-role="pair-slot"][data-side="ergo"]')
        ).toHaveAttribute('data-code', 'color');
        await expect(
            canvasElement.querySelector('[data-role="pair-slot"][data-side="magento"]')
        ).toHaveAttribute('data-code', 'color');
    }
};

export const ParowanieKlawiatura = {
    args: {view: 'attribute', state: 'unmapped'},
    play: async ({canvasElement}) => {
        const left = canvasElement.querySelector('[data-source="ergo"][data-role="entity-card"]');
        const right = canvasElement.querySelector('[data-source="magento"][data-role="entity-card"]');

        left.focus();
        await userEvent.keyboard('{Enter}');
        right.focus();
        await userEvent.keyboard('{Enter}');
        await expect(canvasElement.querySelectorAll('[data-role="mapping-row"]')).toHaveLength(1);
        await expect(
            canvasElement.querySelector('[data-role="pair-slot"][data-side="ergo"]')
        ).toHaveAttribute('data-code', 'color');
        await expect(
            canvasElement.querySelector('[data-role="pair-slot"][data-side="magento"]')
        ).toHaveAttribute('data-code', 'color');
    }
};

export const ZaznaczanieIPrzenoszenieWieluElementow = {
    args: {view: 'option', state: 'unmapped', multipleSources: true},
    play: async ({canvasElement}) => {
        const sourcePanel = canvasElement.querySelector('[data-source-panel="ergo"]');
        const canvas = within(sourcePanel);
        const menu = sourcePanel.querySelector('.veui-source-options');
        const trigger = menu.querySelector('summary');
        const selectAll = sourcePanel.querySelector('[data-role="source-bulk-select-all"]');
        const addToMapping = sourcePanel.querySelector('[data-role="source-bulk-add-to-mapping"]');
        const itemSelections = canvas.getAllByRole('checkbox', {name: /Zaznacz element:/});

        await expect(addToMapping).toBeDisabled();
        await userEvent.click(itemSelections[0]);
        await expect(selectAll).toHaveAccessibleName('Zaznacz wszystkie');
        await expect(addToMapping).toBeEnabled();
        await userEvent.click(trigger);
        await userEvent.click(canvas.getByRole('button', {name: 'Zaznacz wszystkie'}));
        await expect(menu).not.toHaveAttribute('open');
        for (const selection of itemSelections) {
            await expect(selection).toBeChecked();
        }
        trigger.focus();
        await userEvent.keyboard('{Enter}');
        const deselect = canvas.getByRole('button', {name: 'Odznacz wszystkie'});
        deselect.focus();
        await userEvent.keyboard(' ');
        for (const selection of itemSelections) {
            await expect(selection).not.toBeChecked();
        }
        await expect(addToMapping).toBeDisabled();
        await userEvent.click(trigger);
        await userEvent.click(canvas.getByRole('button', {name: 'Zaznacz wszystkie'}));
        await userEvent.click(trigger);
        await userEvent.click(addToMapping);
        await expect(canvasElement.querySelectorAll('[data-role="mapping-row"]')).toHaveLength(2);
        await expect(selectAll).toBeDisabled();
        await expect(addToMapping).toBeDisabled();
    }
};

export const ZaznaczaniePoWyszukiwaniu = {
    args: {view: 'option', state: 'unmapped', multipleSources: true},
    play: async ({canvasElement}) => {
        const panel = canvasElement.querySelector('[data-source-panel="ergo"]');
        const canvas = within(panel);
        const cards = panel.querySelectorAll('[data-role="entity-card"]');
        const selections = [...cards].map((card) => card.querySelector('[data-role="source-bulk-select"]'));
        const trigger = panel.querySelector('.veui-source-options > summary');
        const search = canvas.getByRole('searchbox');
        const selectAll = panel.querySelector('[data-role="source-bulk-select-all"]');
        const addToMapping = panel.querySelector('[data-role="source-bulk-add-to-mapping"]');

        await userEvent.click(selections[0]);
        await userEvent.type(search, cards[1].dataset.label);
        await expect(cards[0]).not.toBeVisible();
        await userEvent.click(trigger);
        await userEvent.click(canvas.getByRole('button', {name: 'Zaznacz wszystkie'}));
        await expect(selections[0]).toBeChecked();
        await expect(selections[1]).toBeChecked();
        await userEvent.click(trigger);
        await userEvent.click(canvas.getByRole('button', {name: 'Odznacz wszystkie'}));
        await expect(selections[0]).toBeChecked();
        await expect(selections[1]).not.toBeChecked();
        await expect(addToMapping).toBeEnabled();
        await userEvent.clear(search);
        await userEvent.type(search, 'brak-wynikow');
        await waitFor(() => expect(selectAll).toBeDisabled());
        await expect(addToMapping).toBeEnabled();
        await userEvent.clear(search);
        await waitFor(() => expect(selectAll).toBeEnabled());
        await expect(selectAll).toHaveAccessibleName('Zaznacz wszystkie');
        await userEvent.click(trigger);
        await userEvent.click(selectAll);
        await userEvent.click(selections[0]);
        await expect(selectAll).toHaveAccessibleName('Zaznacz wszystkie');
        await userEvent.click(selections[0]);
        await expect(selectAll).toHaveAccessibleName('Odznacz wszystkie');
    }
};

export const KontraktTransferuDlaWspolnychWidokow = {
    render: () => {
        const grid = document.createElement('div');

        grid.className = 'veui-storybook-stack';
        mappingViews.forEach((view) => {
            const root = render({view, embedded: true, state: 'unmapped'});

            root.querySelector('nav').setAttribute('aria-label', `Ergonode sections: ${view}`);
            grid.append(root);
        });

        return grid;
    },
    play: async ({canvasElement}) => {
        for (const root of canvasElement.querySelectorAll('[data-mapping-view]')) {
            expect(root.querySelectorAll('input[data-role="source-bulk-select-all"]')).toHaveLength(0);
            expect(root.querySelectorAll(
                '.veui-source-options .veui-entity-options-menu > button[data-role="source-bulk-select-all"]'
            )).toHaveLength(2);
            for (const panel of root.querySelectorAll('[data-source-panel]')) {
                const sourceMenu = panel.querySelector('.veui-source-options');
                const trigger = sourceMenu.querySelector('summary');
                const selection = panel.querySelector('[data-role="source-bulk-select"]');
                const selectAll = panel.querySelector('[data-role="source-bulk-select-all"]');
                const addToMapping = panel.querySelector('[data-role="source-bulk-add-to-mapping"]');

                await userEvent.click(trigger);
                await expect(selectAll).toHaveAccessibleName('Zaznacz wszystkie');
                await userEvent.click(selectAll);
                await expect(selection).toBeChecked();
                await expect(selectAll).toHaveAccessibleName('Odznacz wszystkie');
                await userEvent.click(trigger);
                await userEvent.click(selectAll);
                await expect(selection).not.toBeChecked();
                await userEvent.click(selection);
                await expect(addToMapping).toBeEnabled();
                await userEvent.click(trigger);
                await userEvent.click(addToMapping);
                await expect(root.querySelectorAll('[data-role="mapping-row"]')).toHaveLength(1);
                await expect(addToMapping).toBeDisabled();
                await expect(selectAll).toBeDisabled();
            }
        }
    }
};

export const ScaloneNaglowki = {
    args: {view: 'attribute'},
    play: checkMappingHeaders
};

export const FiltrowanieWykluczonych = {
    args: {view: 'language', state: 'unmapped'},
    play: async ({canvasElement}) => {
        const panel = canvasElement.querySelector('[data-source-panel="ergo"]');
        const card = panel.querySelector('[data-role="entity-card"]');
        const search = panel.querySelector('[data-role="source-search"]');
        await userEvent.click(card.querySelector('[data-role="source-active-toggle"]'));
        await expect(card).not.toBeVisible();
        await userEvent.type(search, 'Polski');
        await expect(card).not.toBeVisible();
        await userEvent.click(panel.querySelector('summary'));
        await userEvent.click(panel.querySelector('[data-role="visibility-toggle"]'));
        await expect(card).toBeVisible();
        await userEvent.clear(search);
        await userEvent.type(search, 'English');
        await expect(card).not.toBeVisible();
    }
};

export const UsunieciePrzywracaZrodla = {
    args: {view: 'attribute', state: 'unmapped'},
    play: async ({canvasElement}) => {
        const card = canvasElement.querySelector('[data-source="ergo"][data-role="entity-card"]');
        await userEvent.dblClick(card);
        await expect(card).not.toBeVisible();
        await userEvent.click(canvasElement.querySelector('[data-role="unlink-mapping"]'));
        await expect(card).toBeVisible();
        await expect(canvasElement.querySelectorAll('[data-role="mapping-row"]')).toHaveLength(0);
        await userEvent.dblClick(card);
        await expect(canvasElement.querySelectorAll('[data-role="mapping-row"]')).toHaveLength(1);
    }
};
