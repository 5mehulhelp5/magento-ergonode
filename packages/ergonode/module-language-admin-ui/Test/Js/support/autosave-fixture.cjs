const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const initialRevision = 'a'.repeat(64);
const nextRevision = 'b'.repeat(64);

function settle() {
    return new Promise((resolve) => setImmediate(resolve));
}

function clock() {
    let now = 0;
    let sequence = 0;
    const timers = new Map();
    return {
        setTimeout(callback, delay) {
            const id = ++sequence;
            timers.set(id, {callback, at: now + delay});
            return id;
        },
        clearTimeout(id) {
            timers.delete(id);
        },
        async advance(milliseconds) {
            const deadline = now + milliseconds;
            while (true) {
                const next = [...timers].sort((a, b) => a[1].at - b[1].at)[0];
                if (!next || next[1].at > deadline) {
                    break;
                }
                now = next[1].at;
                timers.delete(next[0]);
                next[1].callback();
                await settle();
            }
            now = deadline;
            await settle();
        },
    };
}

function element() {
    const attributes = {};
    const classes = new Set();
    return {
        attributes,
        classList: {
            add: (name) => classes.add(name),
            remove: (name) => classes.delete(name),
            contains: (name) => classes.has(name),
            toggle(name, active) {
                if (active) classes.add(name);
                else classes.delete(name);
            },
        },
        setAttribute: (name, value) => { attributes[name] = value; },
        removeAttribute: (name) => { delete attributes[name]; },
        getAttribute: (name) => attributes[name] ?? null,
        querySelector: () => null,
        querySelectorAll: () => [],
    };
}

function fixture() {
    const time = clock();
    const root = element();
    const region = element();
    const error = {hidden: true};
    const loader = {hidden: true};
    const calls = [];
    const notifications = [];
    const state = {code: 'pl_PL', reloads: 0, captures: 0};
    region.querySelector = () => error;
    root.querySelector = (selector) => ({
        '[data-role="autosave-region"]': region,
        '[data-role="language-loader"]': loader,
    }[selector] ?? null);
    const window = {
        setTimeout: time.setTimeout,
        clearTimeout: time.clearTimeout,
        AbortController,
        location: {reload: () => { state.reloads++; }},
    };
    function load(relative, dependencies = {}) {
        const filename = relative.startsWith('CoreAdminUi/')
            ? path.resolve(
                __dirname,
                '../../../../../../vendor/ergonode/module-core-admin-ui',
                relative.slice('CoreAdminUi/'.length)
            )
            : path.resolve(__dirname, '../../..', relative.slice('LanguageAdminUi/'.length));
        let exported;
        vm.runInNewContext(fs.readFileSync(filename, 'utf8'), {
            window, URLSearchParams, Promise, Error, JSON,
            define(names, factory) {
                exported = factory(...names.map((name) => {
                    if (name === 'mage/translate') return (value) => value;
                    if (!(name in dependencies)) throw new Error(`Missing AMD fixture dependency: ${name}`);
                    return dependencies[name];
                }));
            },
        }, {filename});
        return exported;
    }
    const config = {
        canSave: true,
        revision: initialRevision,
        form_key: 'fixture-form-key',
        urls: {save: '/save', refresh: '/refresh', configuration: '/configuration'},
        fetch(url, options) {
            let resolve;
            let reject;
            const promise = new Promise((success, failure) => { resolve = success; reject = failure; });
            const body = new URLSearchParams(options.body);
            calls.push({
                url, options, payload: body.has('payload') ? JSON.parse(body.get('payload')) : null,
                reject,
                respond(data, status = 200) {
                    resolve({status, json: async () => data});
                },
            });
            return promise;
        },
    };
    const request = load('CoreAdminUi/view/adminhtml/web/js/request.js');
    const coreAutosave = load('CoreAdminUi/view/adminhtml/web/js/autosave.js');
    const languageAutosave = load('LanguageAdminUi/view/adminhtml/web/js/language-autosave.js', {
        'Ergonode_CoreAdminUi/js/autosave': coreAutosave,
        'Ergonode_CoreAdminUi/js/request': request,
    });
    function createAutosave(options = {}) {
        return languageAutosave.create(root, config, {
            serialize: () => ({mappings: [{left: {code: state.code}, right: {code: '1'}}]}),
            onSaved: (response, snapshot) => notifications.push({response, snapshot}),
            ...options,
        });
    }
    return {root, region, error, loader, time, calls, config, state, notifications,
        createAutosave, languageAutosave, request, load};
}

module.exports = {fixture, element, settle, initialRevision, nextRevision};
