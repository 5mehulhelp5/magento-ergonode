import {expect, userEvent, within, waitFor} from 'storybook/test';
import {loadAmdModule} from '@ergonode-storybook/load-amd-module.js';
import source from '../../view/adminhtml/web/js/manual-auth.js?raw';
import postSource from '../../view/adminhtml/web/js/json-post.js?raw';
import '../../view/adminhtml/web/css/manual-auth.css';
import logo from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/images/m2_configuration.svg';

function render(args) {
    const root = document.createElement('div');
    const button = document.createElement('button');
    const status = document.createElement('p');
    button.textContent = 'Send products';
    button.type = 'button';
    status.setAttribute('role', 'status');
    root.append(button, status);
    root.requests = [];
    let authenticated = args.state === 'connected';
    function jquery(element) {
        return {element, remove() {element.remove();}, modal(action, option, value) {
            if (action === 'option') { return value === undefined ? !this.shell.hidden : this; }
            if (action === 'openModal') { this.shell.hidden = false; }
            if (action === 'closeModal') { this.shell.hidden = true; }
            if (action === 'closeModal') { this.options.closed(); }
            return this;
        }};
    }
    jquery.ajax = ({url, data}) => {
        let done;
        let fail;
        const chain = {done(callback) {done = callback; return chain;}, fail(callback) {fail = callback; return chain;}};
        setTimeout(() => {
            root.requests.push({url, data});
            if (args.state === 'unavailable') { fail({}); return; }
            if (url === '/login' && args.state !== 'rejected') { authenticated = true; }
            done({success: url !== '/login' || args.state !== 'rejected', authenticated,
                message: 'The Ergonode login or password is incorrect.'});
        }, 10);
        return chain;
    };
    function modal(options, widget) {
        const shell = document.createElement('section');
        shell.className = `modal-popup ${options.modalClass}`;
        shell.hidden = true;
        shell.setAttribute('role', 'dialog');
        shell.setAttribute('aria-label', options.title);
        shell.innerHTML = '<div class="modal-inner-wrap"><header class="modal-header"><h2 class="modal-title"></h2></header><div class="modal-content"></div></div>';
        shell.querySelector('.modal-title').textContent = options.title;
        widget.element.style.display = 'block';
        shell.querySelector('.modal-content').append(widget.element);
        widget.shell = shell;
        widget.options = options;
        root.append(shell);
    }
    const post = loadAmdModule(postSource, {jquery, 'mage/translate': text => text});
    const auth = loadAmdModule(source, {jquery, 'mage/translate': text => text,
        'Magento_Ui/js/modal/modal': modal, 'Ergonode_PublisherAdminUi/js/json-post': post
    })({urls:{login:'/login',status:'/status'}, logo_url:logo});
    root.auth = auth;
    button.addEventListener('click', async () => {
        button.disabled = true;
        try {
            status.textContent = await auth.ensure() ? 'Ready to publish.' : 'Cancelled. No products sent.';
        } catch (error) { status.textContent = error.message; }
        finally { button.disabled = false; }
    });
    return root;
}

async function login({canvasElement, args}) {
    const canvas = within(canvasElement);
    await userEvent.click(canvas.getByRole('button', {name:'Send products'}));
    if (args.state === 'connected') {
        await waitFor(() => expect(canvas.getByRole('status')).toHaveTextContent('Ready to publish.'));
        await expect(canvas.queryByRole('dialog')).not.toBeInTheDocument();
        return;
    }
    if (args.state === 'unavailable') {
        const dialog = await canvas.findByRole('dialog', {name:'Log in to Ergonode'});
        await expect(within(dialog).getByRole('alert')).toHaveTextContent('Unable to connect');
        await userEvent.click(within(dialog).getByRole('button', {name:'Cancel'}));
        return;
    }
    const dialog = await canvas.findByRole('dialog', {name:'Log in to Ergonode'});
    const form = within(dialog);
    await expect(form.getByRole('checkbox', {name:'Remember me'})).not.toBeChecked();
    if (args.cancel) {
        await userEvent.click(form.getByRole('button', {name:'Cancel'}));
        await expect(canvas.getByRole('status')).toHaveTextContent('Cancelled');
        await expect(buttonRoot(canvasElement).requests.filter(r => r.url === '/login')).toHaveLength(0);
        return;
    }
    await userEvent.type(form.getByRole('textbox', {name:'Ergonode login'}), 'fixture@example.test');
    await userEvent.type(form.getByLabelText('Password'), 'fixture-password');
    if (args.remember) { await userEvent.click(form.getByRole('checkbox', {name:'Remember me'})); }
    form.getByRole('button', {name:'Log in', exact:true}).focus();
    await userEvent.keyboard('{Enter}');
    await waitFor(() => expect(form.getByLabelText('Password')).toHaveValue(''));
    const requests = buttonRoot(canvasElement).requests.filter(r => r.url === '/login');
    await expect(requests).toHaveLength(1);
    await expect(requests[0].data.remember_me).toBe(args.remember ? 1 : 0);
    if (args.state === 'rejected') {
        await expect(form.getByRole('alert')).toHaveTextContent('incorrect');
        await expect(dialog).toBeVisible();
    } else {
        await expect(canvas.getByRole('status')).toHaveTextContent('Ready to publish.');
        await expect(dialog).not.toBeVisible();
    }
}

function buttonRoot(canvasElement) {
    return within(canvasElement).getByRole('button', {name:'Send products'}).parentElement;
}

export default {
    id: 'ergo-c-055',title:'Ergonode UI/Komponenty/Wspólne/ERGO-C-055 · Logowanie REST', render, play:login,
    args:{state:'disconnected',remember:false}, parameters:{a11y:{test:'error'}}};
export const Playground = {};
export const RememberMe = {args:{remember:true}};
export const Cancelled = {args:{cancel:true}};
export const Rejected = {args:{state:'rejected'}};
export const Connected = {args:{state:'connected'}};
export const Unavailable = {args:{state:'unavailable'}};
export const Disposed = {play:async ({canvasElement}) => {
    const canvas = within(canvasElement);
    await userEvent.click(canvas.getByRole('button', {name:'Send products'}));
    const dialog = await canvas.findByRole('dialog', {name:'Log in to Ergonode'});
    const password = within(dialog).getByLabelText('Password');
    await userEvent.type(password, 'fixture-password');
    const root = buttonRoot(canvasElement);
    root.auth.destroy();
    root.auth.destroy();
    await waitFor(() => expect(canvas.getByRole('status')).toHaveTextContent('Cancelled'));
    await expect(password).toHaveValue('');
    await expect(canvas.queryByRole('dialog', {hidden:true})).not.toBeInTheDocument();
    await expect(await root.auth.ensure()).toBe(false);
    await expect(root.requests.filter(request => request.url === '/login')).toHaveLength(0);
}};
export const StateMatrix = {play:async ({canvasElement}) => {
    await waitFor(() => expect(within(canvasElement).getAllByRole('dialog')).toHaveLength(3));
}, render:() => {
    const root = document.createElement('div');
    for (const state of ['disconnected','connected','rejected','unavailable']) {
        const section = render({state});
        const title = document.createElement('h2');
        title.textContent = state;
        root.append(title, section);
        section.querySelector('button').click();
    }
    return root;
}};
