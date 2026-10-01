'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(path.resolve(__dirname,
    '../../view/adminhtml/web/js/ergonode-category-publisher-mapping.js'), 'utf8');

test('reinitializing one mapping workspace does not accumulate dialogs, observers or handlers', () => {
    const counts = {dialogs: 0, progress: 0, auth: 0, observers: 0, handlers: 0};
    const node = () => ({setAttribute() {}, addEventListener() {}, hidden: false});
    const form = {...node(), elements: {code: node()}, querySelector: node};
    const root = {addEventListener() { counts.handlers++; }};
    const jquery = () => ({
        on() { counts.handlers++; },
        find: () => ({each() {}})
    });
    let initialize;
    vm.runInNewContext(source, {
        WeakSet,
        window: {MutationObserver: class {
            constructor() { counts.observers++; }
            observe() {}
        }},
        document: {
            body: {appendChild() { counts.dialogs++; }},
            createElement: () => ({...node(), querySelector: (selector) => selector === 'form' ? form : node()})
        },
        define: (dependencies, factory) => {
            initialize = factory(jquery, (text) => text, {}, {},
                () => { counts.progress++; return {}; }, {},
                () => { counts.auth++; return {}; }, () => ({}));
        }
    });
    for (let iteration = 0; iteration < 100; iteration++) initialize({urls: {}}, root);
    assert.deepEqual(counts, {dialogs: 1, progress: 1, auth: 1, observers: 1, handlers: 5});
});

test('workspace cleanup releases all owned resources across 100 mount and unmount cycles', () => {
    const live = {dialogs: 0, progress: 0, auth: 0, observers: 0, handlers: 0};
    let cleanup;
    let recovery;
    const node = () => ({setAttribute() {}, removeAttribute() {}, addEventListener() {}, hidden: false});
    const form = {...node(), reset() {}, elements: {code: node()}, querySelector: node};
    const empty = {length: 0, each() {}, first() {return this;}, prop() {return this;}};
    const root = {
        addEventListener() {live.handlers++;},
        removeEventListener() {live.handlers--;},
        veaCategoryMappingApi: {
            cleanup(callback) {cleanup = callback;},
            setSaveRecoveryHandler(callback) {recovery = callback;},
            getCategories: () => []
        }
    };
    const jquery = element => ({
        on() {live.handlers++;}, off() {live.handlers -= 4;},
        find: () => empty,
        remove() {if (element !== root) live.dialogs--;}
    });
    let initialize;
    vm.runInNewContext(source, {
        WeakSet,
        window: {clearTimeout() {}, removeEventListener() {}, MutationObserver: class {
            constructor() {live.observers++;}
            observe() {}
            disconnect() {live.observers--;}
        }},
        document: {
            body: {appendChild() {live.dialogs++;}}, removeEventListener() {}, querySelectorAll: () => [],
            createElement: () => ({...node(), querySelector: selector => selector === 'form' ? form : node()})
        },
        define: (dependencies, factory) => {
            initialize = factory(jquery, text => text, {}, {},
                () => {live.progress++; return {destroy() {live.progress--;}};}, {},
                () => {live.auth++; return {destroy() {live.auth--;}};},
                () => ({isReady: () => true}));
        }
    });
    for (let iteration = 0; iteration < 100; iteration++) {
        const instance = initialize({urls: {}}, root);
        assert.equal(typeof recovery, 'function');
        cleanup();
        instance.destroy();
        assert.equal(recovery, null);
        assert.equal(root.veaErgonodeCategoryPublisherObserver, undefined);
        assert.deepEqual(live, {dialogs: 0, progress: 0, auth: 0, observers: 0, handlers: 0});
    }
});
