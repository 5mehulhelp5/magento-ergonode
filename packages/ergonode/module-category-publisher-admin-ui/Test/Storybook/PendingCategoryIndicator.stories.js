import { expect, userEvent, waitFor, within } from 'storybook/test';
import { createEntityItem } from '@ergonode-storybook/entity-item.js';
import { loadAmdModule } from '@ergonode-storybook/load-amd-module.js';

import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CategoryAdminUi/view/adminhtml/web/css/category-tree-mapping.css';
import '../../view/adminhtml/web/css/ergonode-category-publisher.css';
import jquerySource from '../../../../../lib/web/jquery.js?raw';
import publisherSource from '../../view/adminhtml/web/js/ergonode-category-publisher-mapping.js?raw';

// Use Magento's real data-* parser; a string-only jQuery stub hides this regression.
const jqueryModule = {exports: {}};
Function('module', 'exports', 'define', jquerySource)(jqueryModule, jqueryModule.exports, undefined);
const jquery = jqueryModule.exports;
const initialize = loadAmdModule(publisherSource, {
    jquery,
    'mage/translate': text => text,
    'Ergonode_CoreAdminUi/js/entity-options': {},
    'Ergonode_CategoryPublisherAdminUi/js/category-code-generator': {},
    'Ergonode_CategoryPublisherAdminUi/js/category-publish-progress': () => ({destroy() {}}),
    'Ergonode_PublisherAdminUi/js/json-post': {},
    'Ergonode_PublisherAdminUi/js/manual-auth': () => ({destroy() {}}),
    'Ergonode_CategoryPublisherAdminUi/js/publication-readiness': () => ({isReady: () => true})
}, 'Ergonode_CategoryPublisherAdminUi/js/ergonode-category-publisher-mapping');

const codes = ['0', 'false', 'null', '001', 'chairs', 'constructor', '__proto__'];

function render() {
    const root = document.createElement('section');
    const complete = document.createElement('button');
    const categories = codes.map((code, index) => ({
        code, magento_category_id: index + 1,
        extension_data: {to_ergonode: {pending_create: true}}
    }));

    root.className = 'veui-workspace';
    root.style.maxWidth = '700px';
    categories.forEach(category => {
        ['ergo', 'magento'].forEach(side => {
            const card = createEntityItem({
                code: category.code, label: category.code, entityKind: 'category', source: side,
                className: `vec-node-card vec-${side}-card`
            });
            const mapping = document.createElement('button');
            const icon = document.createElement('span');
            const hint = document.createElement('span');

            card.querySelector('.vea-card-copy').classList.add('vec-card-copy');
            if (side === 'magento') card.dataset.magentoId = String(category.magento_category_id);
            mapping.type = 'button';
            mapping.className = (side === 'ergo' ? 'vec-source-mapping' : 'vec-magento-mapping is-mapped')
                + ' vec-mapping-indicator';
            mapping.dataset.mappingLabel = category.code;
            mapping.setAttribute('aria-label', `Mapped: ${category.code}`);
            hint.id = `pending-story-mapping-${side}-${category.magento_category_id}`;
            hint.className = 'vec-mapping-hint';
            hint.setAttribute('role', 'tooltip');
            hint.textContent = category.code;
            mapping.setAttribute('aria-describedby', hint.id);
            icon.className = 'vec-mapping-status-icon veui-connected-icon';
            icon.setAttribute('aria-hidden', 'true');
            mapping.append(icon, hint);
            card.append(mapping);
            root.append(card);
        });
    });
    complete.type = 'button';
    complete.className = 'veui-button';
    complete.textContent = 'Mark published';
    complete.addEventListener('click', () => {
        categories.forEach(category => { category.extension_data.to_ergonode.pending_create = false; });
        root.append(document.createTextNode(''));
    });
    root.append(complete);
    root.veaCategoryMappingApi = {getCategories: () => categories};
    root.publisher = initialize({urls: {}}, root);
    return root;
}

export default {
    id: 'ergo-v-068-05',
    title: 'Ergonode UI/Widoki/Kategorie/ERGO-V-068 · Formularz kategorii/ERGO-V-068.05 · Oznaczenie szkicu',
    render,
    parameters: {a11y: {test: 'error'}},
    afterEach: ({canvasElement}) => canvasElement.querySelector('.veui-workspace')?.publisher.destroy()
};

export const Playground = {};

export const PendingCodeIdentity = {
    tags: ['pending-code-regression'],
    play: async ({canvasElement}) => {
        const root = canvasElement.querySelector('.veui-workspace');
        const cards = [...root.querySelectorAll('[data-role="entity-card"]')];

        await expect(cards).toHaveLength(codes.length * 2);
        await expect(jquery(cards[0]).data('code')).toBe(0);
        await expect(jquery(cards[2]).data('code')).toBe(false);
        await expect(jquery(cards[4]).data('code')).toBe(null);
        // Force another observer pass to check idempotent decoration.
        root.append(document.createTextNode(''));
        await waitFor(() => {
            cards.forEach(card => {
                expect(card).toHaveClass('is-pending-create');
                expect(card.querySelectorAll(':scope > .vec-pending-ergonode-category')).toHaveLength(1);
                expect(card.querySelector('.vec-source-mapping, .vec-magento-mapping')).not.toBeVisible();
            });
        });
        const indicator = cards[0].querySelector('.vec-pending-ergonode-category');
        await userEvent.click(indicator);
        await waitFor(() => expect(indicator.querySelector('.vec-pending-ergonode-category-tooltip')).toBeVisible());
        await userEvent.click(within(root).getByRole('button', {name: 'Mark published'}));
        await waitFor(() => {
            cards.forEach(card => {
                expect(card).not.toHaveClass('is-pending-create');
                expect(card.querySelector('.vec-pending-ergonode-category')).toBeNull();
                expect(card.querySelector('.vec-source-mapping, .vec-magento-mapping')).toBeVisible();
            });
        });
    }
};
