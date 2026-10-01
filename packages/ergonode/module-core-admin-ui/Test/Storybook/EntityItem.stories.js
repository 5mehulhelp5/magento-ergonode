import { expect, userEvent, within } from 'storybook/test';

import {
    createEntityItem,
    mountEntityItem
} from '@ergonode-storybook/entity-item.js';
import {
    loadAmdModule,
    translateIdentity
} from '@ergonode-storybook/load-amd-module.js';

import buttonsSource from '../../view/adminhtml/web/js/buttons.js?raw';
import dragDropSource from '../../view/adminhtml/web/js/drag-drop.js?raw';
import workspaceSource from '../../view/adminhtml/web/js/workspace.js?raw';

const amdDependencies = {
    'mage/translate': translateIdentity
};
const modules = {
    buttons: loadAmdModule(buttonsSource, amdDependencies, 'Ergonode_CoreAdminUi/js/buttons'),
    dragDrop: loadAmdModule(dragDropSource, {}, 'Ergonode_CoreAdminUi/js/drag-drop'),
    workspace: loadAmdModule(workspaceSource, amdDependencies, 'Ergonode_CoreAdminUi/js/workspace')
};

function renderItem(args) {
    const frame = document.createElement('div');
    const item = createEntityItem(args);

    frame.className = 'veui-storybook-frame';
    mountEntityItem(item, modules);
    frame.append(item);

    return frame;
}

function renderExample(title, options) {
    const example = document.createElement('section');
    const item = createEntityItem(options);

    example.className = 'veui-storybook-example';
    example.append(Object.assign(document.createElement('h3'), { textContent: title }));
    mountEntityItem(item, modules);
    example.append(item);

    return example;
}

const meta = {
    id: 'ergo-c-026',
    title: 'Ergonode UI/Komponenty/Wspólne/ERGO-C-026 · Element encji',
    tags: ['autodocs'],
    render: renderItem,
    argTypes: {
        entityKind: {
            control: 'select',
            options: ['attribute', 'category-attribute', 'option', 'language', 'store-view', 'template'],
            description: 'Rodzaj danych; nie wybiera osobnego komponentu.'
        },
        type: {
            control: 'select',
            options: [
                'text',
                'textarea',
                'select',
                'multiselect',
                'decimal',
                'unit',
                'file',
                'image',
                'boolean',
                'option',
                'store-view'
            ]
        },
        active: { control: 'boolean' },
        pending: { control: 'boolean' },
        dragging: { control: 'boolean' }
    },
    parameters: {
        docs: {
            description: {
                component: 'Jeden kontrakt elementu dla atrybutu, opcji, języka, Store View i szablonu. ' +
                    'Rodzaj encji zmienia dane, nie markup ani zachowanie.'
            }
        }
    }
};

export default meta;

export const Playground = {
    args: {
        entityKind: 'attribute',
        label: 'Kolor',
        code: 'color',
        type: 'select',
        scope: 'global',
        active: true,
        pending: false,
        dragging: false
    }
};

export const RodzajeDanych = {
    render: () => {
        const grid = document.createElement('div');

        grid.className = 'veui-storybook-grid';
        grid.append(
            renderExample('Atrybut', {
                entityKind: 'attribute', label: 'Kolor', code: 'color', type: 'select', scope: 'global'
            }),
            renderExample('Opcja', {
                entityKind: 'option', label: 'Granatowy', code: 'navy', type: 'option', scope: 'pl_PL'
            }),
            renderExample('Atrybut kategorii', {
                entityKind: 'category-attribute', label: 'Opis kategorii', code: 'category_description',
                type: 'textarea', scope: 'local'
            }),
            renderExample('Język', {
                entityKind: 'language', label: 'Polski', code: 'pl_PL'
            }),
            renderExample('Store View', {
                entityKind: 'store-view', label: 'Default Store View', code: 'admin',
                type: 'store-view', scope: 'Main Website / Main Store'
            }),
            renderExample('Szablon', {
                entityKind: 'template', label: 'Buty', code: 'shoes'
            })
        );

        return grid;
    }
};

export const Stany = {
    render: () => {
        const grid = document.createElement('div');

        grid.className = 'veui-storybook-grid';
        grid.append(
            renderExample('Domyślny', {
                entityKind: 'attribute', label: 'Kolor', code: 'color', type: 'select', scope: 'global'
            }),
            renderExample('Nieaktywny', {
                entityKind: 'attribute', label: 'Marka', code: 'brand', type: 'text', scope: 'global', active: false
            }),
            renderExample('Do utworzenia', {
                entityKind: 'attribute',
                label: 'Materiał',
                code: 'material',
                type: 'multiselect',
                scope: 'global',
                pending: true
            }),
            renderExample('Przeciągany', {
                entityKind: 'attribute', label: 'Waga', code: 'weight', type: 'unit', scope: 'global', dragging: true
            })
        );

        return grid;
    }
};

export const ZmianaAktywnosci = {
    args: {
        entityKind: 'option',
        label: 'Granatowy',
        code: 'navy',
        type: 'option',
        scope: 'pl_PL',
        active: true
    },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const toggle = canvas.getByRole('button', { name: 'Włącz / wyłącz element w mapowaniu' });
        const item = canvasElement.querySelector('[data-role="entity-card"]');

        await userEvent.click(toggle);
        await expect(toggle).toHaveAttribute('aria-pressed', 'false');
        await expect(item).toHaveClass('is-inactive');

        await userEvent.click(toggle);
        await expect(toggle).toHaveAttribute('aria-pressed', 'true');
        await expect(item).not.toHaveClass('is-inactive');
    }
};
