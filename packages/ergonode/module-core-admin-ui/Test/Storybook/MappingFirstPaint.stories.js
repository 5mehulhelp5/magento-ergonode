import { expect, userEvent, within } from 'storybook/test';
import { createMappingWorkspace, mappingViews } from '@ergonode-storybook/mapping-workspace.js';
import { loadAmdModule, translateIdentity } from '@ergonode-storybook/load-amd-module.js';

import '../../view/adminhtml/web/css/ergonode-workspace.css';
import '../../view/adminhtml/web/css/attribute-mapping.css';
import optionsSource from '../../view/adminhtml/web/js/entity-options.js?raw';
import selectionSource from '../../view/adminhtml/web/js/source-bulk-transfer.js?raw';
import workspaceSource from '../../view/adminhtml/web/js/workspace.js?raw';
import placeholderTemplate from '../../view/adminhtml/templates/entity/options-placeholder.phtml?raw';

const entityOptions = loadAmdModule(optionsSource, {'mage/translate': translateIdentity});
const selection = loadAmdModule(selectionSource, {
    'mage/translate': translateIdentity,
    'Ergonode_CoreAdminUi/js/entity-options': entityOptions
});
const workspace = loadAmdModule(workspaceSource, {'mage/translate': translateIdentity});
const placeholderHtml = placeholderTemplate.replace(/<\?php[\s\S]*?\?>/, '');

function render(args) {
    const container = document.createElement('div');
    const initialize = document.createElement('button');

    initialize.type = 'button';
    initialize.className = 'veui-button';
    initialize.textContent = 'Uruchom JavaScript';
    container.append(initialize);
    const roots = (args.allViews ? mappingViews : [args.view]).map((view) => {
        const root = createMappingWorkspace({view, state: args.active ? 'unmapped' : 'inactive', embedded: true});
        const card = root.querySelector('[data-source="ergo"][data-role="entity-card"]');

        root.querySelector('nav').setAttribute('aria-label', `Ergonode sections: ${view}`);
        // The excluded-list filter may already be open when a fragment is inserted.
        root.querySelectorAll('[data-role="entity-card"]').forEach((item) => { item.hidden = false; item.classList.remove('is-filter-hidden'); });
        card.insertAdjacentHTML('beforeend', placeholderHtml);
        container.append(root);

        return root;
    });
    initialize.addEventListener('click', () => {
        roots.forEach((root) => {
            const card = root.querySelector('[data-source="ergo"][data-role="entity-card"]');

            entityOptions.enhance(card, {
                active: args.active,
                toggleSelector: '[data-role="source-active-toggle"]',
                menuLabel: `Opcje: ${card.dataset.label}`
            });
            workspace.mount(root, (scope) => {
                entityOptions.bind(scope, root);
                selection.bind(scope, root, {transfer: () => false});
            });
        });
        initialize.disabled = true;
    }, {once: true});

    return container;
}

function bounds(node) {
    const {x, y, width, height} = node.getBoundingClientRect();

    return {x, y, width, height};
}

async function verifyFirstPaint({canvasElement}) {
    const roots = [...canvasElement.querySelectorAll('[data-mapping-view]')];
    const before = roots.map((root) => {
        const cards = [...root.querySelectorAll('[data-role="entity-card"]')];
        const selections = [...root.querySelectorAll('input[type="checkbox"]')];
        const source = cards.find((card) => card.dataset.source === 'ergo');

        expect(selections).toHaveLength(2);
        expect(source.querySelector(':scope > .vea-card-toggle')).not.toBeVisible();
        const checked = source.querySelector('[data-role="source-bulk-select"]');
        checked.checked = !source.classList.contains('is-inactive');

        return {
            cards,
            selections,
            checked,
            checkedBefore: checked.checked,
            geometry: cards.map((card) => [bounds(card), bounds(card.querySelector('.vea-card-copy'))]),
            controls: selections.map(bounds),
            options: bounds(source.querySelector('[data-role="entity-options-placeholder"]'))
        };
    });

    await userEvent.click(within(canvasElement).getByRole('button', {name: 'Uruchom JavaScript'}));
    roots.forEach((root, index) => {
        const initial = before[index];
        const menu = root.querySelector('[data-role="entity-card"] [data-role="entity-options"]');

        expect(root.querySelector('[data-role="entity-options-placeholder"]')).toBeNull();
        expect([...root.querySelectorAll('input[type="checkbox"]')]).toEqual(initial.selections);
        expect(initial.checked.checked).toBe(initial.checkedBefore);
        expect(initial.cards.map((card) => [bounds(card), bounds(card.querySelector('.vea-card-copy'))]))
            .toEqual(initial.geometry);
        expect(initial.selections.map(bounds)).toEqual(initial.controls);
        expect(bounds(menu)).toEqual(initial.options);
    });
    const trigger = roots[0].querySelector('[data-role="entity-card"] [data-role="entity-options"] > summary');
    trigger.focus();
    await userEvent.keyboard('{Enter}');
    await expect(trigger.parentElement).toHaveAttribute('open');
    await userEvent.keyboard('{Escape}');
    await expect(trigger.parentElement).not.toHaveAttribute('open');
    await userEvent.click(trigger);
    await expect(trigger.parentElement).toHaveAttribute('open');
    await userEvent.keyboard('{Escape}');
}

export default {
    id: 'ergo-c-028',
    title: 'Ergonode UI/Komponenty/Wspólne/ERGO-C-028 · Pierwsze renderowanie mapowania',
    render,
    args: {view: 'attribute', active: true, allViews: false},
    argTypes: {
        view: {control: 'select', options: mappingViews},
        active: {control: 'boolean'},
        allViews: {control: 'boolean'}
    }
};

export const Playground = {};
export const WszystkieWidoki = {args: {allViews: true}, play: verifyFirstPaint};
export const Wykluczone = {args: {allViews: true, active: false}, play: verifyFirstPaint};
