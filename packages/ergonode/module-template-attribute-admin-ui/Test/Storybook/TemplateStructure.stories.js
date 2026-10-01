import {expect, userEvent, within, waitFor} from 'storybook/test';
import {loadAmdModule, translateIdentity} from '@ergonode-storybook/load-amd-module.js';
import {createMappingWorkspace} from '@ergonode-storybook/mapping-workspace.js';
import {createCoreModuleLoader} from '@ergonode-storybook/core-modules.js';
import modalSource from '../../view/adminhtml/web/js/template-structure-modal.js?raw';
import source from '../../view/adminhtml/web/js/template-structure.js?raw';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-actions.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/attribute-mapping.css';
import '@ergonode-modules/TemplateAdminUi/view/adminhtml/web/css/template-admin.css';
import '../../view/adminhtml/web/css/template-structure.css';


const structure = loadAmdModule(source, {'mage/translate': translateIdentity});
import fixture from './structure-fixture.js';

function dataFor(state) {
    if (state === 'empty') { return {...fixture, sections: [], sourceAttributes: [], groups: [], attributes: []}; }
    if (state === 'missing-source') { return {...fixture, sections: [], sourceAttributes: []}; }
    return {...fixture, state};
}
function render(args) {
    const frame = document.createElement('div');
    frame.style.padding = '24px';
    frame.append(structure.render(dataFor(args.state)));
    return frame;
}
export default {
    id: 'ergo-v-058-04',
    title: 'Ergonode UI/Widoki/Szablony/ERGO-V-058 · Mapowanie szablonów/ERGO-V-058.04 · Struktura zestawu atrybutów',
    render, args: {state: 'mapped'},
    argTypes: {state: {control: 'select', options: ['mapped', 'empty', 'missing-source', 'loading', 'error']}}
};
export const Playground = {};
export const Pusty = {args: {state: 'empty'}};
export const BrakDanychErgonode = {args: {state: 'missing-source'}};
export const Wczytywanie = {args: {state: 'loading'}};
export const Blad = {args: {state: 'error'}};
export const SekcjeMyszaIKlawiatura = {
    play: async ({canvasElement}) => {
        const section = canvasElement.querySelector('.vets-group');
        const summary = section.querySelector('summary');
        await userEvent.click(summary);
        await expect(section).not.toHaveAttribute('open');
        summary.focus();
        await userEvent.keyboard('{Enter}');
        await expect(section).toHaveAttribute('open');
        await expect(canvasElement.querySelectorAll('.vets-columns > section')).toHaveLength(3);
        await expect(canvasElement.querySelectorAll('[draggable="true"], input, select')).toHaveLength(0);
        const outside = canvasElement.querySelectorAll('.vets-panel')[2];
        await expect(within(outside).getByText('Materiał')).toBeVisible();
        await expect(within(outside).queryByText('Cena')).not.toBeInTheDocument();
    }
};
export const PopupZListyTemplates = {
    render: () => {
        const frame = document.createElement('div');
        const workspace = createMappingWorkspace({view: 'template', state: 'mapped'});
        const row = workspace.querySelector('[data-role="mapping-row"]');
        const button = document.createElement('button');
        row.classList.add('vet-pair-card');
        button.className = 'vea-option-map-action vet-structure-action';
        button.dataset.role = 'template-structure-action';
        button.dataset.templateCode = 'shoes';
        button.dataset.attributeSetId = '4';
        button.type = 'button';
        button.setAttribute('aria-label', 'Podgląd struktury zestawu atrybutów');
        button.append(document.createElement('span'));
        row.append(button);
        // Stub Magento's modal shell and HTTP transport; run the production popup adapter.
        const dialog = document.createElement('dialog');
        dialog.style.cssText = 'width:94vw;max-width:1600px;border:1px solid #dfe3e9;border-radius:10px;padding:24px';
        const calls = [];
        frame.popupCalls = calls;
        frame.dataset.structurePopupFixture = '';
        function jquery() {
            const element = document.createElement('div');
            return {
                element,
                empty() { element.replaceChildren(); return this; },
                append(child) { element.append(child); return this; },
                remove() { element.remove(); },
                modal(action) {
                    if (action === 'openModal') { dialog.showModal(); }
                    if (action === 'destroy') { dialog.remove(); }
                    return this;
                }
            };
        }
        jquery.ajax = (options) => {
            calls.push(options);
            const response = Promise.resolve({success: true, structure: fixture});
            response.abort = () => {};
            return response;
        };
        function modal(options, content) {
            dialog.setAttribute('aria-label', options.title);
            const close = document.createElement('button');
            close.className = 'veui-button';
            close.textContent = 'Zamknij';
            close.addEventListener('click', () => dialog.close());
            dialog.append(close, content.element);
            dialog.addEventListener('close', options.closed);
        }
        workspace.veaWorkspace = createCoreModuleLoader()('Ergonode_CoreAdminUi/js/workspace').mount(workspace);
        workspace.veaContext = {
            config: {urls: {structure: '/fixture/template/structure'}, structureIcons: fixture.icons},
            autosave: {flush: () => { calls.push('flush'); return Promise.resolve(); }}
        };
        frame.append(workspace, dialog);
        loadAmdModule(modalSource, {
            jquery, 'mage/translate': translateIdentity, 'Magento_Ui/js/modal/modal': modal,
            'Ergonode_TemplateAttributeAdminUi/js/template-structure': structure
        })({}, workspace);
        return frame;
    },
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const button = canvas.getByRole('button', {name: 'Podgląd struktury zestawu atrybutów'});
        await userEvent.click(button);
        const dialog = canvas.getByRole('dialog');
        await expect(await within(dialog).findByText('Tylko podgląd')).toBeVisible();
        const frame = canvasElement.querySelector('[data-structure-popup-fixture]');
        await expect(frame.popupCalls[0]).toBe('flush');
        await expect(frame.popupCalls[1]).toMatchObject({method: 'GET', data: {template_code: 'shoes', attribute_set_id: '4'}});
        await userEvent.click(within(dialog).getByRole('button', {name: 'Zamknij'}));
        await waitFor(() => expect(button).toHaveFocus());
    }
};
