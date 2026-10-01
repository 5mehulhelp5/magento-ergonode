import {expect, userEvent, within, waitFor} from 'storybook/test';
import {loadAmdModule, translateIdentity} from '@ergonode-storybook/load-amd-module.js';
import {createCoreModuleLoader} from '@ergonode-storybook/core-modules.js';
import source from '../../view/adminhtml/web/js/manual-placement.js?raw';
import rendererSource from '@ergonode-modules/TemplateAttributeAdminUi/view/adminhtml/web/js/template-structure.js?raw';
import fixture from '@ergonode-modules/TemplateAttributeAdminUi/Test/Storybook/structure-fixture.js';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-actions.css';
import '@ergonode-modules/TemplateAttributeAdminUi/view/adminhtml/web/css/template-structure.css';
import '../../view/adminhtml/web/css/manual-placement.css';

const renderer = loadAmdModule(rendererSource, {'mage/translate': translateIdentity});
function render(args) {
    const frame = document.createElement('div');
    const structure = structuredClone(fixture);
    const root = renderer.render(structure);
    const calls = [];
    frame.calls = calls;
    frame.dataset.placementFixture = '';
    frame.append(root);
    const core = createCoreModuleLoader();
    frame.veaWorkspace = core('Ergonode_CoreAdminUi/js/workspace').mount(frame);
    frame.veaContext = {config: {urls: {manual_placement: '/fixture/manual-placement'}, can_edit_placement: true}};
    const initialize = loadAmdModule(source, {
        'mage/translate': translateIdentity,
        'Ergonode_CoreAdminUi/js/buttons': core('Ergonode_CoreAdminUi/js/buttons'),
        'Ergonode_CoreAdminUi/js/request': {post: (url, config, payload) => {
            calls.push(payload);
            return args.failure ? Promise.reject(new Error('Nie udało się zapisać ustawienia.')) : Promise.resolve({success: true});
        }}
    });
    initialize({}, frame);
    frame.dispatchEvent(new CustomEvent('ergonode:template-structure-rendered', {
        detail: {root, structure, templateCode: 'shoes', attributeSetId: 4}
    }));
    return frame;
}
export default {
    id: 'ergo-v-058-05',title: 'Ergonode UI/Widoki/Szablony/ERGO-V-058 · Mapowanie szablonów/ERGO-V-058.05 · Ręczne rozmieszczenie', render, args: {failure: false}};
export const Playground = {};
export const ZapisMyszaIKlawiatura = {play: async ({canvasElement}) => {
    const canvas = within(canvasElement);
    const button = canvas.getByRole('button', {name: 'Pozycja zarządzana ręcznie: color'});
    await expect(button).toHaveAttribute('aria-pressed', 'false');
    await userEvent.click(button);
    await waitFor(() => expect(button).toHaveAttribute('aria-pressed', 'true'));
    await waitFor(() => expect(button).toBeEnabled());
    button.focus();
    await userEvent.keyboard('{Enter}');
    await waitFor(() => expect(button).toHaveAttribute('aria-pressed', 'false'));
    const calls = canvasElement.querySelector('[data-placement-fixture]').calls;
    await expect(calls.map(call => call.manual)).toEqual(['1', '0']);
    await expect(calls[0]).toMatchObject({template_code: 'shoes', attribute_set_id: 4});
    await expect(canvas.getByRole('button', {name: 'Pozycja zarządzana ręcznie: price'})).toBeDisabled();
    await expect(canvas.queryByRole('button', {name: 'Pozycja zarządzana ręcznie: image'})).not.toBeInTheDocument();
    await expect(canvas.getByText('Brak w szablonie Ergonode — zachowany ręcznie')).toBeVisible();
    await expect(canvas.getByText('Sekcja Ergonode: details')).toBeVisible();
}};
export const BladZapisu = {args: {failure: true}, play: async ({canvasElement}) => {
    const canvas = within(canvasElement);
    const button = canvas.getByRole('button', {name: 'Pozycja zarządzana ręcznie: color'});
    await userEvent.click(button);
    await expect(await canvas.findByText('Nie udało się zapisać ustawienia.')).toBeVisible();
    await expect(button).toHaveAttribute('aria-pressed', 'false');
    await expect(button).toBeEnabled();
}};
