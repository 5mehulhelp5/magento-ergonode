import {expect, userEvent, within} from 'storybook/test';
import {loadAmdModule} from '@ergonode-storybook/load-amd-module.js';
import connectionSource from '../../view/adminhtml/web/js/test-connection.js?raw';
import '../../view/adminhtml/web/css/ergonode-workspace.css';
import '../../view/adminhtml/web/css/connection-config.css';

function render(args) {
    const root = document.createElement('section');
    root.className = 'ergonode-config-group';
    const owner = args.mode === 'read' ? 'consumer' : 'publisher';
    const prefix = `ergonode_connection_${args.environment}_${owner}`;
    const urlId = `ergonode_connection_${args.environment}_url`;
    root.innerHTML = `
        <strong class="ergonode-config-title open">${args.environment === 'test' ? 'Test' : 'Production'}</strong>
        <fieldset class="config">
        <legend>Connection settings</legend>
        <p>${args.mode === 'read' ? 'Read only' : 'Read and write'}</p>
        <p><label for="${urlId}">URL</label>
        <input id="${urlId}" type="url" value="https://${args.environment}.ergonode.cloud"></p>
        <p><label for="${prefix}_api_key">API key</label>
        <input id="${prefix}_api_key" type="password" value="******"></p>
        <button class="action-default scalable" id="${prefix}_connection" type="button"><span>Test connection</span></button>
        <span id="${prefix}_connection_result" class="ergonode-connection-test-result" role="status"></span>
        <p role="alert"></p></fieldset>`;
    const wrap = (element) => ({
        val: () => element.value,
        prop: (name, value) => { element[name] = value; },
        removeClass(names) { names.split(' ').forEach(name => element.classList.remove(name)); return this; },
        addClass(name) { element.classList.add(name); return this; },
        text(value) { element.textContent = value; return this; }
    });
    const jquery = selector => wrap(root.querySelector(selector));
    jquery.widget = (name, prototype) => {
        const [namespace, widget] = name.split('.');
        jquery[namespace] = {[widget]: (options, element) => {
            const instance = Object.assign({}, prototype, {options, element: wrap(element)});
            instance._on = handlers => Object.entries(handlers).forEach(([event, handler]) => {
                element.addEventListener(event, handler.bind(instance));
            });
            instance._create();
        }};
    };
    jquery.ajax = request => {
        root.dataset.request = JSON.stringify(request.data);
        return {
            done(callback) { callback({success: args.success, message: 'Credentials rejected.'}); return this; },
            fail() { return this; },
            always(callback) { callback(); return this; }
        };
    };
    const initialize = loadAmdModule(connectionSource, {
        jquery,
        'mage/translate': value => value,
        'Magento_Ui/js/modal/alert': ({content}) => { root.querySelector('[role="alert"]').textContent = content; },
        'jquery/ui': {}
    });
    initialize({
        url: '/fixture/connection-test',
        elementId: `${prefix}_connection`,
        mode: args.mode,
        environment: args.environment,
        urlFieldId: urlId,
        apiKeyFieldId: `${prefix}_api_key`
    }, root.querySelector('button'));
    return root;
}

async function play({canvasElement, args}) {
    const canvas = within(canvasElement);
    const button = canvas.getByRole('button', {name: 'Test connection'});
    button.focus();
    await userEvent.keyboard('{Enter}');
    await expect(canvas.getByRole('status')).toHaveTextContent(args.success ? 'Connection successful.' : 'Connection failed.');
    const request = JSON.parse(button.closest('section').dataset.request);
    await expect(request.mode).toBe(args.mode);
    await expect(request.environment).toBe(args.environment);
    await expect(request.api_key).toBe('******');
    await userEvent.click(button);
    await expect(button).toBeEnabled();
}

export default {
    id: 'ergo-c-025',
    title: 'Ergonode UI/Komponenty/Wspólne/ERGO-C-025 · Test połączenia',
    render,
    args: {environment: 'test', mode: 'read', success: true},
    argTypes: {
        environment: {control: 'select', options: ['test', 'production']},
        mode: {control: 'select', options: ['read', 'write']},
        success: {control: 'boolean'}
    },
    play
};
export const Playground = {};
export const TestRead = {};
export const ProductionRead = {args: {environment: 'production'}};
export const TestWrite = {args: {mode: 'write'}};
export const ProductionWrite = {args: {environment: 'production', mode: 'write'}};
export const RejectedCredentials = {args: {success: false}};
