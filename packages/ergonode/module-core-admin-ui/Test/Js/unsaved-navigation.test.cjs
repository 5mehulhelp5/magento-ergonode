'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(
    path.resolve(__dirname, '../../view/adminhtml/web/js/unsaved-navigation.js'),
    'utf8'
);
const styles = fs.readFileSync(
    path.resolve(__dirname, '../../view/adminhtml/web/css/ergonode-workspace.css'),
    'utf8'
);

function load(confirm) {
    let exported;
    const window = {
        clearTimeout() {},
        location: {
            assign() {},
            href: 'https://example.test/admin/options'
        },
        setTimeout() {
            return 1;
        }
    };
    const document = {};

    vm.runInNewContext(source, {
        URL,
        Promise,
        document,
        window,
        define(dependencies, factory) {
            assert.deepEqual(Array.from(dependencies), [
                'Magento_Ui/js/modal/confirm',
                'mage/translate'
            ]);
            exported = factory(confirm, (value) => value);
        }
    });

    return {document, navigation: exported, window};
}

function choose(config, buttonIndex) {
    config.buttons[buttonIndex].click.call({
        closeModal(event, confirmed) {
            if (confirmed) {
                config.actions.confirm(event);
            } else {
                config.actions.cancel(event);
            }
            config.actions.always(event);
        }
    }, {});
}

test('dirty navigation offers cancel, discard and save choices', () => {
    let modal;
    let navigations = 0;
    const {navigation} = load((config) => {
        modal = config;
    });
    const controller = navigation.create({
        isDirty: () => true,
        navigate: () => {
            navigations += 1;
        },
        save: () => Promise.resolve()
    });

    controller.request();

    assert.equal(modal.title, 'Warning, data is unsaved');
    assert.match(modal.content, /Do you want to save them/);
    assert.equal(modal.modalClass, 'confirm veui-unsaved-navigation-modal');
    assert.equal(modal.focus, '.action-accept');
    assert.deepEqual(Array.from(modal.buttons, (button) => button.text), [
        'Cancel',
        'Discard',
        'Save'
    ]);
    assert.equal(navigations, 0);
});

test('dirty navigation modal uses the shared Ergonode visual hierarchy', () => {
    assert.match(styles, /\.modal-popup\.veui-unsaved-navigation-modal \.modal-inner-wrap/);
    assert.match(styles, /\.modal-popup\.veui-unsaved-navigation-modal \.modal-title::before/);
    assert.match(styles, /\.modal-popup\.veui-unsaved-navigation-modal \.modal-footer\s*\{[\s\S]*?display: flex/);
    assert.match(styles, /\.modal-footer \.action-dismiss\s*\{[\s\S]*?margin-right: auto/);
    assert.match(styles, /\.modal-footer \.action-discard\s*\{[\s\S]*?var\(--veui-warning-soft\)/);
    assert.match(styles, /\.modal-footer \.action-accept\s*\{[\s\S]*?var\(--veui-magento-orange\)/);
    assert.match(styles, /\.modal-footer \.action-dismiss::before\s*\{[\s\S]*?unsaved-cancel\.svg/);
    assert.match(styles, /\.modal-footer \.action-discard::before\s*\{[\s\S]*?unsaved-discard\.svg/);
    assert.match(styles, /\.modal-footer \.action-accept::before\s*\{[\s\S]*?save\.svg/);
    assert.match(styles, /\.modal-footer button\s*\{[\s\S]*?font-size: 10px/);
    assert.match(styles, /\.modal-footer button\s*\{[\s\S]*?min-height: 32px/);
});

test('discard navigates without calling save', () => {
    let modal;
    let navigations = 0;
    let saves = 0;
    const {navigation} = load((config) => {
        modal = config;
    });
    const controller = navigation.create({
        isDirty: () => true,
        navigate: () => {
            navigations += 1;
        },
        save: () => {
            saves += 1;
        }
    });

    controller.request();
    choose(modal, 1);

    assert.equal(saves, 0);
    assert.equal(navigations, 1);
});

test('save choice waits for persistence before navigation', async () => {
    let modal;
    let navigations = 0;
    let resolveSave;
    const {navigation} = load((config) => {
        modal = config;
    });
    const controller = navigation.create({
        isDirty: () => true,
        navigate: () => {
            navigations += 1;
        },
        save: () => new Promise((resolve) => {
            resolveSave = resolve;
        })
    });

    controller.request();
    choose(modal, 2);
    await Promise.resolve();
    assert.equal(navigations, 0);

    resolveSave();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(navigations, 1);
});

test('cancel keeps the user on the current page', () => {
    let modal;
    let canceled = 0;
    let navigations = 0;
    const {navigation} = load((config) => {
        modal = config;
    });
    const controller = navigation.create({isDirty: () => true});

    controller.request({
        navigate: () => {
            navigations += 1;
        },
        onCancel: () => {
            canceled += 1;
        }
    });
    choose(modal, 0);

    assert.equal(canceled, 1);
    assert.equal(navigations, 0);
});

test('beforeunload uses the native browser warning for dirty state', () => {
    const {navigation} = load(() => {});
    const controller = navigation.create({isDirty: () => true});
    const event = {
        prevented: false,
        preventDefault() {
            this.prevented = true;
        }
    };

    assert.equal(controller.beforeUnload(event), '');
    assert.equal(event.prevented, true);
    assert.equal(event.returnValue, '');
});

test('bound document links are intercepted only while state is dirty', () => {
    let clickHandler;
    let modalCalls = 0;
    let dirty = false;
    const {document, navigation, window} = load(() => {
        modalCalls += 1;
    });
    const scope = {
        cleanup() {},
        listen(target, type, listener) {
            if (target === document && type === 'click') {
                clickHandler = listener;
            }
        }
    };
    const anchor = {
        href: 'https://example.test/admin/products',
        getAttribute(name) {
            return name === 'href' ? this.href : '';
        },
        hasAttribute() {
            return false;
        }
    };
    const event = {
        button: 0,
        defaultPrevented: false,
        preventDefault() {
            this.defaultPrevented = true;
        },
        stopImmediatePropagation() {},
        target: {closest: () => anchor}
    };

    navigation.bind(scope, {
        isDirty: () => dirty,
        navigate: (url) => window.location.assign(url),
        save: () => Promise.resolve()
    });
    clickHandler(event);
    assert.equal(event.defaultPrevented, false);
    assert.equal(modalCalls, 0);

    dirty = true;
    clickHandler(event);
    assert.equal(event.defaultPrevented, true);
    assert.equal(modalCalls, 1);
});

test('bound navigation lets a consumer handle selected links itself', () => {
    let clickHandler;
    let modalCalls = 0;
    const {document, navigation} = load(() => {
        modalCalls += 1;
    });
    const scope = {
        cleanup() {},
        listen(target, type, listener) {
            if (target === document && type === 'click') {
                clickHandler = listener;
            }
        }
    };
    const anchor = {
        href: 'https://example.test/admin/category-tree/2',
        getAttribute(name) {
            return name === 'href' ? this.href : '';
        },
        hasAttribute() {
            return false;
        }
    };
    const event = {
        button: 0,
        defaultPrevented: false,
        preventDefault() {
            this.defaultPrevented = true;
        },
        stopImmediatePropagation() {},
        target: {closest: () => anchor}
    };

    navigation.bind(scope, {
        isDirty: () => true,
        shouldHandleLink: (candidate) => candidate !== anchor
    });
    clickHandler(event);

    assert.equal(event.defaultPrevented, false);
    assert.equal(modalCalls, 0);
});
