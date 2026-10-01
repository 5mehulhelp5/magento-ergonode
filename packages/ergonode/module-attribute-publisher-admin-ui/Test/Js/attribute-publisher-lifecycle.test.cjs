'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname,
    '../../view/adminhtml/web/js/ergonode-attribute-publisher-mapping.js'), 'utf8');

for (const phase of ['open', 'closing', 'closed', 'never-opened']) {
    test(`workspace releases listeners, observer and ${phase} modal`, () => {
        const handlers = new Map();
        const counts = {observers: 0, progress: 0, shells: 0, modalListeners: 0};
        let initialize;
        let cleanup;
        let closingCallback;
        const root = {
            querySelectorAll: () => [],
            addEventListener(name, callback, capture = false) { handlers.set(callback, [name, capture]); },
            removeEventListener(name, callback, capture = false) {
                assert.deepEqual(handlers.get(callback), [name, capture]);
                handlers.delete(callback);
            },
            veaWorkspace: {cleanup(callback) { cleanup = callback; }}
        };
        const jquery = () => ({
            remove() { counts.shells--; },
            triggerHandler(event) {
                assert.equal(event, 'transitionend');
                if (closingCallback) { closingCallback(); closingCallback = null; }
            }
        });
        vm.runInNewContext(source, {
            MutationObserver: class {
                constructor() { counts.observers++; }
                observe() {}
                disconnect() { counts.observers--; }
            },
            define: (_dependencies, factory) => {
                initialize = factory(jquery, () => {}, value => value, {}, {},
                    () => { counts.progress++; return {destroy() { counts.progress--; }}; }, {}, {});
            }
        });
        for (let cycle = 0; cycle < 100; cycle++) {
            initialize({}, root);
            assert.equal(handlers.size, 2);
            let state;
            let overlay = phase === 'open' || phase === 'closing';
            if (phase !== 'never-opened') {
                counts.shells++;
                counts.modalListeners++;
                const shell = {};
                const options = {isOpen: phase === 'open', transitionEvent: 'transitionend'};
                closingCallback = phase === 'closing' ? () => { overlay = false; } : null;
                state = {
                    trigger: {},
                    onClick() {},
                    element: {
                        closest: () => shell,
                        removeEventListener(name, callback) {
                            assert.equal(name, 'click');
                            assert.equal(callback, state.onClick);
                            counts.modalListeners--;
                        }
                    },
                    widget: {modal(action, option, value) {
                        if (action === 'option') {
                            if (arguments.length === 3) { options[option] = value; }
                            return options[option];
                        }
                        if (action === 'closeModal') {
                            assert.equal(overlay, true, 'closed Magento modals cannot be closed again');
                            assert.equal(options.transitionEvent, null, 'cleanup must complete synchronously');
                            overlay = false;
                        }
                        if (action === 'destroy') { assert.equal(overlay, false); }
                    }}
                };
                root.veaPendingErgonodeTypeModal = state;
            }
            cleanup();
            assert.equal(handlers.size, 0);
            assert.equal(root.veaPendingErgonodeTypeModal, undefined);
            if (state) { assert.equal(state.trigger, null); }
            assert.deepEqual(counts, {observers: 0, progress: 0, shells: 0, modalListeners: 0});
        }
    });
}
